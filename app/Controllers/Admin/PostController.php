<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Sanitizer;
use App\Core\SeoAnalyzer;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;

final class PostController extends ResourceController
{
    protected string $model = Post::class;

    protected string $route = '/admin/posts';

    protected string $views = 'admin/posts';

    protected string $singular = 'Post';

    protected string $plural = 'Posts';

    /** Bespoke two-column layout with a publishing sidebar. */
    protected ?string $formView = 'admin/posts/form';

    protected bool $affectsSitemap = true;

    protected ?string $slugColumn = 'slug';

    protected array $searchable = ['title', 'slug', 'excerpt'];

    protected function columns(): array
    {
        return [
            ['key' => 'title', 'label' => 'Title', 'type' => 'primary', 'sub' => 'slug'],
            [
                'key'    => 'status',
                'label'  => 'Status',
                'type'   => 'badge',
                'labels' => Post::statuses(),
                'tones'  => [
                    Post::STATUS_PUBLISHED => 'positive',
                    Post::STATUS_SCHEDULED => 'warning',
                    Post::STATUS_DRAFT     => 'muted',
                ],
            ],
            ['key' => 'category_name', 'label' => 'Category'],
            ['key' => 'seo_score', 'label' => 'SEO', 'type' => 'score'],
            ['key' => 'published_at', 'label' => 'Published', 'type' => 'date'],
            ['key' => 'is_featured', 'label' => 'Featured', 'type' => 'bool'],
        ];
    }

    protected function filters(): array
    {
        return [
            'status'      => ['label' => 'Status', 'options' => Post::statuses()],
            'category_id' => ['label' => 'Category', 'options' => Category::options()],
        ];
    }

    /**
     * Overridden so the list can show category and author names from one joined
     * query instead of a lookup per row.
     */
    protected function listQuery(array $filters, int $page): array
    {
        return Post::adminList($filters, $page, $this->perPage);
    }

    protected function rules(?int $id): array
    {
        return [
            'title'            => 'required|max:200',
            'slug'             => 'nullable|max:200',
            'excerpt'          => 'nullable|max:500',
            'category_id'      => 'nullable|integer|exists:categories,id',
            'status'           => 'required|in:draft,scheduled,published',
            // Required only for scheduled posts; enforced in payload() where the
            // status is known, since a rule string cannot express the dependency.
            'published_at'     => 'nullable|date',
            'meta_title'       => 'nullable|max:180',
            'meta_description' => 'nullable|max:300',
            'canonical_url'    => 'nullable|url|max:255',
            'focus_keyword'    => 'nullable|max:191',
        ];
    }

    protected function payload(Request $request, ?int $id): array
    {
        // Sanitised on the way IN, so what is stored is already safe for the API,
        // the RSS feed and every other consumer.
        $content     = Sanitizer::rich((string) $request->input('content', ''));
        $contentText = Sanitizer::plain($content);

        $status      = (string) $request->input('status', Post::STATUS_DRAFT);
        $publishedAt = trim((string) $request->input('published_at', ''));

        if ($status === Post::STATUS_PUBLISHED && $publishedAt === '') {
            // Publishing without a date means "now".
            $publishedAt = date('Y-m-d H:i:s');
        }

        if ($status === Post::STATUS_SCHEDULED && $publishedAt === '') {
            // Nothing to schedule against — treat as a draft rather than silently
            // publishing it immediately.
            $status = Post::STATUS_DRAFT;
        }

        // A scheduled date in the past is simply already due.
        if ($status === Post::STATUS_SCHEDULED && $publishedAt !== '' && strtotime($publishedAt) <= time()) {
            $status = Post::STATUS_PUBLISHED;
        }

        $focusKeyword   = $this->nullIfBlank((string) $request->input('focus_keyword', ''));
        $slug           = trim((string) $request->input('slug', ''));
        $metaTitle      = $this->nullIfBlank((string) $request->input('meta_title', ''));
        $metaDesc       = $this->nullIfBlank((string) $request->input('meta_description', ''));
        $excerpt        = $this->nullIfBlank((string) $request->input('excerpt', ''));
        $title          = trim((string) $request->input('title'));

        // Cache the SEO score against the same fields the live editor panel grades.
        // The slug may be empty here and finalised later by the ResourceController,
        // so fall back to the title for the URL check rather than scoring a blank.
        $seoScore = SeoAnalyzer::score([
            'focus_keyword'    => (string) $focusKeyword,
            'title'            => $title,
            'meta_title'       => (string) $metaTitle,
            'excerpt'          => (string) $excerpt,
            'meta_description' => (string) $metaDesc,
            'slug'             => $slug !== '' ? $slug : $title,
            'content'          => $content,
        ]);

        $data = [
            'title'             => $title,
            'slug'              => $slug,
            'excerpt'           => $excerpt,
            'content'           => $content,
            'content_text'      => $contentText,
            'featured_media_id' => $this->nullIfBlank((string) $request->input('featured_media_id', '')),
            'og_media_id'       => $this->nullIfBlank((string) $request->input('og_media_id', '')),
            'category_id'       => $this->nullIfBlank((string) $request->input('category_id', '')),
            'status'            => $status,
            'published_at'      => $publishedAt === '' ? null : date('Y-m-d H:i:s', (int) strtotime($publishedAt)),
            'reading_time'      => Sanitizer::readingTime($contentText),
            'is_featured'       => (int) $request->input('is_featured', 0),
            'meta_title'        => $metaTitle,
            'meta_description'  => $metaDesc,
            'canonical_url'     => $this->nullIfBlank((string) $request->input('canonical_url', '')),
            'noindex'           => (int) $request->input('noindex', 0),
            'focus_keyword'     => $focusKeyword,
            'seo_score'         => $seoScore,
        ];

        if ($id === null) {
            $data['author_id'] = Auth::id();
        }

        return $data;
    }

    protected function formData(?array $record): array
    {
        return [
            'categories'    => Category::options(),
            'statuses'      => Post::statuses(),
            'tagNames'      => $record === null ? '' : Post::tagNamesFor((int) $record['id']),
            'featuredMedia' => $this->mediaFor($record['featured_media_id'] ?? null),
            'ogMedia'       => $this->mediaFor($record['og_media_id'] ?? null),
        ];
    }

    protected function afterSave(int $id, Request $request, bool $isNew): void
    {
        $tags = array_filter(array_map('trim', explode(',', (string) $request->input('tags', ''))));

        Post::syncTags($id, $tags);
    }

    /**
     * @return array<string,mixed>|null
     */
    private function mediaFor(mixed $id): ?array
    {
        return $id === null || $id === '' ? null : Media::find((int) $id);
    }

    private function nullIfBlank(string $value): ?string
    {
        return trim($value) === '' ? null : trim($value);
    }
}
