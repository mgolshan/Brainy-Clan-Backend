<?php

use App\Modules\Content\Models\ContentItem;
use Database\Seeders\CategorySeeder;
use Database\Seeders\ChallengeTypeSeeder;
use Database\Seeders\ContentSeeder;
use Database\Seeders\QuestionBank;
use Database\Seeders\WordPuzzleSeeder;
use Illuminate\Support\Facades\File;

/**
 * The launch question bank (database/seeders/data/questions/*.php) and the French generated
 * content: strict validation, translation groups and locales.
 */
beforeEach(function () {
    $this->seed([CategorySeeder::class, ChallengeTypeSeeder::class]);
    $this->fixtures = sys_get_temp_dir().'/question-bank-'.uniqid();
    File::ensureDirectoryExists($this->fixtures.'/questions');

    $this->writeBank = function (array $files): string {
        File::cleanDirectory($this->fixtures.'/questions');

        foreach ($files as $name => $entries) {
            file_put_contents($this->fixtures.'/questions/'.$name, '<?php return '.var_export($entries, true).';');
        }

        return $this->fixtures.'/questions';
    };
});

afterEach(function () {
    File::deleteDirectory($this->fixtures);
});

function mcEntry(string $id, array $overrides = []): array
{
    return [
        'id' => $id,
        'category' => 'flags',
        'difficulty' => 3,
        'en' => ['question' => 'Which flag has a maple leaf?', 'answer' => 'Canada', 'wrong' => ['Japan', 'Peru', 'Chile'], 'explanation' => null],
        ...$overrides,
    ];
}

it('accepts the real question bank data files', function () {
    $entries = QuestionBank::load(database_path('seeders/data/questions'), ContentSeeder::leafCategorySlugs());

    expect($entries)->toBeArray();
});

it('accepts the real French country and word data files when present', function () {
    $countriesFr = database_path('seeders/data/countries_fr.php');

    if (is_file($countriesFr)) {
        $seeder = app(ContentSeeder::class);
        $seeder->runFrenchGeography();
        expect(ContentItem::query()->where('locale', 'fr')->count())->toBeGreaterThan(0);
    }

    $wordsFr = database_path('seeders/data/words_fr.php');

    if (is_file($wordsFr)) {
        expect(WordPuzzleSeeder::validWords(require $wordsFr, 'words_fr.php'))->toBeArray();
    }

    expect(true)->toBeTrue();
});

it('rejects broken entries with a message naming the id', function (array $files, string $message) {
    $path = ($this->writeBank)($files);

    expect(fn () => QuestionBank::load($path, ContentSeeder::leafCategorySlugs()))
        ->toThrow(RuntimeException::class, $message);
})->with([
    'duplicate id across files' => [['a.php' => [mcEntry('flags-001')], 'b.php' => [mcEntry('flags-001')]], 'duplicate id [flags-001]'],
    'unknown category' => [['a.php' => [mcEntry('x-001', ['category' => 'nope'])]], '[x-001]: category [nope]'],
    'non-leaf category' => [['a.php' => [mcEntry('x-002', ['category' => 'capitals'])]], '[x-002]: category [capitals]'],
    'difficulty too high' => [['a.php' => [mcEntry('x-003', ['difficulty' => 11])]], '[x-003]: difficulty'],
    'difficulty zero' => [['a.php' => [mcEntry('x-004', ['difficulty' => 0])]], '[x-004]: difficulty'],
    'two wrong answers on multiple choice' => [['a.php' => [mcEntry('x-005', ['en' => ['question' => 'Q?', 'answer' => 'A', 'wrong' => ['B', 'C'], 'explanation' => null]])]], '[x-005]: [en.wrong] must list exactly 3'],
    'three wrong answers on a wisdom card' => [['a.php' => [mcEntry('x-006', ['type' => 'wisdom_card', 'category' => 'habits', 'en' => ['text' => 'T', 'author' => null, 'source' => null, 'answer' => 'A', 'wrong' => ['B', 'C', 'D'], 'explanation' => null]])]], '[x-006]: [en.wrong] must list exactly 2'],
    'answer repeated in wrong' => [['a.php' => [mcEntry('x-007', ['fr' => ['question' => 'Q ?', 'answer' => 'Canada', 'wrong' => ['Japon', 'canada', 'Chili'], 'explanation' => null]])]], '[x-007]: [fr.wrong] repeats'],
    'empty question' => [['a.php' => [mcEntry('x-008', ['en' => ['question' => ' ', 'answer' => 'A', 'wrong' => ['B', 'C', 'D'], 'explanation' => null]])]], '[x-008]: [en.question]'],
    'empty wrong answer' => [['a.php' => [mcEntry('x-009', ['en' => ['question' => 'Q?', 'answer' => 'A', 'wrong' => ['B', '', 'D'], 'explanation' => null]])]], '[x-009]: [en.wrong.1]'],
    'empty explanation string' => [['a.php' => [mcEntry('x-010', ['en' => ['question' => 'Q?', 'answer' => 'A', 'wrong' => ['B', 'C', 'D'], 'explanation' => '']])]], '[x-010]: [en.explanation]'],
    'no locale' => [['a.php' => [['id' => 'x-011', 'category' => 'flags', 'difficulty' => 2]]], '[x-011]: needs an "en" and/or "fr" block'],
    'unknown type' => [['a.php' => [mcEntry('x-012', ['type' => 'quiz'])]], '[x-012]: unknown type'],
]);

it('accepts an empty or missing question bank directory', function () {
    expect(QuestionBank::load(($this->writeBank)([]), ['flags']))->toBe([])
        ->and(QuestionBank::load($this->fixtures.'/missing', ['flags']))->toBe([]);
});

it('seeds English and French versions in one translation group, idempotently', function () {
    $seeder = app(ContentSeeder::class);
    $seeder->questionsPath = ($this->writeBank)([
        'geo.php' => [mcEntry('flags-001', [
            'min_age' => 8,
            'fr' => ['question' => "Quel drapeau a une feuille d'érable ?", 'answer' => 'Canada', 'wrong' => ['Japon', 'Pérou', 'Chili'], 'explanation' => 'Depuis 1965.'],
        ])],
        'vocab.php' => [
            ['id' => 'spelling-001', 'category' => 'spelling', 'difficulty' => 2, 'en' => ['question' => 'Which is spelled right?', 'answer' => 'because', 'wrong' => ['becuase', 'becose', 'beacuse'], 'explanation' => null]],
            ['id' => 'spelling-fr-001', 'category' => 'spelling', 'difficulty' => 2, 'cross_locale' => false, 'fr' => ['question' => 'Quel mot est bien écrit ?', 'answer' => 'beaucoup', 'wrong' => ['baucoup', 'beaucou', 'boucoup'], 'explanation' => null]],
        ],
        'wisdom.php' => [[
            'id' => 'habits-001', 'type' => 'wisdom_card', 'category' => 'habits', 'difficulty' => 7,
            'en' => ['text' => 'Well begun is half done.', 'author' => 'Aristotle', 'source' => null, 'answer' => 'Start well', 'wrong' => ['Never start', 'Stop halfway'], 'explanation' => 'A good start helps.'],
            'fr' => ['text' => 'Bien commencer, c\'est à moitié fini.', 'author' => 'Aristote', 'source' => null, 'answer' => 'Bien commencer', 'wrong' => ['Ne jamais commencer', 'Arrêter à mi-chemin'], 'explanation' => 'Un bon départ aide.'],
        ]],
    ]);

    $seeder->runQuestionBank();
    $seeder->runQuestionBank();

    $items = ContentItem::query()->where('source_ref', 'like', 'seed:q:%')->get()->keyBy('source_ref');

    expect($items)->toHaveCount(6);

    $en = $items['seed:q:flags-001:en'];
    $fr = $items['seed:q:flags-001:fr'];
    expect($en->locale)->toBe('en')->and($fr->locale)->toBe('fr')
        ->and($en->translation_group)->toBe('q:flags-001')->and($fr->translation_group)->toBe('q:flags-001')
        ->and($en->cross_locale)->toBeTrue()
        ->and($en->min_age)->toBe(8)
        ->and($en->status)->toBe(ContentItem::STATUS_PUBLISHED)
        ->and($fr->payload['question'])->toBe("Quel drapeau a une feuille d'érable ?")
        ->and(collect($fr->payload['choices'])->firstWhere('id', $fr->answer['choice_id'])['text'])->toBe('Canada')
        ->and(collect($fr->payload['choices'])->pluck('text')->sort()->values()->all())->toBe(['Canada', 'Chili', 'Japon', 'Pérou'])
        ->and($fr->answer['explanation'])->toBe('Depuis 1965.');

    expect($items['seed:q:spelling-001:en']->cross_locale)->toBeFalse()
        ->and($items['seed:q:spelling-001:en']->min_age)->toBe(ContentSeeder::minAgeFor(2))
        ->and($items['seed:q:spelling-fr-001:fr']->locale)->toBe('fr');

    $card = $items['seed:q:habits-001:fr'];
    expect($card->type_key)->toBe('wisdom_card')
        ->and($card->payload['question'])->toBe("Quelle est l'idée principale ?")
        ->and($card->payload['card'])->toBe(['text' => 'Bien commencer, c\'est à moitié fini.', 'author' => 'Aristote', 'source' => null])
        ->and($card->payload['choices'])->toHaveCount(3)
        ->and($items['seed:q:habits-001:en']->payload['question'])->toBe('What is the main idea?');
});

it('seeds French capitals and continents from countries_fr.php in the English translation groups', function () {
    $path = $this->fixtures.'/countries_fr.php';
    file_put_contents($path, '<?php return '.var_export([
        'France' => ['country' => 'France', 'capital' => 'Paris', 'continent' => 'Europe', 'capital_question' => 'Quelle est la capitale de la France ?'],
        'Italy' => ['country' => 'Italie', 'capital' => 'Rome', 'continent' => 'Europe'],
        'Germany' => ['country' => 'Allemagne', 'capital' => 'Berlin', 'continent' => 'Europe'],
        'Spain' => ['country' => 'Espagne', 'capital' => 'Madrid', 'continent' => 'Europe'],
        'Japan' => ['country' => 'Japon', 'capital' => 'Tokyo', 'continent' => 'Asie'],
    ], true).';');

    $seeder = app(ContentSeeder::class);
    $seeder->countriesFrPath = $path;
    $seeder->runFrenchGeography();

    $capital = ContentItem::query()->where('source_ref', 'seed:capital:France:fr')->firstOrFail();
    expect($capital->locale)->toBe('fr')
        ->and($capital->translation_group)->toBe('capital:France')
        ->and($capital->payload['question'])->toBe('Quelle est la capitale de la France ?')
        ->and(collect($capital->payload['choices'])->pluck('text')->sort()->values()->all())->toBe(['Berlin', 'Madrid', 'Paris', 'Rome']);

    $italy = ContentItem::query()->where('source_ref', 'seed:capital:Italy:fr')->firstOrFail();
    expect($italy->payload['question'])->toBe('Quelle est la capitale de ce pays : Italie ?');

    // Only one French capital in Asia: no capital item (too few distractors), the continent item still exists.
    expect(ContentItem::query()->where('source_ref', 'seed:capital:Japan:fr')->exists())->toBeFalse();

    $continent = ContentItem::query()->where('source_ref', 'seed:continent:Japan:fr')->firstOrFail();
    expect($continent->translation_group)->toBe('continent:Japan')
        ->and($continent->payload['question'])->toBe('Sur quel continent se trouve ce pays : Japon ?')
        ->and(collect($continent->payload['choices'])->firstWhere('id', $continent->answer['choice_id'])['text'])->toBe('Asie')
        ->and(collect($continent->payload['choices'])->pluck('text')->diff(ContentSeeder::CONTINENTS_FR)->all())->toBe([]);
});

it('rejects a countries_fr.php entry for an unknown country or continent', function (array $data, string $message) {
    $path = $this->fixtures.'/countries_fr.php';
    file_put_contents($path, '<?php return '.var_export($data, true).';');

    $seeder = app(ContentSeeder::class);
    $seeder->countriesFrPath = $path;

    expect(fn () => $seeder->runFrenchGeography())->toThrow(RuntimeException::class, $message);
})->with([
    'unknown country' => [['Atlantis' => ['country' => 'Atlantide', 'capital' => 'X', 'continent' => 'Europe']], '[Atlantis] is not a country'],
    'unknown continent' => [['France' => ['country' => 'France', 'capital' => 'Paris', 'continent' => 'Eurasie']], 'continent [Eurasie]'],
]);

it('marks language-bound seed items and adds French generated math in the same groups', function () {
    $seeder = app(ContentSeeder::class);
    $seeder->questionsPath = ($this->writeBank)([]);
    $seeder->countriesFrPath = $this->fixtures.'/none.php';
    $seeder->run();

    $times = ContentItem::query()->where('translation_group', 'times:3x4')->get()->keyBy('locale');
    expect($times->keys()->sort()->values()->all())->toBe(['en', 'fr'])
        ->and($times['fr']->payload['question'])->toBe('Combien font 3 × 4 ?')
        ->and($times['en']->source_ref)->toBe('seed:times:3x4');

    expect(ContentItem::query()->where('translation_group', 'mental:72 ÷ 8')->where('locale', 'fr')->value('payload')['question'])->toBe('Combien font 72 ÷ 8 ?')
        ->and(ContentItem::query()->where('source_ref', 'seed:capital:France')->value('translation_group'))->toBe('capital:France')
        ->and(ContentItem::query()->where('source_ref', 'seed:anagram:PLANET')->value('cross_locale'))->toBeFalse()
        ->and(ContentItem::query()->where('source_ref', 'like', 'seed:%:fr')->where('translation_group', 'like', 'capital:%')->exists())->toBeFalse();

    $languageBound = ContentItem::query()->whereHas('category', fn ($q) => $q->whereIn('slug', ContentSeeder::LANGUAGE_BOUND_CATEGORIES))->pluck('cross_locale')->unique()->values()->all();
    expect($languageBound)->toBe([false]);

    $flags = ContentItem::query()->whereHas('category', fn ($q) => $q->where('slug', 'flags'))->pluck('cross_locale')->unique()->values()->all();
    expect($flags)->toBe([true]);
});

it('seeds French word puzzles from words_fr.php without touching the English ones', function () {
    $path = $this->fixtures.'/words_fr.php';
    file_put_contents($path, '<?php return '.var_export([
        'Animaux' => [
            1 => [['CHAT', 'Il miaule'], ['CHIEN', 'Il aboie'], ['VACHE', 'Elle donne du lait'], ['LOUP', 'Il hurle'], ['OURS', 'Il aime le miel']],
            2 => [['TIGRE', 'Un grand chat rayé'], ['ZEBRE', 'Un cheval rayé'], ['LAPIN', 'Il a de longues oreilles'], ['SINGE', 'Il aime les bananes'], ['TORTUE', 'Elle porte sa maison'], ['CHEVAL', 'On peut le monter']],
            3 => [['ELEPHANT', 'Il a une trompe'], ['GIRAFE', 'Le plus grand animal'], ['DAUPHIN', 'Un mammifère marin'], ['PINGOUIN', 'Un oiseau du froid'], ['ECUREUIL', 'Il cache des noisettes'], ['HERISSON', 'Petit animal piquant'], ['CROCODILE', 'Un grand reptile']],
        ],
    ], true).';');

    $seeder = app(WordPuzzleSeeder::class);
    $seeder->frenchWordsPath = $path;
    $seeder->run();

    $chat = ContentItem::query()->where('source_ref', 'seed:fr:anagram:CHAT')->firstOrFail();
    expect($chat->locale)->toBe('fr')->and($chat->cross_locale)->toBeFalse()->and($chat->payload['hint'])->toBe('Il miaule')
        ->and(ContentItem::query()->where('source_ref', 'seed:fr:missing:CHAT')->exists())->toBeTrue()
        ->and(ContentItem::query()->where('source_ref', 'seed:fr:wordsearch:Animaux:1:1')->value('locale'))->toBe('fr')
        ->and(ContentItem::query()->where('source_ref', 'seed:anagram:CAT')->value('locale'))->toBe('en')
        ->and(ContentItem::query()->where('type_key', 'word_puzzle')->where('locale', 'en')->pluck('cross_locale')->unique()->values()->all())->toBe([false]);

    file_put_contents($path, '<?php return '.var_export(['Animaux' => [1 => [['ÉTÉ', 'Saison']]]], true).';');
    expect(fn () => $seeder->run())->toThrow(RuntimeException::class, 'uppercase A-Z without accents');
});
