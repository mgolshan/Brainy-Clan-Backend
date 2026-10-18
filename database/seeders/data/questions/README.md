# Launch question bank (seed data)

Brainy Clan is a family quiz app (kids 5+, teens, adults, seniors). Content is seed data in the backend repo
`F:\Docker-Projects\Brainy-Clan\Brainy-Clan-Backend\database\seeders\data\`. Items are PUBLISHED to real families
after one fact-check pass, so accuracy matters more than volume. Moe (owner) said: "make sure you make no mistakes".

## Files

- Multiple choice + wisdom cards: `database/seeders/data/questions/<area>.php` (one file per area, e.g.
  `geography.php`, `science.php`, `history.php`, `vocabulary.php`, `wisdom.php`). Each returns a PHP list:

```php
<?php

/*
 * <Area> questions (original Brainy Clan content). Shape: see database/seeders/data/questions/README.md.
 */

return [
    [
        'id' => 'flags-001',               // stable, unique across ALL files, never renamed or reused
        'category' => 'flags',             // a LEAF category slug from data/categories.php
        'difficulty' => 3,                 // 1-10 (1 = age 5 easy, 5 = age 9-10, 8+ = adult trivia)
        // 'min_age' => 9,                 // optional; default derived from difficulty (<=2:5, <=4:7, <=6:9, else 11)
        // 'cross_locale' => false,        // optional; false = language-bound item (spelling, idioms, synonyms,
                                           // vocabulary): never shown to members of another language
        'en' => [
            'question' => 'Which country\'s flag has a red maple leaf?',
            'answer' => 'Canada',
            'wrong' => ['Japan', 'Switzerland', 'Peru'],     // exactly 3 for multiple choice
            'explanation' => 'The maple leaf flag became Canada\'s flag in 1965.',  // or null; 1 short sentence
        ],
        'fr' => [                          // optional; a faithful French translation of the same item
            'question' => 'Quel pays a un drapeau avec une feuille d\'érable rouge ?',
            'answer' => 'Canada',
            'wrong' => ['Japon', 'Suisse', 'Pérou'],
            'explanation' => 'Le drapeau à la feuille d\'érable est devenu celui du Canada en 1965.',
        ],
    ],
    // A French-only item (language-bound): no 'en' key, only 'fr', with 'cross_locale' => false.
];
```

- Wisdom cards (`'type' => 'wisdom_card'`) use instead of question/wrong:
  `'en' => ['text' => quote or original card text, 'author' => string|null, 'source' => string|null,
  'answer' => the main idea, 'wrong' => [exactly 2 wrong ideas], 'explanation' => one sentence]`.
  The question is fixed by the seeder ("What is the main idea?" / "Quelle est l'idée principale ?").
  French `source` for a translated quote: e.g. "Lettres à Lucilius (traduction Brainy Clan)".
- Ids: `<leaf-slug>-NNN` (3 digits). For an area, number from 001; french-only items `<leaf-slug>-fr-NNN`.
- Strings: PHP single quotes, escape `\'`. UTF-8. French typography: a normal space before `?` `!` `:` `;`
  (e.g. `Quelle est la capitale ?`), « » are optional, use `’` or `'` consistently (use `'`).
- Run `docker compose exec app php -l database/seeders/data/questions/<file>.php` from the backend repo root to
  syntax-check (no PHP on the Windows host; Docker only).

## Content rules (all mandatory)

1. **Only well-established facts** you are certain of. No records that change ("tallest building", "most
   populous"), no populations, no "current" office holders, no disputed or folk "facts" (e.g. "Great Wall
   visible from space", "goldfish 3-second memory", "we use 10% of our brain"), no exact numbers that sources
   disagree on. If you are not sure, leave the item out.
2. **Exactly one defensible answer.** Distractors are plausible and of the same kind (all countries, all years…)
   but clearly wrong. No "all of the above", no trick questions, no negatives ("Which is NOT…") for difficulty < 5.
   The answer text must not appear in the question. All 4 choices are distinct and similar in length.
3. **Original wording** (no copying from quiz sites or books). Wisdom quotes: only public-domain authors who died
   before 1955 and pre-1929 translations, or "Brainy Clan original" (author null, source null) — no modern authors.
4. **Child-safe and kind:** no violence detail, gore, scary content, politics, religion-as-truth, body shaming,
   stereotypes. History: factual, neutral, age-appropriate (wars may be named, not described graphically).
5. **Difficulty spread** across 1-10 within each leaf (roughly a third easy 1-3, a third 4-6, a third 7-10).
6. **Short:** question ≤ 120 characters, answers ≤ 40 characters, explanation ≤ 140 characters.
7. **Canadian/international neutral spelling in English is fine** (colour/color — prefer the spelling already used
   in the existing data files: "colour"). Units metric (Celsius, km), with no imperial-only facts.
8. **No duplicates** of existing items: read `database/seeders/data/multiple_choice.php`, `wisdom_cards.php`,
   `countries.php` (capitals/continents are generated from it) and other files in `data/questions/` first.
9. French is natural, correct French (Quebec and France both understand it; prefer neutral vocabulary). Translate
   the meaning, keep the same answer/distractors. If a question does not make sense in French (e.g. about English
   spelling), it is English-only with `'cross_locale' => false` — and then write separate French-only items about
   French instead.
10. Before finishing, re-read every item once more specifically to fact-check the answer AND each distractor
    (a distractor that is also true is a bug). Remove anything doubtful.

