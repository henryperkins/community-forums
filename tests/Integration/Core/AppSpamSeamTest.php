<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Repository\SettingRepository;
use App\Service\AntiAbuseService;
use App\Service\Spam\NullSpamScorer;
use App\Service\Spam\SpamScorer;
use App\Service\Spam\SpamVerdict;
use Tests\Support\TestCase;

/**
 * P3-05 spam-scoring provider seam: AntiAbuseService consults a pluggable
 * SpamScorer as one reviewable rule — its [0,1] score maps to a severity that is
 * clamped to the operator mode like every other rule, audited, and fail-safe.
 * Gate A ships the seam + a no-op default; real scorers are Gate B.
 */
final class AppSpamSeamTest extends TestCase
{
    private function service(SpamScorer $scorer): AntiAbuseService
    {
        return new AntiAbuseService(
            $this->db,
            $this->config,
            new SettingRepository($this->db),
            new ModerationLogRepository($this->db),
            $scorer,
        );
    }

    private function member(): User
    {
        return $this->userEntity($this->makeUser(['username' => 'm' . bin2hex(random_bytes(3))]));
    }

    private function setMode(string $mode): void
    {
        (new SettingRepository($this->db))->set('antiabuse_mode', $mode);
    }

    /** A scorer that flags any text containing a marker, else abstains. */
    private function markerScorer(float $score): SpamScorer
    {
        return new class ($score) implements SpamScorer {
            public function __construct(private float $score)
            {
            }

            public function score(User $user, string $context, string $text): ?SpamVerdict
            {
                return str_contains($text, 'BUYNOW') ? new SpamVerdict($this->score, 'stub') : null;
            }
        };
    }

    public function test_default_scorer_abstains_and_clean_content_is_allowed(): void
    {
        $d = $this->service(new NullSpamScorer())->evaluate($this->member(), 'thread', 'hello world', 'Hi');
        self::assertSame('allow', $d->natural);
        self::assertFalse($d->triggered());
    }

    public function test_high_score_holds_in_hold_mode_and_is_recorded(): void
    {
        $this->setMode('hold');
        $d = $this->service($this->markerScorer(0.95))->evaluate($this->member(), 'thread', 'cheap pills BUYNOW', 'Deal');
        self::assertSame('hold', $d->natural);  // 0.95 >= hold threshold (0.9)
        self::assertSame('hold', $d->action);   // mode = hold lets it act
        self::assertSame('spam_score', $d->rule);
        self::assertStringContainsString('spam score 0.95', $d->reasonText());
        self::assertStringContainsString('stub', $d->reasonText());
    }

    public function test_score_is_clamped_to_observe_mode(): void
    {
        $this->setMode('observe');
        // Scorer is unconditional here (no marker needed).
        $scorer = new class implements SpamScorer {
            public function score(User $user, string $context, string $text): ?SpamVerdict
            {
                return new SpamVerdict(0.99, 'stub');
            }
        };
        $d = $this->service($scorer)->evaluate($this->member(), 'thread', 'whatever', null);
        self::assertSame('hold', $d->natural);
        self::assertSame('allow', $d->action); // observe → audited, never enforced
        self::assertTrue($d->triggered());
    }

    public function test_mid_score_flags_not_holds(): void
    {
        $this->setMode('block');
        $d = $this->service($this->markerScorer(0.7))->evaluate($this->member(), 'thread', 'BUYNOW', null);
        self::assertSame('flag', $d->natural); // >= flag (0.6), < hold (0.9)
        self::assertSame('flag', $d->action);
    }

    public function test_low_score_does_not_trigger(): void
    {
        $this->setMode('block');
        $d = $this->service($this->markerScorer(0.3))->evaluate($this->member(), 'thread', 'BUYNOW', null);
        self::assertSame('allow', $d->natural); // below the flag threshold
        self::assertFalse($d->triggered());
    }

    public function test_a_throwing_provider_never_breaks_posting(): void
    {
        $scorer = new class implements SpamScorer {
            public function score(User $user, string $context, string $text): ?SpamVerdict
            {
                throw new \RuntimeException('provider down');
            }
        };
        $d = $this->service($scorer)->evaluate($this->member(), 'thread', 'hello', 'Hi');
        self::assertSame('allow', $d->natural); // failed safe → abstain
    }
}
