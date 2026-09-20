<?php

declare(strict_types=1);
namespace Tests\Unit\Support;

use App\Core\View;
use App\Domain\User;
use App\Support\NotificationPresenter;
use PHPUnit\Framework\TestCase;

final class NotificationPresenterTest extends TestCase
{
    private function item(array $extra = []): array
    {
        self::assertTrue(class_exists(NotificationPresenter::class), 'Shared presenter must exist');
        return NotificationPresenter::item($extra + ['id' => 7, 'type' => 'reply', 'actor_display_name' => 'A Reader', 'thread_title' => 'A topic', 'is_read' => 0, 'created_at' => '2026-01-01 12:30:00'], new User(['id' => 2, 'username' => 'viewer']));
    }

    public function test_every_stored_event_has_copy_and_an_icon(): void
    {
        foreach (['reply' => 'replied to', 'new_thread' => 'started a thread', 'new_post' => 'posted in', 'mention' => 'mentioned you in', 'reaction' => 'reacted to your post', 'follow' => 'followed you', 'badge' => 'You earned a badge', 'solved' => 'Your answer was accepted', 'dm' => 'sent you a message', 'mod' => 'A moderator action affects you', 'announcement' => 'Announcement'] as $type => $copy) {
            $item = $this->item(['type' => $type, 'thread_id' => 1]);
            self::assertStringContainsString($copy, $item['message']);
            self::assertNotEmpty($item['icon']);
            self::assertSame(['id','type','icon','message','context','is_read','created_at','relative_time','full_time','action_label'], array_keys($item));
        }
    }

    public function test_anonymous_missing_actors_and_targetless_appeals(): void
    {
        self::assertSame('Anonymous replied to', $this->item(['actor_display_name' => 'Anonymous', 'actor_username' => null])['message']);
        self::assertSame('Someone replied to', $this->item(['actor_display_name' => null, 'actor_username' => null])['message']);
        $appeal = $this->item(['type' => 'mod', 'thread_id' => null, 'post_id' => null, 'conversation_id' => null, 'thread_title' => null]);
        self::assertSame('Your appeal has been resolved', $appeal['message']);
        self::assertSame('Acknowledge notification', $appeal['action_label']);
    }

    public function test_long_labels_survive_and_template_escapes_at_boundary(): void
    {
        $label = str_repeat('Long', 64) . '<script>alert(1)</script>';
        $item = $this->item(['actor_display_name' => $label, 'thread_title' => $label]);
        self::assertSame($label, $item['context']);
        $view = new View(dirname(__DIR__, 3) . '/templates');
        $view->share(['csrf_token' => 'token']);
        $html = $view->partial('partials/notification_row', ['item' => $item, 'return_path' => '/notifications']);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
        self::assertStringContainsString('datetime="2026-01-01T12:30:00+00:00"', $html);
        self::assertStringContainsString('Unread.', $html);
    }
}
