<?php

/*
 * Legal documents (docs/API.md "Legal, consent and privacy (B9)").
 *
 * The texts live in resources/views/legal/{en,fr}/{terms,privacy}.blade.php. When a text changes
 * in a way users must agree to again, bump its `version` (an opaque string, by convention the
 * publication date) and write a short `summary` of what changed: every non-child user whose last
 * accepted version differs then gets `me.legal.needs_acceptance: true` and the app asks again.
 *
 * LEGAL_DRAFT=true shows a "Draft — under legal review" banner on the pages. The texts are drafts
 * written for the app and MUST be reviewed by a lawyer before store submission; set
 * LEGAL_DRAFT=false only after that review.
 */

return [

    'draft' => (bool) env('LEGAL_DRAFT', true),

    /*
     * Support address shown on the website (/support, footer) and the store listings.
     */
    'support_email' => env('LEGAL_SUPPORT_EMAIL', 'support@brainyclan.com'),

    /*
     * Contact shown on the pages (privacy officer / person in charge of personal information,
     * Quebec Law 25). Placeholders until the company details are final.
     */
    'contact' => [
        'company' => env('LEGAL_COMPANY_NAME', '[Company legal name — to be completed]'),
        'email' => env('LEGAL_CONTACT_EMAIL', 'support@brainyclan.com'),
        'address' => env('LEGAL_POSTAL_ADDRESS', '[Postal address — to be completed]'),
        'privacy_officer' => env('LEGAL_PRIVACY_OFFICER', '[Name and title of the person in charge of personal information — to be completed]'),
    ],

    'documents' => [
        'terms' => [
            'version' => '2026-10-16',
            'summary' => [
                'en' => 'First version of the Brainy Clan terms of service.',
                'fr' => 'Première version des conditions d’utilisation de Brainy Clan.',
            ],
        ],
        'privacy' => [
            'version' => '2026-10-23',
            'summary' => [
                'en' => 'New: family chat — what we store, who can see it (only your family), encryption and how long messages are kept (7 days free, 1 year Family plan).',
                'fr' => 'Nouveau : discussion familiale — ce que nous conservons, qui peut la voir (seulement votre famille), le chiffrement et la durée de conservation des messages (7 jours gratuit, 1 an forfait Famille).',
            ],
        ],
    ],

];
