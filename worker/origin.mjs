/**
 * The origin hop: the PHP app runs on Google Cloud Run.
 *
 * The service only admits callers holding a Google-signed ID token for its URL
 * (Cloud Run IAM). This Worker mints one from the `retroboards-edge` service
 * account key in GCP_INVOKER_KEY and sends it in X-Serverless-Authorization,
 * which Cloud Run checks and strips, so the visitor's own Authorization header
 * (the forum API's bearer tokens) still reaches the app untouched.
 *
 * That token is also what makes the visitor-IP header trustworthy: nobody but
 * this Worker can reach the container to put an X-Forwarded-For in front of it.
 *
 * Runbook: docs/runbooks/deployment-cloud-run.md
 */

const TOKEN_URL = "https://oauth2.googleapis.com/token";

/** Assertion lifetime; Google caps both the assertion and the ID token at 1h. */
const ASSERTION_LIFETIME_S = 3600;

/** Renew this long before expiry so a token never lapses in flight. */
const RENEW_MARGIN_S = 300;

/** Per-isolate cache: { audience, token, expiresAt (unix seconds) }. */
let cached = null;

/** A mint in progress, shared so a burst of requests exchanges one assertion. */
let pending = null;

/** Test hook: forget any cached or in-flight token. */
export function resetOriginTokenCache() {
	cached = null;
	pending = null;
}

/**
 * A Google ID token whose audience is the Cloud Run service URL.
 *
 * @param {{ GCP_INVOKER_KEY?: string }} env
 * @param {string} audience the service URL, e.g. https://name-123.us-east4.run.app
 * @param {{ fetchImpl?: typeof fetch, now?: () => number }} [deps]
 * @returns {Promise<string>}
 */
export async function originToken(env, audience, { fetchImpl = fetch, now = Date.now } = {}) {
	const nowS = Math.floor(now() / 1000);
	if (cached !== null && cached.audience === audience && cached.expiresAt - RENEW_MARGIN_S > nowS) {
		return cached.token;
	}
	if (pending === null || pending.audience !== audience) {
		const mint = mintToken(env, audience, nowS, fetchImpl)
			.then((token) => {
				cached = { audience, token, expiresAt: expiryOf(token) ?? nowS + ASSERTION_LIFETIME_S };
				return token;
			})
			.finally(() => {
				if (pending?.promise === mint) pending = null;
			});
		pending = { audience, promise: mint };
	}
	return pending.promise;
}

/**
 * Forward a dynamic request to the Cloud Run origin.
 *
 * @param {Request} request the visitor's request, already on the canonical host
 * @param {{ ORIGIN_URL?: string, GCP_INVOKER_KEY?: string }} env
 * @param {{ fetchImpl?: typeof fetch, now?: () => number }} [deps]
 * @returns {Promise<Response>}
 */
export async function fetchOrigin(request, env, deps = {}) {
	const { fetchImpl = fetch } = deps;
	const origin = new URL(String(env.ORIGIN_URL ?? ""));
	const url = new URL(request.url);
	const target = new URL(upstreamPath(url.pathname) + url.search, origin);

	// Method, headers and body stream carry over from the visitor's request.
	const upstream = new Request(target, request);

	// The app resolves the client IP from X-Forwarded-For, trusting only the
	// configured proxy hops (src/Security/ClientIdentifier.php). Whatever the
	// client sent is discarded and CF-Connecting-IP -- set by the edge, not
	// forgeable by a client -- becomes the one hop this Worker vouches for.
	// Google's front end then appends this Worker's own egress address, which
	// TRUSTED_PROXIES (deploy/cloudrun/env.yaml) skips.
	const clientIp = request.headers.get("CF-Connecting-IP");
	if (clientIp) {
		upstream.headers.set("X-Forwarded-For", clientIp);
	} else {
		upstream.headers.delete("X-Forwarded-For");
	}
	upstream.headers.set("X-Serverless-Authorization", `Bearer ${await originToken(env, origin.origin, deps)}`);

	// The app's redirects (e.g. / -> /setup, /login?next=...) are for the
	// browser: following them here would serve the target under the old URL.
	return fetchImpl(upstream, { redirect: "manual" });
}

/**
 * Cloud Run's front end answers `/healthz` itself (a reserved path; it never
 * reaches the container), so the health check travels as `/healthz/`, which the
 * app routes identically. Everything else passes through untouched.
 */
function upstreamPath(pathname) {
	return pathname === "/healthz" ? "/healthz/" : pathname;
}

async function mintToken(env, audience, nowS, fetchImpl) {
	const key = parseKey(env.GCP_INVOKER_KEY);
	const header = { alg: "RS256", typ: "JWT", kid: key.private_key_id };
	const claims = {
		iss: key.client_email,
		sub: key.client_email,
		aud: TOKEN_URL,
		iat: nowS,
		exp: nowS + ASSERTION_LIFETIME_S,
		// Asks Google for an ID token (not an access token) for this audience.
		target_audience: audience,
	};
	const signingInput = `${base64url(JSON.stringify(header))}.${base64url(JSON.stringify(claims))}`;
	const signingKey = await crypto.subtle.importKey(
		"pkcs8",
		pemBody(key.private_key),
		{ name: "RSASSA-PKCS1-v1_5", hash: "SHA-256" },
		false,
		["sign"],
	);
	const signature = await crypto.subtle.sign(
		"RSASSA-PKCS1-v1_5",
		signingKey,
		new TextEncoder().encode(signingInput),
	);

	const response = await fetchImpl(TOKEN_URL, {
		method: "POST",
		headers: { "Content-Type": "application/x-www-form-urlencoded" },
		body: new URLSearchParams({
			grant_type: "urn:ietf:params:oauth:grant-type:jwt-bearer",
			assertion: `${signingInput}.${base64url(signature)}`,
		}).toString(),
	});
	if (!response.ok) {
		throw new Error(`origin token exchange failed: HTTP ${response.status}`);
	}
	const body = await response.json();
	if (typeof body?.id_token !== "string" || body.id_token === "") {
		throw new Error("origin token exchange returned no id_token");
	}
	return body.id_token;
}

function parseKey(json) {
	let key;
	try {
		key = JSON.parse(String(json ?? ""));
	} catch {
		throw new Error("GCP_INVOKER_KEY is not a service account key (invalid JSON)");
	}
	for (const field of ["client_email", "private_key", "private_key_id"]) {
		if (typeof key?.[field] !== "string" || key[field] === "") {
			throw new Error(`GCP_INVOKER_KEY is missing ${field}`);
		}
	}
	return key;
}

/** The DER bytes inside a PEM "PRIVATE KEY" block (PKCS #8). */
function pemBody(pem) {
	const base64 = pem.replace(/-----(BEGIN|END) PRIVATE KEY-----/g, "").replace(/\s+/g, "");
	return Uint8Array.from(atob(base64), (char) => char.charCodeAt(0));
}

/** RFC 7515 base64url of a UTF-8 string or raw bytes, unpadded. */
function base64url(data) {
	const bytes = typeof data === "string" ? new TextEncoder().encode(data) : new Uint8Array(data);
	let binary = "";
	for (const byte of bytes) binary += String.fromCharCode(byte);
	return btoa(binary).replace(/\+/g, "-").replace(/\//g, "_").replace(/=+$/, "");
}

/** The `exp` claim of a JWT, or null when it cannot be read. */
function expiryOf(jwt) {
	try {
		const payload = jwt.split(".")[1].replace(/-/g, "+").replace(/_/g, "/");
		const exp = JSON.parse(atob(payload.padEnd(payload.length + ((4 - (payload.length % 4)) % 4), "="))).exp;
		return Number.isInteger(exp) ? exp : null;
	} catch {
		return null;
	}
}
