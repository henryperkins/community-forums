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

/** Cron expression -> `bin/console` commands, in run order. */
const CRON_JOBS = {
	"*/5 * * * *": ["worker:email", "worker:webhooks"],
	"0 */6 * * *": ["worker:registry-refresh"],
	"10 3 * * *": ["worker:purge-ips", "worker:attachments", "worker:packages"],
	"0 7 * * *": ["worker:digest"],
};

export class ForumContainer extends Container {
	defaultPort = 8080; // deploy/apache-vhost.conf listens on 8080
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
	 * forum must mount R2 and confirm migrations before Apache can listen, which
	 * can exceed that on a cold image pull. Give both allocation and readiness a
	 * bounded two-minute window instead of aborting an otherwise healthy boot.
	 */
	async ensureStarted() {
		await this.startAndWaitForPorts({
			ports: [this.defaultPort],
			cancellationOptions: {
				instanceGetTimeoutMS: 120_000,
				portReadyTimeoutMS: 120_000,
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
		const proc = await this.ctx.container.exec(["php", CONSOLE, ...args]);
		const { stdout, stderr, exitCode } = await proc.output();

		return {
			command: args.join(" "),
			exitCode,
			stdout: stdout ?? "",
			stderr: stderr ?? "",
		};
	}
}

export default {
	async fetch(request, env) {
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
