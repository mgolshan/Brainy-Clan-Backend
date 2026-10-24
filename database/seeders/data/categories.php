<?php

/*
 * The 8 launch categories and their sub-scopes (design doc: "Launch categories").
 * Shape: slug => [name, icon, min_age, children[slug => name | [name, min_age, max_age, children]]].
 * Optional `generators`: generated challenge types the node offers (GeneratorSpec shape:
 * type, options, min_difficulty, max_difficulty), e.g. Sudoku sizes and Math Sprint modes.
 * Sort order follows the array order. Top-level order matters for the free tier: the first
 * `limits.max_categories` visible top-level categories are unlocked. Since B7 every category
 * is playable; the first three (Geography, Science, Wisdom) are unchanged, then the B7 games.
 */

return [
    'world-geography' => [
        'name' => ['en' => 'World Geography', 'fr' => 'Géographie du monde'],
        'icon' => 'globe',
        'min_age' => 6,
        'children' => [
            'capitals' => [
                'name' => ['en' => 'Capitals', 'fr' => 'Capitales'],
                'children' => [
                    'capitals-europe' => ['name' => ['en' => 'Europe', 'fr' => 'Europe']],
                    'capitals-asia' => ['name' => ['en' => 'Asia', 'fr' => 'Asie']],
                    'capitals-africa' => ['name' => ['en' => 'Africa', 'fr' => 'Afrique']],
                    'capitals-americas' => ['name' => ['en' => 'Americas', 'fr' => 'Amériques']],
                    'capitals-oceania' => ['name' => ['en' => 'Oceania', 'fr' => 'Océanie']],
                ],
            ],
            'flags' => ['name' => ['en' => 'Flags', 'fr' => 'Drapeaux']],
            'continents' => ['name' => ['en' => 'Continents', 'fr' => 'Continents']],
            'landmarks' => ['name' => ['en' => 'Landmarks', 'fr' => 'Monuments']],
        ],
    ],
    'science-nature' => [
        'name' => ['en' => 'Science & Nature', 'fr' => 'Sciences et nature'],
        'icon' => 'leaf',
        'min_age' => 6,
        'children' => [
            'animals' => ['name' => ['en' => 'Animals', 'fr' => 'Animaux']],
            'space' => ['name' => ['en' => 'Space', 'fr' => 'Espace']],
            'human-body' => ['name' => ['en' => 'Human Body', 'fr' => 'Corps humain']],
        ],
    ],
    'wisdom-cards' => [
        'name' => ['en' => 'Wisdom Cards', 'fr' => 'Cartes de sagesse'],
        'icon' => 'quote',
        'min_age' => 8,
        'children' => [
            'stoicism' => ['name' => ['en' => 'Stoicism', 'fr' => 'Stoïcisme'], 'min_age' => 10],
            'habits' => ['name' => ['en' => 'Habits', 'fr' => 'Habitudes']],
            'kindness' => ['name' => ['en' => 'Kindness', 'fr' => 'Gentillesse']],
            'money-basics' => ['name' => ['en' => 'Money Basics', 'fr' => "L'argent, les bases"]],
        ],
    ],
    'math-sprint' => [
        'name' => ['en' => 'Math Sprint', 'fr' => 'Sprint de maths'],
        'icon' => 'calculator',
        'min_age' => 6,
        'children' => [
            'times-tables' => ['name' => ['en' => 'Times Tables', 'fr' => 'Tables de multiplication'], 'generators' => [['type' => 'math_sprint', 'options' => ['mode' => 'times_tables'], 'min_difficulty' => 1, 'max_difficulty' => 10]]],
            'mental-math' => ['name' => ['en' => 'Mental Math', 'fr' => 'Calcul mental'], 'generators' => [['type' => 'math_sprint', 'options' => ['mode' => 'mental'], 'min_difficulty' => 1, 'max_difficulty' => 10]]],
            'fractions' => ['name' => ['en' => 'Fractions', 'fr' => 'Fractions'], 'min_age' => 8, 'generators' => [['type' => 'math_sprint', 'options' => ['mode' => 'fractions'], 'min_difficulty' => 1, 'max_difficulty' => 10]]],
        ],
    ],
    'sudoku' => [
        'name' => ['en' => 'Sudoku', 'fr' => 'Sudoku'],
        'icon' => 'grid',
        'min_age' => 5,
        'children' => [
            'sudoku-4x4' => ['name' => ['en' => '4x4 (kids)', 'fr' => '4x4 (enfants)'], 'max_age' => 12, 'generators' => [['type' => 'sudoku', 'options' => ['size' => 4], 'min_difficulty' => 1, 'max_difficulty' => 3]]],
            'sudoku-6x6' => ['name' => ['en' => '6x6', 'fr' => '6x6'], 'generators' => [['type' => 'sudoku', 'options' => ['size' => 6], 'min_difficulty' => 3, 'max_difficulty' => 6]]],
            'sudoku-9x9' => [
                'name' => ['en' => '9x9', 'fr' => '9x9'],
                'min_age' => 10,
                'children' => [
                    'sudoku-9x9-easy' => ['name' => ['en' => 'Easy', 'fr' => 'Facile'], 'generators' => [['type' => 'sudoku', 'options' => ['size' => 9], 'min_difficulty' => 5, 'max_difficulty' => 6]]],
                    'sudoku-9x9-medium' => ['name' => ['en' => 'Medium', 'fr' => 'Moyen'], 'generators' => [['type' => 'sudoku', 'options' => ['size' => 9], 'min_difficulty' => 7, 'max_difficulty' => 8]]],
                    'sudoku-9x9-hard' => ['name' => ['en' => 'Hard', 'fr' => 'Difficile'], 'min_age' => 12, 'generators' => [['type' => 'sudoku', 'options' => ['size' => 9], 'min_difficulty' => 9, 'max_difficulty' => 10]]],
                ],
            ],
        ],
    ],
    'word-puzzles' => [
        'name' => ['en' => 'Word Puzzles', 'fr' => 'Jeux de mots'],
        'icon' => 'puzzle',
        'min_age' => 6,
        'children' => [
            'anagrams' => ['name' => ['en' => 'Anagrams', 'fr' => 'Anagrammes']],
            'word-search' => ['name' => ['en' => 'Word Search', 'fr' => 'Mots cachés']],
            'missing-letter' => ['name' => ['en' => 'Missing Letter', 'fr' => 'Lettre manquante']],
        ],
    ],
    'history' => [
        'name' => ['en' => 'History', 'fr' => 'Histoire'],
        'icon' => 'landmark',
        'min_age' => 8,
        'children' => [
            'history-americas' => ['name' => ['en' => 'Americas', 'fr' => 'Amériques']],
            'history-europe' => ['name' => ['en' => 'Europe', 'fr' => 'Europe']],
            'history-asia' => ['name' => ['en' => 'Asia', 'fr' => 'Asie']],
            'ancient-world' => ['name' => ['en' => 'Ancient World', 'fr' => 'Antiquité']],
        ],
    ],
    'vocabulary-spelling' => [
        'name' => ['en' => 'Vocabulary & Spelling', 'fr' => 'Vocabulaire et orthographe'],
        'icon' => 'book',
        'min_age' => 6,
        'children' => [
            'grade-levels' => [
                'name' => ['en' => 'By Grade Level', 'fr' => 'Par niveau scolaire'],
                'children' => [
                    'grades-1-3' => ['name' => ['en' => 'Grades 1–3', 'fr' => '1re à 3e année'], 'max_age' => 10],
                    'grades-4-6' => ['name' => ['en' => 'Grades 4–6', 'fr' => '4e à 6e année'], 'min_age' => 8],
                    'grades-7-9' => ['name' => ['en' => 'Grades 7–9', 'fr' => '7e à 9e année'], 'min_age' => 11],
                ],
            ],
            'idioms' => ['name' => ['en' => 'Idioms', 'fr' => 'Expressions']],
            'synonyms' => ['name' => ['en' => 'Synonyms', 'fr' => 'Synonymes']],
            'spelling' => ['name' => ['en' => 'Spelling', 'fr' => 'Orthographe']],
        ],
    ],

    // Added 2026-10-23 (Moe: "8 categories is not enough"). Multiple choice content.
    'logic-riddles' => [
        'name' => ['en' => 'Logic & Riddles', 'fr' => 'Logique et énigmes'],
        'icon' => 'bulb',
        'min_age' => 6,
        'children' => [
            'patterns' => ['name' => ['en' => 'Patterns', 'fr' => 'Suites logiques']],
            'riddles' => ['name' => ['en' => 'Riddles', 'fr' => 'Devinettes']],
            'brain-teasers' => ['name' => ['en' => 'Brain Teasers', 'fr' => 'Casse-têtes'], 'min_age' => 9],
        ],
    ],
    'language-swap' => [
        'name' => ['en' => 'Language Swap', 'fr' => 'Échange de langues'],
        'icon' => 'language',
        'min_age' => 6,
        'children' => [
            'everyday-words' => ['name' => ['en' => 'Everyday Words', 'fr' => 'Mots de tous les jours']],
            'numbers-colours' => ['name' => ['en' => 'Numbers & Colours', 'fr' => 'Nombres et couleurs']],
            'phrases' => ['name' => ['en' => 'Useful Phrases', 'fr' => 'Phrases utiles'], 'min_age' => 8],
        ],
    ],
    'inventions-technology' => [
        'name' => ['en' => 'Inventions & Technology', 'fr' => 'Inventions et technologie'],
        'icon' => 'rocket',
        'min_age' => 7,
        'children' => [
            'inventions' => ['name' => ['en' => 'Inventions', 'fr' => 'Inventions']],
            'how-things-work' => ['name' => ['en' => 'How Things Work', 'fr' => 'Comment ça marche']],
            'computers-coding' => ['name' => ['en' => 'Computers & Coding', 'fr' => 'Ordinateurs et programmation'], 'min_age' => 9],
        ],
    ],
    'our-planet' => [
        'name' => ['en' => 'Our Planet', 'fr' => 'Notre planète'],
        'icon' => 'earth',
        'min_age' => 6,
        'children' => [
            'weather' => ['name' => ['en' => 'Weather & Seasons', 'fr' => 'Météo et saisons']],
            'oceans' => ['name' => ['en' => 'Oceans & Rivers', 'fr' => 'Océans et rivières']],
            'volcanoes-earthquakes' => ['name' => ['en' => 'Volcanoes & Earthquakes', 'fr' => 'Volcans et séismes']],
            'ecosystems-recycling' => ['name' => ['en' => 'Ecosystems & Recycling', 'fr' => 'Écosystèmes et recyclage']],
        ],
    ],
    'art-music' => [
        'name' => ['en' => 'Art & Music', 'fr' => 'Art et musique'],
        'icon' => 'palette',
        'min_age' => 6,
        'children' => [
            'art-artists' => ['name' => ['en' => 'Art & Artists', 'fr' => 'Art et artistes']],
            'instruments' => ['name' => ['en' => 'Instruments', 'fr' => 'Instruments']],
            'music-basics' => ['name' => ['en' => 'Music Basics', 'fr' => 'Bases de la musique']],
        ],
    ],
    'food-world' => [
        'name' => ['en' => 'Food Around the World', 'fr' => 'Cuisine du monde'],
        'icon' => 'restaurant',
        'min_age' => 5,
        'children' => [
            'fruits-vegetables' => ['name' => ['en' => 'Fruits & Vegetables', 'fr' => 'Fruits et légumes']],
            'world-dishes' => ['name' => ['en' => 'World Dishes', 'fr' => 'Plats du monde']],
            'food-origins' => ['name' => ['en' => 'Where Food Comes From', 'fr' => "D'où vient notre nourriture"]],
        ],
    ],
    'sports-games' => [
        'name' => ['en' => 'Sports & Games', 'fr' => 'Sports et jeux'],
        'icon' => 'football',
        'min_age' => 6,
        'children' => [
            'rules-equipment' => ['name' => ['en' => 'Rules & Equipment', 'fr' => 'Règles et équipement']],
            'olympics' => ['name' => ['en' => 'Olympic Games', 'fr' => 'Jeux olympiques']],
            'board-card-games' => ['name' => ['en' => 'Board & Card Games', 'fr' => 'Jeux de société et de cartes']],
        ],
    ],
    'brain-games' => [
        'name' => ['en' => 'Brain Games', 'fr' => 'Jeux de cerveau'],
        'icon' => 'sparkles',
        'min_age' => 4,
        'children' => [
            'memory-match' => ['name' => ['en' => 'Memory Match', 'fr' => 'Jeu de mémoire'], 'generators' => [['type' => 'memory_match', 'options' => [], 'min_difficulty' => 1, 'max_difficulty' => 10]]],
            'pattern-sequence' => ['name' => ['en' => 'Pattern & Sequence', 'fr' => 'Suites à répéter'], 'generators' => [['type' => 'pattern_sequence', 'options' => [], 'min_difficulty' => 1, 'max_difficulty' => 10]]],
            'nonogram' => ['name' => ['en' => 'Nonograms', 'fr' => 'Logimages'], 'min_age' => 8, 'generators' => [['type' => 'nonogram', 'options' => [], 'min_difficulty' => 3, 'max_difficulty' => 10]]],
            'spot-difference' => ['name' => ['en' => 'Spot the Difference', 'fr' => 'Jeu des différences'], 'generators' => [['type' => 'spot_difference', 'options' => [], 'min_difficulty' => 1, 'max_difficulty' => 10]]],
        ],
    ],
    'life-skills' => [
        'name' => ['en' => 'Life Skills & Safety', 'fr' => 'Vie pratique et sécurité'],
        'icon' => 'shield',
        'min_age' => 5,
        'children' => [
            'road-safety' => ['name' => ['en' => 'Road Safety', 'fr' => 'Sécurité routière']],
            'first-aid' => ['name' => ['en' => 'First-Aid Basics', 'fr' => 'Premiers soins'], 'min_age' => 8],
            'online-safety' => ['name' => ['en' => 'Online Safety', 'fr' => 'Sécurité en ligne'], 'min_age' => 8],
            'everyday-manners' => ['name' => ['en' => 'Everyday Manners', 'fr' => 'Bonnes manières']],
        ],
    ],
];
