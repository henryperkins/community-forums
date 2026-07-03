<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\PackagePublisherRepository;
use App\Repository\PackageReviewDecisionRepository;
use App\Repository\PublisherSigningKeyRepository;
use App\Security\Registry\RegistryVerificationException;
use App\Service\Registry\PublisherTrustService;

/**
 * Local operator security-response console (P5-07-A), deploy-dark behind
 * package_registry: gate() throws NotFoundException (404, never 403/405) before
 * AND after requireAdmin(). No-JS server-rendered forms; every mutation is
 * reauth-gated in the service layer and re-renders 422 with safe old input.
 */
final class AdminPackageSecurityController extends Controller
{
    /** @param array<string,string> $params */
    public function publisher(Request $request, array $params): Response
    {
        $this->gate();
        $this->requireAdmin();
        $this->gate();

        return $this->publisherView((int) ($params['id'] ?? 0));
    }

    /** @param array<string,string> $params */
    public function verifyPublisher(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->container->get(PublisherTrustService::class)->verifyPublisher($admin, (string) $request->post('current_password', ''), $id);
            return $this->noindex($this->redirectWithFlash('/admin/packages/publishers/' . $id, 'Publisher verified.'));
        } catch (ValidationException $e) {
            return $this->publisherView($id, $e->errors, $e->old + $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function suspendPublisher(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        try {
            $affected = $this->container->get(PublisherTrustService::class)->suspendPublisher(
                $admin,
                (string) $request->post('current_password', ''),
                $id,
                $request->str('reason'),
            );
            return $this->noindex($this->redirectWithFlash('/admin/packages/publishers/' . $id, 'Publisher suspended; ' . $affected . ' install(s) force-disabled.'));
        } catch (ValidationException $e) {
            return $this->publisherView($id, $e->errors, $e->old + $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function reinstatePublisher(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->container->get(PublisherTrustService::class)->reinstatePublisher($admin, (string) $request->post('current_password', ''), $id);
            return $this->noindex($this->redirectWithFlash('/admin/packages/publishers/' . $id, 'Publisher reinstated. Re-enable each install explicitly.'));
        } catch (ValidationException $e) {
            return $this->publisherView($id, $e->errors, $e->old + $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function pinPublisherKey(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        try {
            $this->container->get(PublisherTrustService::class)->pinKey(
                $admin,
                (string) $request->post('current_password', ''),
                $id,
                $request->str('key_id'),
                $request->str('public_key'),
                $request->str('valid_from') !== '' ? $request->str('valid_from') : null,
                $request->str('valid_until') !== '' ? $request->str('valid_until') : null,
            );
            return $this->noindex($this->redirectWithFlash('/admin/packages/publishers/' . $id, 'Publisher signing key pinned.'));
        } catch (ValidationException $e) {
            return $this->publisherView($id, $e->errors, $e->old + $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function rotatePublisherKey(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $id = (int) ($params['id'] ?? 0);
        try {
            [$document, $signature, $keyId] = $this->parseEnvelope($request->str('envelope'));
            $this->container->get(PublisherTrustService::class)->applyKeyRotation(
                $admin,
                (string) $request->post('current_password', ''),
                $id,
                $document,
                $signature,
                $keyId,
            );
            return $this->noindex($this->redirectWithFlash('/admin/packages/publishers/' . $id, 'Publisher key rotation applied: successor pinned, old key retired.'));
        } catch (ValidationException $e) {
            return $this->publisherView($id, $e->errors, $request->allInput(), 422);
        } catch (RegistryVerificationException $e) {
            return $this->publisherView($id, ['envelope' => 'Rotation refused (' . $e->code . '): ' . $e->getMessage()], $request->allInput(), 422);
        }
    }

    /** @param array<string,string> $params */
    public function revokePublisherKey(Request $request, array $params): Response
    {
        $this->gate();
        $admin = $this->requireAdmin();
        $this->gate();
        $keyRowId = (int) ($params['id'] ?? 0);
        $key = $this->container->get(PublisherSigningKeyRepository::class)->find($keyRowId);
        $publisherId = $key !== null ? (int) $key['publisher_id'] : 0;
        try {
            $this->container->get(PublisherTrustService::class)->revokeKey(
                $admin,
                (string) $request->post('current_password', ''),
                $keyRowId,
                $request->str('reason'),
            );
            return $this->noindex($this->redirectWithFlash('/admin/packages/publishers/' . $publisherId, 'Publisher signing key revoked; everything it signed now fails closed.'));
        } catch (ValidationException $e) {
            return $this->publisherView($publisherId, $e->errors, $request->allInput(), 422);
        }
    }

    private function gate(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('package_registry')) {
            throw new NotFoundException();
        }
    }

    private function noindex(Response $response): Response
    {
        return $response->header('X-Robots-Tag', 'noindex');
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
    private function publisherView(int $publisherId, array $errors = [], array $old = [], int $status = 200): Response
    {
        $publishers = $this->container->get(PackagePublisherRepository::class);
        $publisher = $publishers->find($publisherId);
        if ($publisher === null) {
            throw new NotFoundException();
        }
        $reviews = $this->container->get(PackageReviewDecisionRepository::class);
        $packages = [];
        foreach ($publishers->packagesFor($publisherId) as $package) {
            $package['decisions'] = $reviews->forPackage((int) $package['id']);
            $packages[] = $package;
        }

        return $this->noindex($this->view('admin/package_publisher', [
            'publisher' => $publisher,
            'keys' => $this->container->get(PublisherSigningKeyRepository::class)->forPublisher($publisherId),
            'packages' => $packages,
            'errors' => $errors,
            'old' => $old,
        ], $status));
    }
}
