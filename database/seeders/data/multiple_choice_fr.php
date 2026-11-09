<?php

/*
 * French versions of the hand-written multiple-choice items in data/multiple_choice.php.
 * Keyed by the exact English question string; same category, difficulty and translation group as the
 * English item (validated by Database\Seeders\LegacyTranslations).
 * Shape: English question => ['question', 'answer', 'wrong' => [3 wrong answers], 'explanation' => string|null].
 * Items about English words (synonyms, idioms, spelling, grade vocabulary, missing letter, animal-young
 * names) stay English-only.
 */

return [
    // Flags
    "Which country's flag has a red maple leaf?" => [
        'question' => 'Quel pays a un drapeau avec une feuille d\'érable rouge ?',
        'answer' => 'Canada', 'wrong' => ['Japon', 'Suisse', 'Pérou'],
        'explanation' => 'Le drapeau à la feuille d\'érable est devenu celui du Canada en 1965.'],
    "Which country's flag is a red circle on a white background?" => [
        'question' => 'Quel pays a pour drapeau un disque rouge sur fond blanc ?',
        'answer' => 'Japon', 'wrong' => ['Chine', 'Corée du Sud', 'Bangladesh'],
        'explanation' => 'Le disque rouge représente le soleil.'],
    "Which country's flag has 50 stars?" => [
        'question' => 'Quel pays a un drapeau avec 50 étoiles ?',
        'answer' => 'États-Unis', 'wrong' => ['Australie', 'Brésil', 'Chine'],
        'explanation' => 'Une étoile pour chacun des 50 États.'],
    'How many stripes are on the flag of the United States?' => [
        'question' => 'Combien de bandes y a-t-il sur le drapeau des États-Unis ?',
        'answer' => '13', 'wrong' => ['10', '12', '50'],
        'explanation' => 'Les 13 bandes représentent les 13 colonies d\'origine.'],
    'Which country has a flag with plain green, white and red vertical stripes and no emblem?' => [
        'question' => 'Quel pays a un drapeau à simples bandes verticales verte, blanche et rouge, sans emblème ?',
        'answer' => 'Italie', 'wrong' => ['France', 'Irlande', 'Allemagne'], 'explanation' => null],
    'Which country has a square red flag with a white cross in the middle?' => [
        'question' => 'Quel pays a un drapeau carré rouge avec une croix blanche au milieu ?',
        'answer' => 'Suisse', 'wrong' => ['Autriche', 'Japon', 'Norvège'], 'explanation' => null],
    'What is the main background colour of the flag of Brazil?' => [
        'question' => 'Quelle est la couleur de fond principale du drapeau du Brésil ?',
        'answer' => 'Vert', 'wrong' => ['Bleu', 'Rouge', 'Jaune'], 'explanation' => null],
    'Which country has a flag that is not a rectangle?' => [
        'question' => 'Quel pays a un drapeau qui n\'est pas un rectangle ?',
        'answer' => 'Népal', 'wrong' => ['Inde', 'Pérou', 'Canada'],
        'explanation' => 'Le drapeau du Népal est formé de deux triangles superposés.'],
    "Which country's flag shows a dragon?" => [
        'question' => 'Quel pays a un dragon sur son drapeau ?',
        'answer' => 'Bhoutan', 'wrong' => ['Chine', 'Japon', 'Népal'], 'explanation' => null],
    "Which country's flag shows a green cedar tree?" => [
        'question' => 'Quel pays a un cèdre vert sur son drapeau ?',
        'answer' => 'Liban', 'wrong' => ['Canada', 'Chypre', 'Kenya'], 'explanation' => null],

    // Landmarks
    'In which city is the Eiffel Tower?' => [
        'question' => 'Dans quelle ville se trouve la tour Eiffel ?',
        'answer' => 'Paris', 'wrong' => ['Londres', 'Rome', 'Berlin'], 'explanation' => null],
    'In which country is the Great Wall?' => [
        'question' => 'Dans quel pays se trouve la Grande Muraille ?',
        'answer' => 'Chine', 'wrong' => ['Japon', 'Inde', 'Mongolie'], 'explanation' => null],
    'In which country are the Great Pyramids of Giza?' => [
        'question' => 'Dans quel pays se trouvent les grandes pyramides de Gizeh ?',
        'answer' => 'Égypte', 'wrong' => ['Mexique', 'Pérou', 'Grèce'], 'explanation' => null],
    'In which city is the Colosseum?' => [
        'question' => 'Dans quelle ville se trouve le Colisée ?',
        'answer' => 'Rome', 'wrong' => ['Athènes', 'Paris', 'Madrid'], 'explanation' => null],
    'In which city is the Statue of Liberty?' => [
        'question' => 'Dans quelle ville se trouve la statue de la Liberté ?',
        'answer' => 'New York', 'wrong' => ['Washington', 'Boston', 'Chicago'], 'explanation' => null],
    'In which country is the Sydney Opera House?' => [
        'question' => 'Dans quel pays se trouve l\'Opéra de Sydney ?',
        'answer' => 'Australie', 'wrong' => ['Nouvelle-Zélande', 'Royaume-Uni', 'Afrique du Sud'], 'explanation' => null],
    'In which city is the clock tower known as Big Ben?' => [
        'question' => 'Dans quelle ville se trouve la tour de l\'horloge surnommée Big Ben ?',
        'answer' => 'Londres', 'wrong' => ['Paris', 'Dublin', 'Édimbourg'], 'explanation' => null],
    'In which country is the Taj Mahal?' => [
        'question' => 'Dans quel pays se trouve le Taj Mahal ?',
        'answer' => 'Inde', 'wrong' => ['Pakistan', 'Iran', 'Turquie'], 'explanation' => null],
    'In which city is the CN Tower?' => [
        'question' => 'Dans quelle ville se trouve la tour CN ?',
        'answer' => 'Toronto', 'wrong' => ['Vancouver', 'Montréal', 'Ottawa'], 'explanation' => null],
    'In which country is Machu Picchu?' => [
        'question' => 'Dans quel pays se trouve le Machu Picchu ?',
        'answer' => 'Pérou', 'wrong' => ['Chili', 'Mexique', 'Bolivie'], 'explanation' => null],
    'Above which city does the Christ the Redeemer statue stand?' => [
        'question' => 'Au-dessus de quelle ville se dresse la statue du Christ Rédempteur ?',
        'answer' => 'Rio de Janeiro', 'wrong' => ['São Paulo', 'Lisbonne', 'Buenos Aires'], 'explanation' => null],
    'In which country is Stonehenge?' => [
        'question' => 'Dans quel pays se trouve Stonehenge ?',
        'answer' => 'Royaume-Uni', 'wrong' => ['Irlande', 'France', 'Norvège'], 'explanation' => null],
    'In which country is the ancient city of Petra?' => [
        'question' => 'Dans quel pays se trouve l\'ancienne cité de Pétra ?',
        'answer' => 'Jordanie', 'wrong' => ['Égypte', 'Israël', 'Arabie saoudite'], 'explanation' => null],
    'In which country is the temple of Angkor Wat?' => [
        'question' => 'Dans quel pays se trouve le temple d\'Angkor Vat ?',
        'answer' => 'Cambodge', 'wrong' => ['Thaïlande', 'Vietnam', 'Laos'], 'explanation' => null],

    // History - Americas
    'Who was the first President of the United States?' => [
        'question' => 'Qui a été le premier président des États-Unis ?',
        'answer' => 'George Washington', 'wrong' => ['Abraham Lincoln', 'Thomas Jefferson', 'John Adams'], 'explanation' => null],
    'In which year was the US Declaration of Independence signed?' => [
        'question' => 'En quelle année la Déclaration d\'indépendance des États-Unis a-t-elle été signée ?',
        'answer' => '1776', 'wrong' => ['1492', '1812', '1867'], 'explanation' => null],
    'In which year did Christopher Columbus first reach the Americas?' => [
        'question' => 'En quelle année Christophe Colomb a-t-il atteint les Amériques pour la première fois ?',
        'answer' => '1492', 'wrong' => ['1066', '1607', '1776'], 'explanation' => null],
    'Which civilization built Machu Picchu?' => [
        'question' => 'Quelle civilisation a construit le Machu Picchu ?',
        'answer' => 'Les Incas', 'wrong' => ['Les Aztèques', 'Les Mayas', 'Les Olmèques'], 'explanation' => null],
    'Which US president issued the Emancipation Proclamation?' => [
        'question' => 'Quel président des États-Unis a promulgué la Proclamation d\'émancipation ?',
        'answer' => 'Abraham Lincoln', 'wrong' => ['George Washington', 'Thomas Jefferson', 'Ulysses S. Grant'], 'explanation' => null],
    'In which year did Canada become a country through Confederation?' => [
        'question' => 'En quelle année le Canada est-il devenu un pays grâce à la Confédération ?',
        'answer' => '1867', 'wrong' => ['1776', '1812', '1901'], 'explanation' => null],
    'Who was the first Prime Minister of Canada?' => [
        'question' => 'Qui a été le premier à occuper le poste de premier ministre du Canada ?',
        'answer' => 'John A. Macdonald', 'wrong' => ['Wilfrid Laurier', 'Robert Borden', 'Alexander Mackenzie'], 'explanation' => null],
    'Which civilization built the city of Tenochtitlan?' => [
        'question' => 'Quelle civilisation a bâti la cité de Tenochtitlan ?',
        'answer' => 'Les Aztèques', 'wrong' => ['Les Incas', 'Les Mayas', 'Les Olmèques'],
        'explanation' => 'Tenochtitlan se trouvait à l\'emplacement de l\'actuelle ville de Mexico.'],

    // History - Europe
    'Which famous ship sank on its first voyage in 1912?' => [
        'question' => 'Quel célèbre navire a coulé lors de son premier voyage en 1912 ?',
        'answer' => 'Titanic', 'wrong' => ['Mayflower', 'Lusitania', 'Santa Maria'], 'explanation' => null],
    'Who painted the Mona Lisa?' => [
        'question' => 'Qui a peint La Joconde ?',
        'answer' => 'Léonard de Vinci', 'wrong' => ['Michel-Ange', 'Raphaël', 'Vincent van Gogh'], 'explanation' => null],
    'From which region did the Vikings mainly come?' => [
        'question' => 'De quelle région venaient surtout les Vikings ?',
        'answer' => 'Scandinavie', 'wrong' => ['Italie', 'Espagne', 'Grèce'], 'explanation' => null],
    'Which French emperor was defeated at Waterloo in 1815?' => [
        'question' => 'Quel empereur des Français a été vaincu à Waterloo en 1815 ?',
        'answer' => 'Napoléon Bonaparte', 'wrong' => ['Louis XIV', 'Charlemagne', 'Jules César'], 'explanation' => null],
    'In which year did the Berlin Wall fall?' => [
        'question' => 'En quelle année le mur de Berlin est-il tombé ?',
        'answer' => '1989', 'wrong' => ['1945', '1961', '2001'], 'explanation' => null],
    'Who built the first European printing press with movable type?' => [
        'question' => 'Qui a construit la première presse d\'imprimerie européenne à caractères mobiles ?',
        'answer' => 'Johannes Gutenberg', 'wrong' => ['Isaac Newton', 'Galilée', 'Martin Luther'], 'explanation' => null],
    'Which famous battle took place in England in 1066?' => [
        'question' => 'Quelle célèbre bataille a eu lieu en Angleterre en 1066 ?',
        'answer' => 'La bataille d\'Hastings', 'wrong' => ['La bataille de Waterloo', 'La bataille de Trafalgar', 'La bataille d\'Azincourt'], 'explanation' => null],

    // History - Asia
    'Samurai were warriors in which country?' => [
        'question' => 'Les samouraïs étaient des guerriers de quel pays ?',
        'answer' => 'Japon', 'wrong' => ['Chine', 'Corée', 'Inde'], 'explanation' => null],
    'Mahatma Gandhi led a peaceful movement for the independence of which country?' => [
        'question' => 'Le Mahatma Gandhi a mené un mouvement pacifique pour l\'indépendance de quel pays ?',
        'answer' => 'Inde', 'wrong' => ['Pakistan', 'Népal', 'Sri Lanka'], 'explanation' => null],
    'Who founded the Mongol Empire?' => [
        'question' => 'Qui a fondé l\'Empire mongol ?',
        'answer' => 'Gengis Khan', 'wrong' => ['Kubilai Khan', 'Attila', 'Alexandre le Grand'], 'explanation' => null],
    'The Great Wall of China was built mainly to protect against what?' => [
        'question' => 'Contre quoi la Grande Muraille de Chine a-t-elle surtout été construite ?',
        'answer' => 'Les envahisseurs venus du nord', 'wrong' => ['Les inondations', 'Les animaux sauvages', 'Les tempêtes venues de la mer'], 'explanation' => null],
    'The Silk Road connected China with which regions?' => [
        'question' => 'La route de la soie reliait la Chine à quelles régions ?',
        'answer' => 'Le Moyen-Orient et l\'Europe', 'wrong' => ['L\'Australie', 'Les Amériques', 'L\'Antarctique'], 'explanation' => null],
    'Who was the first emperor of a unified China?' => [
        'question' => 'Qui a été le premier empereur de la Chine unifiée ?',
        'answer' => 'Qin Shi Huang', 'wrong' => ['Kubilai Khan', 'Confucius', 'Sun Yat-sen'], 'explanation' => null],

    // Ancient world
    'Which ancient people built the pyramids of Giza?' => [
        'question' => 'Quel peuple de l\'Antiquité a construit les pyramides de Gizeh ?',
        'answer' => 'Les Égyptiens', 'wrong' => ['Les Romains', 'Les Grecs', 'Les Vikings'], 'explanation' => null],
    'Where did the ancient Olympic Games begin?' => [
        'question' => 'Où les Jeux olympiques de l\'Antiquité ont-ils commencé ?',
        'answer' => 'En Grèce', 'wrong' => ['À Rome', 'En Égypte', 'En Chine'], 'explanation' => null],
    'Which language did the ancient Romans speak?' => [
        'question' => 'Quelle langue parlaient les Romains de l\'Antiquité ?',
        'answer' => 'Le latin', 'wrong' => ['L\'espagnol', 'L\'italien', 'L\'anglais'], 'explanation' => null],
    'Julius Caesar was a leader of which ancient state?' => [
        'question' => 'Jules César était un dirigeant de quel État de l\'Antiquité ?',
        'answer' => 'Rome', 'wrong' => ['Grèce', 'Égypte', 'Perse'], 'explanation' => null],
    'Cleopatra was the queen of which country?' => [
        'question' => 'Cléopâtre était la reine de quel pays ?',
        'answer' => 'Égypte', 'wrong' => ['Grèce', 'Rome', 'Perse'], 'explanation' => null],
    'What is the picture writing of ancient Egypt called?' => [
        'question' => 'Comment appelle-t-on l\'écriture en images de l\'Égypte ancienne ?',
        'answer' => 'Les hiéroglyphes', 'wrong' => ['Le cunéiforme', 'Les runes', 'Le braille'], 'explanation' => null],
    'In which city is the Parthenon temple?' => [
        'question' => 'Dans quelle ville se trouve le temple du Parthénon ?',
        'answer' => 'Athènes', 'wrong' => ['Rome', 'Le Caire', 'Istanbul'], 'explanation' => null],
    'Which Roman city was buried by the eruption of Mount Vesuvius in 79 AD?' => [
        'question' => 'Quelle ville romaine a été ensevelie par l\'éruption du Vésuve en l\'an 79 ?',
        'answer' => 'Pompéi', 'wrong' => ['Athènes', 'Sparte', 'Carthage'], 'explanation' => null],

    // Animals ("What is a baby kangaroo called?" stays English-only: it asks for an English word.)
    'What do caterpillars turn into?' => [
        'question' => 'En quoi les chenilles se transforment-elles ?',
        'answer' => 'En papillons', 'wrong' => ['En abeilles', 'En scarabées', 'En araignées'], 'explanation' => null],
    'How many legs does a spider have?' => [
        'question' => 'Combien de pattes a une araignée ?',
        'answer' => '8', 'wrong' => ['6', '10', '4'],
        'explanation' => 'Les insectes ont 6 pattes ; les araignées ne sont pas des insectes.'],
    'Which animal is often called the "King of the Jungle"?' => [
        'question' => 'Quel animal surnomme-t-on souvent le « roi des animaux » ?',
        'answer' => 'Lion', 'wrong' => ['Tigre', 'Éléphant', 'Gorille'], 'explanation' => null],
    'What is the largest animal on Earth?' => [
        'question' => 'Quel est le plus grand animal sur Terre ?',
        'answer' => 'Baleine bleue', 'wrong' => ['Éléphant d\'Afrique', 'Girafe', 'Grand requin blanc'], 'explanation' => null],
    'What is the fastest land animal?' => [
        'question' => 'Quel est l\'animal terrestre le plus rapide ?',
        'answer' => 'Guépard', 'wrong' => ['Lion', 'Cheval', 'Gazelle'], 'explanation' => null],
    'How do fish breathe?' => [
        'question' => 'Comment les poissons respirent-ils ?',
        'answer' => 'Avec des branchies', 'wrong' => ['Avec des poumons', 'Par leurs nageoires', 'Ils ne respirent pas'], 'explanation' => null],
    'Which is the only mammal that can truly fly?' => [
        'question' => 'Quel est le seul mammifère vraiment capable de voler ?',
        'answer' => 'La chauve-souris', 'wrong' => ['L\'écureuil volant', 'Le manchot', 'L\'autruche'], 'explanation' => null],
    'What do we call animals that eat only plants?' => [
        'question' => 'Comment appelle-t-on les animaux qui ne mangent que des plantes ?',
        'answer' => 'Les herbivores', 'wrong' => ['Les carnivores', 'Les omnivores', 'Les prédateurs'], 'explanation' => null],
    'Which bird can fly backwards?' => [
        'question' => 'Quel oiseau peut voler à reculons ?',
        'answer' => 'Le colibri', 'wrong' => ['L\'aigle', 'Le manchot', 'L\'autruche'], 'explanation' => null],

    // Space
    'Which planet is known as the Red Planet?' => [
        'question' => 'Quelle planète surnomme-t-on la planète rouge ?',
        'answer' => 'Mars', 'wrong' => ['Vénus', 'Jupiter', 'Saturne'], 'explanation' => null],
    'What is the largest planet in our solar system?' => [
        'question' => 'Quelle est la plus grande planète de notre système solaire ?',
        'answer' => 'Jupiter', 'wrong' => ['Saturne', 'Terre', 'Neptune'], 'explanation' => null],
    'Which planet is famous for its bright rings?' => [
        'question' => 'Quelle planète est célèbre pour ses anneaux brillants ?',
        'answer' => 'Saturne', 'wrong' => ['Mars', 'Mercure', 'Vénus'], 'explanation' => null],
    'What is the closest star to Earth?' => [
        'question' => 'Quelle est l\'étoile la plus proche de la Terre ?',
        'answer' => 'Le Soleil', 'wrong' => ['L\'étoile Polaire', 'Sirius', 'La Lune'],
        'explanation' => 'La Lune n\'est pas une étoile ; elle renvoie la lumière du Soleil.'],
    'How many planets are in our solar system?' => [
        'question' => 'Combien de planètes compte notre système solaire ?',
        'answer' => '8', 'wrong' => ['7', '9', '10'], 'explanation' => null],
    'About how long does Earth take to travel once around the Sun?' => [
        'question' => 'Combien de temps faut-il environ à la Terre pour faire une fois le tour du Soleil ?',
        'answer' => 'Un an', 'wrong' => ['Un jour', 'Un mois', 'Une semaine'], 'explanation' => null],
    'Who was the first person to walk on the Moon?' => [
        'question' => 'Qui a été la première personne à marcher sur la Lune ?',
        'answer' => 'Neil Armstrong', 'wrong' => ['Buzz Aldrin', 'Youri Gagarine', 'John Glenn'], 'explanation' => null],
    'What is the name of our galaxy?' => [
        'question' => 'Comment s\'appelle notre galaxie ?',
        'answer' => 'La Voie lactée', 'wrong' => ['Andromède', 'Orion', 'La Grande Ourse'], 'explanation' => null],
    'What causes day and night on Earth?' => [
        'question' => 'Qu\'est-ce qui cause le jour et la nuit sur Terre ?',
        'answer' => 'La Terre qui tourne sur elle-même', 'wrong' => ['La Terre qui tourne autour du Soleil', 'La Lune qui cache le Soleil', 'Les nuages qui cachent le Soleil'], 'explanation' => null],

    // Human body
    'Which organ pumps blood around your body?' => [
        'question' => 'Quel organe pompe le sang dans tout ton corps ?',
        'answer' => 'Le cœur', 'wrong' => ['Les poumons', 'Le foie', 'Le cerveau'], 'explanation' => null],
    'Which organs do you use to breathe?' => [
        'question' => 'Quels organes utilises-tu pour respirer ?',
        'answer' => 'Les poumons', 'wrong' => ['Les reins', 'L\'estomac', 'Le cœur'], 'explanation' => null],
    'Which organ is the control centre of your body?' => [
        'question' => 'Quel organe est le centre de commande de ton corps ?',
        'answer' => 'Le cerveau', 'wrong' => ['Le cœur', 'L\'estomac', 'La peau'], 'explanation' => null],
    'What do red blood cells carry around the body?' => [
        'question' => 'Que transportent les globules rouges dans le corps ?',
        'answer' => 'De l\'oxygène', 'wrong' => ['De la nourriture', 'Des microbes', 'Du son'], 'explanation' => null],
    'How many teeth does an adult usually have?' => [
        'question' => 'Combien de dents un adulte a-t-il habituellement ?',
        'answer' => '32', 'wrong' => ['20', '40', '50'],
        'explanation' => 'Une dentition adulte complète compte 32 dents, dont 4 dents de sagesse. Les enfants ont 20 dents de lait.'],
    'How many bones does an adult human have?' => [
        'question' => 'Combien d\'os le squelette d\'un adulte compte-t-il ?',
        'answer' => '206', 'wrong' => ['106', '306', '150'], 'explanation' => null],
    'What is the largest organ of the human body?' => [
        'question' => 'Quel est le plus grand organe du corps humain ?',
        'answer' => 'La peau', 'wrong' => ['Le foie', 'Le cœur', 'Le cerveau'], 'explanation' => null],
    'Where is the smallest bone in the human body?' => [
        'question' => 'Où se trouve le plus petit os du corps humain ?',
        'answer' => 'Dans l\'oreille', 'wrong' => ['Dans le nez', 'Dans un orteil', 'Dans un doigt'], 'explanation' => null],

    // Fractions (decimals use a comma in French)
    'What is half of 10?' => [
        'question' => 'Quelle est la moitié de 10 ?',
        'answer' => '5', 'wrong' => ['2', '4', '20'], 'explanation' => null],
    'What is 1/4 of 100?' => [
        'question' => 'Combien font 1/4 de 100 ?',
        'answer' => '25', 'wrong' => ['4', '50', '40'], 'explanation' => null],
    'Which fraction is the biggest?' => [
        'question' => 'Quelle fraction est la plus grande ?',
        'answer' => '1/3', 'wrong' => ['1/4', '1/5', '1/6'],
        'explanation' => 'Partager en moins de parts donne des parts plus grandes.'],
    'What is 1/3 + 1/3?' => [
        'question' => 'Combien font 1/3 + 1/3 ?',
        'answer' => '2/3', 'wrong' => ['2/6', '1/9', '1/6'], 'explanation' => null],
    'Which fraction is the same as 50%?' => [
        'question' => 'Quelle fraction est égale à 50 % ?',
        'answer' => '1/2', 'wrong' => ['1/5', '1/4', '1/50'], 'explanation' => null],
    'What is 4/8 in simplest form?' => [
        'question' => 'Comment écrire 4/8 sous sa forme la plus simple ?',
        'answer' => '1/2', 'wrong' => ['2/4', '1/4', '4/2'], 'explanation' => null],
    'What is 1/2 + 1/4?' => [
        'question' => 'Combien font 1/2 + 1/4 ?',
        'answer' => '3/4', 'wrong' => ['2/6', '1/6', '2/4'], 'explanation' => null],
    'What is 2/5 of 20?' => [
        'question' => 'Combien font 2/5 de 20 ?',
        'answer' => '8', 'wrong' => ['5', '10', '4'], 'explanation' => null],
    'What is 3/4 written as a decimal?' => [
        'question' => 'Comment s\'écrit 3/4 en nombre décimal ?',
        'answer' => '0,75', 'wrong' => ['0,34', '0,43', '0,25'], 'explanation' => null],
    'What is 6/9 in simplest form?' => [
        'question' => 'Comment écrire 6/9 sous sa forme la plus simple ?',
        'answer' => '2/3', 'wrong' => ['3/4', '1/3', '6/9 ne se simplifie pas'], 'explanation' => null],
];
