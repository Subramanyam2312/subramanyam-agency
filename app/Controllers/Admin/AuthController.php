<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\ActivityLogger;
use App\Core\Auth;
use App\Core\Mailer;
use App\Core\RateLimiter;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Models\User;

final class AuthController extends Controller
{
    private const MIN_PASSWORD_LENGTH = 12;

    public function showLogin(Request $request): Response
    {
        if (Auth::check() || Auth::attemptRemember($request)) {
            return $this->redirect('/admin');
        }

        return $this->view('admin/auth/login');
    }

    public function login(Request $request): Response
    {
        $validator = Validator::make($request->all(), [
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->redirectWithErrors('/admin/login', $validator->errors(), $request->only(['email']));
        }

        $result = Auth::attempt(
            (string) $request->input('email'),
            (string) $request->input('password'),
            (bool) $request->input('remember'),
            $request
        );

        if (!$result['ok']) {
            $this->error($result['message']);

            return $this->redirectWithErrors('/admin/login', [], $request->only(['email']));
        }

        // Send them where they were originally headed, if that was an admin page.
        $intended = $this->safeInternalPath(Session::get('intended_url'), '/admin');
        Session::forget('intended_url');

        if (!str_starts_with($intended, '/admin')) {
            $intended = '/admin';
        }

        $this->success('Signed in.');

        return $this->redirect($intended);
    }

    public function logout(Request $request): Response
    {
        Auth::logout($request);

        // Session::destroy() wiped the flash queue, so start a fresh session to
        // carry the confirmation across the redirect.
        Session::start($request->isSecure());
        Session::flash('success', 'Signed out.');

        return $this->redirect('/admin/login');
    }

    public function showForgotPassword(Request $request): Response
    {
        return $this->view('admin/auth/forgot');
    }

    public function sendResetLink(Request $request): Response
    {
        $throttleKey = 'reset:' . $request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, (int) config('security.reset.max_requests', 5))) {
            $this->error('Too many reset requests. Please try again later.');

            return $this->redirect('/admin/forgot-password');
        }

        RateLimiter::hit($throttleKey, (int) config('security.reset.decay_seconds', 3600));

        $validator = Validator::make($request->all(), ['email' => 'required|email']);

        if ($validator->fails()) {
            return $this->redirectWithErrors('/admin/forgot-password', $validator->errors(), $request->only(['email']));
        }

        $email = (string) $request->input('email');
        $user  = User::findByEmail($email);

        if ($user !== null && (int) $user['is_active'] === 1) {
            $token = Auth::createPasswordReset((int) $user['id']);

            Mailer::send(
                (string) $user['email'],
                'Reset your ' . config('app.name') . ' password',
                'password-reset',
                [
                    'name'    => $user['name'],
                    'link'    => url('/admin/reset-password/' . $token),
                    'minutes' => (int) config('security.reset.expires_minutes', 60),
                ],
                ['to_name' => (string) $user['name']]
            );

            ActivityLogger::log('auth.reset_requested', 'user', (int) $user['id'], [], (int) $user['id']);
        }

        // Identical response whether or not the address exists, so the form cannot
        // be used to discover which emails have accounts.
        $this->success('If that email has an account, a reset link is on its way.');

        return $this->redirect('/admin/forgot-password');
    }

    public function showResetPassword(Request $request): Response
    {
        $token = (string) $request->param('token');

        if (Auth::findByResetToken($token) === null) {
            $this->error('That reset link is invalid or has expired. Request a new one.');

            return $this->redirect('/admin/forgot-password');
        }

        return $this->view('admin/auth/reset', ['token' => $token]);
    }

    public function resetPassword(Request $request): Response
    {
        $token = (string) $request->input('token', '');

        $validator = Validator::make($request->all(), [
            'token'    => 'required',
            'password' => 'required|min:' . self::MIN_PASSWORD_LENGTH . '|confirmed',
        ], [
            'password' => 'Password',
        ]);

        if ($validator->fails()) {
            return $this->redirectWithErrors('/admin/reset-password/' . $token, $validator->errors());
        }

        $user = Auth::findByResetToken($token);

        if ($user === null) {
            $this->error('That reset link is invalid or has expired. Request a new one.');

            return $this->redirect('/admin/forgot-password');
        }

        Auth::completePasswordReset((int) $user['id'], (string) $request->input('password'));

        $this->success('Password updated. You can sign in now.');

        return $this->redirect('/admin/login');
    }
}
