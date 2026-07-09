<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\LocalPackageBlockRepository;
use App\Repository\PackageAdvisoryRepository;
use App\Repository\PackageRegistryRepository;
use App\Repository\RegistrySnapshotRepository;
use App\Repository\RegistryTrustKeyRepository;
use App\Security\ReauthGate;
use App\Security\Registry\RegistryVerificationException;
use App\Service\Registry\LocalBlocklistService;
use App\Service\Registry\RegistryAdvisoryService;
use App\Service\Registry\RegistryTrustService;

/**
 * Registry trust console (flag-gated by package_registry): sources, pinned keys, signed rotation,
 * revocation, local blocklist, and advisory ingest/ack.
 */
final class AdminRegistryController extends Controller
{
    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('package_registry')) {
            throw new NotFoundException();
        }
    }

    /** @param array<string,string> $params */
    public function index(Request $request, array $params): Response
    {
        $this->requireAdmin();
        $this->gate();

        return $this->consoleView();
    }

    /** @param array<string,string> $params */
    public function create(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $this->container->get(RegistryTrustService::class)->createRegistry(
                $admin,
                (string) $request->post('current_password', ''),
                $request->str('source_id'),
                $request->str('display_name'),
                $request->str('base_url'),
            );
            return $this->noindex($this->redirectWithFlash('/admin/registries', 'Registry added (disabled until you enable it).'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $e->old + $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function setEnabled(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();
        $enabled = $request->post('enabled', '0') === '1';

        try {
            $this->container->get(RegistryTrustService::class)->setEnabled(
                $admin,
                $enabled ? (string) $request->post('current_password', '') : null,
                (int) ($params['id'] ?? 0),
                $enabled,
            );
            return $this->noindex($this->redirectWithFlash('/admin/registries', $enabled ? 'Registry enabled.' : 'Registry disabled.'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function pinKey(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $this->container->get(RegistryTrustService::class)->pinKey(
                $admin,
                (string) $request->post('current_password', ''),
                (int) ($params['id'] ?? 0),
                $request->str('key_id'),
                $request->str('public_key'),
                $request->str('valid_from') !== '' ? $request->str('valid_from') : null,
                $request->str('valid_until') !== '' ? $request->str('valid_until') : null,
            );
            return $this->noindex($this->redirectWithFlash('/admin/registries', 'Trust key pinned.'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $e->old + $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function rotate(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            [$document, $signature, $keyId] = $this->parseEnvelope($request->str('envelope'));
            $this->container->get(RegistryTrustService::class)->applyRotation(
                $admin,
                (string) $request->post('current_password', ''),
                (int) ($params['id'] ?? 0),
                $document,
                $signature,
                $keyId,
            );
            return $this->noindex($this->redirectWithFlash('/admin/registries', 'Key rotation applied: successor pinned, old key retired.'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $request->allInput(), 422);
        } catch (RegistryVerificationException $e) {
            return $this->consoleView(['envelope' => 'Rotation refused (' . $e->code . '): ' . $e->getMessage()], $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function revokeKey(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $this->container->get(RegistryTrustService::class)->revokeKey(
                $admin,
                (string) $request->post('current_password', ''),
                (int) ($params['id'] ?? 0),
                $request->str('reason'),
            );
            return $this->noindex($this->redirectWithFlash('/admin/registries', 'Trust key revoked; everything it signed now fails closed.'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function ingestAdvisory(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $this->container->get(ReauthGate::class)->requirePassword($admin, (string) $request->post('current_password', ''));
            [$document, $signature, $keyId] = $this->parseEnvelope($request->str('envelope'));
            $out = $this->container->get(RegistryAdvisoryService::class)->ingest(
                (int) ($params['id'] ?? 0),
                $document,
                $signature,
                $keyId,
                null,
                $admin->id(),
            );
            return $this->noindex($this->redirectWithFlash('/admin/registries', 'Advisory ingested (action: ' . $out['action'] . ').'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $request->allInput(), 422);
        } catch (RegistryVerificationException $e) {
            return $this->consoleView(['advisory_envelope' => 'Advisory refused (' . $e->code . '): ' . $e->getMessage()], $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function ackAdvisory(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $this->container->get(RegistryAdvisoryService::class)->acknowledge($admin, (int) ($params['id'] ?? 0));
            return $this->noindex($this->redirectWithFlash('/admin/registries', 'Advisory acknowledged.'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, [], 422);
        }
    }

    /** @param array<string,string> $params */
    public function block(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $this->container->get(LocalBlocklistService::class)->block(
                $admin,
                $request->str('digest') !== '' ? $request->str('digest') : null,
                $request->str('package_uid') !== '' ? $request->str('package_uid') : null,
                $request->str('reason') !== '' ? $request->str('reason') : null,
            );
            return $this->noindex($this->redirectWithFlash('/admin/registries', 'Local block added; it applies regardless of registry state.'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $e->old + $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function unblock(Request $request, array $params): Response
    {
        $admin = $this->requireAdmin();
        $this->gate();

        try {
            $this->container->get(LocalBlocklistService::class)->unblock(
                $admin,
                (string) $request->post('current_password', ''),
                (int) ($params['id'] ?? 0),
            );
            return $this->noindex($this->redirectWithFlash('/admin/registries', 'Local block removed.'));
        } catch (ValidationException $e) {
            return $this->consoleView($e->errors, $request->allInput(), 422);
        }
    }

    /** @return array{0:string,1:string,2:string} document, raw signature bytes, key id */
    private function parseEnvelope(string $raw): array
    {
        $decoded = json_decode(trim($raw), true);
        $signature = is_array($decoded) ? base64_decode((string) ($decoded['signature'] ?? ''), true) : false;
        if (!is_array($decoded) || !is_string($decoded['document'] ?? null) || $signature === false) {
            throw new ValidationException(['envelope' => 'Paste the JSON envelope: {"document": "...", "signature": "<base64>", "key_id": "..."}']);
        }

        return [(string) $decoded['document'], $signature, (string) ($decoded['key_id'] ?? '')];
    }

    /** @param array<string,string> $errors @param array<string,mixed> $old */
    private function consoleView(array $errors = [], array $old = [], int $status = 200): Response
    {
        $registryRepo = $this->container->get(PackageRegistryRepository::class);
        $keyRepo = $this->container->get(RegistryTrustKeyRepository::class);
        $snapshotRepo = $this->container->get(RegistrySnapshotRepository::class);

        $registries = [];
        foreach ($registryRepo->all() as $registry) {
            $registry['keys'] = $keyRepo->forRegistry((int) $registry['id']);
            $registry['latest_snapshot'] = $snapshotRepo->latestFor((int) $registry['id']);
            $registries[] = $registry;
        }

        return $this->noindex($this->view('admin/registries', [
            'registries' => $registries,
            'blocks' => $this->container->get(LocalPackageBlockRepository::class)->all(),
            'advisories' => $this->container->get(PackageAdvisoryRepository::class)->all(),
            'errors' => $errors,
            'old' => $old,
        ], $status));
    }
}
