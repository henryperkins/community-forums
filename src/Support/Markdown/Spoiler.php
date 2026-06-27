<?php

declare(strict_types=1);

namespace App\Support\Markdown;

use League\CommonMark\Node\Inline\AbstractInline;

/**
 * Inline spoiler node (P3-02): the parsed form of `||hidden text||`. Rendered as
 * a <span class="spoiler"> that the sanitizer's allowlist explicitly permits.
 */
final class Spoiler extends AbstractInline
{
}
