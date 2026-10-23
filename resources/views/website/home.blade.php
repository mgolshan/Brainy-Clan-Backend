{{--
    Landing page (/). Every claim here must stay true to docs/PLAN.md and docs/API.md; the plan
    limits come from the same settings as the app's policy ($plans, WebsiteController::plan).
--}}
@php
    $fr = $locale === 'fr';
    $lang = ['lang' => $locale];
    $categories = $fr ? [
        ['Géographie du monde', 'Capitales, drapeaux, continents et monuments.'],
        ['Sciences et nature', 'Les animaux, l’espace et le corps humain.'],
        ['Cartes de sagesse', 'Habitudes, gentillesse, l’argent et la pensée stoïcienne.'],
        ['Sprint de maths', 'Tables de multiplication, calcul mental et fractions.'],
        ['Sudoku', 'Grilles 4x4 pour les enfants, puis 6x6 et 9x9.'],
        ['Jeux de mots', 'Anagrammes, mots cachés et lettre manquante.'],
        ['Histoire', 'Des Amériques à l’Europe, à l’Asie et à l’Antiquité.'],
        ['Vocabulaire et orthographe', 'Par niveau scolaire, synonymes, expressions et orthographe.'],
    ] : [
        ['World Geography', 'Capitals, flags, continents and landmarks.'],
        ['Science & Nature', 'Animals, space and the human body.'],
        ['Wisdom Cards', 'Habits, kindness, money basics and Stoic thinking.'],
        ['Math Sprint', 'Times tables, mental math and fractions.'],
        ['Sudoku', '4x4 grids for kids, then 6x6 and 9x9.'],
        ['Word Puzzles', 'Anagrams, word search and missing letters.'],
        ['History', 'The Americas, Europe, Asia and the ancient world.'],
        ['Vocabulary & Spelling', 'By grade level, synonyms, idioms and spelling.'],
    ];
    $free = $plans['free'];
    $family = $plans['family'];
    $allCategories = count($categories);
@endphp
@extends('website.layout')

@section('title', $fr ? 'Brainy Clan — Des défis d’apprentissage en famille' : 'Brainy Clan — Family learning challenges')
@section('description', $fr
    ? 'Brainy Clan est une application de quiz et d’apprentissage pour toute la famille : de courts défis adaptés à chaque âge, des objectifs à atteindre ensemble et un espace sûr pour les enfants.'
    : 'Brainy Clan is a quiz and learning app for the whole family: short challenges adapted to every age, goals you reach together, and a safe space for kids.')

@section('content')
    <div class="hero">
        <div class="wrap">
            <div>
                <h1>{{ $fr ? 'Apprendre, jouer et grandir en famille.' : 'Learn, play and grow together as a family.' }}</h1>
                <p class="lead">{{ $fr
                    ? 'De courts défis de quiz et de casse-têtes pour toute la famille, adaptés à l’âge de chacun, avec des objectifs que vous atteignez ensemble.'
                    : 'Short quiz and puzzle challenges for the whole family, adapted to each person’s age, with goals you reach together.' }}</p>
                <ul class="stores" aria-label="{{ $fr ? 'Disponibilité' : 'Availability' }}">
                    <li>{{ $fr ? 'Bientôt sur l’App Store' : 'Coming soon to the App Store' }}</li>
                    <li>{{ $fr ? 'Bientôt sur Google Play' : 'Coming soon to Google Play' }}</li>
                </ul>
            </div>
            <img src="/images/owl.svg" width="260" height="260" alt="{{ $fr ? 'Ollie, la chouette de Brainy Clan' : 'Ollie, the Brainy Clan owl' }}">
        </div>
    </div>

    <section id="what" aria-labelledby="what-title">
        <div class="wrap">
            <h2 id="what-title">{{ $fr ? 'C’est quoi, Brainy Clan ?' : 'What is Brainy Clan?' }}</h2>
            <ul class="grid">
                <li class="card">
                    <h3>{{ $fr ? 'Des défis en famille' : 'Family challenges' }}</h3>
                    <p>{{ $fr
                        ? 'De courtes parties de quiz et de casse-têtes. Chaque bonne réponse rapporte des points, et le classement reste entre vous.'
                        : 'Short rounds of quizzes and puzzles. Every correct answer earns points, and the leaderboard stays inside your family.' }}</p>
                </li>
                <li class="card">
                    <h3>{{ $fr ? 'Des objectifs ensemble' : 'Goals together' }}</h3>
                    <p>{{ $fr
                        ? 'Fixez un objectif pour la journée, la semaine ou le mois : affrontez-vous ou faites équipe vers un total commun, avec une récompense que vous choisissez.'
                        : 'Set a goal for the day, week or month: compete for the top spot or team up toward a shared total, with a reward you choose.' }}</p>
                </li>
                <li class="card">
                    <h3>{{ $fr ? 'Adapté à chaque âge' : 'Adapted to every age' }}</h3>
                    <p>{{ $fr
                        ? 'La difficulté, les contenus et l’apparence s’adaptent à l’âge : ludique pour les enfants, épuré pour les ados, classique avec un texte plus grand pour les aînés.'
                        : 'Difficulty, content and look adapt to each age: playful for kids, sleek for teens, classic with larger text for seniors.' }}</p>
                </li>
                <li class="card">
                    <h3>{{ $fr ? 'En français et en anglais' : 'In English and French' }}</h3>
                    <p>{{ $fr
                        ? 'Chaque membre choisit sa langue. Les questions et les cartes sont vérifiées par une personne avant d’être publiées.'
                        : 'Each member picks their language. Questions and cards are checked by a person before they are published.' }}</p>
                </li>
            </ul>
        </div>
    </section>

    <section id="categories" class="alt" aria-labelledby="categories-title">
        <div class="wrap">
            <h2 id="categories-title">{{ $fr ? $allCategories.' catégories pour commencer' : $allCategories.' categories to start with' }}</h2>
            <ul class="grid">
                @foreach ($categories as [$name, $description])
                    <li class="card">
                        <h3>{{ $name }}</h3>
                        <p>{{ $description }}</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section id="safety" aria-labelledby="safety-title">
        <div class="wrap">
            <h2 id="safety-title">{{ $fr ? 'Pensé pour les enfants' : 'Safe for kids' }}</h2>
            <ul class="checks">
                <li>{{ $fr ? 'Aucune publicité pour les enfants, jamais.' : 'No ads for children, ever.' }}</li>
                <li>{{ $fr
                    ? 'Les parents décident : un parent invite chaque enfant, donne son consentement et gère la famille.'
                    : 'Parents are in control: a parent invites each child, gives consent and manages the family.' }}</li>
                <li>{{ $fr
                    ? 'Pas de clavardage ni de contact avec des inconnus : votre famille est un espace privé.'
                    : 'No chat and no contact with strangers: your family is a private space.' }}</li>
                <li>{{ $fr
                    ? 'Le minimum de données : pour un enfant, un surnom, un avatar, l’année et le mois de naissance. Pas de courriel, pas de mot de passe, pas de photo.'
                    : 'Minimal data: for a child, a nickname, an avatar and a birth year and month. No email, no password, no photo.' }}</li>
                <li>{{ $fr
                    ? 'Les ados et adultes du forfait gratuit peuvent choisir de regarder une courte pub pour une seconde chance; ces pubs ne sont jamais personnalisées et sont classées pour tous.'
                    : 'Teens and adults on the free plan can choose to watch a short ad for a second chance; those ads are never personalized and are rated for general audiences.' }}</li>
                <li>{{ $fr
                    ? 'Vous pouvez télécharger vos données ou supprimer votre compte en tout temps.'
                    : 'You can download your data or delete your account at any time.' }}</li>
            </ul>
        </div>
    </section>

    <section id="plans" class="alt" aria-labelledby="plans-title">
        <div class="wrap">
            <h2 id="plans-title">{{ $fr ? 'Forfaits' : 'Plans' }}</h2>
            <div class="plans">
                <div class="card plan">
                    <h3>{{ $fr ? 'Gratuit' : 'Free' }}</h3>
                    <ul>
                        <li>{{ $fr ? 'Jusqu’à '.$free['members'].' membres de la famille, vous compris' : 'Up to '.$free['members'].' family members, you included' }}</li>
                        <li>{{ $free['categories'] >= $allCategories
                            ? ($fr ? 'Toutes les catégories' : 'All categories')
                            : ($fr ? $free['categories'].' catégories' : $free['categories'].' categories') }}</li>
                        <li>{{ $free['goals'] === null
                            ? ($fr ? 'Objectifs familiaux illimités' : 'Unlimited family goals')
                            : ($fr ? $free['goals'].' objectif familial actif' : $free['goals'].' active family goal'.($free['goals'] === 1 ? '' : 's')) }}</li>
                        <li>{{ $fr
                            ? 'Seconde chance : une ronde de rattrapage, ou une courte pub (ados et adultes seulement)'
                            : 'Second chance: a redemption round, or a short ad (teens and adults only)' }}</li>
                    </ul>
                </div>
                <div class="card plan featured">
                    <h3>{{ $fr ? 'Forfait Famille' : 'Family plan' }}</h3>
                    <ul>
                        <li>{{ $fr ? 'Jusqu’à '.$family['members'].' membres de la famille, vous compris' : 'Up to '.$family['members'].' family members, you included' }}</li>
                        <li>{{ $family['categories'] >= $allCategories
                            ? ($fr ? 'Les '.$allCategories.' catégories' : 'All '.$allCategories.' categories')
                            : ($fr ? $family['categories'].' catégories' : $family['categories'].' categories') }}</li>
                        <li>{{ $family['goals'] === null
                            ? ($fr ? 'Objectifs familiaux illimités' : 'Unlimited family goals')
                            : ($fr ? $family['goals'].' objectifs familiaux actifs' : $family['goals'].' active family goals') }}</li>
                        @if ($family['free_second_chances'] > 0)
                            <li>{{ $fr
                                ? $family['free_second_chances'].' secondes chances gratuites par membre et par jour'
                                : $family['free_second_chances'].' free second chances per member per day' }}</li>
                        @endif
                        @unless ($family['ads'])
                            <li>{{ $fr ? 'Aucune publicité' : 'No ads' }}</li>
                        @endunless
                    </ul>
                    <p class="note">{{ $fr
                        ? 'Un abonnement acheté par un adulte dans l’application couvre toute la famille.'
                        : 'One subscription, bought by an adult in the app, covers the whole family.' }}</p>
                </div>
            </div>
        </div>
    </section>

    <section id="get" aria-labelledby="get-title">
        <div class="wrap">
            <h2 id="get-title">{{ $fr ? 'Bientôt disponible' : 'Coming soon' }}</h2>
            <p>{{ $fr
                ? 'Brainy Clan arrive bientôt sur iPhone, iPad et Android. Des questions ?'
                : 'Brainy Clan is coming soon to iPhone, iPad and Android. Questions?' }}
                <a href="{{ route('website.support', $lang) }}">{{ $fr ? 'Visitez la page d’aide' : 'Visit the support page' }}</a>.</p>
        </div>
    </section>
@endsection
