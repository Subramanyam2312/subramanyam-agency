<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Controllers\Controller;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Media;
use App\Models\Post;

final class BlogController extends Controller
{
    private const PER_PAGE = 9;

    public function index(Request $request): Response
    {
        // Resolve anything whose scheduled time has passed before listing, so the
        // blog is correct even if cron has stopped.
        Post::publishDue();

        $search   = trim((string) $request->query('q', ''));
        $page     = max(1, $request->integer('page', 1));
        $category = null;

        if (($slug = (string) $request->param('slug', '')) !== '') {
            $category = Category::findBy('slug', $slug);

            if ($category === null) {
                throw new HttpException(404, 'That category does not exist.');
            }
        }

        $result = $this->query($search, $category === null ? null : (int) $category['id'], $page);

        // A category keeps its own name — it is already a topic phrase. Only the
        // unfiltered index needs help, where "Blog" on its own said nothing.
        $title = $category !== null ? $category['name'] : 'Digital Marketing Blog — SEO & Paid Media';

        return $this->view('site/blog/index', [
            'posts'      => $result['data'],
            'pagination' => $result,
            'categories' => Category::withCounts(),
            'category'   => $category,
            'search'     => $search,
            'meta'       => [
                'title'       => $title,
                'description' => $category !== null
                    ? ($category['description'] ?: 'Posts filed under ' . $category['name'] . '.')
                    : 'Working notes on search, spend and measurement.',
                'canonical'   => $category !== null
                    ? url('/blog/category/' . $category['slug'])
                    : url('/blog'),
            ],
        ]);
    }

    public function show(Request $request): Response
    {
        Post::publishDue();

        $post = Database::selectOne(
            "SELECT p.*, c.name AS category_name, c.slug AS category_slug, u.name AS author_name
             FROM `posts` p
             LEFT JOIN `categories` c ON c.id = p.category_id
             LEFT JOIN `users` u ON u.id = p.author_id
             WHERE p.slug = :slug
               AND p.deleted_at IS NULL
               AND p.status = 'published'
               AND p.published_at <= NOW()
             LIMIT 1",
            [':slug' => (string) $request->param('slug')]
        );

        if ($post === null) {
            throw new HttpException(404, 'That post does not exist.');
        }

        // Fire-and-forget view counter. Deliberately not deduplicated: it is a
        // rough popularity signal for the CMS, not analytics, and making it exact
        // would mean a write plus a session lookup on every read.
        Database::query('UPDATE `posts` SET `views` = `views` + 1 WHERE `id` = :id', [':id' => $post['id']]);

        $featured = $post['featured_media_id'] ? Media::find((int) $post['featured_media_id']) : null;
        $ogMedia  = $post['og_media_id'] ? Media::find((int) $post['og_media_id']) : $featured;

        return $this->view('site/blog/show', [
            'post'     => $post,
            'featured' => $featured,
            'tags'     => Post::tagsFor((int) $post['id']),
            'related'  => $this->related($post),
            'meta'     => [
                'title'       => $post['meta_title'] ?: $post['title'],
                'description' => $post['meta_description'] ?: $post['excerpt'],
                'canonical'   => $post['canonical_url'] ?: url('/blog/' . $post['slug']),
                'noindex'     => (bool) $post['noindex'],
                'og_type'     => 'article',
                'og_image'    => $ogMedia !== null ? url('/' . ltrim((string) $ogMedia['path'], '/')) : null,
            ],
        ]);
    }

    /**
     * RSS 2.0. Kept as a real endpoint rather than a generated file so it can
     * never go stale relative to what is published.
     */
    public function feed(Request $request): Response
    {
        Post::publishDue();

        $posts = Database::select(
            "SELECT `title`, `slug`, `excerpt`, `published_at`
             FROM `posts`
             WHERE `deleted_at` IS NULL AND `status` = 'published' AND `published_at` <= NOW()
             ORDER BY `published_at` DESC
             LIMIT 20"
        );

        $items = '';

        foreach ($posts as $post) {
            $link = url('/blog/' . $post['slug']);

            $items .= "        <item>\n"
                . '            <title>' . htmlspecialchars((string) $post['title'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</title>\n"
                . '            <link>' . htmlspecialchars($link, ENT_XML1, 'UTF-8') . "</link>\n"
                . '            <guid isPermaLink="true">' . htmlspecialchars($link, ENT_XML1, 'UTF-8') . "</guid>\n"
                . '            <pubDate>' . date(DATE_RSS, (int) strtotime((string) $post['published_at'])) . "</pubDate>\n"
                . '            <description>' . htmlspecialchars((string) $post['excerpt'], ENT_XML1 | ENT_QUOTES, 'UTF-8') . "</description>\n"
                . "        </item>\n";
        }

        $siteName = htmlspecialchars((string) config('app.name'), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n"
            . "    <channel>\n"
            . "        <title>{$siteName}</title>\n"
            . '        <link>' . htmlspecialchars(url('/blog'), ENT_XML1, 'UTF-8') . "</link>\n"
            . "        <description>Working notes on search, spend and measurement.</description>\n"
            . "        <language>en</language>\n"
            . '        <atom:link href="' . htmlspecialchars(url('/feed.xml'), ENT_XML1, 'UTF-8') . '" rel="self" type="application/rss+xml"/>' . "\n"
            . $items
            . "    </channel>\n"
            . '</rss>';

        return Response::make($xml)->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    /**
     * @return array{data:array<int,array<string,mixed>>,total:int,per_page:int,current_page:int,last_page:int}
     */
    private function query(string $search, ?int $categoryId, int $page): array
    {
        $where  = ["p.deleted_at IS NULL", "p.status = 'published'", 'p.published_at <= NOW()'];
        $params = [];

        if ($search !== '') {
            // Distinct placeholders per column — a named placeholder cannot be
            // reused under native prepared statements (HY093 otherwise).
            $where[]        = '(p.title LIKE :q_t OR p.excerpt LIKE :q_e OR p.content_text LIKE :q_c)';
            $like           = '%' . $search . '%';
            $params[':q_t'] = $like;
            $params[':q_e'] = $like;
            $params[':q_c'] = $like;
        }

        if ($categoryId !== null) {
            $where[]         = 'p.category_id = :cat';
            $params[':cat']  = $categoryId;
        }

        $clause = 'WHERE ' . implode(' AND ', $where);

        $total  = (int) Database::scalar("SELECT COUNT(*) FROM `posts` p {$clause}", $params);
        $last   = max(1, (int) ceil($total / self::PER_PAGE));
        $page   = max(1, min($page, $last));
        $offset = ($page - 1) * self::PER_PAGE;

        return [
            'data' => Database::select(
                "SELECT p.id, p.title, p.slug, p.excerpt, p.published_at, p.reading_time, p.featured_media_id,
                        c.name AS category_name, c.slug AS category_slug
                 FROM `posts` p
                 LEFT JOIN `categories` c ON c.id = p.category_id
                 {$clause}
                 ORDER BY p.published_at DESC
                 LIMIT " . self::PER_PAGE . " OFFSET {$offset}",
                $params
            ),
            'total'        => $total,
            'per_page'     => self::PER_PAGE,
            'current_page' => $page,
            'last_page'    => $last,
        ];
    }

    /**
     * Same category first, then anything recent, never the post itself.
     *
     * @param array<string,mixed> $post
     * @return array<int,array<string,mixed>>
     */
    private function related(array $post): array
    {
        return Database::select(
            "SELECT p.title, p.slug, p.excerpt, p.published_at, p.reading_time, c.name AS category_name
             FROM `posts` p
             LEFT JOIN `categories` c ON c.id = p.category_id
             WHERE p.deleted_at IS NULL
               AND p.status = 'published'
               AND p.published_at <= NOW()
               AND p.id != :id
             ORDER BY (p.category_id <=> :category) DESC, p.published_at DESC
             LIMIT 3",
            [':id' => $post['id'], ':category' => $post['category_id']]
        );
    }
}
