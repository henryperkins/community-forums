<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\NotificationRepository;
use Tests\Support\TestCase;

/**
 * Board index remediation (ADR 0028): the fidelity gaps and defects a design
 * audit of `templates/board-index/BoardIndex.dc.html` found in the landed
 * surface. Each test pins the behaviour the fix restored, so a regression has
 * to argue with an assertion rather than merely look wrong in a browser.
 */
final class AppForumIndexRemediationTest extends TestCase
{
    private const CSS = __DIR__ . '/../../../public/assets/app.css';

    private array $author;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeAdmin(['username' => 'remediation_admin']);
        $this->author = $this->makeUser(['username' => 'remediation_author', 'display_name' => 'Remediation Author']);
    }

    /**
     * The dot exists so a member reading ANOTHER pane learns something is
     * waiting (BoardIndex.dc.html:620). The unread count was resolved only
     * while already on the Notices pane, which put the signal in the one place
     * it could say nothing new.
     */
    public function test_the_notices_dot_reaches_a_member_reading_the_boards_pane(): void
    {
        $reader = $this->makeUser(['username' => 'dot_reader']);
        $board = $this->makeBoard($this->makeCategory('Council'), ['slug' => 'dot-board']);
        $topic = $this->makeThread($board, $this->author, 'A topic worth a notice');
        $this->actingAs($reader);

        $quiet = $this->get('/')->body();
        self::assertStringNotContainsString('directory-tab-dot', $quiet);

        (new NotificationRepository($this->db))->create([
            'user_id' => (int) $reader['id'],
            'type' => 'reply',
            'actor_id' => (int) $this->author['id'],
            'thread_id' => $topic['thread_id'],
        ]);

        // The dot is visible from Boards — the pane the member is actually on.
        $boards = $this->get('/')->body();
        self::assertStringContainsString('data-directory-pane="boards"', $boards);
        self::assertStringContainsString('directory-tab-dot', $boards);
        self::assertStringContainsString('Unread notices', $boards);

        // And from every other pane the surface offers.
        foreach (['tags', 'connections'] as $pane) {
            self::assertStringContainsString('directory-tab-dot', $this->get('/', ['pane' => $pane])->body());
        }

        $this->post('/notifications/read-all', []);
        self::assertStringNotContainsString('directory-tab-dot', $this->get('/')->body());
    }

    /**
     * The design's notice names its topic — "Galadriel mentioned you in
     * 'Evaluations as ritual, not gate'" (BoardIndex.dc.html:448). recent()
     * already selected thread_title; the pane threw it away, so every notice of
     * a kind read identically.
     */
    public function test_a_notice_names_its_topic_and_states_its_unread_state_in_text(): void
    {
        $reader = $this->makeUser(['username' => 'notice_reader']);
        $board = $this->makeBoard($this->makeCategory('Council'), ['slug' => 'notice-board']);
        $topic = $this->makeThread($board, $this->author, 'Reading attention as a map');
        $notices = new NotificationRepository($this->db);
        $notices->create([
            'user_id' => (int) $reader['id'],
            'type' => 'mention',
            'actor_id' => (int) $this->author['id'],
            'thread_id' => $topic['thread_id'],
        ]);
        $this->actingAs($reader);

        $unread = $this->get('/', ['pane' => 'notices'])->body();
        self::assertStringContainsString('Remediation Author mentioned you in', $unread);
        self::assertStringContainsString('class="directory-notice-topic">“Reading attention as a map”', $unread);
        self::assertStringContainsString('class="is-unread"', $unread);
        // Unread never rests on colour alone.
        self::assertStringContainsString('class="sr-only">Unread.', $unread);
        self::assertMatchesRegularExpression('~action="/notifications/read-all"(?s).{0,400}?<button[^>]*>Mark all read~', $unread);
        self::assertDoesNotMatchRegularExpression('~<button[^>]*disabled[^>]*>Mark all read~', $unread);

        $this->post('/notifications/read-all', []);

        $read = $this->get('/', ['pane' => 'notices'])->body();
        self::assertStringContainsString('class="is-read"', $read);
        self::assertStringNotContainsString('class="sr-only">Unread.', $read);
        // Nothing left to mark, so the control says so.
        self::assertMatchesRegularExpression('~<button[^>]*disabled[^>]*>Mark all read~', $read);
    }

    /**
     * Notices is a pane of this surface as well as a standalone page, so a bulk
     * action has to come back to the pane it was invoked from. Both actions
     * always redirected to /notifications, which threw the member off the board
     * index entirely. The return target reuses the settings guard, so it can
     * never be pointed off-site.
     */
    public function test_a_bulk_notice_action_returns_to_the_pane_it_was_invoked_from(): void
    {
        $reader = $this->makeUser(['username' => 'return_reader']);
        $board = $this->makeBoard($this->makeCategory('Council'), ['slug' => 'return-board']);
        $topic = $this->makeThread($board, $this->author, 'Something happened');
        $notices = new NotificationRepository($this->db);
        foreach (['reply', 'mention'] as $type) {
            $notices->create([
                'user_id' => (int) $reader['id'],
                'type' => $type,
                'actor_id' => (int) $this->author['id'],
                'thread_id' => $topic['thread_id'],
            ]);
        }
        $this->actingAs($reader);

        // The pane's own forms carry the return target.
        self::assertStringContainsString(
            '<input type="hidden" name="return" value="/?pane=notices">',
            $this->get('/', ['pane' => 'notices'])->body(),
        );

        $this->assertRedirect($this->post('/notifications/read-all', ['return' => '/?pane=notices']), '/?pane=notices');
        $this->assertRedirect($this->post('/notifications/clear', ['return' => '/?pane=notices']), '/?pane=notices');

        // An off-site target is refused, not followed.
        foreach (['//evil.example', 'https://evil.example', '/\\evil.example', ''] as $hostile) {
            $this->assertRedirect($this->post('/notifications/read-all', ['return' => $hostile]), '/notifications');
        }
    }

    /**
     * Anonymity is a property of the OP that survives its moderation state. The
     * peek joined the OP with `is_deleted = 0 AND is_pending = 0`, so a
     * soft-deleted OP lost the row carrying is_anonymous = 1, COALESCE defaulted
     * it to 0, and the row printed the real author of an anonymous topic.
     */
    public function test_a_peek_row_masks_an_anonymous_author_even_when_the_op_is_soft_deleted(): void
    {
        $board = $this->makeBoard($this->makeCategory('Council'), ['slug' => 'anon-board', 'allow_anonymous' => 1]);
        $topic = $this->makeThread($board, $this->author, 'Posted behind the veil');
        $opId = (int) $this->db->fetchValue(
            'SELECT id FROM posts WHERE thread_id = ? AND is_op = 1',
            [$topic['thread_id']],
        );
        $this->db->run('UPDATE posts SET is_anonymous = 1 WHERE id = ?', [$opId]);

        $live = $this->get('/', ['peek' => '3'])->body();
        self::assertStringContainsString('Posted behind the veil', $live);
        self::assertStringContainsString('Anonymous', $live);
        self::assertStringNotContainsString('remediation_author', $live);
        self::assertStringNotContainsString('Remediation Author', $live);

        // A moderator soft-deletes the opening post; the topic itself survives,
        // so it is still listed — and must still be masked.
        $this->db->run('UPDATE posts SET is_deleted = 1 WHERE id = ?', [$opId]);

        $afterDelete = $this->get('/', ['peek' => '3'])->body();
        self::assertStringContainsString('Posted behind the veil', $afterDelete);
        self::assertStringContainsString('Anonymous', $afterDelete);
        self::assertStringNotContainsString('remediation_author', $afterDelete);
        self::assertStringNotContainsString('Remediation Author', $afterDelete);
    }

    /**
     * Under strict CSP there are no inline styles, so a class the templates emit
     * and the stylesheet does not name is a shipping rendering failure. Three of
     * the surface's four panes shipped that way.
     */
    public function test_the_stylesheet_names_every_class_the_surface_emits(): void
    {
        $css = file_get_contents(self::CSS);
        self::assertIsString($css);

        $selectors = [
            // The account-adjacent panes, which had no rules at all.
            '.directory-light-pane', '.directory-pane-heading', '.directory-pane-actions',
            '.directory-signin-state', '.directory-tag-list', '.directory-notice-list',
            '.directory-notice-mark', '.directory-notice-text', '.directory-notice-topic',
            '.directory-connection-tabs', '.directory-people-list',
            // The Boards pane, which did.
            '.forum-directory__tabs', '.directory-tab-dot', '.forum-directory__stats',
            '.directory-viewbar', '.directory-viewbar-mobile', '.directory-order-note',
            '.directory-guest-note', '.forum-directory__board-row',
            '.forum-directory__board-signal', '.forum-directory__peek',
        ];
        foreach ($selectors as $selector) {
            self::assertStringContainsString($selector, $css, $selector . ' has no rule in app.css');
        }
    }

    /**
     * The pre-rewrite block was never deleted. Its `.board-index`-scoped
     * selectors outranked the replacements, and `display: grid` on the board
     * <article> laid the peek list out BESIDE the row it belongs under.
     */
    public function test_the_superseded_directory_rules_are_gone(): void
    {
        $css = file_get_contents(self::CSS);
        self::assertIsString($css);

        foreach ([
            '.board-index .forum-directory__board',
            '.board-index .forum-directory__boards',
            '.board-index .forum-directory__categories',
            '.board-index .forum-directory__category-heading',
            '.board-index .forum-directory__copy',
            '.board-index .forum-directory__counts',
            ':not(.eyebrow)',
        ] as $stale) {
            self::assertStringNotContainsString($stale, $css, $stale . ' still outranks the current directory rules');
        }
    }

    /**
     * Compact is the triage register, so the description goes
     * (BoardIndex.dc.html:607). The peek is the reader's own explicit Viewing
     * choice: hiding it made the Peek control a silent no-op for every member
     * on compact density.
     */
    public function test_compact_density_drops_the_description_and_keeps_the_chosen_peek(): void
    {
        $css = file_get_contents(self::CSS);
        self::assertIsString($css);

        self::assertStringContainsString(
            '[data-density="compact"] .forum-directory__board-description { display: none; }',
            $css,
        );
        self::assertStringContainsString(
            '[data-density="compact"] .forum-directory__peek { padding-bottom: 8px; }',
            $css,
        );
        self::assertStringNotContainsString(
            '[data-density="compact"] .forum-directory__peek { display: none; }',
            $css,
        );
    }

    /**
     * Without JavaScript a <details> can only be closed by its own <summary>.
     * The phone Viewing sheet's scrim is a child of that <details> and painted
     * over the summary, so the sheet had no way back.
     */
    public function test_the_phone_viewing_sheet_keeps_its_only_close_control_above_the_scrim(): void
    {
        $css = file_get_contents(self::CSS);
        self::assertIsString($css);

        self::assertStringContainsString('.directory-viewbar-mobile[open] { z-index: 70; }', $css);
        self::assertMatchesRegularExpression(
            '~\.directory-viewbar-mobile\[open\] > summary \{[^}]*z-index: 72;~',
            $css,
        );
    }

    /**
     * aria-pressed is a toggle-button attribute and is not valid on a link, so
     * a guest — whose Viewing controls are links, because there is no
     * preference to write — was never told which order or peek was active. The
     * selected link states itself with aria-current instead; a member's control
     * is a real <button> and keeps aria-pressed.
     */
    public function test_each_viewing_control_states_its_selection_with_an_attribute_valid_on_its_element(): void
    {
        $this->makeBoard($this->makeCategory('Council'), ['slug' => 'aria-board']);

        $guest = $this->get('/', ['sort' => 'newest', 'peek' => '5'])->body();
        self::assertStringNotContainsString('aria-pressed', $guest);
        self::assertMatchesRegularExpression('~<a[^>]*data-directory-sort-option="newest"[^>]*aria-current="true"~', $guest);
        self::assertMatchesRegularExpression('~<a[^>]*data-directory-peek-option="5"[^>]*aria-current="true"~', $guest);
        // Only the selected one claims it.
        self::assertDoesNotMatchRegularExpression('~data-directory-sort-option="active"[^>]*aria-current~', $guest);

        $this->actingAs($this->makeUser(['username' => 'aria_member']));
        $member = $this->get('/', ['sort' => 'newest', 'peek' => '5'])->body();
        self::assertMatchesRegularExpression('~<button[^>]*data-directory-sort-option="newest"[^>]*aria-pressed="true"~', $member);
        self::assertMatchesRegularExpression('~<button[^>]*data-directory-sort-option="active"[^>]*aria-pressed="false"~', $member);
    }

    /** The order note counts the boards it is describing, so it has to agree with itself at one. */
    public function test_the_order_note_pluralises_its_own_board_count(): void
    {
        $category = $this->makeCategory('Council');
        $this->makeBoard($category, ['slug' => 'only-board']);

        self::assertStringContainsString('· 1 board · the same order every member sees.', $this->get('/')->body());

        $this->makeBoard($category, ['slug' => 'second-board']);
        self::assertStringContainsString('· 2 boards · the same order every member sees.', $this->get('/')->body());
    }

    /**
     * The rail rewrite dropped the mobile-only Search link, and the topbar entry
     * was already hidden below 861px — which left /search with no route into it
     * from the shell on any phone.
     */
    public function test_search_keeps_a_route_into_it_below_the_phone_breakpoint(): void
    {
        $css = file_get_contents(self::CSS);
        self::assertIsString($css);

        self::assertStringNotContainsString(
            ".topbar-search-entry,\n    .topbar-panel-form { display: none; }",
            $css,
        );

        $this->actingAs($this->makeUser(['username' => 'phone_reader']));
        $home = $this->get('/')->body();
        self::assertStringContainsString('href="/search"', $home);
        self::assertStringContainsString('topbar-search-entry', $home);
    }
}
