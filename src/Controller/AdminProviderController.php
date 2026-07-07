<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\IdentityProviderRepository;
use App\Repository\OAuthIdentityRepository;
use App\Service\IdentityProviderService;
use App\Service\OAuth\ProviderRegistry;

/**
 * Operator console for the identity-provider registry (P5-12), behind the
 * dark `provider_registry` flag: list builtin + generic-OIDC providers with
 * health and sole-method counts, add a generic provider (secret → vault),
 * probe health, and enable/disable — with the TM-ID-09-clause-2 handoff
 * honoured: disable goes through a confirm page that lists every account
 * whose only sign-in method is this provider, BEFORE anything changes.
 */
final class AdminProviderController extends Controller
{
    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();
        return $this->providersView();
    }

    /** @param array<string,string> $params */
    public function create(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $this->container->get(IdentityProviderService::class)->create(
                $admin,
                (string) $request->post('current_password', ''),
                $request->allInput(),
            );
        } catch (ValidationException $e) {
            // Anti-draft-loss: re-render with the typed values (never the secret).
            $old = $request->allInput();
            unset($old['client_secret'], $old['current_password'], $old['_token']);
            return $this->providersView($e->errors, $old, 422);
        }
        return $this->noindex($this->redirectWithFlash('/admin/providers', 'Provider added (disabled). Run "Test connection", then enable it.'));
    }

    /** @param array<string,string> $params */
    public function test(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();

        try {
            $result = $this->container->get(IdentityProviderService::class)->healthProbe((int) ($params['id'] ?? 0));
        } catch (ValidationException $e) {
            return $this->providersView($e->errors, [], 422);
        }
        return $this->noindex($this->redirectWithFlash(
            '/admin/providers',
            'Provider health: ' . $result['status'] . ' — ' . $result['detail'],
        ));
    }

    /** @param array<string,string> $params */
    public function enable(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $row = $this->container->get(IdentityProviderService::class)->setEnabled(
                $admin,
                (string) $request->post('current_password', ''),
                (int) ($params['id'] ?? 0),
                true,
            );
        } catch (ValidationException $e) {
            // A reauth failure must surface beside the row's own inline form,
            // not under the unrelated add-provider form's password field.
            $errors = $e->errors;
            if (isset($errors['current_password'])) {
                $errors['enable_password'] = $errors['current_password'];
                unset($errors['current_password']);
            }
            return $this->providersView($errors, [], 422, (int) ($params['id'] ?? 0));
        }
        return $this->noindex($this->redirectWithFlash('/admin/providers', $row['display_name'] . ' is now offered at sign-in.'));
    }

    /** Confirm page: the sole-method listing BEFORE disable (TM-ID-09 §2). */
    public function disableConfirm(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();
        return $this->disableView((int) ($params['id'] ?? 0));
    }

    /** @param array<string,string> $params */
    public function disable(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);

        try {
            $row = $this->container->get(IdentityProviderService::class)->setEnabled(
                $admin,
                (string) $request->post('current_password', ''),
                $id,
                false,
            );
        } catch (ValidationException $e) {
            return $this->disableView($id, $e->errors, 422);
        }
        return $this->noindex($this->redirectWithFlash(
            '/admin/providers',
            $row['display_name'] . ' disabled. Linked identities are retained; members keep their password/passkey fallbacks.',
        ));
    }

    // ---- internals ---------------------------------------------------------

    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('provider_registry')) {
            throw new NotFoundException('Not found.');
        }
    }

    /** @param array<string,string> $errors @param array<string,mixed> $old */
    private function providersView(array $errors = [], array $old = [], int $status = 200, ?int $enableErrorId = null): Response
    {
        $identities = $this->container->get(OAuthIdentityRepository::class);
        $registry = $this->container->get(ProviderRegistry::class);
        $usableProviders = $this->usableProviderNames();

        $rows = [];
        foreach ($this->container->get(IdentityProviderRepository::class)->all() as $row) {
            $key = (string) $row['provider_key'];
            $builtin = (string) $row['type'] !== 'generic_oidc';
            $rows[] = $row + [
                'sole_method_count' => $identities->soleMethodCount($key, self::includingProvider($usableProviders, $key)),
                'env_configured' => $builtin && ($registry->get($key)?->isConfigured() ?? false),
            ];
        }

        return $this->noindex($this->view('admin/providers', [
            'rows' => $rows,
            'errors' => $errors,
            'old' => $old,
            'enable_error_id' => $enableErrorId,
        ], $status));
    }

    /** @param array<string,string> $errors */
    private function disableView(int $id, array $errors = [], int $status = 200): Response
    {
        $row = $this->container->get(IdentityProviderRepository::class)->find($id);
        if ($row === null || (string) $row['type'] !== 'generic_oidc') {
            throw new NotFoundException('Provider not found.');
        }
        $key = (string) $row['provider_key'];

        return $this->noindex($this->view('admin/provider_disable', [
            'row' => $row,
            'sole_accounts' => $this->container->get(OAuthIdentityRepository::class)
                ->soleMethodAccounts($key, self::includingProvider($this->usableProviderNames(), $key)),
            'errors' => $errors,
        ], $status));
    }

    /**
     * The enforcement-side definition of "usable sign-in provider" (mirrors
     * the App.php closures feeding the unlink/passkey lockout guards): the
     * `oauth` master flag gates whether ANY provider is usable at all.
     *
     * @return list<string>
     */
    private function usableProviderNames(): array
    {
        return $this->container->get(FeatureFlags::class)->enabled('oauth')
            ? $this->container->get(ProviderRegistry::class)->configuredNames()
            : [];
    }

    /**
     * The listed row's own key always counts as usable for ITS OWN column:
     * a disabled provider must keep reporting the members who depend on it
     * (sole-method lockout), not drop to a reassuring 0.
     *
     * @param list<string> $usable
     * @return list<string>
     */
    private static function includingProvider(array $usable, string $provider): array
    {
        return in_array($provider, $usable, true) ? $usable : [...$usable, $provider];
    }

    private function noindex(Response $response): Response
    {
        return $response->header('X-Robots-Tag', 'noindex');
    }
}
