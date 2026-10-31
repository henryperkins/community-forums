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

        return new Response((string) $build['css'], 200, $this->previewHeaders('text/css; charset=UTF-8', (string) $build['css_digest']));
    }

    public function asset(Request $request, array $params = []): Response
    {
        $this->requireThemesEnabled();
        $digest = $this->digest($params['digest'] ?? '');
        $themes = $this->container->get(ThemeStateService::class);
        $assets = $this->container->get(PackageThemeRepository::class);

        // Identical asset bytes may belong to several builds. Resolve within
        // the eligible build so merely building a preview cannot shadow a
        // currently public asset with a newer, private database row.
        $active = $themes->activeBuild();
        if ($active !== null) {
            $asset = $assets->findAssetForBuild((int) $active['id'], $digest);
            if ($asset !== null) {
                return new Response((string) $asset['bytes'], 200, $this->immutableHeaders((string) $asset['mime'], $digest));
            }
        }

        // Preview-only bytes have the same session boundary and cache policy
        // as preview.css. Both build resolvers fail dark in safe mode and when
        // their installed package is no longer eligible to serve.
        $user = $this->currentUser();
        if ($user !== null && $user->isAdmin()) {
            $previewId = $this->session()->get('theme_preview_build');
            $preview = $themes->previewBuildFor(is_int($previewId) ? $previewId : null);
            if ($preview !== null) {
                $asset = $assets->findAssetForBuild((int) $preview['id'], $digest);
                if ($asset !== null) {
                    return new Response((string) $asset['bytes'], 200, $this->previewHeaders((string) $asset['mime'], $digest));
                }
            }
        }

        throw new NotFoundException();
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

    /** @return array<string,string> */
    private function previewHeaders(string $contentType, string $digest): array
    {
        return [
            'Content-Type' => $contentType,
            'Cache-Control' => 'private, no-store',
            'ETag' => '"' . $digest . '"',
        ];
    }
}
