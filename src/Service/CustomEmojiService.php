<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Repository\ModerationLogRepository;
use App\Security\WriteGate;

final class CustomEmojiService
{
    // Markdown expands these tokens before custom emoji rendering runs.
    private const RESERVED_SHORTCODES = ['smile', 'heart', 'thumbsup', 'thumbs_up', 'tada'];

    public function __construct(
        private Database $db,
        private WriteGate $writeGate,
        private ModerationLogRepository $log,
    ) {
    }

    /**
     * @param array<string,mixed> $input
     * @return array{replaced:bool,shortcode:string} replaced=true when the
     *         shortcode already existed and this write overwrote it
     */
    public function create(User $admin, array $input): array
    {
        $this->writeGate->assertCanWrite($admin);
        if (!$admin->isAdmin()) {
            throw new NotFoundException('Not found.');
        }

        $shortcode = strtolower(trim((string) ($input['shortcode'] ?? '')));
        $shortcode = trim($shortcode, ':');
        $name = trim((string) ($input['name'] ?? $shortcode));
        $imagePath = trim((string) ($input['image_path'] ?? ''));
        $mime = (string) ($input['mime'] ?? '');
        $allowReactions = !empty($input['allow_reactions']);

        $errors = [];
        if (preg_match('/^[a-z0-9_+-]{2,40}$/', $shortcode) !== 1) {
            $errors['shortcode'] = 'Use 2-40 lowercase letters, numbers, underscores, plus, or hyphen.';
        } elseif (in_array($shortcode, self::RESERVED_SHORTCODES, true)) {
            $errors['shortcode'] = 'This shortcode is reserved for a built-in emoji. Choose a different shortcode.';
        }
        if ($name === '' || mb_strlen($name) > 80) {
            $errors['name'] = 'Name is required and must be 80 characters or fewer.';
        }
        if (!in_array($mime, ['image/png', 'image/webp'], true)) {
            $errors['mime'] = 'Custom emoji must be PNG or WebP.';
        }
        if (!$this->validStaticPath($imagePath)) {
            $errors['image_path'] = 'Use a static /emoji/*.png, /emoji/*.webp, or finalized /media/{id} asset.';
        }
        if ($errors !== []) {
            throw new ValidationException($errors, $input);
        }

        return $this->db->transaction(function () use ($admin, $shortcode, $name, $imagePath, $mime, $allowReactions): array {
            $before = $this->lockedEmoji($shortcode);
            // Replacing an existing shortcode remains the edit flow. Lock the
            // previous state so the audit describes the write that replaces it.
            $this->db->insert(
                "INSERT INTO custom_emoji (shortcode, name, image_path, mime, is_enabled, allow_reactions, created_by, created_at)
                 VALUES (?, ?, ?, ?, 1, ?, ?, UTC_TIMESTAMP())
                 ON DUPLICATE KEY UPDATE name = VALUES(name), image_path = VALUES(image_path), mime = VALUES(mime),
                    is_enabled = 1, allow_reactions = VALUES(allow_reactions), updated_at = UTC_TIMESTAMP()",
                [$shortcode, $name, $imagePath, $mime, $allowReactions ? 1 : 0, $admin->id()],
            );
            $after = $this->lockedEmoji($shortcode);
            $this->audit($admin, $before === null ? 'custom_emoji.create' : 'custom_emoji.replace', $before, $after);

            return ['replaced' => $before !== null, 'shortcode' => $shortcode];
        });
    }

    public function setEnabled(User $admin, string $shortcode, bool $enabled): void
    {
        $this->writeGate->assertCanWrite($admin);
        if (!$admin->isAdmin()) {
            throw new NotFoundException('Not found.');
        }
        $shortcode = trim(strtolower(trim($shortcode)), ':');
        $this->db->transaction(function () use ($admin, $shortcode, $enabled): void {
            $before = $this->lockedEmoji($shortcode);
            if ($before === null) {
                throw new NotFoundException('Custom emoji not found.');
            }
            // Legacy collisions can always be disabled. Populate the replacement
            // form on a failed enable so the operator can reuse its asset safely.
            if ($enabled && in_array($shortcode, self::RESERVED_SHORTCODES, true)) {
                throw new ValidationException([
                    'shortcode' => 'This shortcode is reserved for a built-in emoji. Choose a different shortcode.',
                ], $this->snapshot($before));
            }
            $this->db->run(
                'UPDATE custom_emoji SET is_enabled = ?, updated_at = UTC_TIMESTAMP() WHERE shortcode = ?',
                [$enabled ? 1 : 0, $shortcode],
            );
            $this->audit($admin, $enabled ? 'custom_emoji.enable' : 'custom_emoji.disable', $before, $this->lockedEmoji($shortcode));
        });
    }

    /** @return array<string,mixed>|null */
    private function lockedEmoji(string $shortcode): ?array
    {
        return $this->db->fetch(
            'SELECT id, shortcode, name, image_path, mime, is_enabled, allow_reactions
             FROM custom_emoji WHERE shortcode = ? FOR UPDATE',
            [$shortcode],
        );
    }

    /** @param array<string,mixed>|null $before @param array<string,mixed> $after */
    private function audit(User $admin, string $action, ?array $before, array $after): void
    {
        $this->log->log([
            'actor_id' => $admin->id(),
            'action' => $action,
            // Configuration entities use the existing setting audit target;
            // action and shortcode snapshots identify the catalogue entry.
            'target_type' => 'setting',
            'target_id' => (int) $after['id'],
            'before' => $before === null ? null : $this->snapshot($before),
            'after' => $this->snapshot($after),
        ]);
    }

    /** @param array<string,mixed> $emoji @return array<string,mixed> */
    private function snapshot(array $emoji): array
    {
        return [
            'shortcode' => (string) $emoji['shortcode'],
            'name' => (string) $emoji['name'],
            'image_path' => (string) $emoji['image_path'],
            'mime' => (string) $emoji['mime'],
            'is_enabled' => (int) $emoji['is_enabled'],
            'allow_reactions' => (int) $emoji['allow_reactions'],
        ];
    }

    /**
     * @param array<string,mixed> $overlay
     * @return array<string,mixed>
     */
    public function pageModel(array $overlay = []): array
    {
        return array_replace([
            'custom_emoji' => $this->catalogue(),
            'emoji_errors' => [],
            'emoji_old' => [],
        ], $overlay);
    }

    /** @return list<array{shortcode:string,name:string,image_path:string,mime:string,is_enabled:int,allow_reactions:int,created_at:string,updated_at:?string}> */
    public function catalogue(): array
    {
        return $this->db->fetchAll(
            'SELECT shortcode, name, image_path, mime, is_enabled, allow_reactions, created_at, updated_at
             FROM custom_emoji
             ORDER BY shortcode ASC',
        );
    }

    public function isReactionAllowed(string $shortcode): bool
    {
        $shortcode = trim(strtolower($shortcode), ':');
        return $this->db->fetchValue(
            'SELECT 1 FROM custom_emoji WHERE shortcode = ? AND is_enabled = 1 AND allow_reactions = 1 LIMIT 1',
            [$shortcode],
        ) !== false;
    }

    /** @return list<string> */
    public function reactionShortcodes(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT shortcode FROM custom_emoji WHERE is_enabled = 1 AND allow_reactions = 1 ORDER BY shortcode ASC LIMIT 50',
        );
        return array_map(static fn (array $row): string => ':' . (string) $row['shortcode'] . ':', $rows);
    }

    public function renderInto(\DOMDocument $doc): void
    {
        $map = $this->enabledMap();
        if ($map === []) {
            return;
        }

        $walk = function (\DOMNode $node) use (&$walk, $map, $doc): void {
            if ($node instanceof \DOMText) {
                if (!$this->emojiAllowedIn($node) || !str_contains($node->nodeValue, ':')) {
                    return;
                }
                $this->replaceTextNode($doc, $node, $map);
                return;
            }
            foreach (iterator_to_array($node->childNodes) as $child) {
                $walk($child);
            }
        };
        $walk($doc);
    }

    /** @return array<string,array{path:string,name:string}> shortcode token => data */
    private function enabledMap(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT shortcode, name, image_path FROM custom_emoji WHERE is_enabled = 1 ORDER BY shortcode ASC LIMIT 500',
        );
        $map = [];
        foreach ($rows as $row) {
            $map[':' . (string) $row['shortcode'] . ':'] = [
                'path' => (string) $row['image_path'],
                'name' => (string) $row['name'],
            ];
        }
        return $map;
    }

    /** @param array<string,array{path:string,name:string}> $map */
    private function replaceTextNode(\DOMDocument $doc, \DOMText $node, array $map): void
    {
        $text = $node->nodeValue;
        if (preg_match_all('/:[a-z0-9_+-]{2,40}:/', $text, $matches, PREG_OFFSET_CAPTURE) === false || $matches[0] === []) {
            return;
        }
        $parent = $node->parentNode;
        if ($parent === null) {
            return;
        }
        $cursor = 0;
        $fragment = $doc->createDocumentFragment();
        foreach ($matches[0] as [$token, $offset]) {
            if (!isset($map[$token])) {
                continue;
            }
            $before = substr($text, $cursor, $offset - $cursor);
            if ($before !== '') {
                $fragment->appendChild($doc->createTextNode($before));
            }
            $img = $doc->createElement('img');
            $img->setAttribute('src', $map[$token]['path']);
            $img->setAttribute('alt', $token);
            $img->setAttribute('title', $map[$token]['name']);
            $img->setAttribute('class', 'custom-emoji');
            $fragment->appendChild($img);
            $cursor = $offset + strlen($token);
        }
        $after = substr($text, $cursor);
        if ($after !== '') {
            $fragment->appendChild($doc->createTextNode($after));
        }
        $parent->replaceChild($fragment, $node);
    }

    private function emojiAllowedIn(\DOMText $node): bool
    {
        $parent = $node->parentNode;
        while ($parent !== null) {
            $name = strtolower($parent->nodeName);
            if ($name === 'code' || $name === 'pre') {
                return false;
            }
            $parent = $parent->parentNode;
        }
        return true;
    }

    private function validStaticPath(string $path): bool
    {
        return preg_match('~^/emoji/[A-Za-z0-9_.-]+\.(png|webp)$~', $path) === 1
            || preg_match('~^/media/\d+$~', $path) === 1;
    }
}
