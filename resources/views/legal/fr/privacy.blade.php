@extends('legal.layout')

@section('title', 'Politique de confidentialité')
@section('other_document', 'Conditions d’utilisation')

@section('content')
    <p><span class="review">[ÉBAUCHE POUR RÉVISION JURIDIQUE — ce texte décrit le fonctionnement réel de l’application. Il doit être révisé par un avocat qualifié (Canada : LPRPDE et Loi 25 du Québec; États-Unis : COPPA et lois des États) avant la publication de l’application.]</span></p>

    <p>Brainy Clan est une application familiale d’apprentissage et de jeux-questionnaires. Un parent ou tuteur crée une famille, invite les membres de la famille (y compris des enfants) et joue avec eux à de courts quiz et casse-têtes. La présente politique explique quels renseignements personnels nous recueillons, pourquoi, avec qui nous les partageons, combien de temps nous les conservons et quels sont vos droits. Le service est exploité par {{ $contact['company'] }} (« nous »).</p>

    <h2>1. En bref</h2>
    <ul>
        <li>Pour les enfants, nous recueillons seulement un <strong>surnom, un avatar ainsi que l’année et le mois de naissance</strong> — aucune adresse courriel, aucun nom complet, aucune photo, aucune date de naissance exacte, aucune localisation précise. Si un parent laisse la discussion familiale activée pour eux, nous conservons aussi les messages qu’ils y écrivent, que seule leur famille peut lire (voir « Discussion familiale »).</li>
        <li>Les enfants ne voient <strong>aucune publicité et ne font l’objet d’aucun pistage</strong>. Les adolescents et les adultes du forfait gratuit peuvent regarder des publicités avec récompense facultatives, <strong>non personnalisées</strong>.</li>
        <li>Un parent ou tuteur donne son consentement pour chaque enfant avant que celui-ci puisse se joindre à la famille.</li>
        <li>Nous ne vendons pas de renseignements personnels et ne les utilisons ni pour du profilage ni pour de la publicité ciblée.</li>
        <li>Vous pouvez télécharger une copie de vos données dans l’application et supprimer votre compte en tout temps.</li>
    </ul>

    <h2>2. Renseignements recueillis</h2>
    <h3>Adultes (18 ans et plus) et adolescents ayant leur propre connexion</h3>
    <ul>
        <li>Nom affiché, avatar, année et mois de naissance (pour appliquer les règles selon l’âge), langue.</li>
        <li>Adresse courriel et mot de passe (conservé uniquement sous forme de hachage sécurisé), ou l’identifiant du compte Google, Apple ou Facebook choisi pour se connecter. Avec « Se connecter avec Apple », vous pouvez utiliser l’adresse de relais privée d’Apple.</li>
    </ul>
    <h3>Enfants (moins de 13 ans)</h3>
    <ul>
        <li>Surnom, avatar ainsi qu’année et mois de naissance, saisis par le parent ou tuteur dans l’invitation. Un enfant ne saisit jamais d’adresse courriel ni de mot de passe et ne peut pas se connecter avec Google, Apple ou Facebook.</li>
    </ul>
    <h3>Tout le monde</h3>
    <ul>
        <li><strong>Données de famille et d’activité :</strong> nom de la famille, rôle (administrateur ou membre), réponses données aux quiz et casse-têtes, points, séries, objectifs et leurs récompenses, réglages d’accessibilité et de difficulté.</li>
        <li><strong>Données de l’appareil :</strong> nom et plateforme de l’appareil (iOS, Android, web), version de l’application, dernière utilisation et, si les notifications sont permises, un jeton de notification.</li>
        <li><strong>Preuves de consentement :</strong> la version des présents documents acceptée, le moment et un hachage à sens unique, avec clé, de l’adresse réseau (IP) — jamais l’adresse elle-même.</li>
        <li><strong>Achats :</strong> si un adulte s’abonne dans l’application, la boutique d’applications traite le paiement. Si un adulte s’abonne sur notre site Web, <strong>Paddle.com</strong> (notre revendeur et marchand officiel) traite le paiement et recueille les renseignements de facturation dont il a besoin (nom, courriel, pays, code postal, moyen de paiement) selon sa propre politique de confidentialité. Nous recevons seulement l’état de l’abonnement (par exemple actif ou expiré), le forfait, les montants payés, remboursés et taxés, et les identifiants de client et d’abonnement de Paddle — jamais les données de carte. <span class="review">[ÉBAUCHE À FAIRE RÉVISER PAR UN AVOCAT]</span></li>
        <li><strong>Codes de parrainage :</strong> si un administrateur de la famille entre le code de parrainage d’un partenaire (ou utilise son offre en boutique ou son lien), nous conservons le code associé à la famille et la commission due à ce partenaire sur les paiements de la famille. Les partenaires reçoivent seulement des totaux, jamais le nom ni les coordonnées des familles. <span class="review">[ÉBAUCHE À FAIRE RÉVISER PAR UN AVOCAT]</span></li>
        <li><strong>Rapports d’erreurs :</strong> détails techniques des plantages et erreurs (par exemple la version de l’application) dont les noms, adresses courriel, données de naissance et données d’authentification sont retirés avant l’envoi.</li>
    </ul>
    <h3>Discussion familiale</h3>
    <p><span class="review">[ÉBAUCHE POUR RÉVISION JURIDIQUE]</span> Chaque famille dispose d’une discussion de groupe privée pour de courts messages texte et des émojis (pas de photos, de fichiers, d’aperçus de liens ni de messages vocaux). Nous conservons le texte de chaque message, son auteur (le membre de la famille), le moment de l’envoi et s’il a été supprimé, ainsi que la position « dernier message lu » de chaque membre. <strong>Seuls les membres actifs de cette famille peuvent voir la discussion</strong> — ni les autres familles, ni notre personnel : les messages ne sont pas affichés dans nos outils d’administration et sont exclus des journaux, des rapports d’erreurs et de nos registres d’activité. Les messages sont <strong>chiffrés au repos</strong> sur nos serveurs avec la clé de l’application. Les notifications de nouveaux messages indiquent seulement qui a écrit (« Sam a envoyé un message »), jamais le texte du message. Un administrateur de la famille peut désactiver la discussion pour n’importe quel membre et supprimer n’importe quel message; chaque membre peut supprimer ses propres messages. Les messages sont supprimés automatiquement après <strong>7 jours avec le forfait gratuit et 1 an avec le forfait Famille</strong> (après un passage au forfait gratuit, la durée la plus courte s’applique). À la suppression d’un compte, tous les messages de cette personne sont supprimés; quand un membre est retiré de la famille, ses messages restent visibles pour la famille comme « Ancien membre » jusqu’à leur expiration; à la suppression de la famille, toute sa discussion est supprimée. Vos propres messages sont inclus dans le téléchargement de vos données. La discussion n’est pas chiffrée de bout en bout.</p>

    <p>Nous ne recueillons sciemment aucun autre renseignement personnel auprès des enfants. Si vous croyez qu’un enfant nous a fourni des renseignements que nous n’avons pas demandés, communiquez avec nous et nous les supprimerons.</p>

    <h2>3. Utilisation des renseignements</h2>
    <ul>
        <li>Pour fournir le service : comptes, connexion, familles, quiz, pointages, objectifs et rappels.</li>
        <li>Pour appliquer des règles adaptées à l’âge : l’année et le mois de naissance déterminent la tranche d’âge, qui fixe le contenu, la difficulté, la possibilité de voir des publicités et celle d’avoir une connexion par courriel.</li>
        <li>Pour envoyer les notifications que chaque personne a activées (par exemple les résultats d’un objectif ou un rappel quotidien), en respectant les heures de silence.</li>
        <li>Pour gérer les abonnements (achats dans l’application et sur le Web), verser leur commission aux partenaires de parrainage et, pour les adolescents et adultes du forfait gratuit, vérifier qu’une publicité avec récompense a été regardée.</li>
        <li>Pour assurer la sécurité du service, prévenir les abus, corriger les erreurs et respecter nos obligations légales.</li>
    </ul>
    <p>Nous n’utilisons pas les renseignements personnels pour de la publicité comportementale, ne créons pas de profils marketing et n’utilisons pas les renseignements des enfants pour entraîner des modèles d’intelligence artificielle.</p>

    <h2>4. Publicité</h2>
    <p>Les enfants ne voient jamais de publicité. Les adolescents et les adultes du forfait gratuit peuvent choisir de regarder une publicité avec récompense (Google AdMob) pour obtenir une deuxième chance. Ces publicités sont demandées en mode <strong>non personnalisé</strong> et limitées à un contenu tous publics; pour les adolescents, elles sont aussi signalées comme destinées à des utilisateurs n’ayant pas l’âge du consentement. L’application n’utilise pas l’identifiant publicitaire pour suivre l’activité dans les applications ou sites d’autres entreprises.</p>

    <h2>5. Fournisseurs de services</h2>
    <p>Nous communiquons des renseignements personnels uniquement à des fournisseurs qui les traitent pour notre compte, par contrat, aux fins décrites ci-dessus :</p>
    <table>
        <thead><tr><th>Fournisseur</th><th>Fin</th><th>Données</th></tr></thead>
        <tbody>
            <tr><td>Hetzner Online GmbH (hébergement)</td><td>Serveurs et sauvegardes</td><td>Toutes les données du service (sauvegardes chiffrées)</td></tr>
            <tr><td>Apple, Google, Meta (Facebook)</td><td>Connexion facultative des adultes</td><td>Identifiant du compte et courriel transmis par le fournisseur</td></tr>
            <tr><td>App Store d’Apple, Google Play, RevenueCat</td><td>Abonnements</td><td>Identifiant de la famille et état de l’abonnement</td></tr>
            <tr><td>Paddle.com (marchand officiel des achats sur le Web)</td><td>Paiement sur le Web, facturation, taxes, reçus, remboursements et portail client</td><td>Identifiant de la famille, le courriel et les renseignements de facturation fournis à Paddle, état de l’abonnement et des paiements</td></tr>
            <tr><td>Google AdMob</td><td>Publicités avec récompense (adolescents et adultes du forfait gratuit seulement)</td><td>Données de la demande de publicité; une référence de récompense aléatoire</td></tr>
            <tr><td>Expo (notifications), Apple Push Notification service, Firebase Cloud Messaging</td><td>Notifications</td><td>Jeton de notification et texte de la notification (jamais le texte des messages de discussion)</td></tr>
            <tr><td>Sentry</td><td>Surveillance des erreurs</td><td>Détails techniques des erreurs, sans renseignements personnels</td></tr>
        </tbody>
    </table>
    <p>Nous pouvons aussi communiquer des renseignements lorsque la loi l’exige ou pour protéger la sécurité des utilisateurs. En cas de transfert de l’entreprise, les renseignements seraient transférés selon la présente politique et vous en seriez avisé.</p>

    <h2>6. Lieu de conservation</h2>
    <p>Nos serveurs sont exploités par Hetzner <span class="review">[confirmer l’emplacement du centre de données : Allemagne/Finlande (UE) ou États-Unis]</span>. Certains fournisseurs ci-dessus traitent des renseignements aux États-Unis ou dans d’autres pays. Les renseignements traités hors de votre province ou de votre pays peuvent être accessibles aux autorités de ces endroits. <span class="review">[Révision juridique : évaluation des facteurs relatifs à la vie privée pour les communications hors Québec (Loi 25).]</span></p>

    <h2>7. Enfants et consentement parental</h2>
    <p>Avant qu’un enfant puisse se joindre à la famille, l’administrateur doit confirmer qu’il est le parent ou le tuteur légal de l’enfant (ou qu’il agit avec sa permission) et accepter le traitement décrit ici. Nous conservons une preuve de ce consentement. Le parent ou tuteur peut en tout temps consulter les renseignements de l’enfant dans l’application, modifier son surnom ou son avatar, mettre fin à la collecte en retirant l’enfant de la famille (ce qui efface le compte de l’enfant) ou communiquer avec nous. <span class="review">[Révision juridique : méthode de « consentement parental vérifiable » de la COPPA; Québec : consentement du titulaire de l’autorité parentale pour les moins de 14 ans.]</span></p>

    <h2>8. Conservation</h2>
    <ul>
        <li>Données du compte et d’activité : tant que le compte existe.</li>
        <li>À la suppression d’un compte (ou au retrait d’un enfant de la famille), nous effaçons immédiatement le nom, le courriel, le mot de passe, les données de naissance, l’avatar, les appareils, les jetons de notification et les comptes de connexion liés. Les données de jeu anonymes (points sans aucun nom) sont conservées pour que les pointages des autres membres restent exacts.</li>
        <li>Messages de la discussion familiale : 7 jours (forfait gratuit) ou 1 an (forfait Famille) après leur envoi, puis supprimés automatiquement; immédiatement lorsqu’ils sont supprimés par leur auteur ou un administrateur de la famille (seule une mention « Message supprimé » sans le texte subsiste jusqu’à la même échéance), à la suppression du compte de l’auteur ou à la suppression de la famille.</li>
        <li>Les preuves de consentement sont conservées aussi longtemps que la loi l’exige <span class="review">[confirmer la durée]</span>.</li>
        <li>Les sauvegardes chiffrées sont conservées 14 jours, puis supprimées (des données supprimées, y compris des messages de discussion, peuvent donc subsister dans une sauvegarde jusqu’à 14 jours).</li>
        <li>Les rapports d’erreurs sont conservés par Sentry jusqu’à 90 jours.</li>
        <li>Les registres d’achats et de commissions (montants, dates, code de parrainage) sont conservés aussi longtemps que l’exigent les lois fiscales et comptables <span class="review">[confirmer la durée, p. ex. 6 ans au Canada]</span>, sans nom.</li>
    </ul>

    <h2>9. Vos droits</h2>
    <p>Selon l’endroit où vous habitez, vous pouvez : accéder à vos renseignements et en obtenir une copie (dans l’application : Réglages → Télécharger mes données), les faire rectifier, supprimer votre compte (Réglages → Supprimer le compte), retirer votre consentement et poser des questions sur leur utilisation. En vertu de la loi québécoise, vous pouvez aussi obtenir vos renseignements dans un format technologique structuré et couramment utilisé, et porter plainte à la Commission d’accès à l’information du Québec; ailleurs au Canada, au Commissariat à la protection de la vie privée du Canada. Les parents et tuteurs exercent ces droits pour leurs enfants.</p>

    <h2>10. Sécurité</h2>
    <p>Les données sont chiffrées en transit (HTTPS). Les mots de passe sont hachés, les jetons d’accès sont conservés sous forme de hachage et chaque appareil a son propre jeton révocable. Les messages de la discussion familiale sont chiffrés au repos. Les sauvegardes sont chiffrées. L’accès aux outils d’administration est limité au personnel autorisé. Aucune méthode n’est parfaitement sûre; si un incident de confidentialité présente un risque de préjudice sérieux, nous vous en aviserons, ainsi que les autorités, comme la loi l’exige.</p>

    <h2>11. Modifications</h2>
    <p>Lorsque nous modifions cette politique de façon importante, nous publions une nouvelle version accompagnée d’un résumé des changements, et l’application demande aux adultes et aux adolescents de l’accepter de nouveau.</p>

    <h2>12. Nous joindre</h2>
    <p>Personne responsable de la protection des renseignements personnels : {{ $contact['privacy_officer'] }}<br>
        Courriel : <a href="mailto:{{ $contact['email'] }}">{{ $contact['email'] }}</a><br>
        Adresse : {{ $contact['company'] }}, {{ $contact['address'] }}</p>
@endsection
