<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\NotificationRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserPreferenceRepository;
use DOMDocument;
use DOMXPath;
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
    private const TOKENS = __DIR__ . '/../../../resources/forum/tokens.css';

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
            '.thread-row-inbox .thread-row-chips .chip-reason', '.inbox-empty-state',
            // The reading pane's new anatomy.
            '.inbox-preview-attribution', '.inbox-preview-author', '.inbox-preview-tier',
            '.inbox-preview-lede', '.inbox-preview-count', '.inbox-preview-open',
            // The row and the bar.
            '.thread-row-inbox .thread-meta-commends', '.inbox-view-bar', '.inbox-menu', '.inbox-keyboard-help',
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
        self::assertStringContainsString('.inbox-reading .thread-view, .inbox-reading .board-view { max-width: 760px; }', $css);
    }

    /**
     * The inbox used to lift the shell's --maxw cap for itself alone, so above
     * 1280px every trip to or from it slid the board rail out from under the
     * top bar's lockup and resized the panes. One frame serves every member
     * surface: the base `.app-shell` rule is the only one that may size it.
     */
    public function test_no_route_lifts_the_shared_shell_width(): void
    {
        $css = file_get_contents(self::CSS);
        self::assertIsString($css);
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        preg_match_all('/([^{}]*\.app-shell\b[^{}]*)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
        $sizing = [];
        foreach ($rules as [, $selector, $declarations]) {
            if (preg_match('/(?:^|[;\s])max-width\s*:/', $declarations) !== 1) {
                continue;
            }
            // Only rules whose subject is the shell: `.app-shell > .board-rail`
            // sizes the drawer, not the frame. Drop (possibly nested) arguments
            // so the commas left are the selector list's own.
            $flat = $selector;
            do {
                $flat = (string) preg_replace('/\([^()]*\)/', '', $flat, -1, $replaced);
            } while ($replaced > 0);
            foreach (explode(',', $flat) as $complex) {
                if (preg_match('/\.app-shell(?::[\w-]+)*$/', trim($complex)) === 1) {
                    $sizing[] = (string) preg_replace('/\s+/', ' ', trim($selector) . ' { ' . trim($declarations) . ' }');
                    break;
                }
            }
        }

        self::assertCount(1, $sizing, "Only the base shell rule may size .app-shell:\n" . implode("\n", $sizing));
        self::assertStringStartsWith('.app-shell { max-width: var(--maxw);', $sizing[0]);
    }

    /**
     * While the queue took up to 660px of the shared frame, the reading pane was
     * about 340px wide with the board rail open. The composer's narrow layout was
     * keyed to the viewport alone, so on a desktop the pane's formatting row
     * clipped Spoiler and Link off its edge without offering "More formatting"
     * (COMPOSER.md, Responsive overflow), and its action bar clipped the identity
     * and the Anonymous label away. The pane applies the phone's layout on its
     * own width, which still holds wherever the pane is narrower than 30rem; the
     * --list-w queue now leaves it about 600px, where the full row fits.
     */
    public function test_the_reading_pane_lays_out_the_composer_on_its_own_width(): void
    {
        $css = file_get_contents(self::CSS);
        self::assertIsString($css);

        self::assertStringContainsString('.inbox-reading { container: inbox-reading / inline-size; }', $css);
        self::assertSame(1, preg_match('/@container inbox-reading \(max-width: 30rem\) \{(.*?)\n\}/s', $css, $query));
        self::assertMatchesRegularExpression('/\.composer-toolbar-action:not\(\.is-essential\),\s*\.composer-toolbar-sep \{ display: none; \}/', $query[1]);
        self::assertStringContainsString('.composer-more-wrap { display: inline-flex;', $query[1]);
        self::assertStringContainsString('.composer-meta-row { grid-template-columns: minmax(0, 1fr) auto; }', $query[1]);
        // The phone rule clips the row; a desktop pane keeps the hover tips above it.
        self::assertStringNotContainsString('overflow', $query[1]);

        // The reply box's action bar wraps rather than clipping, on phones and in the pane alike.
        self::assertSame(1, preg_match('/@media \(max-width: 640px\) \{\s*\.composer-toolbar \{ overflow: hidden; \}(.*?)\n\}/s', $css, $phone));
        foreach ([$phone[1], $query[1]] as $narrow) {
            self::assertStringContainsString('.reply-composer .composer-actions-bar { flex-wrap: wrap;', $narrow);
            self::assertStringContainsString('.reply-composer .composer-actions-start { display: contents; }', $narrow);
            self::assertStringContainsString('.reply-composer .composer-anonymous-chip { flex: none; }', $narrow);
        }
    }

    /**
     * The 2026-11-07 critique of /inbox. An open reading pane was clipped and
     * pinned to 100% height by rules left from when it embedded a whole topic
     * with its own scroller: the wheel scrolled the shell off the page instead,
     * and every later reply and the reply box were cut away. The queue took up
     * to 660px of the shared frame and left the pane about 340px, and the pane's
     * reply box never took the topic dock's one-line rest, so its Minimize did
     * nothing. And a positional rule printed every count, and every unselected
     * Sort label in the compact menu, in faint 10.56px mono.
     */
    public function test_the_reading_pane_scrolls_itself_beside_a_list_width_queue(): void
    {
        $css = file_get_contents(self::CSS);
        self::assertIsString($css);
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);

        self::assertSame(0, preg_match('/\.inbox-reading\.is-open[^{]*\{[^}]*overflow\s*:\s*hidden/', $css), 'An open reading pane must keep its own scroller.');
        // The pane itself takes the shell's height, which bounds its scroller;
        // nothing inside it may be pinned to that height.
        self::assertStringContainsString('.inbox-reading.is-open { height: 100%; }', $css);
        self::assertSame(0, preg_match('/\.inbox-reading\.is-open\s+[^\s{][^{]*\{[^}]*height\s*:\s*100%/', $css), 'Nothing inside an open reading pane may be pinned to the pane height.');
        self::assertStringContainsString('body.is-reading-open .inbox-shell { grid-template-columns: minmax(360px, var(--list-w)) minmax(0, 1fr); }', $css);
        self::assertStringContainsString(':is(.thread-dock, .inbox-preview-reply) .reply-composer:not(.is-expanded)', $css);

        // The positional rule lives in the generated bridge, which must stay a
        // copy of its Imladris source, so the class-based rules answer it at
        // equal specificity from after the bridge: counts by class, labels in
        // the menu's own type.
        $raw = (string) file_get_contents(self::CSS);
        $end = strpos($raw, '/* ══ End member surfaces production transfer ══ */');
        self::assertIsInt($end, 'The generated bridge keeps its end marker.');
        $after = (string) preg_replace('#/\*.*?\*/#s', '', substr($raw, $end));
        self::assertStringContainsString('.inbox-scope-menu-panel a > span:not(.inbox-choice-state) { color: inherit; font: inherit; }', $after);
        self::assertStringContainsString('.inbox-scope-menu-panel a > span.inbox-choice-state { color: var(--text-muted); font-family: var(--font-meta); font-size: var(--text-chip); }', $after);
        self::assertStringContainsString('.thread-row-inbox .thread-row-chips .chip-reason:not(.is-direct) {', $after);
    }

    /**
     * Every For You row states why it is there, so gold on every reason stopped
     * marking where to look (the One Gold Rule). A direct call — an assignment,
     * a mention, a reply to your topic — keeps the gold cue; a subscription the
     * member set up once states its reason in the quiet tag.
     */
    public function test_only_a_direct_call_wears_the_gold_reason_cue(): void
    {
        $reader = $this->makeUser(['username' => 'reason_reader']);
        $mentioned = $this->makeThread($this->board, $this->author, 'A topic that calls on the reader');
        $watched = $this->makeThread($this->board, $this->author, 'A topic the reader watches');

        $postId = $this->posting()->reply($this->userEntity($this->author), (int) $mentioned['thread_id'], [
            'body' => 'The council would value your view.',
        ]);
        (new NotificationRepository($this->db))->create([
            'user_id' => (int) $reader['id'],
            'type' => 'mention',
            'actor_id' => (int) $this->author['id'],
            'thread_id' => (int) $mentioned['thread_id'],
            'post_id' => $postId,
        ]);
        (new SubscriptionRepository($this->db))->set((int) $reader['id'], 'thread', (int) $watched['thread_id'], true, true, 'instant');

        $this->actingAs($reader);
        $dom = $this->dom($this->get('/inbox', ['scope' => 'for_you', 'order' => 'active'])->body());
        $cue = static function (DOMXPath $dom, int $threadId): \DOMElement {
            $chip = $dom->query('//*[@data-inbox-row][@data-thread-id="' . $threadId . '"]//span[contains(concat(" ", @class, " "), " chip-reason ")]')->item(0);
            self::assertInstanceOf(\DOMElement::class, $chip, 'Every For You row states its reason.');
            return $chip;
        };

        $direct = $cue($dom, (int) $mentioned['thread_id']);
        self::assertSame('Mentioned you', trim($direct->textContent));
        self::assertStringContainsString(' is-direct', $direct->getAttribute('class'));

        $quiet = $cue($dom, (int) $watched['thread_id']);
        self::assertSame('Watched topic', trim($quiet->textContent));
        self::assertStringNotContainsString('is-direct', $quiet->getAttribute('class'));
    }

    /**
     * The compact menu's trigger reads "For You · N topics · Activity", but its
     * panel opened on Sort. It now reads in the trigger's order, with Snoozed
     * still second (ADR 0044). A scope holding nothing stays a destination but
     * recedes, unless it is the scope being shown.
     */
    public function test_the_view_menus_list_scopes_first_and_quiet_the_empty_ones(): void
    {
        $reader = $this->makeUser(['username' => 'quiet_reader']);
        $topic = $this->makeThread($this->board, $this->author, 'The one topic the reader starred');
        $this->actingAs($reader);
        $this->post('/t/' . (int) $topic['thread_id'] . '/star', ['return' => '/inbox']);

        $dom = $this->dom($this->get('/inbox', ['scope' => 'for_you', 'order' => 'active'])->body());
        $panel = $dom->query('//details[contains(concat(" ", @class, " "), " inbox-compact-menu ")]/div[contains(concat(" ", @class, " "), " inbox-menu-panel ")]')->item(0);
        self::assertInstanceOf(\DOMElement::class, $panel);
        $groups = [];
        foreach ($dom->query('./span[contains(concat(" ", @class, " "), " inbox-scope-group-label ")]', $panel) as $label) {
            $groups[] = trim($label->textContent);
        }
        self::assertSame('Your queue', $groups[0]);
        self::assertSame('Sort', $groups[count($groups) - 1]);
        self::assertSame('/inbox?scope=for_you&order=active', $dom->query('./a', $panel)->item(0)?->getAttribute('href'));
        self::assertSame('/inbox?scope=snoozed&order=active', $dom->query('./a', $panel)->item(1)?->getAttribute('href'));

        $classOf = static fn (DOMXPath $dom, string $scope): string => (string) $dom->query('//*[@data-inbox-scope-menu]//a[@href="/inbox?scope=' . $scope . '&order=active"]')->item(0)?->getAttribute('class');
        self::assertSame('is-active', $classOf($dom, 'for_you'));
        self::assertSame('', $classOf($dom, 'starred'), 'A scope holding a topic keeps the full ink.');
        self::assertSame('is-empty', $classOf($dom, 'mentions'));
        self::assertSame(1, $dom->query('//details[contains(concat(" ", @class, " "), " inbox-compact-menu ")]//a[@href="/inbox?scope=mentions&order=active" and @class="is-empty"]')->length);

        // An empty scope being shown is the current choice, not a receding one.
        $shown = $this->dom($this->get('/inbox', ['scope' => 'mentions', 'order' => 'active'])->body());
        self::assertSame('is-active', $classOf($shown, 'mentions'));
    }

    private function dom(string $html): DOMXPath
    {
        $document = new DOMDocument();
        @$document->loadHTML($html);
        return new DOMXPath($document);
    }

    /**
     * The anonymity notice is a sentence. Sharing the meta row's third column, it
     * took every spare pixel from the draft status below about 670px, printed
     * over it and took Discard's clicks, so it takes its own line at every width.
     */
    public function test_the_anonymity_notice_takes_its_own_line(): void
    {
        $css = file_get_contents(self::CSS);
        self::assertIsString($css);

        self::assertStringContainsString('.composer-anonymous-disclosure { grid-column: 1 / -1;', $css);
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

        self::assertStringContainsString('.thread-row-inbox .thread-meta a,', $css);
        self::assertStringContainsString('color: var(--artifact-link)', $css);
        // The Forum tokens keep a darker link in light mode and a lighter one in
        // both dark registers (ADR 0056); the retired Imladris river ramp is gone.
        self::assertStringContainsString('--artifact-link: #1d4ed8', $tokens);
        self::assertSame(2, substr_count($tokens, '--artifact-link: #93c5fd'));
    }

    /** Density still applies to rows and is managed by the appearance surface. */
    public function test_density_remains_a_saved_appearance_preference(): void
    {
        $reader = $this->makeUser(['username' => 'density_reader']);
        $this->actingAs($reader);
        self::assertStringContainsString('data-density="comfortable"', $this->get('/inbox')->body());

        (new UserPreferenceRepository($this->db))->merge((int) $reader['id'], ['density' => 'compact']);
        self::assertStringContainsString('data-density="compact"', $this->get('/inbox')->body());
        $settings = $this->get('/settings/appearance');
        $this->assertStatus(200, $settings);
        self::assertMatchesRegularExpression('/name="density"[^>]*value="compact"[^>]*checked/', $settings->body());
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
        self::assertStringNotContainsString('thread-meta-commends', $active);

        $commended = $this->get('/inbox', ['scope' => 'starred', 'order' => 'commended'])->body();
        self::assertStringContainsString('thread-meta-commends', $commended);
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
