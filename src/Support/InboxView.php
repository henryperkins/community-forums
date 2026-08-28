<?php

declare(strict_types=1);

namespace App\Support;

use App\Core\Request;

/**
 * Canonical Inbox URL state. Scope answers "which topics?" while order answers
 * "in what sequence?"; keeping the two vocabularies here prevents the GET and
 * bulk-POST paths from drifting back into the retired single filter enum.
 */
final class InboxView
{
    public const SCOPES = [
        'for_you',
        'unread',
        'mentions',
        'replies',
        'watching',
        'assigned',
        'starred',
        'mine',
        'snoozed',
        'needs_answer',
        'decisions',
        'solved',
    ];

    public const ORDERS = ['active', 'newest', 'commended'];

    public const LABELS = [
        'for_you' => 'For You',
        'unread' => 'Unread',
        'mentions' => 'Mentions',
        'replies' => 'Replies',
        'watching' => 'Watching',
        'assigned' => 'Assigned',
        'starred' => 'Starred',
        'mine' => 'Mine',
        'snoozed' => 'Snoozed',
        'needs_answer' => 'Needs Answer',
        'decisions' => 'Decisions',
        'solved' => 'Solved',
    ];

    public const ORDER_LABELS = [
        'active' => ['short' => 'Activity', 'full' => 'latest activity'],
        'newest' => ['short' => 'Newest', 'full' => 'newest first'],
        'commended' => ['short' => 'Commended', 'full' => 'most commended'],
    ];

    public const GROUPS = [
        'Your queue' => ['for_you', 'unread', 'mentions', 'replies', 'watching'],
        'Yours' => ['assigned', 'starred', 'mine', 'snoozed'],
        'Topic state' => ['needs_answer', 'decisions', 'solved'],
    ];

    private const WORKFLOW_SCOPES = ['assigned', 'snoozed', 'needs_answer', 'decisions', 'solved'];

    /**
     * @return array{scope:string,order:string,page:int,scopes:list<string>,legacy:bool}
     */
    public static function resolve(Request $request, bool $workflowEnabled, bool $mentionsEnabled): array
    {
        $scopes = self::availableScopes($workflowEnabled, $mentionsEnabled);
        $rawScope = $request->input('scope');
        $rawOrder = $request->input('order');
        $rawLegacy = $request->input('filter');
        $legacy = $rawScope === null && $rawOrder === null && is_string($rawLegacy) && $rawLegacy !== '';

        $scope = 'for_you';
        $order = 'active';
        if ($legacy) {
            if (in_array($rawLegacy, $scopes, true)) {
                $scope = $rawLegacy;
            } elseif ($rawLegacy === 'newest') {
                $order = 'newest';
            } elseif ($rawLegacy === 'unanswered') {
                $scope = $workflowEnabled ? 'needs_answer' : 'for_you';
                $order = 'newest';
            }
        } else {
            if (is_string($rawScope) && in_array($rawScope, $scopes, true)) {
                $scope = $rawScope;
            }
            if (is_string($rawOrder) && in_array($rawOrder, self::ORDERS, true)) {
                $order = $rawOrder;
            }
        }

        return [
            'scope' => $scope,
            'order' => $order,
            'page' => max(1, $request->int('page', 1)),
            'scopes' => $scopes,
            'legacy' => $legacy,
        ];
    }

    /** @return list<string> */
    public static function availableScopes(bool $workflowEnabled, bool $mentionsEnabled): array
    {
        return array_values(array_filter(
            self::SCOPES,
            static fn (string $scope): bool => ($workflowEnabled || !in_array($scope, self::WORKFLOW_SCOPES, true))
                && ($mentionsEnabled || $scope !== 'mentions'),
        ));
    }

    public static function query(string $scope, string $order, int $page = 1): string
    {
        $query = '/inbox?scope=' . rawurlencode($scope) . '&order=' . rawurlencode($order);
        return $page > 1 ? $query . '&page=' . $page : $query;
    }
}
