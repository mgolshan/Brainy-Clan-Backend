<?php

use App\Modules\ChallengeTypes\ChallengeTypeRegistry;
use App\Modules\ChallengeTypes\Handlers\MultipleChoice;
use App\Modules\ChallengeTypes\Handlers\WisdomCard;
use App\Modules\Content\Models\ChallengeType;
use App\Modules\Content\Models\ContentItem;

it('resolves the launch handlers from the registry', function () {
    $registry = app(ChallengeTypeRegistry::class);

    expect($registry->get('multiple_choice'))->toBeInstanceOf(MultipleChoice::class)
        ->and($registry->get('wisdom_card'))->toBeInstanceOf(WisdomCard::class)
        ->and($registry->activeKeys())->toBe(['multiple_choice', 'wisdom_card'])
        ->and(fn () => $registry->get('nope'))->toThrow(InvalidArgumentException::class);
});

it('lets the admin switch a type off', function () {
    ChallengeType::query()->create(['key' => 'wisdom_card', 'name' => 'Wisdom', 'handler_class' => WisdomCard::class, 'is_active' => false]);

    expect(app(ChallengeTypeRegistry::class)->isActive('wisdom_card'))->toBeFalse()
        ->and(app(ChallengeTypeRegistry::class)->activeKeys())->toBe(['multiple_choice']);
});

it('multiple choice strips the answer and checks responses', function () {
    $item = ContentItem::factory()->make();
    $mc = new MultipleChoice;

    $client = $mc->forClient($item);
    expect($client)->toHaveKeys(['question', 'choices', 'image_url'])
        ->and(json_encode($client))->not->toContain('choice_id')
        ->and(json_encode($client))->not->toContain('explanation')
        ->and($mc->check($item, ['choice_id' => 'a']))->toBeTrue()
        ->and($mc->check($item, ['choice_id' => 'b']))->toBeFalse()
        ->and($mc->check($item, ['choice_id' => ['a']]))->toBeFalse()
        ->and($mc->check($item, []))->toBeFalse()
        ->and($mc->correctResponse($item))->toBe(['choice_id' => 'a'])
        ->and($mc->explanation($item))->toBe('Because.')
        ->and($mc->score($item, true))->toBe(1)
        ->and($mc->score($item, false))->toBe(0);
});

it('multiple choice validates payload and answer', function () {
    $mc = new MultipleChoice;

    expect($mc->validate(['question' => 'Q?', 'choices' => [['id' => 'a', 'text' => 'A'], ['id' => 'b', 'text' => 'B']]], ['choice_id' => 'a']))->toBe([]);

    $errors = $mc->validate(['question' => '', 'choices' => [['id' => 'a', 'text' => 'A'], ['id' => 'a', 'text' => 'B']], 'image_url' => 'not a url'], ['choice_id' => 'z']);
    expect($errors)->toHaveKeys(['payload.question', 'payload.choices', 'payload.image_url', 'answer.choice_id']);

    expect($mc->validate(['question' => 'Q?', 'choices' => [['id' => 'a', 'text' => 'A']]], ['choice_id' => 'a']))->toHaveKey('payload.choices');
});

it('wisdom card renders the card and validates it', function () {
    $item = ContentItem::factory()->wisdomCard()->make();
    $wc = new WisdomCard;

    expect($wc->forClient($item))->toMatchArray([
        'card' => ['text' => 'Little strokes fell great oaks.', 'author' => 'Benjamin Franklin', 'source' => "Poor Richard's Almanack"],
        'question' => 'What is the main idea?',
    ])->and($wc->check($item, ['choice_id' => 'a']))->toBeTrue();

    expect($wc->validate(['question' => 'Main idea?', 'choices' => [['id' => 'a', 'text' => 'x'], ['id' => 'b', 'text' => 'y']]], ['choice_id' => 'a']))
        ->toHaveKey('payload.card.text');
});

it('builds items from flat CSV rows', function () {
    $mc = (new MultipleChoice)->fromFlatRow(['question' => 'Capital of Peru?', 'choice_a' => 'Lima', 'choice_b' => 'Quito', 'choice_c' => '', 'correct' => 'A', 'explanation' => '']);
    expect($mc['payload']['choices'])->toHaveCount(2)
        ->and($mc['answer'])->toBe(['choice_id' => 'a', 'explanation' => null]);

    $wc = (new WisdomCard)->fromFlatRow(['card_text' => 'Know thyself.', 'card_author' => '', 'choice_a' => 'x', 'choice_b' => 'y', 'correct' => 'b']);
    expect($wc['payload']['card'])->toBe(['text' => 'Know thyself.', 'author' => null, 'source' => null])
        ->and($wc['payload']['question'])->toBe('What is the main idea?');
});
