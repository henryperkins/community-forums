<?php

declare(strict_types=1);

namespace Tests\Integration\Core;

use App\Repository\TagRepository;
use Tests\Support\TestCase;

final class AppTagAdminTest extends TestCase
{
    public function test_invalid_tag_create_rerenders_422_with_typed_values(): void
    {
        $this->actingAs($this->makeAdmin(['username' => 'tag_create_admin']));

        $res = $this->post('/admin/tags', [
            'name' => '',
            'slug' => 'kept-slug',
            'description' => 'Description that should survive.',
        ]);

        $this->assertStatus(422, $res);
        self::assertStringContainsString('kept-slug', $res->body());
        self::assertStringContainsString('Description that should survive.', $res->body());
        self::assertSame(0, (int) $this->db->fetchValue('SELECT COUNT(*) FROM tags WHERE slug = ?', ['kept-slug']));
    }

    public function test_duplicate_tag_create_rerenders_422_with_typed_values(): void
    {
        $admin = $this->makeAdmin(['username' => 'tag_dup_admin']);
        $repo = new TagRepository($this->db);
        $repo->create('existing-tag', 'Existing tag', null, (int) $admin['id']);
        $this->actingAs($admin);

        $res = $this->post('/admin/tags', [
            'name' => 'Duplicate typed name',
            'slug' => 'existing-tag',
            'description' => 'Duplicate description survives.',
        ]);

        $this->assertStatus(422, $res);
        self::assertStringContainsString('Duplicate typed name', $res->body());
        self::assertStringContainsString('existing-tag', $res->body());
        self::assertStringContainsString('Duplicate description survives.', $res->body());
    }

    public function test_invalid_tag_update_rerenders_422_with_typed_row_values(): void
    {
        $admin = $this->makeAdmin(['username' => 'tag_update_admin']);
        $repo = new TagRepository($this->db);
        $tagId = $repo->create('stored-tag', 'Stored tag', 'Stored description', (int) $admin['id']);
        $this->actingAs($admin);

        $res = $this->post('/admin/tags/' . $tagId, [
            'name' => '',
            'slug' => 'typed-row-slug',
            'description' => 'Typed row description survives.',
            'visibility' => 'hidden',
            'enabled' => '1',
        ]);

        $this->assertStatus(422, $res);
        self::assertStringContainsString('typed-row-slug', $res->body());
        self::assertStringContainsString('Typed row description survives.', $res->body());
        self::assertSame('stored-tag', (string) $this->db->fetchValue('SELECT slug FROM tags WHERE id = ?', [$tagId]));
    }
}
