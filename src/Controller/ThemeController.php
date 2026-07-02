<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\PackageThemeRepository;
use App\Service\Packages\ThemeStateService;

final class ThemeController extends Controller
{
    public function css(Request $request, array $params = []): Response
    {
        $this->requireThemesEnabled();
        $digest = $this->digest($params['digest'] ?? '');
        $build = $this->container->get(ThemeStateService::class)->activeBuild();
        if ($build === null || !hash_equals($digest, (string) $build['css_digest'])) {
            throw new NotFoundException();
        }

        return new Response((string) $build['css'], 200, $this->immutableHeaders('text/css; charset=UTF-8', $digest));
    }

    public function previewCss(Request $request): Response
    {
        $this->requireThemesEnabled();
        $user = $this->currentUser();
        if ($user === null || !$user->isAdmin()) {
            throw new NotFoundException();
        }

        $id = $this->session()->get('theme_preview_build');
        $build = $this->container->get(ThemeStateService::class)->previewBuildFor(is_int($id) ? $id : null);
        if ($build === null) {
            throw new NotFoundException();
        }

        return new Response((string) $build['css'], 200, [
            'Content-Type' => 'text/css; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'ETag' => '"' . (string) $build['css_digest'] . '"',
        ]);
    }

    public function asset(Request $request, array $params = []): Response
    {
        $this->requireThemesEnabled();
        $digest = $this->digest($params['digest'] ?? '');
        $build = $this->container->get(ThemeStateService::class)->activeBuild();
        if ($build === null) {
            throw new NotFoundException();
        }

        $asset = $this->container->get(PackageThemeRepository::class)->findAssetByDigest($digest);
        if ($asset === null || (int) $asset['build_id'] !== (int) $build['id']) {
            throw new NotFoundException();
        }

        return new Response((string) $asset['bytes'], 200, $this->immutableHeaders((string) $asset['mime'], $digest));
    }

    private function requireThemesEnabled(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('package_themes')) {
            throw new NotFoundException();
        }
    }

    private function digest(string $value): string
    {
        if (preg_match('/\A[a-f0-9]{64}\z/', $value) !== 1) {
            throw new NotFoundException();
        }

        return $value;
    }

    /** @return array<string,string> */
    private function immutableHeaders(string $contentType, string $digest): array
    {
        return [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'ETag' => '"' . $digest . '"',
        ];
    }
}
