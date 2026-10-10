<?php

/*
 * The 8 launch categories and their sub-scopes (design doc: "Launch categories").
 * Shape: slug => [name, icon, min_age, children[slug => name | [name, min_age, max_age, children]]].
 * Sort order follows the array order. Top-level order matters for the free tier: the first
 * `limits.max_categories` top-level categories are unlocked (playable content first).
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
    'math-sprint' => [
        'name' => ['en' => 'Math Sprint', 'fr' => 'Sprint de maths'],
        'icon' => 'calculator',
        'min_age' => 6,
        'children' => [
            'times-tables' => ['name' => ['en' => 'Times Tables', 'fr' => 'Tables de multiplication']],
            'mental-math' => ['name' => ['en' => 'Mental Math', 'fr' => 'Calcul mental']],
            'fractions' => ['name' => ['en' => 'Fractions', 'fr' => 'Fractions'], 'min_age' => 8],
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
    'sudoku' => [
        'name' => ['en' => 'Sudoku', 'fr' => 'Sudoku'],
        'icon' => 'grid',
        'min_age' => 5,
        'children' => [
            'sudoku-4x4' => ['name' => ['en' => '4x4 (kids)', 'fr' => '4x4 (enfants)'], 'max_age' => 12],
            'sudoku-6x6' => ['name' => ['en' => '6x6', 'fr' => '6x6']],
            'sudoku-9x9' => [
                'name' => ['en' => '9x9', 'fr' => '9x9'],
                'min_age' => 9,
                'children' => [
                    'sudoku-9x9-easy' => ['name' => ['en' => 'Easy', 'fr' => 'Facile']],
                    'sudoku-9x9-medium' => ['name' => ['en' => 'Medium', 'fr' => 'Moyen']],
                    'sudoku-9x9-hard' => ['name' => ['en' => 'Hard', 'fr' => 'Difficile'], 'min_age' => 12],
                ],
            ],
        ],
    ],
];
