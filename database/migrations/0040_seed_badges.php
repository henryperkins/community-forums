<?php

declare(strict_types=1);

/**
 * 0040 · seed the fixed badge catalogue (COMMUNITY §6, P2-09).
 *
 * Idempotent: INSERT IGNORE on the unique slug, so re-running (clean install,
 * upgrade, or a re-seed) never double-inserts. Badges are recognition only —
 * they grant no powers (COMMUNITY §1). Auto badges award on their triggering
 * event / a periodic job; manual badges are admin-granted.
 */
return new class {
    /** @var list<array{0:string,1:string,2:string,3:string,4:string}> slug, name, description, icon, kind */
    private const CATALOGUE = [
        ['welcome',              'Welcome',              'Verified your account.',          '🎉', 'auto'],
        ['first-post',           'First Post',           'Posted your first reply.',        '💬', 'auto'],
        ['first-thread',         'First Thread',         'Started your first topic.',       '🧵', 'auto'],
        ['conversation-starter', 'Conversation Starter', 'Started 10 topics.',              '🗣️', 'auto'],
        ['appreciated',          'Appreciated',          'Received 100 reactions.',         '⭐', 'auto'],
        ['well-liked',           'Well-Liked',           'Received 1,000 reactions.',       '🌟', 'auto'],
        ['problem-solver',       'Problem Solver',       'Had an answer accepted.',         '✅', 'auto'],
        ['trusted-answerer',     'Trusted Answerer',     'Had 10 answers accepted.',        '🏅', 'auto'],
        ['anniversary',          'Anniversary',          'One year as a member.',           '🎂', 'auto'],
        ['staff',                'Staff',                'Member of the staff team.',       '🛡️', 'manual'],
        ['founder',              'Founder',              'Here from the very beginning.',   '🚩', 'manual'],
    ];

    public function up(\PDO $pdo): void
    {
        $stmt = $pdo->prepare(
            'INSERT IGNORE INTO badges (slug, name, description, icon, kind) VALUES (?, ?, ?, ?, ?)',
        );
        foreach (self::CATALOGUE as $row) {
            $stmt->execute($row);
        }
    }

    public function down(\PDO $pdo): void
    {
        $slugs = array_map(static fn (array $r): string => $r[0], self::CATALOGUE);
        $place = implode(',', array_fill(0, count($slugs), '?'));
        $pdo->prepare("DELETE FROM badges WHERE slug IN ($place)")->execute($slugs);
    }
};
