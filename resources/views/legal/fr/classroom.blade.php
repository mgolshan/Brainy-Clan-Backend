@extends('legal.layout')

@section('title', 'Conditions du forfait Classe et déclaration de consentement')
@section('other_document', 'Conditions d’utilisation')

@section('content')
    <p><span class="review">[ÉBAUCHE À FAIRE RÉVISER PAR UN AVOCAT — cette page décrit le fonctionnement du forfait Classe et doit être révisée par un avocat qualifié (Canada et États-Unis, y compris le droit québécois sur les renseignements personnels et l’usage scolaire) avant le lancement.]</span></p>

    <p>Ces conditions du forfait Classe s’ajoutent à nos Conditions d’utilisation et à notre Politique de confidentialité. Elles s’appliquent aux groupes du forfait Classe, utilisés par des enseignants, des tuteurs et des entraîneurs avec leurs élèves.</p>

    <h2>1. La déclaration de consentement</h2>
    <p>Avant d’ajouter des élèves, l’enseignant qui gère le groupe (ou le co-enseignant) lit et accepte cette déclaration dans l’application :</p>
    <p class="notice"><strong>{{ $statement }}</strong></p>
    <p>Nous conservons qui l’a acceptée, quelle version, quand, et une forme brouillée (hachée) de l’adresse réseau, jamais l’adresse elle-même. Quand la déclaration change, un enseignant accepte la nouvelle version avant d’utiliser à nouveau les outils du forfait Classe.</p>

    <h2>2. Qui gère une Classe</h2>
    <ul>
        <li>Le propriétaire d’un groupe Classe doit avoir au moins {{ $ownerMinAge }} ans.</li>
        @if ($maxAdults !== null)
        <li>Une Classe compte {{ $maxAdults }} places d’adulte : l’enseignant et un co-enseignant. Seuls les adultes peuvent être administrateurs.</li>
        @endif
        <li>L’enseignant est responsable des noms et des années de naissance qu’il saisit et de la garde des cartes de codes de connexion imprimées.</li>
    </ul>

    <h2>3. Les élèves</h2>
    <ul>
        <li>Les élèves sont ajoutés avec un surnom et une année de naissance seulement. Nous ne demandons ni l’adresse courriel, ni le nom complet, ni l’école, ni la photo d’un élève. Utilisez des prénoms ou des surnoms, pas des noms complets.</li>
        <li>Les élèves se connectent avec une carte de code à usage unique. Une carte fonctionne une fois et expire après {{ $cardDays }} jours. Imprimer de nouvelles cartes remplace les anciennes.</li>
        <li>Comme la déclaration de l’enseignant couvre la classe, l’application ne demande pas de consentement parental distinct pour chaque élève. L’enseignant conserve toute autorisation exigée par son école ou par les parents.</li>
        <li>Les élèves voient des quiz et des casse-têtes adaptés à leur âge, et ne voient jamais de publicité ni d’écran d’achat.</li>
    </ul>

    <h2>4. Le résumé de classe</h2>
    <p>L’enseignant peut télécharger un résumé de classe (surnoms, points, parties, précision et séries). Il est responsable de la sécurité de ce fichier et ne le partage que de la façon permise par son école.</p>

    <h2>5. La fin de l’année scolaire</h2>
    <p>L’enseignant peut archiver l’année et en commencer une nouvelle : retirer les élèves, commencer une nouvelle saison de points, supprimer la discussion et supprimer les objectifs. Retirer un élève efface son compte (il n’a pas de connexion à lui). Une nouvelle saison de points ne supprime aucun point : les totaux de la classe comptent simplement à partir de la nouvelle saison. C’est irréversible; l’application propose donc d’abord le résumé de classe.</p>

    <h2>6. Contact</h2>
    <p>Questions sur le forfait Classe ou les données d’un élève : {{ $contact['email'] }}. Un parent ou tuteur peut aussi demander à l’enseignant de retirer son enfant du groupe.</p>
@endsection
