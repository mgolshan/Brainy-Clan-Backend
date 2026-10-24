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
            'version' => '2026-10-24',
            'summary' => [
                'en' => 'New: buying the Family plan on our website through Paddle (merchant of record), refunds for web purchases, and partner referral codes.',
                'fr' => 'Nouveau : achat du forfait Famille sur notre site Web par Paddle (marchand officiel), remboursement des achats sur le Web et codes de parrainage des partenaires.',
            ],
        ],
        'privacy' => [
            'version' => '2026-10-24',
            'summary' => [
                'en' => 'New: family chat (only your family can see it, encrypted, kept 7 days free / 1 year Family plan); web purchases through Paddle (merchant of record) and partner referral codes.',
                'fr' => 'Nouveau : discussion familiale (visible seulement par votre famille, chiffrée, conservée 7 jours gratuit / 1 an forfait Famille); achats sur le Web par Paddle (marchand officiel) et codes de parrainage des partenaires.',
            ],
        ],
    ],

];
