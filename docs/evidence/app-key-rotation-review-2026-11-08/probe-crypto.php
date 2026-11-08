<?php

declare(strict_types=1);

/*
 * Isolated probe of the CURRENT APP_KEY primitives with dummy keys only.
 * No database, no network, no configured key is read.
 *
 * Usage: php probe-crypto.php <repo-root> > results/probe-crypto.json
 */

$root = $argv[1] ?? dirname(__DIR__, 3);
require $root . '/vendor/autoload.php';

use App\Security\SecretBox;
use App\Support\SignedToken;

$k1 = str_repeat('1a', 32);          // 64 lowercase hex, the key:generate shape
$k2 = str_repeat('2b', 32);
$k1Upper = strtoupper($k1);          // same bytes to hex2bin, different string
$k1Extra = $k1 . 'ff';               // SecretBox reads only the first 64 hex chars
$passphrase = 'correct horse battery staple'; // non-hex: SecretBox uses sha256(raw)

$decrypts = static function (string $key, array $enc): bool {
    try {
        (new SecretBox($key))->decrypt($enc['ciphertext'], $enc['nonce'], $enc['tag']);
        return true;
    } catch (RuntimeException) {
        return false;
    }
};

$out = ['php' => PHP_VERSION];

// 1. AES-256-GCM under a wrong key or tampered ciphertext fails closed.
$plaintext = 'JBSWY3DPEHPK3PXP';
$enc = (new SecretBox($k1))->encrypt($plaintext);
$accepted = 0;
$trials = 2000;
for ($i = 0; $i < $trials; $i++) {
    $accepted += $decrypts(bin2hex(random_bytes(32)), $enc) ? 1 : 0;
}
$tampered = $enc;
$tampered['ciphertext'][0] = chr(ord($tampered['ciphertext'][0]) ^ 1);
$out['gcm'] = [
    'k1_roundtrip' => (new SecretBox($k1))->decrypt($enc['ciphertext'], $enc['nonce'], $enc['tag']) === $plaintext,
    'random_wrong_keys_tried' => $trials,
    'random_wrong_keys_accepted' => $accepted,
    'k2_accepts_k1_ciphertext' => $decrypts($k2, $enc),
    'tampered_ciphertext_accepted' => $decrypts($k1, $tampered),
    'ciphertext_has_no_key_identifier' => true, // three raw columns: ciphertext, nonce, tag
];

// 2. One APP_KEY string feeds two different derivations.
$hmacSame = static fn (string $a, string $b): bool =>
    SignedToken::sign('unsubscribe', 'member@example.test', $a) === SignedToken::sign('unsubscribe', 'member@example.test', $b);
$out['key_material'] = [
    'uppercase_variant_decrypts_k1_ciphertext' => $decrypts($k1Upper, $enc),
    'uppercase_variant_same_hmac' => $hmacSame($k1, $k1Upper),
    'extra_trailing_hex_decrypts_k1_ciphertext' => $decrypts($k1Extra, $enc),
    'extra_trailing_hex_same_hmac' => $hmacSame($k1, $k1Extra),
    'passphrase_key_roundtrip' => (static function () use ($passphrase): bool {
        $e = (new SecretBox($passphrase))->encrypt('x');
        return (new SecretBox($passphrase))->decrypt($e['ciphertext'], $e['nonce'], $e['tag']) === 'x';
    })(),
    'note' => 'SecretBox uses hex2bin(first 64 hex) or sha256(trimmed raw); every HMAC consumer uses the raw string.',
];

// 3. Stateless unsubscribe token: deterministic, no timestamp, no expiry.
$email = 'member@example.test';
$t1 = SignedToken::sign('unsubscribe', $email, $k1);
$out['unsubscribe_token'] = [
    'deterministic_per_address' => $t1 === SignedToken::sign('unsubscribe', $email, $k1),
    'format_is_bare_hmac_hex' => strlen($t1) === 64 && ctype_xdigit($t1),
    'verifies_under_k1' => SignedToken::verify('unsubscribe', $email, $t1, $k1),
    'verifies_under_k2' => SignedToken::verify('unsubscribe', $email, $t1, $k2),
    'verifies_with_keyring_trial_k2_then_k1' => SignedToken::verify('unsubscribe', $email, $t1, $k2)
        || SignedToken::verify('unsubscribe', $email, $t1, $k1),
    'verifies_after_k1_removed_keyring_k2_only' => SignedToken::verify('unsubscribe', $email, $t1, $k2),
];

// 4. Recovery-code verifier: HMAC(APP_KEY, normalized code), 40-bit codes.
$normalized = 'ABCDE12345';
$storedUnderK1 = hash_hmac('sha256', $normalized, $k1);
$out['recovery_code_verifier'] = [
    'code_entropy_bits' => 40,
    'k2_recomputation_matches_k1_stored' => hash_hmac('sha256', $normalized, $k2) === $storedUnderK1,
    'keyring_candidates_k2_k1_include_stored' => in_array($storedUnderK1, [hash_hmac('sha256', $normalized, $k2), hash_hmac('sha256', $normalized, $k1)], true),
    'after_k1_removed_candidates_include_stored' => in_array($storedUnderK1, [hash_hmac('sha256', $normalized, $k2)], true),
    'rewrap_possible_without_plaintext' => false, // the stored value is a one-way verifier
];

// 5. Email-change verifier (same shape as EmailChangeService::tokenHash).
$raw = bin2hex(random_bytes(32));
$bound = json_encode(['email_change', 7, 'old@example.test', '$argon2id$dummy', $raw], JSON_THROW_ON_ERROR);
$out['email_change_verifier'] = [
    'k2_recomputation_matches_k1_stored' => hash_hmac('sha256', $bound, $k2) === hash_hmac('sha256', $bound, $k1),
    'ttl_seconds_default_min_max' => [3600, 300, 86400],
];

// 6. OAuth state cookie (same construction as OAuthController::writeStateCookie).
$b64 = rtrim(strtr(base64_encode('{"p":"github","state":"abc"}'), '+/', '-_'), '=');
$sig = hash_hmac('sha256', $b64, $k1);
$out['oauth_state_cookie'] = [
    'verifies_under_k1' => hash_equals(hash_hmac('sha256', $b64, $k1), $sig),
    'verifies_under_k2' => hash_equals(hash_hmac('sha256', $b64, $k2), $sig),
    'cookie_ttl_seconds' => 600,
];

// 7. Passkey decoys (PasskeyService::loginRequestOptions): generated, never verified.
$decoy = static fn (string $key): string => bin2hex(hash_hmac('sha256', 'passkey-decoy:' . $email . ':0', $key, true));
$out['passkey_decoy'] = [
    'stable_under_one_key' => $decoy($k1) === $decoy($k1),
    'changes_when_key_changes' => $decoy($k1) !== $decoy($k2),
];

// 8. Candidate key identifier over the encryption root, and the collisions it implies.
$rootBytes = static function (string $raw): string {
    $raw = trim($raw);
    if (ctype_xdigit($raw) && strlen($raw) >= 64) {
        return (string) hex2bin(substr($raw, 0, 64));
    }
    return hash('sha256', $raw, true);
};
$kid = static fn (string $raw): string => substr(hash_hmac('sha256', 'retroboards/app-key-id/v1', $rootBytes($raw)), 0, 16);
$out['candidate_key_id'] = [
    'k1' => $kid($k1),
    'k2' => $kid($k2),
    'k1_uppercase_same_id' => $kid($k1Upper) === $kid($k1),
    'k1_extra_hex_same_id' => $kid($k1Extra) === $kid($k1),
    'implication' => 'Same encryption root, different HMAC key: the keyring must reject two entries with one root.',
];

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
