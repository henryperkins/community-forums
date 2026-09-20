import manifest from "../config/assets.json" with { type: "json" };

const IMMUTABLE = "public, max-age=31536000, immutable";
const REVALIDATE = "public, max-age=0, must-revalidate";

/** Static requests never wake the container, including missing assets. */
export async function routeRequest(request, env, fetchForum) {
	const url = new URL(request.url);
	if (!url.pathname.startsWith("/assets/")) return fetchForum();
	if (request.method !== "GET" && request.method !== "HEAD") {
		return new Response(null, { status: 405, headers: { Allow: "GET, HEAD", "Cache-Control": "no-store" } });
	}
	const asset = Object.hasOwn(manifest.files, url.pathname) ? manifest.files[url.pathname] : null;
	if (!asset) return new Response(null, { status: 404, headers: { "Cache-Control": "no-store" } });

	// Pass conditional/range headers to the Assets binding. It owns ETag matching,
	// 304 responses, HEAD, compression and the static edge cache.
	const response = await env.ASSETS.fetch(request);
	const headers = new Headers(response.headers);
	const version = url.searchParams.getAll("v");
	const versioned = asset.immutable || (version.length === 1 && version[0] === manifest.version);
	headers.set("Cache-Control", [200, 206, 304].includes(response.status)
		? (versioned ? IMMUTABLE : REVALIDATE)
		: "no-store");
	headers.set("X-Content-Type-Options", "nosniff");
	headers.set("X-RetroBoards-Cache", "STATIC");
	headers.delete("Set-Cookie");
	return new Response(response.body, { status: response.status, statusText: response.statusText, headers });
}
