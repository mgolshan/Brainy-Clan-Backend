<?php

namespace App\Modules\Family\Http\Controllers;

use App\Modules\Family\Actions\ConfirmAccountDeletionRequest;
use App\Modules\Family\Actions\RequestAccountDeletionByEmail;
use App\Modules\Family\Http\Requests\AccountDeletionPageRequest;
use App\Modules\Identity\Legal\LegalDocuments;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public account deletion page `/account/delete` (Google Play requirement; docs/API.md "Account
 * deletion web page"): how to delete in the app, plus an email form for people without the app.
 * The emailed link opens a confirmation page whose button (a POST, so link scanners can't trigger
 * it) runs the same deletion as `DELETE /me`.
 */
class AccountDeletionPageController
{
    public function show(Request $request): Response
    {
        return $this->page($request, 'form');
    }

    /**
     * Same answer whether or not the email has an account (and for the honeypot).
     */
    public function store(AccountDeletionPageRequest $request, RequestAccountDeletionByEmail $requestDeletion): Response
    {
        if (! $request->isBot()) {
            $requestDeletion->handle((string) $request->string('email'), $this->locale($request), $request->ip());
        }

        return $this->page($request, 'sent');
    }

    public function confirmForm(Request $request, string $token): Response
    {
        return $this->page($request, 'confirm', ['token' => $token]);
    }

    public function confirm(Request $request, string $token, ConfirmAccountDeletionRequest $confirm): Response
    {
        return $this->page($request, $confirm->handle($token));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function page(Request $request, string $state, array $data = []): Response
    {
        return response()->view('account.delete', [
            'locale' => $this->locale($request),
            'state' => $state,
            'contactEmail' => (string) config('legal.contact.email'),
            ...$data,
        ])->header('Cache-Control', 'no-store, private');
    }

    private function locale(Request $request): string
    {
        return LegalDocuments::locale($request->query('lang'));
    }
}
