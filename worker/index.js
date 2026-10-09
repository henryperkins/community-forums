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

export default {
	async fetch(request, env) {
		// One canonical origin (APP_URL): every other hostname that reaches this
		// Worker is redirected there before anything else runs. See canonical.mjs.
		return canonicalRedirect(request, env) ?? routeRequest(request, env, () => fetchForum(request, env));
	},
};
