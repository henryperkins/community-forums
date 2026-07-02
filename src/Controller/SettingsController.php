<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\Request;
use App\Core\Response;
use App\Repository\BoardRepository;
use App\Repository\CategoryRepository;
use App\Repository\SessionRepository;
use App\Repository\SubscriptionRepository;
use App\Repository\UserBoardPrefRepository;
use App\Repository\UserPreferenceRepository;
use App\Repository\UserRepository;
use App\Security\BoardPolicy;
use App\Service\AccountService;
use App\Service\EmailPreferenceService;
use App\Service\PersonalOrganizationService;
use App\Service\PreferenceService;
use App\Support\PreferenceSchema;

/**
 * Member self-service controls (USER §3–§4, P2-10): privacy, reading/appearance
 * preferences, notification digest + subscriptions, active sessions/devices, and
 * board organization. All are owner-only (requireUser) and CSRF-protected.
 */
final class SettingsController extends Controller
{
    // ---- Privacy ----------------------------------------------------------

    public function privacyForm(Request $request): Response
    {
        $user = $this->requireUser();
        $row = $this->container->get(UserRepository::class)->find($user->id()) ?? [];
        $prefs = $this->container->get(UserPreferenceRepository::class)->get($user->id());
        return $this->view('account/privacy', [
            'errors' => [],
            'row' => $row,
            'prefs' => $prefs,
        ]);
    }

    public function updatePrivacy(Request $request): Response
    {
        $user = $this->requireUser();
        $this->container->get(AccountService::class)->updatePrivacy($user, $request->allInput());
        return $this->redirectWithFlash('/settings/privacy', 'Your privacy settings were saved.');
    }

    // ---- Appearance / reading / composing preferences (P3-01) -------------

    public function appearanceForm(Request $request): Response
    {
        $user = $this->requireUser();
        return $this->view('account/appearance', [
            'prefs' => $this->container->get(PreferenceService::class)->resolved($user->id()),
        ]);
    }

    public function updateAppearance(Request $request): Response
    {
        $user = $this->requireUser();
        $this->container->get(PreferenceService::class)->updateSection($user->id(), 'appearance', $request->allInput());
        return $this->redirectWithFlash('/settings/appearance', 'Your appearance settings were saved.');
    }

    public function preferencesForm(Request $request): Response
    {
        $user = $this->requireUser();
        return $this->view('account/preferences', [
            'prefs' => $this->container->get(PreferenceService::class)->resolved($user->id()),
        ]);
    }

    public function updatePreferences(Request $request): Response
    {
        $user = $this->requireUser();
        $this->container->get(PreferenceService::class)->updateSection($user->id(), 'reading', $request->allInput());
        return $this->redirectWithFlash('/settings/preferences', 'Your reading preferences were saved.');
    }

    public function composingForm(Request $request): Response
    {
        $user = $this->requireUser();
        return $this->view('account/composing', [
            'prefs' => $this->container->get(PreferenceService::class)->resolved($user->id()),
        ]);
    }

    public function updateComposing(Request $request): Response
    {
        $user = $this->requireUser();
        $this->container->get(PreferenceService::class)->updateSection($user->id(), 'composing', $request->allInput());
        return $this->redirectWithFlash('/settings/composing', 'Your composing preferences were saved.');
    }

    public function resetPreferences(Request $request): Response
    {
        $user = $this->requireUser();
        $this->container->get(PreferenceService::class)->reset($user->id());
        return $this->redirectWithFlash('/settings/appearance', 'Your appearance, reading, and composing preferences were reset to defaults.');
    }

    /**
     * Download the user's appearance/reading/composing preferences as a
     * self-describing JSON file (P3-01 Gate A "export of preferences"). Read-only
     * and owner-scoped, so no CSRF token is required for the GET.
     */
    public function exportPreferences(Request $request): Response
    {
        $user = $this->requireUser();
        $payload = [
            'app' => 'RetroBoards',
            'schema_version' => PreferenceSchema::VERSION,
            'exported_at' => gmdate('c'),
            'username' => $user->username(),
            'preferences' => $this->container->get(PreferenceService::class)->export($user->id()),
        ];
        $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        return new Response($json, 200, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="retroboards-preferences.json"',
        ]);
    }

    // ---- Notifications: digest + subscriptions ----------------------------

    public function notificationsForm(Request $request): Response
    {
        $user = $this->requireUser();
        $row = $this->container->get(UserRepository::class)->find($user->id()) ?? [];
        return $this->view('account/notifications', [
            'row' => $row,
            'subscriptions' => $this->container->get(SubscriptionRepository::class)->listForUserWithContext($user->id()),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'pause_all_email' => $this->container->get(EmailPreferenceService::class)->pauseAllEmail($user->id()),
        ]);
    }

    public function updateNotifications(Request $request): Response
    {
        $user = $this->requireUser();

        $tz = trim((string) $request->str('timezone'));
        if ($tz !== '' && !in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            $tz = '';
        }
        $hourRaw = $request->post('digest_hour');
        $hour = ($hourRaw === null || $hourRaw === '') ? null : max(0, min(23, (int) $hourRaw));

        $this->container->get(UserRepository::class)->updateDigest($user->id(), $tz !== '' ? $tz : null, $hour);
        $this->container->get(EmailPreferenceService::class)->setPauseAllEmail($user->id(), $request->post('pause_all_email') === '1');
        return $this->redirectWithFlash('/settings/notifications', 'Notification settings saved.');
    }

    // ---- Active sessions & devices ----------------------------------------

    public function sessions(Request $request): Response
    {
        $user = $this->requireUser();
        return $this->view('account/sessions', [
            'sessions' => $this->container->get(SessionRepository::class)->listActiveForUser($user->id()),
            'current_id' => $this->session()->currentSessionId(),
        ]);
    }

    public function revokeSession(Request $request): Response
    {
        $user = $this->requireUser();
        $sid = (string) $request->post('sid', '');
        if ($sid !== '') {
            $this->container->get(SessionRepository::class)->revokeForUser($sid, $user->id());
        }
        return $this->redirectWithFlash('/settings/sessions', 'That device was signed out.');
    }

    public function revokeOtherSessions(Request $request): Response
    {
        $user = $this->requireUser();
        $current = $this->session()->currentSessionId() ?? '';
        $this->container->get(SessionRepository::class)->revokeOthersForUser($user->id(), $current);
        return $this->redirectWithFlash('/settings/sessions', 'Signed out of all other devices.');
    }

    // ---- Board organization (favorite / mute) -----------------------------

    public function boards(Request $request): Response
    {
        $user = $this->requireUser();
        $featureFlags = $this->container->get(FeatureFlags::class);
        $policy = $this->container->get(BoardPolicy::class);
        $categories = $this->container->get(CategoryRepository::class)->all();
        $allBoards = $this->container->get(BoardRepository::class)->allOrdered();
        $prefs = $this->container->get(UserBoardPrefRepository::class)->forUser($user->id());
        $memberIds = array_flip($this->container->get(\App\Repository\BoardMemberRepository::class)->boardIdsFor($user->id()));

        $groups = [];
        foreach ($categories as $cat) {
            $boards = array_values(array_filter(
                $allBoards,
                fn (array $b): bool => (int) $b['category_id'] === (int) $cat['id']
                    && $policy->isListed($b, $user, isset($memberIds[(int) $b['id']])),
            ));
            if ($boards !== []) {
                $groups[] = ['category' => $cat, 'boards' => $boards];
            }
        }

        $organizationFlags = [
            'board_folders' => $featureFlags->enabled('board_folders'),
            'saved_feeds' => $featureFlags->enabled('saved_feeds'),
            'bookmark_folders' => $featureFlags->enabled('bookmark_folders'),
        ];
        $organization = [
            'board_folders' => [],
            'saved_feeds' => [],
            'bookmark_folders' => [],
            'starred_threads' => [],
        ];
        if (in_array(true, $organizationFlags, true)) {
            $organization = $this->container->get(PersonalOrganizationService::class)->overview($user, $organizationFlags);
        }

        return $this->view('account/boards', [
            'groups' => $groups,
            'prefs' => $prefs,
            'board_folders' => $organization['board_folders'],
            'saved_feeds' => $organization['saved_feeds'],
            'bookmark_folders' => $organization['bookmark_folders'],
            'starred_threads' => $organization['starred_threads'],
        ]);
    }

    public function toggleBoardPref(Request $request): Response
    {
        $user = $this->requireUser();
        $boardId = (int) $request->int('board_id', 0);
        $which = (string) $request->post('pref', '');
        $board = $this->container->get(BoardRepository::class)->find($boardId);
        if ($board === null || !in_array($which, ['favorite', 'mute'], true)) {
            return $this->redirectWithFlash('/settings/boards', 'Invalid board preference.');
        }

        $repo = $this->container->get(UserBoardPrefRepository::class);
        $current = $repo->forUser($user->id())[$boardId] ?? ['is_favorite' => 0, 'is_muted' => 0];
        if ($which === 'favorite') {
            $repo->setFavorite($user->id(), $boardId, ((int) $current['is_favorite']) === 0);
        } else {
            $repo->setMuted($user->id(), $boardId, ((int) $current['is_muted']) === 0);
        }
        return $this->redirectWithFlash('/settings/boards', 'Board preferences updated.');
    }
}
