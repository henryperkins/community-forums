<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Repository\SettingRepository;

final class SlashGiphyController extends Controller
{
    /** @param array<string,string> $params */
    public function pickerConfig(Request $request, array $params): Response
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('slash_giphy')) {
            throw new NotFoundException('Not found.');
        }
        $settings = $this->container->get(SettingRepository::class);
        $key = $settings->getString('giphy_public_key', (string) $this->config()->get('giphy.public_key', ''));
        if ($key === '') {
            return Response::json(['ok' => false, 'enabled' => false], 404);
        }
        $rating = $settings->getString('giphy_rating', (string) $this->config()->get('giphy.rating', 'pg'));
        if (!in_array($rating, ['g', 'pg', 'pg-13'], true)) {
            $rating = 'pg';
        }
        return Response::json([
            'ok' => true,
            'enabled' => true,
            'provider' => 'giphy',
            'public_key' => $key,
            'rating' => $rating,
            'attribution' => 'Powered by GIPHY',
            'direct_media_only' => true,
            'server_proxy' => false,
            'allowed_inserts' => ['table', 'task_list', 'poll', 'custom_emoji', 'giphy'],
        ]);
    }
}
