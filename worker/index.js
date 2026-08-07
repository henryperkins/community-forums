import { Container, getContainer } from "@cloudflare/containers";

/**
 * RetroBoards front door.
 *
 * The Worker owns two things the container cannot do for itself: it establishes
 * the trusted client IP, and it drives the cron workers that would otherwise
 * need a crontab inside the image.
 *
 * Runbook: docs/runbooks/deployment-cloudflare.md
 */

// The app is stateful (sessions, counters, an ephemeral rate-limit ledger), so
// every request must reach the same instance. A fixed id guarantees that.
const CONTAINER_ID = "main";

const CONSOLE = "/var/www/html/bin/console";
const EDGE_CACHE_CONTROL = "public, max-age=300, s-maxage=3600";

/** Cron expression -> `bin/console` commands, in run order. */
const CRON_JOBS = {
	"*/5 * * * *": ["worker:email", "worker:webhooks"],
	"0 */6 * * *": ["worker:registry-refresh"],
	"10 3 * * *": ["worker:purge-ips", "worker:attachments", "worker:packages"],
	"0 7 * * *": ["worker:digest"],
};

function isPublicAssetRequest(request) {
	if (request.method !== "GET") {
		return false;
	}
	const url = new URL(request.url);
	return url.pathname === "/brand.css"
		|| (url.pathname.startsWith("/assets/") && url.searchParams.has("v"))
		|| url.pathname.startsWith("/assets/fonts/");
}

function withCacheStatus(response, status) {
	const headers = new Headers(response.headers);
	headers.set("X-RetroBoards-Cache", status);
	return new Response(response.body, {
		status: response.status,
		statusText: response.statusText,
		headers,
	});
}

async function fetchForum(request, env) {
	// The app resolves the client IP from X-Forwarded-For, honouring it only
	// when the immediate peer is a configured trusted proxy
	// (src/Security/ClientIdentifier.php). Inside Containers that peer is
	// Cloudflare infrastructure, and the container is not addressable from
	// the internet -- it is only reachable through this Worker. That makes
	// overwriting the header here the security boundary: whatever the client
	// sent is discarded, and CF-Connecting-IP (which the edge sets and a
	// client cannot forge) becomes the single hop the app sees.
	const forwarded = new Request(request);
	const clientIp = request.headers.get("CF-Connecting-IP");
	if (clientIp) {
		forwarded.headers.set("X-Forwarded-For", clientIp);
	} else {
		forwarded.headers.delete("X-Forwarded-For");
	}

	return getContainer(env.FORUM, CONTAINER_ID).fetch(forwarded);
}

export class ForumContainer extends Container {
	defaultPort = 8080; // deploy/apache-vhost.conf listens on 8080

	// Readiness must be a STATIC file, not an app route.
	//
	// The SDK aborts this probe after PING_TIMEOUT_MS (5s) and retries every
	// 300ms, and a container that never passes is never marked ready -- so every
	// real request blocks in startAndWaitForPorts() instead of being served.
	// The default `ping` resolves to `GET /`, which this app answers with a 302
	// to /setup; the Workers fetch follows that redirect, so one probe cost two
	// full PHP+MySQL renders (~2.7s each measured) and reliably blew the 5s
	// budget. Apache serves public/ping.txt directly (the vhost only rewrites to
	// index.php when the file does not exist), so this probe cannot be dragged
	// over the limit by app or database latency.
	//
	// This deliberately checks "Apache is serving", not "the app is well" -- a
	// broken app should return 5xx, which is diagnosable, rather than flap
	// readiness, which takes the whole deployment down. Application health stays
	// on /healthz.
	pingEndpoint = "ping/ping.txt";
	// A forum's traffic is bursty and a cold start pays image pull + Apache boot
	// + migrations. An hour of idle is cheap next to that; the cron ticks above
	// keep it warm during quiet periods anyway.
	sleepAfter = "1h";

	constructor(ctx, env) {
		super(ctx, env);

		// Everything the PHP app reads via Env::get(). Plain `vars` and secrets
		// arrive on `env` identically, so both are forwarded the same way.
		this.envVars = {
			APP_ENV: env.APP_ENV,
			APP_DEBUG: env.APP_DEBUG,
			APP_URL: env.APP_URL,
			APP_KEY: env.APP_KEY,
			SESSION_SECURE: env.SESSION_SECURE,
			SECURITY_HSTS: env.SECURITY_HSTS,
			TRUSTED_PROXIES: env.TRUSTED_PROXIES,
			MAIL_DRIVER: env.MAIL_DRIVER,
			MAIL_FROM: env.MAIL_FROM,
			MAIL_FROM_NAME: env.MAIL_FROM_NAME,
			MAIL_TIMEOUT_SECONDS: env.MAIL_TIMEOUT_SECONDS,
			CLOUDFLARE_EMAIL_API_TOKEN: env.CLOUDFLARE_EMAIL_API_TOKEN,

			DB_HOST: env.DB_HOST,
			DB_PORT: env.DB_PORT,
			DB_DATABASE: env.DB_DATABASE,
			DB_USERNAME: env.DB_USERNAME,
			DB_PASSWORD: env.DB_PASSWORD,
			DB_SSL: env.DB_SSL,
			DB_SSL_CA: env.DB_SSL_CA,
			DB_SSL_CA_PEM: env.DB_SSL_CA_PEM,

			R2_BUCKET: env.R2_BUCKET,
			R2_ACCOUNT_ID: env.R2_ACCOUNT_ID,
			R2_ACCESS_KEY_ID: env.R2_ACCESS_KEY_ID,
			R2_SECRET_ACCESS_KEY: env.R2_SECRET_ACCESS_KEY,

			UPLOADS_PATH: env.UPLOADS_PATH,
			PACKAGES_STORAGE_PATH: env.PACKAGES_STORAGE_PATH,
			RATELIMIT_PATH: env.RATELIMIT_PATH,
			RUN_MIGRATIONS: env.RUN_MIGRATIONS,
		};
	}

	/**
	 * Cloudflare's SDK gives a first start about eight seconds by default. The
	 * forum must mount R2 and run migrations before Apache can listen, which can
	 * exceed that on a cold image pull, so allocation gets a generous window.
	 *
	 * Readiness does not: once Apache is up, ping.txt answers immediately, so a
	 * probe that keeps failing means something is genuinely wrong. Keep that
	 * window short -- a long one does not rescue a broken boot, it just retries
	 * the probe every 300ms for the whole duration, which is how a single failing
	 * deploy turned into a sustained request flood against the container.
	 */
	async ensureStarted() {
		await this.startAndWaitForPorts({
			ports: [this.defaultPort],
			cancellationOptions: {
				instanceGetTimeoutMS: 120_000,
				portReadyTimeoutMS: 30_000,
			},
		});
	}

	async fetch(request) {
		await this.ensureStarted();

		const startedAt = Date.now();
		const response = await this.containerFetch(request, this.defaultPort);
		console.log(
			`container ${request.method} ${new URL(request.url).pathname} -> ${response.status} in ${Date.now() - startedAt}ms`,
		);

		return response;
	}

	/**
	 * Run a `bin/console` command inside the container and return its result.
	 * Called over RPC from the scheduled handler.
	 *
	 * @param {string[]} args
	 * @returns {Promise<{command: string, exitCode: number, stdout: string, stderr: string}>}
	 */
	async runConsole(args) {
		// exec() does not start a stopped container, and a cron tick can easily
		// land after sleepAfter has fired.
		await this.ensureStarted();

		// exec() runs the executable directly -- no shell, no PATH lookup, no
		// inherited working directory. Hence absolute paths.
		//
		// It also starts with an almost empty environment (HOME, PATH, PWD): the
		// variables handed to the container at start are the entrypoint process's,
		// not every later exec's. Without this the workers would run with no
		// DB_* configuration at all and every cron tick would fail.
		const proc = await this.ctx.container.exec(["php", CONSOLE, ...args], {
			env: this.envVars,
		});
		const { stdout, stderr, exitCode } = await proc.output();

		// output() buffers stdout/stderr as ArrayBuffers, not strings.
		const decoder = new TextDecoder();

		return {
			command: args.join(" "),
			exitCode,
			stdout: stdout ? decoder.decode(stdout) : "",
			stderr: stderr ? decoder.decode(stderr) : "",
		};
	}

}

export default {
	async fetch(request, env, ctx) {
		if (!isPublicAssetRequest(request)) {
			return fetchForum(request, env);
		}

		const cache = caches.default;
		const cacheKey = new Request(request.url, { method: "GET" });
		const cached = await cache.match(cacheKey);
		if (cached) {
			return withCacheStatus(cached, "HIT");
		}

		const response = await fetchForum(request, env);
		if (response.status !== 200) {
			return response;
		}

		const headers = new Headers(response.headers);
		headers.delete("Set-Cookie");
		headers.set("Cache-Control", EDGE_CACHE_CONTROL);
		const cacheable = new Response(response.body, {
			status: response.status,
			statusText: response.statusText,
			headers,
		});
		ctx.waitUntil(
			cache.put(cacheKey, cacheable.clone()).catch((err) => {
				console.error(`asset cache put failed: ${err}`);
			}),
		);

		return withCacheStatus(cacheable, "MISS");
	},

	async scheduled(controller, env, ctx) {
		const jobs = CRON_JOBS[controller.cron] ?? [];
		if (jobs.length === 0) {
			console.warn(`no jobs mapped for cron "${controller.cron}"`);
			return;
		}

		const container = getContainer(env.FORUM, CONTAINER_ID);

		// Sequential on purpose: these workers share the database and the
		// counters it maintains, and the container is sized for one app process.
		ctx.waitUntil(
			(async () => {
				for (const job of jobs) {
					try {
						const result = await container.runConsole([job]);
						if (result.exitCode !== 0) {
							console.error(
								`${job} exited ${result.exitCode}: ${result.stderr.trim()}`,
							);
						}
					} catch (err) {
						// One failing worker must not skip the rest of the tick.
						console.error(`${job} threw: ${err}`);
					}
				}
			})(),
		);
	},
};
