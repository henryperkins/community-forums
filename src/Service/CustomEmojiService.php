<?php

declare(strict_types=1);

namespace App\Service;

use App\Core\Database;
use App\Core\NotFoundException;
use App\Core\ValidationException;
use App\Domain\User;
use App\Security\WriteGate;

final class CustomEmojiService
{
    public function __construct(
        private Database $db,
        private WriteGate $writeGate,
    ) {
    }

    /** @param array<string,mixed> $input */
    public function create(User $admin, array $input): int
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

        return $this->db->insert(
            "INSERT INTO custom_emoji (shortcode, name, image_path, mime, is_enabled, allow_reactions, created_by, created_at)
             VALUES (?, ?, ?, ?, 1, ?, ?, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE name = VALUES(name), image_path = VALUES(image_path), mime = VALUES(mime),
                is_enabled = 1, allow_reactions = VALUES(allow_reactions), updated_at = UTC_TIMESTAMP()",
            [$shortcode, $name, $imagePath, $mime, $allowReactions ? 1 : 0, $admin->id()],
        );
    }

    public function setEnabled(User $admin, string $shortcode, bool $enabled): void
    {
        $this->writeGate->assertCanWrite($admin);
        if (!$admin->isAdmin()) {
            throw new NotFoundException('Not found.');
        }
        $this->db->run(
            'UPDATE custom_emoji SET is_enabled = ?, updated_at = UTC_TIMESTAMP() WHERE shortcode = ?',
            [$enabled ? 1 : 0, trim($shortcode, ':')],
        );
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
