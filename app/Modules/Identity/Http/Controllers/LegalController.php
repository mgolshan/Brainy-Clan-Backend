<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\AcceptLegalTerms;
use App\Modules\Identity\Actions\ExportUserData;
use App\Modules\Identity\Http\Requests\LegalAcceptanceRequest;
use App\Modules\Identity\Legal\LegalDocuments;
use App\Modules\Identity\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

#[Group('Legal', 'Terms of service, privacy policy, consent and the personal data export.', 9)]
class LegalController
{
    /**
     * Current legal documents.
     *
     * Versions (opaque strings), page URLs and a short localized "what changed" `summary` for the
     * re-acceptance screen. The texts are drafts until reviewed by a lawyer.
     *
     * @unauthenticated
     *
     * @return array{data: array{terms: array{version: string, url: string, summary: string|null}, privacy: array{version: string, url: string, summary: string|null}}}
     */
    #[QueryParameter('lang', 'Language of the summaries and page URLs.', type: 'string', default: 'en', example: 'fr')]
    public function show(Request $request): array
    {
        /** @var array{terms: array{version: string, url: string, summary: string|null}, privacy: array{version: string, url: string, summary: string|null}} $documents */
        $documents = LegalDocuments::describe(LegalDocuments::locale($request->query('lang')));

        return ['data' => $documents];
    }

    /**
     * Accept the current terms and privacy policy.
     *
     * After a version change (`me.legal.needs_acceptance`). Both versions must equal the current
     * ones (422 on the stale field otherwise). Children get 403 `forbidden` (their parent consented).
     */
    public function accept(LegalAcceptanceRequest $request, AcceptLegalTerms $accept): Response
    {
        /** @var User $user */
        $user = $request->user();

        $accept->confirm($user, (string) $request->string('terms_version'), (string) $request->string('privacy_version'), $request->ip());

        return response()->noContent();
    }

    /**
     * Export my data.
     *
     * A JSON download of everything stored about the signed-in user: profile, memberships, points
     * ledger, play sessions, goal participation, consents, devices and linked accounts (never
     * tokens or passwords). Right of access (PIPEDA, Quebec Law 25). Children get 403 `forbidden`
     * (their parent asks support). Rate-limited to 1 per hour (429 `rate_limited`).
     */
    public function export(Request $request, ExportUserData $export): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $filename = 'brainy-clan-export-'.now()->format('Y-m-d').'.json';

        return response()
            ->json(['data' => $export->handle($user)], 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"')
            ->header('Cache-Control', 'no-store, private');
    }
}
