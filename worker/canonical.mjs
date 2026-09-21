/**
 * Single-origin policy, enforced at the edge.
 *
 * The app has exactly one canonical origin, APP_URL. Absolute links in email,
 * the sitemap, OAuth callback URIs and the WebAuthn relying party all derive
 * from it, and the session and CSRF cookies are host-bound. Serving the forum
 * on a second hostname would split sessions, break passkeys and OAuth on the
 * non-canonical host, and publish duplicate content. So any hostname that
 * reaches this Worker other than the canonical one is answered with a redirect
 * to the same path and query on the canonical origin.
 *
 * That makes attaching a hostname (a Workers Custom Domain, or a Cloudflare for
 * SaaS custom hostname) safe at any time: the new name redirects to the current
 * APP_URL until APP_URL is flipped, after which the old name redirects to the
 * new one. The redirect is never cached, so a flip cannot leave a browser
 * holding a stale redirect in each direction (a loop).
 *
 * Runbook: docs/runbooks/deployment-cloudflare.md §16
 */

const LOOPBACK = new Set(["localhost", "127.0.0.1", "[::1]"]);

/**
 * Certificate validation for a Cloudflare for SaaS custom hostname: the
 * certificate authority fetches a token from these paths, and Cloudflare serves
 * it from the edge on the way to the origin. A redirect would fail issuance and
 * every renewal after it, so on a non-canonical host these paths are handed to
 * the origin pipeline instead of being redirected.
 */
const DCV_PATH_PREFIXES = [
	"/.well-known/pki-validation/",
	"/.well-known/acme-challenge/",
	"/.well-known/cf-custom-hostname-challenge/",
];

/**
 * A Custom Domain re-enters this Worker on fetch() (routes do not), so the
 * pass-through marks the request and lets the app answer the second time.
 */
const PASSTHROUGH_HEADER = "X-RetroBoards-DCV-Passthrough";

/** @returns {URL|null} APP_URL parsed, or null when it is missing or not http(s). */
export function canonicalOrigin(env) {
	try {
		const url = new URL(String(env?.APP_URL ?? ""));
		return url.protocol === "https:" || url.protocol === "http:" ? url : null;
	} catch {
		return null;
	}
}

/**
 * @param {Request} request
 * @param {{ APP_URL?: string }} env
 * @param {(request: Request) => Promise<Response>} passThrough origin fetch for
 *   certificate-validation paths; injectable for tests
 * @returns {Response|Promise<Response>|null} a redirect to the canonical origin,
 *   the origin's answer for a certificate-validation path, or null when the
 *   request is already canonical. Fails open (null) when APP_URL is unusable or
 *   the request targets a loopback host (`wrangler dev`): a misconfiguration
 *   must never lock the site out.
 */
export function canonicalRedirect(request, env, passThrough = fetch) {
	const canonical = canonicalOrigin(env);
	if (canonical === null) return null;

	const url = new URL(request.url);
	if (LOOPBACK.has(url.hostname) || url.hostname.endsWith(".localhost")) return null;
	if (url.protocol === canonical.protocol && url.host === canonical.host) return null;

	if (DCV_PATH_PREFIXES.some((prefix) => url.pathname.startsWith(prefix))) {
		if (request.headers.has(PASSTHROUGH_HEADER)) return null;
		const forwarded = new Request(request);
		forwarded.headers.set(PASSTHROUGH_HEADER, "1");
		return passThrough(forwarded);
	}

	url.protocol = canonical.protocol;
	url.hostname = canonical.hostname;
	url.port = canonical.port; // "" clears a stray port; a non-default canonical port is carried over
	url.hash = ""; // never on the wire in production; strip it for local runtimes that keep it

	const method = request.method.toUpperCase();
	return new Response(null, {
		// Permanent for the safe methods (search engines move ranking to the
		// canonical host); 308 keeps the method and body for everything else.
		status: method === "GET" || method === "HEAD" ? 301 : 308,
		headers: {
			Location: url.toString(),
			"Cache-Control": "no-store",
		},
	});
}
