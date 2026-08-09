<?php

declare(strict_types=1);

/** HTTP-level authorization + filter matrix probe for /inbox. */

const BASE = 'http://localhost:8013';

final class Client
{
    private string $jar;

    public function __construct(string $name)
    {
        $this->jar = sys_get_temp_dir() . "/rb-inbox-$name.cookies";
        @unlink($this->jar);
    }

    /** @return array{status:int,body:string,location:string} */
    public function req(string $path, ?array $post = null): array
    {
        $ch = curl_init(BASE . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar,
            CURLOPT_HEADER => true,
        ]);
        if ($post !== null) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
        }
        $raw = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hlen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        $headers = substr($raw, 0, $hlen);
        $body = substr($raw, $hlen);
        preg_match('/^Location:\s*(.+)$/mi', $headers, $m);
        return ['status' => $status, 'body' => $body, 'location' => trim($m[1] ?? '')];
    }

    public function login(string $email, string $password): bool
    {
        $page = $this->req('/login');
        preg_match('/name="_token"[^>]*value="([^"]+)"/', $page['body'], $m);
        $this->req('/login', ['email' => $email, 'password' => $password, '_token' => $m[1] ?? '', 'next' => '/inbox']);
        return $this->req('/inbox')['status'] === 200;   // authenticated iff no login redirect
    }
}

$filters = ['for_you', 'unread', 'mentions', 'replies', 'watching', 'needs_answer', 'assigned',
    'decisions', 'solved', 'snoozed', 'starred', 'mine', 'active', 'newest', 'unanswered'];

$canaries = ['HIDDEN-BOARD-CANARY', 'PRIVATE-BOARD-CANARY', 'PENDING-CANARY', 'DELETED-CANARY',
    'FALSE-MENTION-CANARY', 'ANON-CANARY'];

$rowCount = static fn (string $b): int => preg_match_all('/<li class="[^"]*thread-row/', $b);
$activeTab = static function (string $b): string {
    if (!preg_match('#<nav class="inbox-tabs".*?</nav>#s', $b, $nav)) {
        return '?';
    }
    preg_match('/class="inbox-tab is-active"[^>]*>\s*([^<]*)</s', $nav[0], $m);
    return trim($m[1] ?? '(none)');
};
$unreadBadge = static function (string $b): string {
    preg_match('/<span class="badge">(\d+) unread<\/span>/', $b, $m);
    return $m[1] ?? '0';
};

echo "=== GUEST ===\n";
$g = new Client('guest');
foreach (['/inbox', '/inbox?filter=starred', '/inbox?filter=mine&page=2'] as $p) {
    $r = $g->req($p);
    printf("  %-28s -> %d  %s\n", $p, $r['status'], $r['location']);
}

$personas = ['alice', 'bob', 'carol', 'dana', 'admin'];
$sessions = [];
foreach ($personas as $u) {
    $c = new Client($u);
    $ok = $c->login("$u@retro.test", 'password123');
    $sessions[$u] = $c;
    echo "\n=== $u (auth " . ($ok ? 'ok' : 'FAILED') . ") ===\n";
    if (!$ok) {
        continue;
    }
    $home = $c->req('/inbox');
    printf("  default tab=%-10s unread badge=%s\n", $activeTab($home['body']), $unreadBadge($home['body']));
    foreach ($filters as $f) {
        $res = $c->req('/inbox?filter=' . $f);
        $hits = [];
        foreach ($canaries as $can) {
            if (str_contains($res['body'], $can)) {
                $hits[] = $can;
            }
        }
        printf("  %-13s %d rows=%-3d %s\n", $f, $res['status'], $rowCount($res['body']), $hits ? '<< ' . implode(',', $hits) : '');
    }
}

echo "\n=== PARAMETER TAMPERING (alice) ===\n";
$a = $sessions['alice'];
foreach ([
    '/inbox',
    '/inbox?filter=bogus',
    '/inbox?filter=drafts',
    '/inbox?filter[]=unread',
    '/inbox?page=99999',
    '/inbox?page=-4',
    '/inbox?page=abc',
    "/inbox?filter=unread'--",
    '/inbox?filter=%3Cscript%3Ealert(1)%3C/script%3E',
] as $p) {
    $res = $a->req($p);
    printf("  %-42s -> %d rows=%-3d activeTab=%s\n", $p, $res['status'], $rowCount($res['body']), $activeTab($res['body']));
}

echo "\n=== ANON MASKING (dana's ANON-CANARY row, seen by alice) ===\n";
$res = $a->req('/inbox?filter=watching');
if (preg_match('/<li class="[^"]*thread-row.*?ANON-CANARY.*?<\/li>/s', $res['body'], $m)) {
    echo '  ' . trim(preg_replace('/\s+/', ' ', strip_tags($m[0]))) . "\n";
    echo '  leaks "dana": ' . (stripos($m[0], 'dana') !== false ? 'YES *** LEAK ***' : 'no') . "\n";
} else {
    echo "  (row not found)\n";
}

echo "\n=== PRIVATE BOARD: membership revocation mid-session ===\n";
$bob = $sessions['bob'];
$before = $bob->req('/inbox?filter=newest');
echo '  bob sees private canary before revoke: ' . (str_contains($before['body'], 'PRIVATE-BOARD-CANARY') ? 'yes' : 'no') . "\n";
exec('docker exec rb-mariadb mariadb -uroot -prootpw -e "USE retroboards_inbox_audit; DELETE bm FROM board_members bm JOIN boards b ON b.id=bm.board_id WHERE b.slug=\'staff-room\';" 2>&1');
$after = $bob->req('/inbox?filter=newest');
echo '  bob sees private canary after revoke:  ' . (str_contains($after['body'], 'PRIVATE-BOARD-CANARY') ? 'YES *** STALE ***' : 'no') . "\n";
$direct = $bob->req('/c/staff-room');
echo '  bob direct /c/staff-room after revoke: ' . $direct['status'] . ' ' . $direct['location'] . "\n";
exec('docker exec rb-mariadb mariadb -uroot -prootpw -e "USE retroboards_inbox_audit; INSERT IGNORE INTO board_members (board_id,user_id,added_by,created_at) SELECT b.id,u.id,1,UTC_TIMESTAMP() FROM boards b, users u WHERE b.slug=\'staff-room\' AND u.username=\'bob\';" 2>&1');
echo '  membership restored: ' . (str_contains($bob->req('/inbox?filter=newest')['body'], 'PRIVATE-BOARD-CANARY') ? 'yes' : 'no') . "\n";

echo "\n=== ACCOUNT STATE: suspended member can still READ the inbox ===\n";
exec('docker exec rb-mariadb mariadb -uroot -prootpw -e "USE retroboards_inbox_audit; UPDATE users SET status=\'suspended\', suspended_until=DATE_ADD(UTC_TIMESTAMP(), INTERVAL 3 DAY) WHERE username=\'carol\';" 2>&1');
$carol = $sessions['carol'];
$r = $carol->req('/inbox');
printf("  suspended carol /inbox -> %d  %s\n", $r['status'], $r['location']);
exec('docker exec rb-mariadb mariadb -uroot -prootpw -e "USE retroboards_inbox_audit; UPDATE users SET status=\'active\', suspended_until=NULL WHERE username=\'carol\';" 2>&1');

echo "\n=== BOARD MODERATOR OF A PRIVATE BOARD (not a member) ===\n";
exec('docker exec rb-mariadb mariadb -uroot -prootpw -e "USE retroboards_inbox_audit; INSERT IGNORE INTO board_moderators (board_id,user_id,assigned_by,created_at) SELECT b.id,u.id,1,UTC_TIMESTAMP() FROM boards b, users u WHERE b.slug=\'staff-room\' AND u.username=\'carol\';" 2>&1');
$r = $carol->req('/inbox?filter=newest');
echo '  carol (board mod, non-member) sees private canary in inbox: ' . (str_contains($r['body'], 'PRIVATE-BOARD-CANARY') ? 'yes' : 'no') . "\n";
$r = $carol->req('/c/staff-room');
echo '  carol direct /c/staff-room: ' . $r['status'] . ' ' . $r['location'] . "\n";

echo "\n=== READING PANE FETCH (what app.js requests) ===\n";
$t = (int) shell_exec('docker exec rb-mariadb mariadb -uroot -prootpw -N -e "USE retroboards_inbox_audit; SELECT id FROM threads WHERE title LIKE \'LONG-TITLE-CANARY%\' LIMIT 1;"');
$r = $a->req("/t/$t");
printf("  GET /t/%d -> %d  has #main=%s has thread-view=%s\n", $t, $r['status'],
    str_contains($r['body'], 'id="main"') ? 'y' : 'n',
    preg_match('/class="[^"]*(thread-view|post-stream|thread-head)/', $r['body']) ? 'y' : 'n');
echo '  full layout returned (topbar present): ' . (str_contains($r['body'], 'class="topbar"') || str_contains($r['body'], '<header') ? 'yes — whole page fetched for the pane' : 'no') . "\n";
printf("  response bytes: %d\n", strlen($r['body']));

echo "\n=== UNREAD BADGE vs LIST after opening a topic ===\n";
$before = $a->req('/inbox?filter=unread');
printf("  before: badge=%s unreadRows=%d\n", $unreadBadge($before['body']), $rowCount($before['body']));
$a->req("/t/$t");
$after = $a->req('/inbox?filter=unread');
printf("  after opening one topic: badge=%s unreadRows=%d\n", $unreadBadge($after['body']), $rowCount($after['body']));
