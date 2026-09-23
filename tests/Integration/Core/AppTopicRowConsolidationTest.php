<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\TagRepository;
use App\Repository\ThreadUserRepository;
use Tests\Support\TestCase;

/**
 * One topic row with a presentation axis, and one star (ADR 0035). The inbox
 * used to render its own partial with its own star, the board printed a ★
 * character, and the topic head drew the commend star: three stars for one
 * bookmark, two rows for one object.
 */
final class AppTopicRowConsolidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(['username' => 'topic_row_admin']);
    }

    public function test_the_inbox_renders_the_one_topic_row_in_its_queue_presentation(): void
    {
        [$viewer, $starred] = $this->starredTopicFor('row_queue_viewer', 'row-queue', 'A queued topic');
        $this->actingAs($viewer);

        $body = $this->get('/inbox', ['scope' => 'starred', 'order' => 'active'])->body();
        $id = (int) $starred['thread_id'];

        self::assertStringContainsString('<li class="thread-row thread-row-inbox', $body);
        self::assertStringContainsString('data-inbox-row data-thread-id="' . $id . '"', $body);
        self::assertStringContainsString('<a class="thread-title" href="/t/' . $id . '-', $body);
        self::assertStringContainsString('data-inbox-preview-url="/inbox/preview/' . $id . '"', $body);
        self::assertStringContainsString('<span class="thread-row-select">', $body);
        self::assertStringContainsString('<details class="thread-row-menu" data-inbox-row-menu>', $body);
        self::assertStringContainsString('<div class="thread-row-menu-panel">', $body);
        self::assertDoesNotMatchRegularExpression('~class="[^"]*\binbox-(?:thread-row|row-)~', $body);
        self::assertFileDoesNotExist(dirname(__DIR__, 3) . '/templates/partials/inbox_thread_row.php');
    }

    public function test_the_star_is_one_control_and_one_glyph_on_every_surface(): void
    {
        [$viewer, $starred, $board] = $this->starredTopicFor('row_star_viewer', 'row-star', 'A starred topic');
        $plain = $this->makeThread($board, $this->makeUser(['username' => 'row_star_other']), 'A plain topic');
        $threadUsers = new ThreadUserRepository($this->db);
        $threadUsers->markUnread((int) $viewer['id'], (int) $starred['thread_id']);
        $threadUsers->markUnread((int) $viewer['id'], (int) $plain['thread_id']);
        $this->actingAs($viewer);

        // The queue's toggle: the name names the topic, the pressed state carries
        // the star, and the glyph is outlined until the topic is starred.
        $queue = $this->get('/inbox', ['scope' => 'unread', 'order' => 'active'])->body();
        self::assertMatchesRegularExpression(
            '~<button class="star-toggle" type="submit" aria-pressed="true" aria-label="Star A starred topic" title="Starred"><svg class="icon icon-commend-star" ~',
            $queue,
        );
        self::assertMatchesRegularExpression(
            '~<button class="star-toggle" type="submit" aria-pressed="false" aria-label="Star A plain topic" title="Star"><svg class="icon icon-commend-star is-outline" ~',
            $queue,
        );
        self::assertStringContainsString('data-inbox-action="star"', $queue);

        // The topic head: the same control at its labelled size.
        $topicResponse = $this->get('/t/' . (int) $starred['thread_id'] . '-a-starred-topic');
        $this->assertStatus(200, $topicResponse);
        $topic = $topicResponse->body();
        self::assertStringContainsString('<form class="inline star-form" method="post" action="/t/' . (int) $starred['thread_id'] . '/star">', $topic);
        self::assertMatchesRegularExpression('~<button class="linkbtn star-btn star-on" type="submit" aria-pressed="true"><svg class="icon icon-commend-star" [^>]*>.*?</svg>\s*<span>Starred</span></button>~s', $topic);

        // The index: a quiet marker, with a name a screen reader announces.
        $index = $this->get('/c/row-star')->body();
        self::assertStringContainsString('<span class="thread-star" title="Starred" role="img" aria-label="Starred"><svg class="icon icon-commend-star" ', $index);

        foreach (['queue' => $queue, 'topic' => $topic, 'index' => $index] as $surface => $html) {
            self::assertStringNotContainsString('★', $html, $surface . ' prints the ★ character');
            self::assertStringNotContainsString('☆', $html, $surface . ' prints the ☆ character');
            self::assertStringNotContainsString('icon-star-filled', $html, $surface . ' draws the retired five-point star');
            self::assertDoesNotMatchRegularExpression('~class="icon icon-star[ "]~', $html, $surface . ' draws the retired five-point star');
        }
    }

    public function test_no_template_prints_a_star_character(): void
    {
        $root = dirname(__DIR__, 3) . '/templates';
        $offenders = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            if (str_contains($source, '★') || str_contains($source, '☆')) {
                $offenders[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }

        self::assertSame([], $offenders, 'The commend star is the one esteem glyph; draw it with partials/icon.');
    }

    public function test_topic_facts_read_the_same_in_every_presentation(): void
    {
        [$viewer, $starred] = $this->starredTopicFor('row_facts_viewer', 'row-facts', 'A decided topic');
        $id = (int) $starred['thread_id'];
        $this->db->run("UPDATE threads SET status = 'decision_made' WHERE id = ?", [$id]);
        $tags = new TagRepository($this->db);
        $tagId = $tags->create('row-facts', 'Row facts', null, (int) $viewer['id']);
        $tags->setForThread($id, [$tagId], (int) $viewer['id']);
        $this->actingAs($viewer);

        $surfaces = [
            'list' => $this->get('/tags/row-facts')->body(),
            'index' => $this->get('/c/row-facts')->body(),
            'queue' => $this->get('/inbox', ['scope' => 'starred', 'order' => 'active'])->body(),
        ];
        foreach ($surfaces as $surface => $html) {
            self::assertMatchesRegularExpression('~<span class="chip chip-decision_made">(?:<svg[^>]*>.*?</svg>)?Decision</span>~s', $html, $surface . ' names the status in the ledger\'s word');
            self::assertStringNotContainsString('Decision Made', $html, $surface);
            // Last activity is elapsed time on the page and the exact instant on the element.
            self::assertMatchesRegularExpression('~<time datetime="\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|\+00:00)" title="[^"]+ UTC">[^<]+</time>~', $html, $surface);
        }
    }

    public function test_board_favourites_draw_the_same_star(): void
    {
        $member = $this->makeUser(['username' => 'row_favourite_member']);
        $board = $this->makeBoard($this->makeCategory('Favourites'), ['slug' => 'row-favourites', 'name' => 'Row Favourites']);
        $this->actingAs($member);

        $before = $this->get('/settings/boards')->body();
        self::assertStringContainsString('<svg class="icon icon-commend-star board-pref-star is-outline" ', $before);
        self::assertStringNotContainsString('☆', $before);

        $this->post('/settings/boards/toggle', ['board_id' => (int) $board['id'], 'pref' => 'favorite']);
        $after = $this->get('/settings/boards')->body();
        self::assertStringContainsString('<svg class="icon icon-commend-star board-pref-star" ', $after);
        self::assertStringContainsString('Favorited', $after);
        self::assertStringNotContainsString('★', $after);
    }

    public function test_each_presentation_is_insulated_from_the_default_list_registers(): void
    {
        $root = dirname(__DIR__, 3);
        $application = (string) file_get_contents($root . '/public/assets/app.css');
        $source = (string) file_get_contents($root . '/docs/design-system/imladris/components.css');

        foreach (['application' => $application, 'source' => $source] as $name => $css) {
            self::assertStringNotContainsString('.inbox-thread-row', $css, $name . ' still styles the retired queue row');
            self::assertStringNotContainsString('.inbox-row-', $css, $name . ' still styles the retired queue row');
        }
        // The queue keeps ADR 0029's geometry against the generic row: no status
        // left-rule, no clip over the no-JavaScript row menu, a stacked copy column.
        self::assertMatchesRegularExpression('~\.thread-row\.thread-row-inbox \{[^}]*overflow: visible;~', $application);
        self::assertStringContainsString('.thread-row-inbox::before { content: none; }', $application);
        self::assertStringContainsString('.thread-row-inbox .thread-row-main { display: block; min-width: 0; flex: 1; }', $application);
        // The board's copy stacks at every density: compact used to crop a long
        // title into a column of single clipped words.
        self::assertStringContainsString('.board-view .thread-row-board .thread-row-main { min-width: 0; display: block; }', $application);
        self::assertMatchesRegularExpression('~\.board-view \.thread-row-board \.thread-title \{[^}]*text-overflow: clip;[^}]*white-space: normal;~', $application);
        self::assertStringContainsString('.icon-commend-star.is-outline {', $application);
    }

    /** @return array{0: array<string,mixed>, 1: array<string,mixed>, 2: array<string,mixed>} */
    private function starredTopicFor(string $username, string $boardSlug, string $title): array
    {
        $viewer = $this->makeUser(['username' => $username]);
        $author = $this->makeUser(['username' => $username . '_author']);
        $board = $this->makeBoard($this->makeCategory('Rows ' . $boardSlug), ['slug' => $boardSlug]);
        $thread = $this->makeThread($board, $author, $title);
        (new ThreadUserRepository($this->db))->setStar((int) $viewer['id'], (int) $thread['thread_id'], true);

        return [$viewer, $thread, $board];
    }
}
