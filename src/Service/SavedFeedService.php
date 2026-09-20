<?php

declare(strict_types=1);
namespace App\Service;

use App\Core\FeatureFlags;
use App\Core\NotFoundException;
use App\Domain\User;
use App\Repository\SavedFeedRepository;
use App\Support\SavedFeedFilter;

final class SavedFeedService
{
    public function __construct(private SavedFeedRepository $feeds, private FeedService $activity, private FeatureFlags $flags) {}

    public function open(User $viewer, int $feedId, int $page = 1): array
    {
        if (!$this->flags->enabled('saved_feeds')) { throw new NotFoundException('Not found.'); }
        $feed = $this->feeds->findOwned($viewer->id(), $feedId) ?? throw new NotFoundException('Saved feed not found.');
        $filter = SavedFeedFilter::parse((string) $feed['filter_json']);
        $result = $filter === null ? ['items' => [], 'page' => max(1, $page), 'has_more' => false]
            : $this->activity->forSavedFeed($viewer, $filter, $page);
        return $result + ['id' => $feedId, 'name' => (string) $feed['name'], 'unavailable' => $filter === null, 'digest_enabled' => (bool) $feed['digest_enabled']];
    }
}
