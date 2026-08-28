<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\UserPreferenceRepository;
use Tests\Support\TestCase;

/**
 * Forum inbox remediation (ADR 0029): the fidelity gaps a design audit of
 * `templates/forum-inbox/ForumInbox.dc.html` found in the landed surface. Each
 * test pins the behaviour a fix restored, so a regression has to argue with an
 * assertion rather than merely look wrong in a browser.
 *
 * The two stylesheet tests are the cheap half of the lesson ADR 0028 recorded:
 * under a strict CSP a class the templates emit and the stylesheet never names
 * is a shipping rendering failure, and nothing but an assertion sees it.
 */
final class AppInboxRemediationTest extends TestCase
{
    private const CSS = __DIR__ . '/../../../public/assets/app.css';
    private const TOKENS = __DIR__ . '/../../../public/assets/imladris.css';

    private array $author;
    private array $board;

    protected function setUp(): void
    {
        parent::setUp();
        // The setup gate answers every route until the forum has an operator.
        $this->makeAdmin(['username' => 'inbox_remediation_admin']);
        $this->author = $this->makeUser(['username' => 'inbox_author', 'display_name' => 'Inbox Author']);
        $this->db->run('UPDATE users SET title = ? WHERE id = ?', ['Loremaster', (int) $this->author['id']]);
        $this->board = $this->makeBoard($this->makeCategory('Council'), ['slug' => 'inbox-remediation']);
    }

    /**
     * `.chip-reason` had no rules at all, so the inclusion cue — the row's
     * answer to "why am I being shown this?" — fell through to the base `.chip`
     * and rendered as an evergreen status pill shouting a whole sentence in
     * caps. `.inbox-empty-state` had none either, so an empty scope was a star,
     * a heading and a paragraph against the left gutter.
     */
    public function test_the_stylesheet_names_every_class_the_inbox_emits(): void
    {
        $css = file_get_contents(self::CSS);
        self::assertIsString($css);

        foreach ([
            // The two that had no rules whatsoever.
            '.inbox-row-chips .chip-reason', '.inbox-empty-state',
            // The reading pane's new anatomy.
            '.inbox-preview-attribution', '.inbox-preview-author', '.inbox-preview-tier',
            '.inbox-preview-lede', '.inbox-preview-count', '.inbox-preview-open',
            // The row and the bar.
            '.inbox-row-commends', '.inbox-view-bar', '.inbox-density', '.inbox-key-hint',
            '.inbox-select-all', '.inbox-thread-list', '.inbox-empty', '.inbox-empty-title',
        ] as $selector) {
            self::assertStringContainsString($selector, $css, $selector . ' has no rule in app.css');
        }
    }

    /**
     * The pre-Imladris inbox block sat 13,000 lines upstream of the block that
     * replaced it. `.inbox-empty-star { width: 56px }` survived the rewrite —
     * the replacement re-coloured the mark and never resized it — so the quiet
     * state printed at nearly twice the size the design asks for.
     */
    public function test_the_superseded_inbox_rules_are_gone(): void
    {
        $css = file_get_contents(self::CSS);
        self::assertIsString($css);

        foreach ([
            '.inbox-empty-star { width: 56px',
            '.inbox-list .inbox-tabs',
            '.inbox-list-head .eyebrow',
            '.inbox-list .thread-list',
        ] as $dead) {
            self::assertStringNotContainsString($dead, $css, $dead . ' is a superseded rule still in app.css');
        }

        // What the retired block still owns, and must keep owning.
        self::assertStringContainsString('body[data-route="inbox"] .app-shell { max-width: none; }', $css);
        self::assertStringContainsString('.inbox-reading .thread-view, .inbox-reading .board-view { max-width: 760px; }', $css);
    }

    /**
     * A board reference is a citation of the record and the design spends its
     * one --artifact-link on it. river-500 is 3.08:1 on the twilight page, so
     * the token has to climb in that register the way --info already does.
     */
    public function test_the_artifact_link_token_climbs_in_the_twilight_register(): void
    {
        $css = file_get_contents(self::CSS);
        $tokens = file_get_contents(self::TOKENS);
        self::assertIsString($css);
        self::assertIsString($tokens);

        self::assertStringContainsString('.inbox-row-meta a,', $css);
        self::assertStringContainsString('color: var(--artifact-link)', $css);
        self::assertStringContainsString('--artifact-link: var(--river-500)', $tokens);
        self::assertStringContainsString('--artifact-link: var(--river-200)', $tokens);
    }

    /**
     * The statement names the register the reader actually has — the same
     * sentence the board index states from the same CSS class. It read "Rows
     * follow your appearance preference", so two surfaces sharing one class
     * were saying different things.
     */
    public function test_the_density_statement_names_the_register_in_force(): void
    {
        $reader = $this->makeUser(['username' => 'density_reader']);
        $this->actingAs($reader);

        self::assertStringContainsString('Comfortable rows', $this->get('/inbox')->body());

        (new UserPreferenceRepository($this->db))->merge((int) $reader['id'], ['density' => 'compact']);
        $compact = $this->get('/inbox')->body();
        self::assertStringContainsString('Compact rows', $compact);
        self::assertStringContainsString('href="/settings/appearance"', $compact);
        self::assertStringNotContainsString('Rows follow your appearance preference', $compact);
    }

    /**
     * Commends are the Commended order's own column of numbers. Printed in every
     * order they were a fourth statistic competing for the meta line, which then
     * wrapped — turning a compact triage row into a three-line one.
     */
    public function test_commends_print_in_the_commended_order_and_nowhere_else(): void
    {
        $reader = $this->makeUser(['username' => 'commend_reader']);
        $commender = $this->makeUser(['username' => 'commend_giver']);
        $topic = $this->makeThread($this->board, $this->author, 'A commended topic');
        $this->actingAs($commender);
        $this->post('/posts/' . (int) $topic['post_id'] . '/react', ['emoji' => '👍']);

        $this->actingAs($reader);
        $this->post('/t/' . (int) $topic['thread_id'] . '/star', ['return' => '/inbox']);

        $active = $this->get('/inbox', ['scope' => 'starred', 'order' => 'active'])->body();
        self::assertStringContainsString('A commended topic', $active);
        self::assertStringNotContainsString('inbox-row-commends', $active);

        $commended = $this->get('/inbox', ['scope' => 'starred', 'order' => 'commended'])->body();
        self::assertStringContainsString('inbox-row-commends', $commended);
    }

    /**
     * The design's pane names the author, their standing and the size of the
     * conversation on one ruled line before it prints a word of the post, and
     * sets the opening post as the topic's lede rather than the first row of
     * the reply list. The transfer had no byline at all.
     */
    public function test_the_reading_pane_states_the_topic_author_before_the_post(): void
    {
        $reader = $this->makeUser(['username' => 'pane_reader']);
        $topic = $this->makeThread($this->board, $this->author, 'A topic with an author', 'The opening statement.');
        $this->actingAs($reader);

        $body = $this->get('/inbox/preview/' . (int) $topic['thread_id'])->body();
        self::assertStringContainsString('inbox-preview-attribution', $body);
        self::assertStringContainsString('Inbox Author', $body);
        self::assertStringContainsString('>Loremaster<', $body);
        self::assertStringContainsString('inbox-preview-lede', $body);
        self::assertStringContainsString('The opening statement.', $body);

        // The lede is the opening post, so it is not also a row of the list.
        self::assertSame(0, substr_count($body, 'data-inbox-preview-post'));

        // The kicker times the topic the way the row that opened the pane does.
        self::assertMatchesRegularExpression('/inbox-preview-kicker.*?(ago|just now)/s', $body);
    }

    /**
     * The pane prints an author now, so it has to prove it prints the mask
     * instead — and withholds the rank, which would narrow the field the mask
     * exists to widen.
     */
    public function test_an_anonymously_opened_topic_is_masked_and_states_no_rank(): void
    {
        $reader = $this->makeUser(['username' => 'anon_pane_reader']);
        $topic = $this->makeThread($this->board, $this->author, 'A topic opened anonymously', 'Unsigned, on purpose.');
        $this->db->run('UPDATE posts SET is_anonymous = 1 WHERE thread_id = ? AND is_op = 1', [(int) $topic['thread_id']]);
        $this->actingAs($reader);

        $body = $this->get('/inbox/preview/' . (int) $topic['thread_id'])->body();
        self::assertStringContainsString('inbox-preview-attribution', $body);
        self::assertStringContainsString('Anonymous', $body);
        self::assertStringNotContainsString('Inbox Author', $body);
        self::assertStringNotContainsString('inbox_author', $body);
        self::assertStringNotContainsString('inbox-preview-tier', $body);
    }
}
