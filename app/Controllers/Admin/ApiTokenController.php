<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\ActivityLogger;
use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\ApiToken;

/**
 * Issue and revoke API tokens. Admin-only, enforced by RequireAdmin on the routes.
 */
final class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->view('admin/api-tokens/index', [
            'tokens'    => ApiToken::withOwners(),
            'abilities' => ApiToken::abilities(),
            // Shown exactly once, immediately after creation, then gone forever.
            'freshToken' => Session::pull('fresh_api_token'),
        ]);
    }

    public function store(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'name'       => 'required|max:120',
            'expires_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return $this->redirectWithErrors('/admin/api-tokens', $validator->errors(), $request->only(['name']));
        }

        $abilities = (array) $request->input('abilities', []);
        $allowed   = array_keys(ApiToken::abilities());
        $abilities = array_values(array_intersect($abilities, $allowed));

        if ($abilities === []) {
            $this->error('Choose at least one ability — a token that can do nothing is not useful.');

            return $this->redirect('/admin/api-tokens');
        }

        $expiresAt = trim((string) $request->input('expires_at', ''));

        $issued = ApiToken::issue(
            (int) Auth::id(),
            trim((string) $request->input('name')),
            $abilities,
            $expiresAt === '' ? null : date('Y-m-d H:i:s', (int) strtotime($expiresAt))
        );

        ActivityLogger::log('api_token.created', 'api_tokens', $issued['id'], [
            'name'      => $request->input('name'),
            'abilities' => $abilities,
        ]);

        // Flash rather than render directly, so a refresh cannot redisplay it and
        // the value never sits in a URL or in the browser's back cache.
        Session::flash('fresh_api_token', $issued['token']);
        $this->success('Token created. Copy it now — it cannot be shown again.');

        return $this->redirect('/admin/api-tokens');
    }

    public function destroy(Request $request): Response
    {
        $id    = $request->paramInt('id');
        $token = ApiToken::find($id);

        if ($token === null) {
            $this->error('That token no longer exists.');

            return $this->redirect('/admin/api-tokens');
        }

        ApiToken::revoke($id);

        ActivityLogger::log('api_token.revoked', 'api_tokens', $id, ['name' => $token['name']]);
        $this->success('Token revoked. Any client using it will start getting 401s immediately.');

        return $this->redirect('/admin/api-tokens');
    }
}
