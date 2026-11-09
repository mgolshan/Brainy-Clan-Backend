<?php

/*
 * French versions of the wisdom cards in data/wisdom_cards.php (quotes translated by Brainy Clan;
 * Brainy Clan originals have author and source null, like the question bank).
 * Keyed by the exact English card text; same category, difficulty and translation group as the
 * English card (validated by Database\Seeders\LegacyTranslations).
 * Shape: English text => ['text', 'author', 'source', 'answer', 'wrong' => [2 wrong ideas], 'explanation'].
 * The question is fixed by the seeder ("Quelle est l'idée principale ?").
 */

$seneca = ['author' => 'Sénèque', 'source' => 'Lettres à Lucilius (traduction Brainy Clan)'];
$marcus = ['author' => 'Marc Aurèle', 'source' => 'Pensées pour moi-même (traduction Brainy Clan)'];
$original = ['author' => null, 'source' => null];
$wealth = ['author' => 'Benjamin Franklin', 'source' => 'La Voie de la richesse (traduction Brainy Clan)'];
$almanack = fn (int $year) => ['author' => 'Benjamin Franklin', 'source' => "L'Almanach du pauvre Richard, {$year} (traduction Brainy Clan)"];
$analects = ['author' => 'Confucius', 'source' => 'Entretiens (traduction Brainy Clan)'];
$aesop = fn (string $fable) => ['author' => 'Ésope', 'source' => "{$fable} (traduction Brainy Clan)"];

return [
    // Stoicism
    'We suffer more often in imagination than in reality.' => [
        'text' => 'Nous souffrons plus souvent en imagination que dans la réalité.', ...$seneca,
        'answer' => 'S\'inquiéter est souvent pire que ce qui arrive vraiment',
        'wrong' => ['L\'imagination est dangereuse et il faut l\'éviter', 'La vraie vie est toujours douloureuse'],
        'explanation' => 'Beaucoup de nos peurs ne se réalisent jamais : nous pouvons donc moins nous inquiéter.'],
    'While we are postponing, life speeds by.' => [
        'text' => 'Pendant que nous remettons à plus tard, la vie file à toute vitesse.', ...$seneca,
        'answer' => 'Ne perds pas ton temps à tout remettre à plus tard',
        'wrong' => ['La vie va trop vite pour en profiter', 'On peut tout remettre à plus tard sans souci'],
        'explanation' => 'Le temps continue d\'avancer, que nous agissions ou non.'],
    'Begin at once to live, and count each separate day as a separate life.' => [
        'text' => 'Commence dès maintenant à vivre, et compte chaque jour comme une vie à part entière.', ...$seneca,
        'answer' => 'Profite au maximum de chaque journée',
        'wrong' => ['Planifie toute ta vie avant de commencer', 'Chaque jour devrait être exactement pareil'],
        'explanation' => 'Donne de la valeur à aujourd\'hui au lieu d\'attendre « un jour ».'],
    'No man was ever wise by chance.' => [
        'text' => 'Personne n\'est jamais devenu sage par hasard.', ...$seneca,
        'answer' => 'La sagesse vient de l\'effort et de la pratique',
        'wrong' => ['Certains ont la chance d\'être sages', 'La sagesse ne s\'apprend pas'],
        'explanation' => 'Devenir sage demande du travail, pas de la chance.'],
    'Men are disturbed not by the things which happen, but by the opinions about the things.' => [
        'text' => 'Ce qui trouble les hommes, ce ne sont pas les choses qui arrivent, mais les opinions qu\'ils ont sur ces choses.',
        'author' => 'Épictète', 'source' => 'Manuel (traduction Brainy Clan)',
        'answer' => 'Notre façon de voir les événements change ce qu\'on ressent',
        'wrong' => ['Les malheurs bouleversent toujours tout le monde', 'Ce sont les opinions des autres qui nous blessent'],
        'explanation' => 'Changer notre regard sur un problème peut changer ce qu\'on ressent.'],
    'Of things some are in our power, and others are not.' => [
        'text' => 'Parmi les choses, certaines dépendent de nous, d\'autres non.',
        'author' => 'Épictète', 'source' => 'Manuel (traduction Brainy Clan)',
        'answer' => 'Concentre-toi sur ce que tu peux maîtriser',
        'wrong' => ['Rien ne dépend de nous', 'On peut tout maîtriser si on essaie assez fort'],
        'explanation' => 'Mets ton énergie dans tes propres choix et tes propres actions.'],
    'Very little indeed is necessary for living a happy life.' => [
        'text' => 'Il faut vraiment très peu de chose pour vivre une vie heureuse.', ...$marcus,
        'answer' => 'Le bonheur n\'a pas besoin de beaucoup de choses',
        'wrong' => ['Les gens heureux possèdent très peu exprès', 'Une vie heureuse est impossible'],
        'explanation' => 'Le bonheur vient plus de notre façon de vivre que de ce que nous possédons.'],
    'The best way of avenging thyself is not to become like the wrong doer.' => [
        'text' => 'La meilleure façon de te venger est de ne pas devenir semblable à celui qui t\'a fait du tort.', ...$marcus,
        'answer' => 'N\'imite pas ceux qui te traitent mal',
        'wrong' => ['Rends toujours la pareille à ceux qui te blessent', 'Ignore tous ceux qui t\'entourent'],
        'explanation' => 'Rester gentil est une meilleure réponse que la vengeance.'],
    'No longer talk at all about the kind of man that a good man ought to be, but be such.' => [
        'text' => 'Cesse enfin de discourir sur ce que doit être un homme de bien, et sois-le.', ...$marcus,
        'answer' => 'Agir compte plus que parler d\'être bon',
        'wrong' => ['Les gens bons parlent beaucoup de bonté', 'Il est impossible d\'être bon'],
        'explanation' => 'Arrête de décrire une bonne personne et agis simplement comme elle.'],
    'Loss is nothing else than change.' => [
        'text' => 'La perte n\'est rien d\'autre qu\'un changement.', ...$marcus,
        'answer' => 'Perdre quelque chose fait partie des changements naturels de la vie',
        'wrong' => ['Il ne faut jamais accepter la moindre perte', 'Le changement est toujours mauvais'],
        'explanation' => 'Tout change ; voir une perte comme un changement peut aider à l\'accepter.'],
    'Benevolence is invincible, if it be genuine.' => [
        'text' => 'La bienveillance est invincible, si elle est sincère.', ...$marcus,
        'answer' => 'La vraie gentillesse est plus forte que la colère',
        'wrong' => ['Les gens gentils gagnent toujours aux jeux', 'La gentillesse est un signe de faiblesse'],
        'explanation' => 'Il est difficile de résister à une gentillesse sincère.'],
    'It is not the man who has too little, but the man who craves more, that is poor.' => [
        'text' => 'Ce n\'est pas celui qui a trop peu qui est pauvre, mais celui qui désire toujours plus.', ...$seneca,
        'answer' => 'Vouloir toujours plus donne un sentiment de pauvreté',
        'wrong' => ['Manquer d\'argent est la seule sorte de pauvreté', 'Vouloir plus rend riche'],
        'explanation' => 'Se contenter de ce qui suffit est une forme de richesse.'],

    // Habits
    'Early to bed and early to rise, makes a man healthy, wealthy, and wise.' => [
        'text' => 'Se coucher tôt et se lever tôt rend l\'homme sain, riche et sage.', ...$almanack(1735),
        'answer' => 'De bonnes habitudes de sommeil aident de bien des façons',
        'wrong' => ['Se coucher tard rend plus intelligent', 'Seuls les riches se couchent tôt'],
        'explanation' => 'Un sommeil régulier garde ton corps et ton esprit en forme.'],
    'Lost time is never found again.' => [
        'text' => 'Le temps perdu ne se retrouve jamais.', ...$almanack(1748),
        'answer' => 'Utilise bien ton temps, car tu ne peux pas le récupérer',
        'wrong' => ['On peut toujours rattraper le temps perdu', 'Les horloges se trompent souvent'],
        'explanation' => 'Le temps est une chose qu\'on ne peut jamais remplacer.'],
    'Well done is better than well said.' => [
        'text' => 'Bien faire vaut mieux que bien dire.', ...$almanack(1737),
        'answer' => 'Faire les choses compte plus qu\'en parler',
        'wrong' => ['Bien parler est le talent le plus important', 'Mieux vaut ne rien dire du tout'],
        'explanation' => 'Les actes prouvent plus que les paroles.'],
    'Little strokes fell great oaks.' => [
        'text' => 'À petits coups, on abat les grands chênes.', ...$almanack(1750),
        'answer' => 'De petits efforts répétés peuvent accomplir de grandes choses',
        'wrong' => ['Abattre des arbres est facile', 'Seuls les grands efforts comptent'],
        'explanation' => 'Beaucoup de petits pas mènent à un grand résultat.'],
    'The journey of a thousand li commences with a single step.' => [
        'text' => 'Un voyage de mille li commence par un seul pas.',
        'author' => 'Lao-tseu', 'source' => 'Tao-tö-king (traduction Brainy Clan)',
        'answer' => 'Tout grand objectif commence par un petit pas',
        'wrong' => ['Les longs voyages sont trop durs à commencer', 'Il faut courir, pas marcher'],
        'explanation' => 'Le « li » est une ancienne unité de distance chinoise. Les grands projets commencent petit.'],
    'Is it not pleasant to learn with a constant perseverance and application?' => [
        'text' => 'N\'est-il pas agréable d\'apprendre avec une persévérance et une application constantes ?', ...$analects,
        'answer' => 'Apprendre avec régularité est gratifiant',
        'wrong' => ['Apprendre n\'est amusant que lorsque c\'est facile', 'Il faut tout apprendre d\'un coup'],
        'explanation' => 'La pratique régulière rend l\'apprentissage agréable.'],
    'When you know a thing, to hold that you know it; and when you do not know a thing, to allow that you do not know it; this is knowledge.' => [
        'text' => 'Quand tu sais une chose, tenir que tu la sais ; quand tu ne la sais pas, reconnaître que tu ne la sais pas : voilà le savoir.', ...$analects,
        'answer' => 'Être honnête sur ce qu\'on ne sait pas fait partie de la sagesse',
        'wrong' => ['Fais toujours semblant de connaître la réponse', 'Le savoir, c\'est tout connaître'],
        'explanation' => 'Admettre « je ne sais pas » est le premier pas pour apprendre.'],
    'Slow but steady wins the race.' => [
        'text' => 'Lentement mais sûrement, on gagne la course.', ...$aesop('Le Lièvre et la Tortue'),
        'answer' => 'Avancer avec régularité vaut mieux que foncer puis s\'arrêter',
        'wrong' => ['Le plus rapide gagne toujours', 'Faire la course n\'en vaut pas la peine'],
        'explanation' => 'La tortue a continué d\'avancer pendant que le lièvre faisait une sieste.'],
    'Our life is frittered away by detail. Simplify, simplify.' => [
        'text' => 'Notre vie s\'émiette en détails. Simplifiez, simplifiez.',
        'author' => 'Henry David Thoreau', 'source' => 'Walden (traduction Brainy Clan)',
        'answer' => 'Une vie simple aide à se concentrer sur l\'essentiel',
        'wrong' => ['Les détails sont la partie la plus importante de la vie', 'La vie devrait être aussi chargée que possible'],
        'explanation' => 'Trop de petites choses peuvent prendre la place de ce qui compte.'],
    'If you would be a good reader, read.' => [
        'text' => 'Si tu veux devenir un bon lecteur, lis.',
        'author' => 'Épictète', 'source' => 'Entretiens (traduction Brainy Clan)',
        'answer' => 'On progresse en pratiquant',
        'wrong' => ['Certaines personnes naissent bons lecteurs', 'Lire n\'est pas important'],
        'explanation' => 'C\'est en pratiquant que les talents grandissent.'],
    'Diligence is the mother of good luck.' => [
        'text' => 'Le travail assidu est la mère de la chance.', ...$wealth,
        'answer' => 'Le travail fait naître sa propre chance',
        'wrong' => ['La chance n\'a rien à voir avec l\'effort', 'Seuls les chanceux travaillent dur'],
        'explanation' => 'Ceux qui travaillent avec régularité sont prêts quand une occasion se présente.'],
    'Trust thyself: every heart vibrates to that iron string.' => [
        'text' => 'Aie confiance en toi : chaque cœur vibre à cette corde de fer.',
        'author' => 'Ralph Waldo Emerson', 'source' => 'La Confiance en soi (traduction Brainy Clan)',
        'answer' => 'Crois en toi',
        'wrong' => ['Ne fais jamais confiance à personne', 'Les cœurs sont faits de fer'],
        'explanation' => 'Emerson nous encourage à avoir confiance en notre propre pensée.'],
    'Practice a little every day. Small steps add up to big skills.' => [
        'text' => 'Entraîne-toi un peu chaque jour. Les petits pas finissent par donner de grands talents.', ...$original,
        'answer' => 'Un peu d\'entraînement chaque jour développe les talents',
        'wrong' => ['Ne s\'entraîner qu\'une fois par an', 'Les talents apparaissent sans entraînement'],
        'explanation' => 'Quelques minutes par jour valent mieux qu\'une longue séance de temps en temps.'],
    'A mistake is a lesson that arrived early.' => [
        'text' => 'Une erreur, c\'est une leçon arrivée en avance.', ...$original,
        'answer' => 'Les erreurs nous aident à apprendre',
        'wrong' => ['Il faut toujours cacher ses erreurs', 'Les leçons ne viennent que des enseignants'],
        'explanation' => 'Chaque erreur nous montre quoi essayer autrement la prochaine fois.'],
    'Finish one thing before you start the next.' => [
        'text' => 'Termine une chose avant de commencer la suivante.', ...$original,
        'answer' => 'Faire une tâche à la fois aide à aller au bout des choses',
        'wrong' => ['Commence autant de choses que possible', 'Ne termine jamais rien'],
        'explanation' => 'Passer sans cesse d\'une tâche à l\'autre empêche souvent de finir quoi que ce soit.'],

    // Kindness
    'Including someone new can make their whole day.' => [
        'text' => 'Accueillir une nouvelle personne peut illuminer toute sa journée.', ...$original,
        'answer' => 'Les petits gestes d\'accueil comptent beaucoup',
        'wrong' => ['Ne jouer qu\'avec ses anciens amis', 'Les nouveaux doivent attendre leur tour'],
        'explanation' => 'Inviter quelqu\'un à se joindre à toi lui montre qu\'il a sa place.'],
    'Little friends may prove great friends.' => [
        'text' => 'Les plus petits peuvent se révéler de grands amis.', ...$aesop('Le Lion et la Souris'),
        'answer' => 'N\'importe qui, même tout petit, peut être un grand ami',
        'wrong' => ['Seuls les grands amis sont utiles', 'Les lions et les souris sont toujours amis'],
        'explanation' => 'La petite souris a sauvé le puissant lion en rongeant le filet.'],
    'Persuasion is better than force.' => [
        'text' => 'La persuasion vaut mieux que la force.', ...$aesop('Le Vent du nord et le Soleil'),
        'answer' => 'La douceur marche mieux que la force',
        'wrong' => ['Le plus fort obtient toujours ce qu\'il veut', 'Le vent est plus fort que le soleil'],
        'explanation' => 'La chaleur du soleil a fait retirer son manteau au voyageur, alors que le vent avait échoué.'],
    'One good turn deserves another.' => [
        'text' => 'Un service en appelle un autre.', ...$aesop('La Colombe et la Fourmi'),
        'answer' => 'Quand quelqu\'un t\'aide, aide-le en retour',
        'wrong' => ['N\'accepte jamais d\'aide', 'N\'aide les gens qu\'une seule fois'],
        'explanation' => 'La fourmi et la colombe se sont sauvées l\'une l\'autre.'],
    'What you do not want done to yourself, do not do to others.' => [
        'text' => 'Ce que tu ne veux pas qu\'on te fasse, ne le fais pas aux autres.', ...$analects,
        'answer' => 'Traite les autres comme tu veux être traité',
        'wrong' => ['Fais aux autres ce qu\'ils te font', 'Ne pense qu\'à toi'],
        'explanation' => 'Cette idée se retrouve dans de nombreuses cultures du monde.'],
    'The only way to have a friend is to be one.' => [
        'text' => 'La seule façon d\'avoir un ami, c\'est d\'en être un.',
        'author' => 'Ralph Waldo Emerson', 'source' => 'Essais : L\'Amitié (traduction Brainy Clan)',
        'answer' => 'Pour se faire des amis, il faut d\'abord être un bon ami',
        'wrong' => ['Les amis n\'arrivent que par chance', 'Mieux vaut n\'avoir aucun ami'],
        'explanation' => 'L\'amitié grandit quand on fait preuve de gentillesse et de loyauté.'],
    'How far that little candle throws his beams! So shines a good deed in a naughty world.' => [
        'text' => 'Comme cette petite chandelle projette loin ses rayons ! Ainsi brille une bonne action dans un monde méchant.',
        'author' => 'William Shakespeare', 'source' => 'Le Marchand de Venise (traduction Brainy Clan)',
        'answer' => 'Même une petite bonne action peut faire une grande différence',
        'wrong' => ['Les chandelles brillent plus que le soleil', 'Le monde est trop sombre pour changer'],
        'explanation' => 'Comme une chandelle dans le noir, la gentillesse se remarque.'],
    'Sorry is a small word that can fix big things.' => [
        'text' => '« Pardon » est un petit mot qui peut réparer de grandes choses.', ...$original,
        'answer' => 'S\'excuser peut apaiser les sentiments blessés',
        'wrong' => ['« Pardon » est un mot dont personne n\'a besoin', 'Les gros problèmes ne se règlent jamais'],
        'explanation' => 'De vraies excuses aident les gens à se refaire confiance.'],
    'Ask a friend how they feel, then really listen.' => [
        'text' => 'Demande à un ami comment il se sent, puis écoute-le vraiment.', ...$original,
        'answer' => 'Écouter est une façon de montrer qu\'on tient à quelqu\'un',
        'wrong' => ['Parler est plus important qu\'écouter', 'Ne jamais demander aux autres comment ils se sentent'],
        'explanation' => 'On se sent compris quand quelqu\'un nous écoute vraiment.'],
    'Kind words cost nothing but are worth a lot.' => [
        'text' => 'Les mots gentils ne coûtent rien, mais valent beaucoup.', ...$original,
        'answer' => 'Être gentil est facile et très précieux',
        'wrong' => ['Les mots gentils coûtent cher', 'Les mots n\'ont aucune importance'],
        'explanation' => 'Un mot gentil peut changer toute la journée de quelqu\'un.'],
    'Share the credit when a team wins together.' => [
        'text' => 'Quand une équipe gagne ensemble, partage le mérite.', ...$original,
        'answer' => 'Une réussite est plus belle quand elle est partagée',
        'wrong' => ['Le chef devrait recevoir tout le mérite', 'Les équipes ne gagnent jamais'],
        'explanation' => 'Tous ceux qui ont aidé méritent d\'être reconnus.'],

    // Money basics
    'Beware of little expenses; a small leak will sink a great ship.' => [
        'text' => 'Méfie-toi des petites dépenses : une petite fuite fera couler un grand navire.', ...$wealth,
        'answer' => 'Les petites dépenses s\'additionnent avec le temps',
        'wrong' => ['Les navires coulent souvent', 'Seuls les gros achats comptent'],
        'explanation' => 'Beaucoup de petits achats peuvent finir par coûter beaucoup d\'argent.'],
    'A penny saved is twopence clear.' => [
        'text' => 'Un sou épargné, c\'est deux sous de gagnés.', ...$almanack(1737),
        'answer' => 'Économiser de l\'argent vaut autant que d\'en gagner',
        'wrong' => ['Les sous ne valent rien', 'Il faut dépenser chaque sou'],
        'explanation' => 'L\'argent que tu ne dépenses pas, tu le gardes.'],
    'Rather go to bed supperless than rise in debt.' => [
        'text' => 'Mieux vaut se coucher sans souper que se lever avec des dettes.', ...$wealth,
        'answer' => 'Évite d\'emprunter de l\'argent que tu ne peux pas rembourser',
        'wrong' => ['Sauter le repas du soir est bon pour la santé', 'S\'endetter est toujours une bonne idée'],
        'explanation' => 'Devoir de l\'argent peut causer des soucis pendant longtemps.'],
    'If you would know the value of money, go and try to borrow some.' => [
        'text' => 'Si tu veux connaître la valeur de l\'argent, va essayer d\'en emprunter.', ...$wealth,
        'answer' => 'On apprend ce que vaut l\'argent quand on n\'en a pas',
        'wrong' => ['Emprunter de l\'argent est facile', 'L\'argent n\'a aucune vraie valeur'],
        'explanation' => 'Avoir besoin d\'argent nous apprend à le respecter.'],
    'It is best to prepare for the days of necessity.' => [
        'text' => 'Il vaut mieux se préparer pour les jours de besoin.', ...$aesop('La Cigale et la Fourmi'),
        'answer' => 'Épargne et prépare l\'avenir',
        'wrong' => ['Amuse-toi maintenant et ne prévois jamais rien', 'Les fourmis valent mieux que les cigales'],
        'explanation' => 'La fourmi faisait des réserves pour l\'hiver pendant que la cigale ne faisait que s\'amuser.'],
    'Much wants more and loses all.' => [
        'text' => 'Qui veut toujours plus finit par tout perdre.', ...$aesop('L\'Oie aux œufs d\'or'),
        'answer' => 'L\'avidité peut faire perdre ce qu\'on a',
        'wrong' => ['Vouloir plus finit toujours par payer', 'Les oies ont beaucoup de valeur'],
        'explanation' => 'En voulant tout l\'or d\'un coup, le propriétaire a perdu l\'oie qui le pondait.'],
    'Neither a borrower nor a lender be.' => [
        'text' => 'Ne sois ni emprunteur ni prêteur.',
        'author' => 'William Shakespeare', 'source' => 'Hamlet (traduction Brainy Clan)',
        'answer' => 'Prêter et emprunter de l\'argent peut causer des problèmes',
        'wrong' => ['Prête toujours de l\'argent à tes amis', 'Emprunter est le meilleur moyen de devenir riche'],
        'explanation' => 'Polonius prévient qu\'un prêt peut faire perdre à la fois l\'argent et les amis.'],
    'A man is rich in proportion to the number of things which he can afford to let alone.' => [
        'text' => 'Un homme est riche en proportion du nombre de choses dont il peut se passer.',
        'author' => 'Henry David Thoreau', 'source' => 'Walden (traduction Brainy Clan)',
        'answer' => 'Pouvoir se passer de beaucoup de choses est une forme de richesse',
        'wrong' => ['Les riches sont ceux qui possèdent le plus de choses', 'Il faut acheter tout ce qu\'on peut se payer'],
        'explanation' => 'Ne pas avoir besoin de grand-chose est une forme de liberté.'],
    'A budget is a plan you make before you spend.' => [
        'text' => 'Un budget, c\'est un plan que l\'on fait avant de dépenser.', ...$original,
        'answer' => 'Planifier ses dépenses aide à bien utiliser son argent',
        'wrong' => ['Avec un budget, on ne peut jamais rien acheter', 'L\'argent décide tout seul où il va'],
        'explanation' => 'Avec un plan, tu choisis comment ton argent est utilisé.'],
    'Needs come first; wants can wait.' => [
        'text' => 'Les besoins d\'abord ; les envies peuvent attendre.', ...$original,
        'answer' => 'Paie ce dont tu as besoin avant ce dont tu as envie',
        'wrong' => ['Les envies sont plus importantes que les besoins', 'N\'achète jamais rien dont tu as envie'],
        'explanation' => 'La nourriture et un toit passent avant les jouets et les friandises.'],
    'Save a little from every coin you get, and watch it grow.' => [
        'text' => 'Mets de côté un peu de chaque pièce que tu reçois, et regarde ton épargne grandir.', ...$original,
        'answer' => 'Épargner régulièrement, ça s\'additionne',
        'wrong' => ['Il faut dépenser ses pièces tout de suite', 'Épargner ne marche qu\'avec de grosses sommes'],
        'explanation' => 'Même de petites sommes mises de côté souvent finissent par faire beaucoup.'],
    'Before you buy, ask: will I still want this next week?' => [
        'text' => 'Avant d\'acheter, demande-toi : est-ce que j\'en aurai encore envie la semaine prochaine ?', ...$original,
        'answer' => 'Réfléchis avant de dépenser',
        'wrong' => ['Achète les choses le plus vite possible', 'N\'achète jamais rien du tout'],
        'explanation' => 'Attendre un peu aide à éviter d\'acheter des choses dont on n\'a pas vraiment envie.'],
];
