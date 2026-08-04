<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\View;
use PHPUnit\Framework\TestCase;

final class SharedConsolePartialsTest extends TestCase
{
    private const TEMPLATES = __DIR__ . '/../../../templates';

    public function test_pager_preserves_and_escapes_query_values_with_real_boundary_controls(): void
    {
        self::assertFileExists(self::TEMPLATES . '/partials/pager.php');
        $view = new View(self::TEMPLATES);
        $middle = $view->partial('partials/pager', [
            'path' => '/admin/audit',
            'query' => ['action' => 'user updated', 'actor' => 'A&B<x>'],
            'page' => 2,
            'total_pages' => 3,
            'aria_label' => 'Audit "pages"',
        ]);

        self::assertStringContainsString('<nav class="pager" aria-label="Audit &quot;pages&quot;">', $middle);
        self::assertStringContainsString(
            'href="/admin/audit?action=user+updated&amp;actor=A%26B%3Cx%3E&amp;page=1"',
            $middle,
        );
        self::assertStringContainsString('<span class="pager-label">Page 2 of 3</span>', $middle);
        self::assertStringContainsString(
            'href="/admin/audit?action=user+updated&amp;actor=A%26B%3Cx%3E&amp;page=3"',
            $middle,
        );

        $first = $view->partial('partials/pager', [
            'path' => '/admin/users',
            'query' => [],
            'page' => 1,
            'total_pages' => 2,
        ]);
        self::assertStringContainsString('<nav class="pager" aria-label="Pagination">', $first);
        self::assertStringContainsString(
            '<span class="pager-control is-disabled" aria-disabled="true">Previous</span>',
            $first,
        );
        self::assertStringNotContainsString('href="/admin/users?page=0"', $first);

        $last = $view->partial('partials/pager', [
            'path' => '/admin/users',
            'query' => [],
            'page' => 2,
            'total_pages' => 2,
        ]);
        self::assertStringContainsString(
            '<span class="pager-control is-disabled" aria-disabled="true">Next</span>',
            $last,
        );
        self::assertStringNotContainsString('href="/admin/users?page=3"', $last);
    }

    public function test_back_link_escapes_values_and_reuses_the_decorative_chevron_icon(): void
    {
        self::assertFileExists(self::TEMPLATES . '/partials/back_link.php');
        $view = new View(self::TEMPLATES);
        $default = $view->partial('partials/back_link', [
            'href' => '/admin/users?role=admin&sort=name',
            'label' => 'All <members>',
        ]);

        self::assertStringContainsString(
            '<a class="admin-back" href="/admin/users?role=admin&amp;sort=name">',
            $default,
        );
        self::assertStringContainsString('icon-chevron-left', $default);
        self::assertStringContainsString('<span>All &lt;members&gt;</span>', $default);

        $custom = $view->partial('partials/back_link', [
            'href' => '/admin',
            'label' => 'Return',
            'class' => 'record-back" data-bad="1',
        ]);
        self::assertStringContainsString('class="record-back&quot; data-bad=&quot;1"', $custom);
        self::assertStringNotContainsString(' data-bad="1"', $custom);
    }

    public function test_empty_state_escapes_copy_and_only_renders_a_complete_action(): void
    {
        self::assertFileExists(self::TEMPLATES . '/partials/empty_state.php');
        $view = new View(self::TEMPLATES);
        $withAction = $view->partial('partials/empty_state', [
            'heading' => 'Nothing <here>',
            'message' => 'Try "another" filter & return.',
            'action_href' => '/admin/audit?reset=1&actor=all',
            'action_label' => 'Reset <filters>',
        ]);

        self::assertStringContainsString('<section class="state-empty">', $withAction);
        self::assertStringContainsString('<h3>Nothing &lt;here&gt;</h3>', $withAction);
        self::assertStringContainsString('<p>Try &quot;another&quot; filter &amp; return.</p>', $withAction);
        self::assertStringContainsString(
            '<a href="/admin/audit?reset=1&amp;actor=all">Reset &lt;filters&gt;</a>',
            $withAction,
        );

        $incompleteAction = $view->partial('partials/empty_state', [
            'heading' => 'No rows',
            'message' => 'There is nothing to show.',
            'action_href' => '/admin/audit',
        ]);
        self::assertStringNotContainsString('<a ', $incompleteAction);
    }
}
