<?php

declare(strict_types=1);

namespace App\Support;

use App\Repository\UserRepository;

final class MentionLinker
{
    // $enabled is bound from FeatureFlags::enabled('mentions') so the @mention
    // surface (notifications and rendered links) toggles as a unit. link() is
    // always invoked on already-sanitised HTML; the sanitizer strips every <a>
    // attribute except href, so class="mention" must be added after it runs.
    public function __construct(private UserRepository $users, private bool $enabled = true)
    {
    }

    public function link(string $html): string
    {
        if (!$this->enabled || $html === '' || !str_contains($html, '@')) {
            return $html;
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $ok = $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        if (!$ok) {
            return $html;
        }

        $handles = [];
        $textNodes = [];
        $walk = function (\DOMNode $node) use (&$walk, &$handles, &$textNodes): void {
            if ($node instanceof \DOMText) {
                if ($this->insideExcludedNode($node)) {
                    return;
                }
                $text = $node->nodeValue ?? '';
                if (preg_match_all('/(?<![\w@])@([A-Za-z0-9_]{3,32})\b/', $text, $m)) {
                    foreach ($m[1] as $handle) {
                        $handles[] = $handle;
                    }
                    $textNodes[] = $node;
                }
                return;
            }
            foreach (iterator_to_array($node->childNodes) as $child) {
                $walk($child);
            }
        };
        $walk($doc);

        $targets = $this->users->activeMentionTargets($handles);
        if ($targets === []) {
            return $html;
        }

        foreach ($textNodes as $textNode) {
            $this->replaceTextNode($doc, $textNode, $targets);
        }

        $out = $doc->saveHTML();
        if (!is_string($out)) {
            return $html;
        }
        return preg_replace('/^<\?xml encoding="UTF-8"\?>\s*/', '', $out) ?? $out;
    }

    private function insideExcludedNode(\DOMNode $node): bool
    {
        $parent = $node->parentNode;
        while ($parent !== null) {
            $name = strtolower($parent->nodeName);
            if ($name === 'code' || $name === 'pre' || $name === 'a') {
                return true;
            }
            $parent = $parent->parentNode;
        }
        return false;
    }

    /** @param array<string,array{id:int,username:string}> $targets */
    private function replaceTextNode(\DOMDocument $doc, \DOMText $textNode, array $targets): void
    {
        $text = $textNode->nodeValue ?? '';
        $parts = preg_split('/((?<![\w@])@[A-Za-z0-9_]{3,32}\b)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts) || count($parts) === 1) {
            return;
        }
        $frag = $doc->createDocumentFragment();
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            if ($part[0] === '@') {
                $handle = substr($part, 1);
                $target = $targets[strtolower($handle)] ?? null;
                if ($target !== null) {
                    $a = $doc->createElement('a');
                    $a->setAttribute('href', '/u/' . $target['username']);
                    $a->setAttribute('class', 'mention');
                    $a->appendChild($doc->createTextNode($part));
                    $frag->appendChild($a);
                    continue;
                }
            }
            $frag->appendChild($doc->createTextNode($part));
        }
        $textNode->parentNode?->replaceChild($frag, $textNode);
    }
}
