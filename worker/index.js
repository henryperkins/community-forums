import { Container } from "@cloudflare/containers";
import { routeRequest } from "./assets.mjs";
import { canonicalRedirect } from "./canonical.mjs";
import { fetchOrigin } from "./origin.mjs";

/**
 * RetroBoards front door.
 *
 * The Worker enforces the single canonical origin (APP_URL), serves the compiled
 * public assets, establishes the trusted client IP, and forwards everything else
 * to the PHP app on Google Cloud Run (worker/origin.mjs). Cron work runs there
 * too, as Cloud Run jobs started by Cloud Scheduler.
 *
 * Runbooks: docs/runbooks/deployment-cloud-run.md (origin, cron),
 *           docs/runbooks/deployment-cloudflare.md (this Worker, domains)
 */

const CONSOLE = "/var/www/html/bin/console";

async function fetchForum(request, env) {
	try {
		return await fetchOrigin(request, env);
	} catch (err) {
		// No token, an unreachable token endpoint or a bad ORIGIN_URL: answer
		// with a retryable gateway error instead of the platform's 1101 page.
		console.error(`origin fetch failed: ${err}`);
		return new Response("The forum is temporarily unavailable. Please retry in a moment.", {
			status: 502,
			headers: { "Content-Type": "text/plain; charset=utf-8", "Cache-Control": "no-store", "Retry-After": "5" },
		});
	}
}

/**
 * DORMANT: the Cloudflare Containers origin that served the app until the
 * Cloud Run move. Nothing calls it any more, so its instance sleeps and costs
 * nothing; it stays defined so `wrangler rollback` to a pre-move version still
 * finds its Durable Object class. Removal (class, binding, `deleted_classes`
 * migration) is runbook deployment-cloud-run.md §9.
 */
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
			DB_EMULATE_PREPARES: env.DB_EMULATE_PREPARES,
			DB_SSL: env.DB_SSL,
			DB_SSL_CA: env.DB_SSL_CA,
			DB_SSL_CA_PEM: env.DB_SSL_CA_PEM,
			DB_SSL_VERIFY: env.DB_SSL_VERIFY,

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
	async fetch(request, env) {
		// One canonical origin (APP_URL): every other hostname that reaches this
		// Worker is redirected there before anything else runs. See canonical.mjs.
		return canonicalRedirect(request, env) ?? routeRequest(request, env, () => fetchForum(request, env));
	},
};
