<?php

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Family\Models\Family;
use App\Modules\Identity\Legal\LegalDocuments;
use App\Modules\Policy\PolicyResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Public HTML pages `/terms` and `/privacy` on the website (`?lang=fr` for French), opened by the app
 * in a browser view and linked from the store listings. Not part of `/api/v1`.
 */
class LegalPageController
{
    public function terms(Request $request): Response
    {
        return $this->page($request, LegalDocuments::TERMS);
    }

    public function privacy(Request $request): Response
    {
        return $this->page($request, LegalDocuments::PRIVACY);
    }

    /**
     * `/legal/classroom`: the Classroom terms and the teacher's consent statement (docs/API.md
     * "Classroom tools" → "Consent statement"); draft until the lawyer reviewed it.
     */
    public function classroom(Request $request): Response
    {
        $locale = LegalDocuments::locale($request->query('lang'));
        $policy = app(PolicyResolver::class);

        return response()->view("legal.{$locale}.classroom", [
            'locale' => $locale,
            'document' => 'classroom',
            'version' => LegalDocuments::classroomConsentVersion(),
            'statement' => LegalDocuments::classroomConsentStatement($locale),
            'draft' => LegalDocuments::isDraft(),
            'contact' => (array) config('legal.contact'),
            'ownerMinAge' => $policy->classroomOwnerMinAge(),
            'maxAdults' => $policy->maxAdultsFor(Family::TIER_CLASSROOM),
            'cardDays' => $policy->signInCodeCardTtlDays(Family::TIER_CLASSROOM),
            'otherDocumentUrl' => LegalDocuments::url(LegalDocuments::TERMS, $locale),
            'otherLocaleUrl' => LegalDocuments::classroomConsentUrl($locale === 'fr' ? 'en' : 'fr'),
        ]);
    }

    private function page(Request $request, string $document): Response
    {
        $locale = LegalDocuments::locale($request->query('lang'));

        return response()->view("legal.{$locale}.{$document}", [
            'locale' => $locale,
            'document' => $document,
            'version' => LegalDocuments::version($document),
            'draft' => LegalDocuments::isDraft(),
            'contact' => (array) config('legal.contact'),
            'otherDocumentUrl' => LegalDocuments::url($document === LegalDocuments::TERMS ? LegalDocuments::PRIVACY : LegalDocuments::TERMS, $locale),
            'otherLocaleUrl' => LegalDocuments::url($document, $locale === 'fr' ? 'en' : 'fr'),
        ]);
    }
}
