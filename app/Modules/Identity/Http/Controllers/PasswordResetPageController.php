<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\ResetPasswordWithToken;
use App\Modules\Identity\Http\Requests\PasswordResetPageRequest;
use App\Modules\Identity\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Web page `/password/reset?token=…` opened from the email of `POST /auth/password/forgot`
 * (docs/API.md Auth): asks for the new password twice. Language: `?lang=` (the emailed link
 * carries it), else the user's locale, else English; the page links to the other language.
 * Invalid, used, replaced or expired links get a page explaining how to ask for a new one.
 */
class PasswordResetPageController
{
    public function show(Request $request, ResetPasswordWithToken $reset): Response
    {
        $token = $this->token($request->query('token'));
        $user = $reset->find($token);

        return $this->page($request, $user !== null ? 'form' : 'invalid', $token, $user);
    }

    public function store(PasswordResetPageRequest $request, ResetPasswordWithToken $reset): Response
    {
        $token = $this->token($request->input('token'));
        $user = $reset->handle($token, (string) $request->string('password'));

        return $this->page($request, $user !== null ? 'done' : 'invalid', $token, $user);
    }

    private function page(Request $request, string $state, string $token, ?User $user): Response
    {
        $locale = $this->locale($request, $user);
        $other = $locale === 'fr' ? 'en' : 'fr';

        return response()->view('password.reset', [
            'locale' => $locale,
            'state' => $state,
            'token' => $token,
            'otherLocale' => $other,
            'otherLocaleUrl' => route('password.reset', [...($state === 'form' ? ['token' => $token] : []), 'lang' => $other]),
            'supportEmail' => (string) config('legal.support_email'),
        ])->header('Cache-Control', 'no-store, private');
    }

    private function locale(Request $request, ?User $user): string
    {
        $requested = $request->query('lang');

        if (is_string($requested) && in_array($requested, User::LOCALES, true)) {
            return $requested;
        }

        return $user !== null && in_array($user->locale, User::LOCALES, true) ? $user->locale : 'en';
    }

    private function token(mixed $token): string
    {
        return is_string($token) && strlen($token) <= 255 ? $token : '';
    }
}
