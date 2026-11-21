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
    'support_email' => env('LEGAL_SUPPORT_EMAIL', 'support@'.env('CENTRAL_DOMAIN', 'brainyclan.com')),

    /*
     * Contact shown on the pages (privacy officer / person in charge of personal information,
     * Quebec Law 25). Placeholders until the company details are final.
     */
    'contact' => [
        'company' => env('LEGAL_COMPANY_NAME', '[Company legal name — to be completed]'),
        'email' => env('LEGAL_CONTACT_EMAIL', 'support@'.env('CENTRAL_DOMAIN', 'brainyclan.com')),
        'address' => env('LEGAL_POSTAL_ADDRESS', '[Postal address — to be completed]'),
        'privacy_officer' => env('LEGAL_PRIVACY_OFFICER', '[Name and title of the person in charge of personal information — to be completed]'),
    ],

    /*
     * The Classroom consent statement (docs/API.md "Classroom tools" → "Consent statement"): a
     * teacher accepts it before adding students; the page `/legal/classroom` holds it with the
     * Classroom terms (draft — the lawyer reviews it before launch). Bump `version` when the
     * statement changes: teachers then accept again before using the Classroom tools.
     */
    'classroom_consent' => [
        'version' => '2026-11-20',
        'statement' => [
            'en' => 'I’m a teacher, tutor or coach, and I am authorised by my school or organisation, or I have the parents’ or guardians’ consent, to add these students and their progress to Brainy Clan.',
            'fr' => 'Je suis enseignant, tuteur ou entraîneur, et je suis autorisé par mon école ou mon organisme, ou j’ai le consentement des parents ou tuteurs, pour ajouter ces élèves et leurs progrès à Brainy Clan.',
        ],
    ],

    'documents' => [
        'terms' => [
            'version' => '2026-11-20',
            'summary' => [
                'en' => 'New: three paid plans (Family, Big Clan and Classroom) with the same features and different member limits, and how to change plans.',
                'fr' => 'Nouveau : trois forfaits payants (Famille, Grand Clan et Classe) avec les mêmes fonctions et des limites de membres différentes, et comment changer de forfait.',
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
