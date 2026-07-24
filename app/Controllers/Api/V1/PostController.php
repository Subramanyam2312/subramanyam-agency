<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Api;
use App\Core\Auth;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Sanitizer;
use App\Core\Sitemap;
use App\Core\Slugger;
use App\Core\Validator;
use App\Models\ApiToken;
use App\Models\Media;
use App\Models\Post;

/**
 * Post endpoints — the ones an external publishing agent actually needs.
 *
 * Deliberately no DELETE. An agent that can create and update can also unpublish
 * by patching status, and a bug in an automated caller should not be able to
 * destroy content. Deletion stays a deliberate human action in the CMS.
 */
final class PostController extends ApiController
{
    public function index(Request $request): Response
    {
        if (($denied = $this->requires($request, ApiToken::ABILITY_READ)) !== null) {
            return $denied;
        }

        $page    = max(1, $request->integer('page', 1));
        $perPage = max(1, min(50, $request->integer('per_page', 20)));

        $filters = [
            'search'      => (string) $request->query('search', ''),
            'status'      => (string) $request->query('status', ''),
            'category_id' => (string) $request->query('category_id', ''),
        ];

        if ($filters['status'] !== '' && !isset(Post::statuses()[$filters['status']])) {
            return Api::validationFailed(['status' => 'Status must be draft, scheduled or published.']);
        }

        $result = Post::adminList($filters, $page, $perPage);

        $items = array_map(
            fn (array $post): array => Api::post($this->withMedia($post), array_column(Post::tagsFor((int) $post['id']), 'name')),
            $result['data']
        );

        return Api::data($items, 200, [
            'page'      => $result['current_page'],
            'per_page'  => $result['per_page'],
            'total'     => $result['total'],
            'last_page' => $result['last_page'],
        ]);
    }

    public function show(Request $request): Response
    {
        if (($denied = $this->requires($request, ApiToken::ABILITY_READ)) !== null) {
            return $denied;
        }

        $post = $this->findWithRelations($request->paramInt('id'));

        if ($post === null) {
            return Api::notFound('No post with that id.');
        }

        return Api::data(Api::post($post, array_column(Post::tagsFor((int) $post['id']), 'name')));
    }

    public function store(Request $request): Response
    {
        if (($denied = $this->requires($request, ApiToken::ABILITY_WRITE)) !== null) {
            return $denied;
        }

        $validator = Validator::make($request->all(), [
            'title'            => 'required|max:200',
            'content'          => 'required',
            'slug'             => 'nullable|max:200',
            'excerpt'          => 'nullable|max:500',
            'status'           => 'nullable|in:draft,scheduled,published',
            'published_at'     => 'nullable|date',
            'category_id'      => 'nullable|integer|exists:categories,id',
            'meta_title'       => 'nullable|max:180',
            'meta_description' => 'nullable|max:300',
            'canonical_url'    => 'nullable|url|max:255',
        ]);

        if ($validator->fails()) {
            return Api::validationFailed($validator->errors());
        }

        $category = $this->resolveCategory($request);

        if ($category instanceof Response) {
            return $category;
        }

        $image = $this->resolveFeaturedImage($request);

        if ($image instanceof Response) {
            return $image;
        }

        $content     = Sanitizer::rich((string) $request->input('content'));
        $contentText = Sanitizer::plain($content);

        [$status, $publishedAt] = $this->resolveSchedule($request);

        $id = Post::create([
            'title'             => trim((string) $request->input('title')),
            'slug'              => Slugger::unique(
                (string) ($request->input('slug') ?: $request->input('title')),
                'posts'
            ),
            'excerpt'           => $this->blankToNull((string) $request->input('excerpt', '')),
            'content'           => $content,
            'content_text'      => $contentText,
            'featured_media_id' => $image,
            'category_id'       => $category,
            'author_id'         => Auth::id(),
            'status'            => $status,
            'published_at'      => $publishedAt,
            'reading_time'      => Sanitizer::readingTime($contentText),
            'is_featured'       => (int) (bool) $request->input('is_featured', false),
            'meta_title'        => $this->blankToNull((string) $request->input('meta_title', '')),
            'meta_description'  => $this->blankToNull((string) $request->input('meta_description', '')),
            'canonical_url'     => $this->blankToNull((string) $request->input('canonical_url', '')),
            'noindex'           => (int) (bool) $request->input('noindex', false),
        ]);

        $this->syncTags($id, $request);

        // An agent publishing a post should get it into the sitemap immediately,
        // exactly as the CMS does.
        Sitemap::generate();

        $this->log('api.post_created', 'posts', $id, ['title' => $request->input('title')]);

        $post = $this->findWithRelations($id);

        return Api::data(
            Api::post((array) $post, array_column(Post::tagsFor($id), 'name')),
            201
        )->header('Location', url('/api/v1/posts/' . $id));
    }

    public function update(Request $request): Response
    {
        if (($denied = $this->requires($request, ApiToken::ABILITY_WRITE)) !== null) {
            return $denied;
        }

        $id       = $request->paramInt('id');
        $existing = Post::find($id);

        if ($existing === null) {
            return Api::notFound('No post with that id.');
        }

        $validator = Validator::make($request->all(), [
            'title'            => 'nullable|max:200',
            'slug'             => 'nullable|max:200',
            'excerpt'          => 'nullable|max:500',
            'status'           => 'nullable|in:draft,scheduled,published',
            'published_at'     => 'nullable|date',
            'category_id'      => 'nullable|integer|exists:categories,id',
            'meta_title'       => 'nullable|max:180',
            'meta_description' => 'nullable|max:300',
            'canonical_url'    => 'nullable|url|max:255',
        ]);

        if ($validator->fails()) {
            return Api::validationFailed($validator->errors());
        }

        $data = [];

        // PATCH semantics: only what was actually sent is touched. Absent keys keep
        // their stored value rather than being nulled out.
        foreach (['title', 'excerpt', 'meta_title', 'meta_description', 'canonical_url'] as $field) {
            if ($request->has($field)) {
                $data[$field] = $this->blankToNull((string) $request->input($field));
            }
        }

        if ($request->has('content')) {
            $content              = Sanitizer::rich((string) $request->input('content'));
            $data['content']      = $content;
            $data['content_text'] = Sanitizer::plain($content);
            $data['reading_time'] = Sanitizer::readingTime($data['content_text']);
        }

        if ($request->has('slug')) {
            $data['slug'] = Slugger::unique((string) $request->input('slug'), 'posts', $id);
        }

        if ($request->has('category_id')) {
            $category = $this->resolveCategory($request);

            if ($category instanceof Response) {
                return $category;
            }

            $data['category_id'] = $category;
        }

        if ($request->has('is_featured')) {
            $data['is_featured'] = (int) (bool) $request->input('is_featured');
        }

        if ($request->has('noindex')) {
            $data['noindex'] = (int) (bool) $request->input('noindex');
        }

        if ($request->has('featured_image') || $request->has('featured_media_id')) {
            $image = $this->resolveFeaturedImage($request);

            if ($image instanceof Response) {
                return $image;
            }

            $data['featured_media_id'] = $image;
        }

        if ($request->has('status') || $request->has('published_at')) {
            [$status, $publishedAt] = $this->resolveSchedule($request, $existing);

            $data['status']       = $status;
            $data['published_at'] = $publishedAt;
        }

        if ($data !== []) {
            Post::updateById($id, $data);
        }

        if ($request->has('tags')) {
            $this->syncTags($id, $request);
        }

        Sitemap::generate();

        $this->log('api.post_updated', 'posts', $id, ['fields' => array_keys($data)]);

        $post = $this->findWithRelations($id);

        return Api::data(Api::post((array) $post, array_column(Post::tagsFor($id), 'name')));
    }

    // ---------------------------------------------------------------- helpers

    /**
     * Works out the status/published_at pair from whatever the caller sent,
     * matching the CMS behaviour exactly so the two cannot drift apart.
     *
     * @param array<string,mixed>|null $existing
     * @return array{0:string,1:?string}
     */
    private function resolveSchedule(Request $request, ?array $existing = null): array
    {
        $status = (string) $request->input('status', $existing['status'] ?? Post::STATUS_DRAFT);

        $publishedAt = $request->has('published_at')
            ? trim((string) $request->input('published_at'))
            : (string) ($existing['published_at'] ?? '');

        if ($status === Post::STATUS_PUBLISHED && $publishedAt === '') {
            $publishedAt = date('Y-m-d H:i:s');
        }

        if ($status === Post::STATUS_SCHEDULED && $publishedAt === '') {
            $status = Post::STATUS_DRAFT;
        }

        if ($status === Post::STATUS_SCHEDULED && $publishedAt !== '' && strtotime($publishedAt) <= time()) {
            $status = Post::STATUS_PUBLISHED;
        }

        return [
            $status,
            $publishedAt === '' ? null : date('Y-m-d H:i:s', (int) strtotime($publishedAt)),
        ];
    }

    /**
     * Accepts either a category id or a category slug, because an agent that knows
     * the site by its URLs should not have to look ids up first.
     *
     * @return int|null|Response
     */
    private function resolveCategory(Request $request): int|null|Response
    {
        if ($request->has('category')) {
            $slug     = (string) $request->input('category');
            $category = Database::selectOne(
                'SELECT `id` FROM `categories` WHERE `slug` = :slug AND `deleted_at` IS NULL',
                [':slug' => $slug]
            );

            if ($category === null) {
                return Api::validationFailed(['category' => "No category with the slug \"{$slug}\"."]);
            }

            return (int) $category['id'];
        }

        $id = $request->input('category_id');

        return $id === null || $id === '' ? null : (int) $id;
    }

    private function syncTags(int $postId, Request $request): void
    {
        $tags = $request->input('tags', []);

        if (is_string($tags)) {
            $tags = explode(',', $tags);
        }

        if (!is_array($tags)) {
            return;
        }

        Post::syncTags($postId, array_map('strval', $tags));
    }

    /**
     * @return array<string,mixed>|null
     */
    private function findWithRelations(int $id): ?array
    {
        $post = Database::selectOne(
            'SELECT p.*, c.name AS category_name, u.name AS author_name
             FROM `posts` p
             LEFT JOIN `categories` c ON c.id = p.category_id
             LEFT JOIN `users` u ON u.id = p.author_id
             WHERE p.id = :id AND p.deleted_at IS NULL',
            [':id' => $id]
        );

        return $post === null ? null : $this->withMedia($post);
    }

    /**
     * @param array<string,mixed> $post
     * @return array<string,mixed>
     */
    private function withMedia(array $post): array
    {
        $post['featured_image'] = null;

        if (!empty($post['featured_media_id'])) {
            $media = Media::find((int) $post['featured_media_id']);

            if ($media !== null) {
                $post['featured_image'] = Api::media($media);
            }
        }

        return $post;
    }

    private function blankToNull(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
