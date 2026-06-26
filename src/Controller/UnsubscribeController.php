<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\EmailSuppressionRepository;
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
        [$email] = $this->validate($request, fromQuery: true);
        return $this->view('unsubscribe', [
            'email' => $email,
            'token' => (string) $request->query('token', ''),
            'done' => false,
            'resubscribed' => false,
        ]);
    }

    public function confirm(Request $request): Response
    {
        [$email, $token] = $this->validate($request, fromQuery: false);
        $this->container->get(EmailSuppressionRepository::class)->suppress($email, 'unsubscribe');
        return $this->view('unsubscribe', ['email' => $email, 'token' => $token, 'done' => true, 'resubscribed' => false]);
    }

    public function resubscribe(Request $request): Response
    {
        [$email, $token] = $this->validate($request, fromQuery: false);
        $this->container->get(EmailSuppressionRepository::class)->unsuppress($email);
        return $this->view('unsubscribe', ['email' => $email, 'token' => $token, 'done' => false, 'resubscribed' => true]);
    }

    /**
     * @return array{0:string,1:string} [email, token]
     * @throws NotFoundException on an invalid/forged token
     */
    private function validate(Request $request, bool $fromQuery): array
    {
        $email = $fromQuery ? (string) $request->query('email', '') : (string) $request->post('email', '');
        $token = $fromQuery ? (string) $request->query('token', '') : (string) $request->post('token', '');
        $key = (string) $this->config()->get('app.key', '');
        if (!SignedToken::verify(self::PURPOSE, strtolower($email), $token, $key)) {
            throw new NotFoundException('This unsubscribe link is invalid or has expired.');
        }
        return [$email, $token];
    }

    /** Build the signed one-click link for an email footer. */
    public static function link(string $appUrl, string $email, string $key): string
    {
        $token = SignedToken::sign(self::PURPOSE, strtolower($email), $key);
        return rtrim($appUrl, '/') . '/unsubscribe?email=' . rawurlencode($email) . '&token=' . $token;
    }
}
