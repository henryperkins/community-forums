<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\BoardMemberRepository;
use Tests\Support\TestCase;

/**
 * P3-10: public discovery. The sitemap lists public canonical URLs and excludes
 * private/hidden boards, held, and deleted content; robots disallows the
 * authenticated areas; public pages carry a canonical link; private/search
 * surfaces are noindex.
 */
final class AppSeoVisibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin();
    }

    public function test_robots_txt_disallows_private_areas_and_points_at_sitemap(): void
    {
        $res = $this->get('/robots.txt');
        $this->assertStatus(200, $res);
        self::assertStringContainsString('text/plain', (string) $res->getHeader('content-type'));
        $this->assertSeeText($res, 'Disallow: /settings');
        $this->assertSeeText($res, 'Disallow: /admin');
        $this->assertSeeText($res, 'Disallow: /messages');
        $this->assertSeeText($res, 'Sitemap:');
    }

    public function test_sitemap_includes_public_excludes_private_and_hidden(): void
    {
        $cat = $this->makeCategory();
        $pub = $this->makeBoard($cat, ['slug' => 'public-board']);
        $priv = $this->makeBoard($cat, ['slug' => 'private-board', 'visibility' => 'private']);

        $author = $this->makeUser(['username' => 'seoauthor']);
        (new BoardMemberRepository($this->db))->add((int) $priv['id'], (int) $author['id'], null);

        $visible = $this->makeThread($pub, $author, 'Indexable topic');
        $hidden = $this->makeThread($priv, $author, 'Private topic');
        $deleted = $this->makeThread($pub, $author, 'Deleted topic');
        $this->db->run('UPDATE threads SET is_deleted = 1 WHERE id = ?', [$deleted['thread_id']]);
        $pending = $this->makeThread($pub, $author, 'Pending topic');
        $this->db->run('UPDATE threads SET is_pending = 1 WHERE id = ?', [$pending['thread_id']]);

        $res = $this->get('/sitemap.xml');
        $this->assertStatus(200, $res);
        self::assertStringContainsString('application/xml', (string) $res->getHeader('content-type'));

        $this->assertSeeText($res, '/c/public-board');
        $this->assertSeeText($res, '/t/' . $visible['thread_id'] . '-' . $visible['slug']);

        $this->assertDontSeeText($res, '/c/private-board');
        $this->assertDontSeeText($res, '/t/' . $hidden['thread_id'] . '-');
        $this->assertDontSeeText($res, '/t/' . $deleted['thread_id'] . '-');
        $this->assertDontSeeText($res, '/t/' . $pending['thread_id'] . '-');
    }

    public function test_sitemap_excludes_archived_board_threads(): void
    {
        $cat = $this->makeCategory();
        $live = $this->makeBoard($cat, ['slug' => 'live-board']);
        $archived = $this->makeBoard($cat, ['slug' => 'archived-board']);
        $author = $this->makeUser(['username' => 'archauthor']);
        $liveT = $this->makeThread($live, $author, 'Live indexable');
        $archT = $this->makeThread($archived, $author, 'Archived topic');
        $this->db->run('UPDATE boards SET is_archived = 1 WHERE id = ?', [(int) $archived['id']]);

        $res = $this->get('/sitemap.xml');
        $this->assertSeeText($res, '/t/' . $liveT['thread_id'] . '-' . $liveT['slug']);
        $this->assertDontSeeText($res, '/c/archived-board');               // board already excluded
        $this->assertDontSeeText($res, '/t/' . $archT['thread_id'] . '-'); // and now its threads too
    }

    public function test_seo_subsystem_can_be_disabled(): void
    {
        (new \App\Repository\SettingRepository($this->db))->set('features', ['seo' => false]);
        $this->assertStatus(404, $this->get('/sitemap.xml'));
        // robots still serves, but no longer advertises the (now 404) sitemap.
        $robots = $this->get('/robots.txt');
        $this->assertStatus(200, $robots);
        $this->assertDontSeeText($robots, 'Sitemap:');
    }

    public function test_public_thread_has_canonical_link(): void
    {
        $cat = $this->makeCategory();
        $board = $this->makeBoard($cat, ['slug' => 'canon-board']);
        $author = $this->makeUser(['username' => 'canonauthor']);
        $t = $this->makeThread($board, $author, 'Canonical topic');

        $res = $this->get('/t/' . $t['thread_id'] . '-' . $t['slug']);
        $this->assertStatus(200, $res);
        $this->assertSeeText($res, 'rel="canonical"');
        $this->assertSeeText($res, '/t/' . $t['thread_id'] . '-' . $t['slug']);
    }

    public function test_search_page_is_noindex(): void
    {
        $user = $this->makeUser(['username' => 'searcher']);
        $this->actingAs($user);
        $this->assertSeeText($this->get('/search'), 'name="robots" content="noindex, nofollow"');
    }

    public function test_private_board_page_is_noindex(): void
    {
        $cat = $this->makeCategory();
        $priv = $this->makeBoard($cat, ['slug' => 'hush-board', 'visibility' => 'private']);
        $member = $this->makeUser(['username' => 'hushmember']);
        (new BoardMemberRepository($this->db))->add((int) $priv['id'], (int) $member['id'], null);
        $this->actingAs($member);

        $this->assertSeeText($this->get('/c/hush-board'), 'noindex, nofollow');
    }
}
