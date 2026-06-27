<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\Database;
use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;

/**
 * Public discovery surfaces (P3-10): sitemap + robots. The sitemap lists only
 * publicly readable canonical URLs — public boards and their non-deleted,
 * non-pending threads in public boards — and never private/hidden boards, held
 * or deleted content, DMs, settings, moderation, or tokenized URLs. robots.txt
 * disallows the authenticated/sensitive areas and points at the sitemap.
 */
final class SeoController extends Controller
{
    private const MAX_THREADS = 5000;

    public function sitemap(Request $request): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('seo')) {
            throw new NotFoundException();
        }
        $base = rtrim((string) $this->config()->get('app.url', ''), '/');
        $db = $this->container->get(Database::class);

        $urls = [$base . '/'];

        foreach ($db->fetchAll("SELECT slug FROM boards WHERE visibility = 'public' AND is_archived = 0 ORDER BY position ASC") as $b) {
            $urls[] = $base . '/c/' . rawurlencode((string) $b['slug']);
        }

        $threads = $db->fetchAll(
            "SELECT t.id, t.slug FROM threads t
             JOIN boards b ON b.id = t.board_id
             WHERE b.visibility = 'public' AND b.is_archived = 0
               AND t.is_deleted = 0 AND t.is_pending = 0
             ORDER BY t.last_post_at DESC
             LIMIT " . self::MAX_THREADS,
        );
        foreach ($threads as $t) {
            $urls[] = $base . '/t/' . (int) $t['id'] . '-' . rawurlencode((string) $t['slug']);
        }

        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $xml .= "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";
        foreach ($urls as $loc) {
            $xml .= '  <url><loc>' . htmlspecialchars($loc, ENT_QUOTES | ENT_XML1) . "</loc></url>\n";
        }
        $xml .= "</urlset>\n";

        return new Response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots(Request $request): Response
    {
        $base = rtrim((string) $this->config()->get('app.url', ''), '/');
        $lines = [
            'User-agent: *',
            // Authenticated / private / non-canonical areas are kept out of crawls.
            'Disallow: /settings',
            'Disallow: /admin',
            'Disallow: /mod',
            'Disallow: /messages',
            'Disallow: /notifications',
            'Disallow: /search',
            'Disallow: /upload',
            'Disallow: /media/',
            'Disallow: /unsubscribe',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /reset',
            'Disallow: /verify',
            'Allow: /',
        ];
        // Only advertise the sitemap when the SEO subsystem is enabled (else it 404s).
        if ($this->container->get(FeatureFlags::class)->enabled('seo')) {
            $lines[] = 'Sitemap: ' . $base . '/sitemap.xml';
        }
        return Response::text(implode("\n", $lines) . "\n");
    }
}
