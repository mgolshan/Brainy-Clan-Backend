@extends('legal.layout')

@section('title', 'Conditions d’utilisation')
@section('other_document', 'Politique de confidentialité')

@section('content')
    <p><span class="review">[ÉBAUCHE POUR RÉVISION JURIDIQUE — ces conditions décrivent le fonctionnement de l’application et doivent être révisées par un avocat qualifié (Canada et États-Unis, y compris la Loi sur la protection du consommateur du Québec) avant la publication de l’application.]</span></p>

    <p>Les présentes conditions constituent une entente entre vous et {{ $contact['company'] }} (« nous ») pour l’utilisation de l’application et du service Brainy Clan. En créant un compte ou en utilisant l’application, vous acceptez ces conditions et notre Politique de confidentialité.</p>

    <h2>1. Qui peut utiliser Brainy Clan</h2>
    <ul>
        <li>Seuls les adultes (18 ans ou plus, ou l’âge de la majorité là où vous habitez) peuvent créer un compte et une famille.</li>
        <li>Les enfants et les adolescents se joignent à une famille uniquement par une invitation d’un administrateur de la famille. Pour un enfant, l’administrateur confirme qu’il est le parent ou le tuteur légal (ou qu’il agit avec sa permission) et accepte ces conditions et la Politique de confidentialité au nom de l’enfant.</li>
        <li>Les adolescents ayant leur propre connexion acceptent eux-mêmes ces conditions; un parent ou tuteur devrait les lire avec eux.</li>
    </ul>

    <h2>2. Votre famille</h2>
    <p>La personne qui crée une famille en est la propriétaire et l’administratrice. Les administrateurs peuvent inviter et retirer des membres, fixer des objectifs et des récompenses, émettre des codes de connexion et gérer les appareils. Ils sont responsables des invitations qu’ils créent et des renseignements qu’ils saisissent au sujet des membres. Les récompenses associées aux objectifs (par exemple « soirée pizza ») sont des promesses entre membres de la famille, et non des offres de notre part.</p>

    <h2>3. Comptes et sécurité</h2>
    <p>Protégez votre mot de passe et vos appareils. Les codes de connexion sont à usage unique et expirent rapidement; ne les partagez pas hors de votre famille. Avisez-nous si vous croyez que votre compte a été utilisé sans permission. Vous pouvez déconnecter un appareil perdu dans l’application.</p>

    <h2>4. Utilisation acceptable</h2>
    <p>N’utilisez pas le service de façon abusive : aucun outil de triche ni jeu automatisé, aucune tentative d’accès aux données d’autres familles, aucune entrave au service, et aucun surnom, nom de famille ou texte d’objectif offensant. Nous pouvons suspendre les comptes qui enfreignent ces règles.</p>

    <h2>5. Contenu</h2>
    <p>Les questions, casse-têtes, cartes de sagesse et autres contenus sont fournis à des fins d’apprentissage et de divertissement. Nous révisons le contenu avec soin, mais il peut contenir des erreurs; signalez-les-nous. Le contenu et l’application sont protégés par les lois sur la propriété intellectuelle et vous sont concédés sous licence pour un usage personnel et non commercial seulement.</p>

    <h2>6. Abonnements et achats</h2>
    <p>Le forfait gratuit comporte des limites (par exemple le nombre de membres et de catégories). Le forfait Famille est un abonnement à renouvellement automatique acheté par un adulte dans l’App Store d’Apple, sur Google Play ou sur notre site Web (voir « Achat sur le Web » ci-dessous). Le prix, la période de facturation et le renouvellement sont affichés avant l’achat. Pour un achat en boutique, le paiement, le renouvellement, l’annulation et les remboursements sont gérés par la boutique selon ses conditions; vous pouvez annuler en tout temps dans les réglages de votre compte de la boutique, et le forfait reste actif jusqu’à la fin de la période payée. Les enfants et les adolescents ne peuvent pas faire d’achats dans l’application. <span class="review">[Révision juridique : Loi sur la protection du consommateur du Québec — contrats à distance, annulation et divulgation du prix.]</span></p>

    <p><strong>Achat sur le Web (Paddle).</strong> <span class="review">[ÉBAUCHE À FAIRE RÉVISER PAR UN AVOCAT]</span> Un administrateur adulte de la famille peut aussi acheter le forfait Famille sur notre site Web. Les achats sur le Web sont vendus par notre revendeur <strong>Paddle.com</strong>, qui agit comme <strong>marchand officiel (« merchant of record »)</strong> : Paddle encaisse le paiement, perçoit et remet les taxes de vente applicables, émet le reçu et gère la facturation, et l’achat est aussi régi par les conditions d’achat de Paddle (paddle.com/legal/checkout-buyer-terms). Le prix, la période de facturation, l’essai gratuit éventuel et le renouvellement automatique sont affichés avant le paiement. Vous pouvez annuler, changer de carte ou télécharger vos factures en tout temps dans le portail client de Paddle (lien dans l’application et dans les courriels de Paddle); le forfait reste actif jusqu’à la fin de la période payée. Une famille ne peut avoir qu’un seul forfait actif à la fois, acheté soit dans une boutique d’applications, soit sur le Web; un forfait acheté sur le Web débloque le forfait Famille dans l’application sur tous les appareils.</p>
    <p><strong>Remboursement des achats sur le Web.</strong> <span class="review">[ÉBAUCHE À FAIRE RÉVISER PAR UN AVOCAT — confirmer le délai selon la politique de remboursement de Paddle et la Loi sur la protection du consommateur du Québec]</span> Vous pouvez demander le remboursement d’un achat fait sur le Web dans les 14 jours suivant le paiement en répondant à votre reçu de Paddle ou en nous écrivant à <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a>. Paddle traite les remboursements selon ses conditions d’achat. Un essai gratuit n’est pas facturé si vous annulez avant sa fin. Les achats faits dans l’App Store ou sur Google Play sont remboursés par Apple ou Google selon leurs propres politiques. Rien ici ne limite les droits que vous accorde la loi sur la protection du consommateur.</p>
    <p><strong>Codes de parrainage.</strong> <span class="review">[ÉBAUCHE À FAIRE RÉVISER PAR UN AVOCAT]</span> Un administrateur de la famille peut entrer un seul code de parrainage d’un partenaire (par exemple un créateur qui recommande Brainy Clan) dans l’application ou sur notre site Web, avant le premier paiement de la famille. Un code peut donner accès à une offre du partenaire; il ne change jamais le contenu du forfait. Nous l’utilisons seulement pour savoir quel partenaire nous a recommandés et pour lui verser une commission. Les partenaires ne reçoivent jamais le nom ni les coordonnées des familles qu’ils ont recommandées. Après le premier paiement, seul notre service d’assistance peut changer le code.</p>

    <h2>7. Publicité</h2>
    <p>Avec le forfait gratuit, les adolescents et les adultes peuvent choisir de regarder une publicité avec récompense facultative pour obtenir une deuxième chance. Les publicités ne sont pas personnalisées. Les enfants ne voient jamais de publicité.</p>

    <h2>8. Fin de l’utilisation</h2>
    <p>Vous pouvez supprimer votre compte en tout temps dans l’application. La personne propriétaire peut supprimer la famille, ce qui efface aussi les comptes des membres qui n’ont pas leur propre connexion (comme les enfants). Nous pouvons suspendre ou fermer les comptes qui enfreignent ces conditions de façon grave ou répétée, ou si la loi l’exige.</p>

    <h2>9. Exclusions et responsabilité</h2>
    <p>Le service est fourni « tel quel ». Dans la mesure permise par la loi, nous ne sommes pas responsables des dommages indirects, et notre responsabilité totale est limitée au montant que vous nous avez payé au cours des douze mois précédant la réclamation. Rien dans ces conditions ne limite les droits que vous accordent les lois sur la protection du consommateur auxquels on ne peut renoncer. <span class="review">[Révision juridique : validité de ces limites pour les consommateurs au Québec et dans les États américains.]</span></p>

    <h2>10. Modifications</h2>
    <p>Nous pouvons mettre ces conditions à jour. Pour les changements importants, nous publions une nouvelle version accompagnée d’un résumé, et l’application vous demande de l’accepter de nouveau avant de continuer.</p>

    <h2>11. Droit applicable</h2>
    <p><span class="review">[À compléter par l’avocat : droit applicable (par exemple les lois du Québec / de l’Ontario et les lois fédérales du Canada) et tribunal compétent; les droits du consommateur dans son lieu de résidence sont préservés.]</span></p>

    <h2>12. Nous joindre</h2>
    <p>Courriel : <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a><br>
        Adresse : {{ $contact['company'] }}, {{ $contact['address'] }}</p>
@endsection
