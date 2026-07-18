<?php

declare(strict_types=1);

namespace App\Controller;

use App\Core\FeatureFlags;
use App\Core\ForbiddenException;
use App\Core\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Core\ValidationException;
use App\Repository\BoardMemberRepository;
use App\Repository\FollowRepository;
use App\Repository\TagRepository;
use App\Service\TagService;
use App\Repository\ThreadRepository;
use App\Security\AuthorityGate;
use App\Security\BoardPolicy;
use App\Security\Cap;
use App\Security\WriteGate;
use App\Service\FollowService;
use App\Support\Str;

final class TagController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requireTags();
        $user = $this->currentUser();
        return $this->view('tags/index', [
            'tags' => $this->container->get(TagRepository::class)->catalogForViewer($user?->id() ?? 0, $user?->isAdmin() ?? false),
        ]);
    }

    /** @param array<string,string> $params */
    public function show(Request $request, array $params): Response
    {
        $this->requireTags();
        $tag = $this->container->get(TagRepository::class)->findBySlug((string) ($params['slug'] ?? ''));
        if ($tag === null || (int) ($tag['is_enabled'] ?? 0) !== 1 || (string) ($tag['visibility'] ?? 'public') !== 'public') {
            throw new NotFoundException('Tag not found.');
        }
        $user = $this->currentUser();
        $viewerId = $user?->id() ?? 0;
        $isAdmin = $user?->isAdmin() ?? false;
        $perPage = (int) $this->config()->get('pagination.threads_per_page', 20);
        $repo = $this->container->get(TagRepository::class);
        $total = $repo->countThreadsForTag((int) $tag['id'], $viewerId, $isAdmin);
        $pages = max(1, (int) ceil(max(1, $total) / $perPage));
        $page = min($pages, max(1, $request->int('page', 1)));

        $following = false;
        if ($user !== null) {
            $following = $this->container->get(FollowRepository::class)
                ->isFollowingTarget($user->id(), 'tag', (int) $tag['id']);
        }

        return $this->view('tags/show', [
            'tag' => $tag,
            'threads' => $repo->threadsForTag((int) $tag['id'], $viewerId, $isAdmin, $perPage, ($page - 1) * $perPage),
            'page' => $page,
            'pages' => $pages,
            'following' => $following,
            'expanded_feeds' => $this->container->get(FeatureFlags::class)->enabled('expanded_feeds'),
        ]);
    }

    /** @param array<string,string> $params */
    public function follow(Request $request, array $params): Response
    {
        $this->requireTags();
        if (!$this->container->get(FeatureFlags::class)->enabled('expanded_feeds')) {
            throw new NotFoundException('Not found.');
        }
        $user = $this->requireUser();
        $tag = $this->container->get(TagRepository::class)->findBySlug((string) ($params['slug'] ?? ''));
        if ($tag === null || (int) ($tag['is_enabled'] ?? 0) !== 1 || (string) ($tag['visibility'] ?? 'public') !== 'public') {
            throw new NotFoundException('Tag not found.');
        }
        $following = $this->container->get(FollowService::class)->toggleTarget($user, 'tag', (int) $tag['id']);
        return $this->redirectWithFlash('/tags/' . (string) $tag['slug'], $following ? 'Tag followed.' : 'Tag unfollowed.');
    }

    public function admin(Request $request): Response
    {
        $this->requireTags();
        $this->requireAdmin();
        return $this->renderAdminTags();
    }

    public function create(Request $request): Response
    {
        $this->requireTags();
        $admin = $this->requireAdmin();
        try {
            [$slug, $name, $description] = $this->validateTag($request);
            $this->container->get(TagRepository::class)->create($slug, $name, $description, $admin->id());
        } catch (ValidationException $e) {
            return $this->renderAdminTags(422, $e->errors, $this->oldTagInput($request), 'create');
        } catch (\PDOException) {
            return $this->renderAdminTags(422, ['slug' => 'That tag slug is already in use.'], $this->oldTagInput($request), 'create');
        }
        return $this->redirectWithFlash('/admin/tags', 'Tag created.');
    }

    /** @param array<string,string> $params */
    public function update(Request $request, array $params): Response
    {
        $this->requireTags();
        $this->requireAdmin();
        $id = (int) ($params['id'] ?? 0);
        if ($this->container->get(TagRepository::class)->find($id) === null) {
            throw new NotFoundException('Tag not found.');
        }
        try {
            [$slug, $name, $description] = $this->validateTag($request);
            $enabled = $request->post('enabled') !== null;
            $visibility = (string) $request->post('visibility', 'public');
            $this->container->get(TagRepository::class)->update($id, $slug, $name, $description, $enabled, $visibility);
        } catch (ValidationException $e) {
            return $this->renderAdminTags(422, $e->errors, $this->oldTagInput($request, $id), 'update');
        } catch (\PDOException) {
            return $this->renderAdminTags(422, ['slug' => 'That tag slug is already in use.'], $this->oldTagInput($request, $id), 'update');
        }
        return $this->redirectWithFlash('/admin/tags', 'Tag updated.');
    }

    /**
     * Confirmation page for a tag merge (GET). Merging retags every thread and
     * deletes the source tag — irreversible mass-retagging — so it shows the
     * affected-thread count before the POST (ADMIN §9.4 "show impact").
     *
     * @param array<string,string> $params
     */
    public function mergeConfirm(Request $request, array $params): Response
    {
        $this->requireTags();
        $this->requireAdmin();
        try {
            $impact = $this->container->get(TagService::class)
                ->mergeImpact((int) ($params['id'] ?? 0), (int) $request->int('target_id', 0));
        } catch (ValidationException) {
            return $this->redirectWithFlash('/admin/tags', 'Choose a different target tag to merge into.');
        }

        return $this->view('admin/tag_merge_confirm', [
            'source' => $impact['source'],
            'target' => $impact['target'],
            // The honest impact: every association the merge will move.
            'association_count' => $impact['associations'],
        ]);
    }

    /** @param array<string,string> $params */
    public function merge(Request $request, array $params): Response
    {
        $this->requireTags();
        $this->requireAdmin();
        try {
            $this->container->get(TagService::class)
                ->merge((int) ($params['id'] ?? 0), (int) $request->post('target_id', 0));
        } catch (ValidationException) {
            return $this->redirectWithFlash('/admin/tags', 'Choose a different target tag.');
        }
        return $this->redirectWithFlash('/admin/tags', 'Tag merged.');
    }

    /** @param array<string,string> $params */
    public function updateThread(Request $request, array $params): Response
    {
        $this->requireTags();
        $user = $this->requireUser();
        $threadId = (int) ($params['id'] ?? 0);
        $thread = $this->container->get(ThreadRepository::class)->findWithBoard($threadId);
        if ($thread === null || (int) ($thread['is_deleted'] ?? 0) === 1) {
            throw new NotFoundException('Thread not found.');
        }
        if ((int) ($thread['board_tags_enabled'] ?? 1) !== 1) {
            throw new ForbiddenException('Tags are disabled for this board.');
        }

        $this->container->get(WriteGate::class)->assertCanWrite($user);
        $boardId = (int) $thread['board_id'];
        // An archived board is frozen for everyone — no admin/moderator carve-out.
        if ((int) ($thread['board_is_archived'] ?? 0) === 1) {
            throw new ForbiddenException('This board is archived and is read-only.');
        }
        // Posting rights are the single tagging gate (archive-aware via canPost):
        // no role carve-out, so admins/moderators are bound by the same rules.
        // The capability predicate is the only thing routed through the gate —
        // assertCanWrite/isMember/archived above stay exactly where they were
        // (Phase 5 Inc 6 Task 5 [STATE-KEEP]).
        $isMember = $this->container->get(BoardMemberRepository::class)->isMember($boardId, $user->id());
        $policy = $this->container->get(BoardPolicy::class);
        $gate = $this->container->get(AuthorityGate::class);
        $canTag = $gate->allows(
            fn (): bool => $policy->canPost(
                [
                    'visibility' => $thread['board_visibility'],
                    'post_min_role' => $thread['board_post_min_role'] ?? 'user',
                    'is_archived' => $thread['board_is_archived'] ?? 0,
                ],
                $user,
                $isMember,
            ),
            $user,
            Cap::THREAD_TAG,
            ['board_id' => $boardId],
            'TagController::updateThread',
        );
        if (!$canTag) {
            throw new ForbiddenException('You cannot tag this thread.');
        }

        $tagIds = array_map('intval', (array) $request->post('tag_ids', []));
        $this->container->get(TagRepository::class)->setForThread($threadId, $tagIds, $user->id());
        return $this->redirectWithFlash('/t/' . $threadId . '-' . (string) $thread['slug'], 'Tags updated.');
    }

    private function requireTags(): void
    {
        if (!$this->container->get(FeatureFlags::class)->enabled('tags')) {
            throw new NotFoundException('Not found.');
        }
    }

    /**
     * @param array<string,string> $errors
     * @param array<string,mixed> $old
     */
    private function renderAdminTags(int $status = 200, array $errors = [], array $old = [], ?string $errorForm = null): Response
    {
        return $this->view('admin/tags', [
            'tags' => $this->container->get(TagRepository::class)->allForAdmin(),
            'errors' => $errors,
            'old' => $old,
            'error_form' => $errorForm,
        ], $status);
    }

    /** @return array<string,mixed> */
    private function oldTagInput(Request $request, ?int $id = null): array
    {
        return [
            'id' => $id,
            'name' => $request->str('name'),
            'slug' => $request->str('slug'),
            'description' => $request->str('description'),
            'visibility' => $request->str('visibility') !== '' ? $request->str('visibility') : 'public',
            'enabled' => $request->post('enabled') !== null,
        ];
    }

    /** @return array{0:string,1:string,2:?string} */
    private function validateTag(Request $request): array
    {
        $name = trim((string) $request->str('name'));
        if ($name === '' || mb_strlen($name) > 80) {
            throw new ValidationException(['name' => 'Tag name must be 1-80 characters.']);
        }
        $rawSlug = trim((string) $request->str('slug'));
        $slug = Str::slug($rawSlug !== '' ? $rawSlug : $name, 64);
        $description = trim((string) $request->str('description'));
        return [$slug, $name, $description !== '' ? mb_substr($description, 0, 255) : null];
    }
}
