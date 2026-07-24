<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Core\Api;
use App\Core\Request;
use App\Core\Response;
use App\Models\ApiToken;
use App\Models\Category;
use App\Models\Tag;

/**
 * Read-only taxonomy, so a publishing agent can discover valid category slugs
 * instead of guessing and getting a validation error.
 */
final class TaxonomyController extends ApiController
{
    public function categories(Request $request): Response
    {
        if (($denied = $this->requires($request, ApiToken::ABILITY_READ)) !== null) {
            return $denied;
        }

        $categories = array_map(static fn (array $row): array => [
            'id'          => (int) $row['id'],
            'name'        => $row['name'],
            'slug'        => $row['slug'],
            'description' => $row['description'],
            'post_count'  => (int) $row['post_count'],
        ], Category::withCounts());

        return Api::data($categories);
    }

    public function tags(Request $request): Response
    {
        if (($denied = $this->requires($request, ApiToken::ABILITY_READ)) !== null) {
            return $denied;
        }

        $tags = array_map(static fn (array $row): array => [
            'id'         => (int) $row['id'],
            'name'       => $row['name'],
            'slug'       => $row['slug'],
            'post_count' => (int) $row['post_count'],
        ], Tag::withCounts());

        return Api::data($tags);
    }

    /**
     * Identifies the calling token. Exists so a client can verify credentials and
     * abilities in one call rather than discovering a permissions problem halfway
     * through a publish.
     */
    public function me(Request $request): Response
    {
        $token = $request->apiToken();

        if ($token === null) {
            return Api::error('unauthenticated', 'Missing token.', 401);
        }

        return Api::data([
            'token' => [
                'id'           => (int) $token['id'],
                'name'         => $token['name'],
                'prefix'       => $token['prefix'],
                'abilities'    => is_array($token['abilities'] ?? null)
                    ? $token['abilities']
                    : json_column($token['abilities'] ?? null),
                'expires_at'   => $token['expires_at'],
                'last_used_at' => $token['last_used_at'],
            ],
            'owner' => [
                'name'  => $token['user_name'],
                'email' => $token['user_email'],
                'role'  => $token['user_role'],
            ],
            'site' => [
                'name' => config('app.name'),
                'url'  => config('app.url'),
            ],
        ]);
    }
}
