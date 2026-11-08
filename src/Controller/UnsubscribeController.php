<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\EmailSuppressionRepository;
use App\Security\AppKeyRing;
use App\Support\SignedToken;

/**
 * Login-free one-click unsubscribe (P2-04, ADMIN §7.6). The email footer carries
 * a signed link; GET shows a confirmation (so a link prefetch can't silently
 * unsubscribe — and re-subscribe likewise requires confirmation), POST applies
 * the suppression. The token binds the address to APP_KEY via HMAC.
 */
final class UnsubscribeController extends Controller
{
    private const PURPOSE = 'unsubscribe';

    public function show(Request $request): Response
    {
        [$email, $token, $activeKey] = $this->validate($request, fromQuery: true);
        return $this->view('unsubscribe', [
            'email' => $email,
            'token' => $token,
            'done' => false,
            'resubscribed' => false,
            'can_resubscribe' => $activeKey,
        ]);
    }

    public function confirm(Request $request): Response
    {
        [$email, $token, $activeKey] = $this->validate($request, fromQuery: false);
        $this->container->get(EmailSuppressionRepository::class)->suppress($email, 'unsubscribe');
        return $this->view('unsubscribe', [
            'email' => $email, 'token' => $token, 'done' => true, 'resubscribed' => false, 'can_resubscribe' => $activeKey,
        ]);
    }

    /**
     * Resubscription is a separate permission (ADR 0059): only a link signed with
     * the active key grants it, never one that verifies under a previous key. A
     * signed-in member can always turn emails back on in notification settings.
     */
    public function resubscribe(Request $request): Response
    {
        $email = (string) $request->post('email', '');
        $token = (string) $request->post('token', '');
        if (!SignedToken::verify(self::PURPOSE, strtolower($email), $token, $this->keys()->active()->hmacKey())) {
            throw new NotFoundException('This link cannot turn notification emails back on. Sign in to turn them back on in your notification settings.');
        }
        $this->container->get(EmailSuppressionRepository::class)->unsuppress($email);
        return $this->view('unsubscribe', [
            'email' => $email, 'token' => $token, 'done' => false, 'resubscribed' => true, 'can_resubscribe' => true,
        ]);
    }

    /**
     * Opt-out links verify under the active key or any previous key permitted to
     * verify (APP_PREVIOUS_KEY_VERIFY_IDS), so links in mail already sent keep
     * working through a planned key overlap.
     *
     * @return array{0:string,1:string,2:bool} [email, token, verified under the active key]
     * @throws NotFoundException on an invalid or forged token
     */
    private function validate(Request $request, bool $fromQuery): array
    {
        $email = $fromQuery ? (string) $request->query('email', '') : (string) $request->post('email', '');
        $token = $fromQuery ? (string) $request->query('token', '') : (string) $request->post('token', '');
        foreach ($this->keys()->verificationKeys() as $key) {
            if (SignedToken::verify(self::PURPOSE, strtolower($email), $token, $key->hmacKey())) {
                return [$email, $token, $key->active];
            }
        }
        throw new NotFoundException('This unsubscribe link is invalid or has expired.');
    }

    private function keys(): AppKeyRing
    {
        return $this->container->get(AppKeyRing::class);
    }

    /** Build the signed one-click link for an email footer. */
    public static function link(string $appUrl, string $email, string $key): string
    {
        $token = SignedToken::sign(self::PURPOSE, strtolower($email), $key);
        return rtrim($appUrl, '/') . '/unsubscribe?email=' . rawurlencode($email) . '&token=' . $token;
    }
}
