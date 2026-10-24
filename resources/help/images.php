<?php

/*
 * Screenshots of the "How to play" help center: key => [path under public/, width, height].
 * help/*.webp are captures of the demo family on the app's web build (390×844 phone at 2x,
 * cropped, GD WebP q78; clan-variants is the site's Clan SVGs rendered at 2x); screens/*.webp are
 * shared with the landing page. Alt texts live in the language files. Keep each file ≤ 60 KB and
 * the page's images ≤ 1.5 MB (WebsiteTest).
 */

return [
    'create-family' => ['images/site/help/create-family.webp', 480, 948],
    'invite-code' => ['images/site/help/invite-code.webp', 480, 825],
    'sign-in-code' => ['images/site/help/sign-in-code.webp', 480, 898],
    'play-categories' => ['images/site/screens/play-categories.webp', 480, 1039],
    'game-question' => ['images/site/screens/game-question.webp', 480, 1039],
    'game-sudoku' => ['images/site/screens/game-sudoku.webp', 480, 1039],
    'brain-memory' => ['images/site/screens/brain-memory.webp', 333, 403],
    'goal-team' => ['images/site/screens/world-jungle.webp', 480, 1039],
    'worlds-picker' => ['images/site/screens/worlds-picker.webp', 480, 1039],
    'clan-variants' => ['images/site/help/clan-variants.webp', 960, 164],
    'chat-family' => ['images/site/screens/chat-family.webp', 480, 1039],
    'chat-report' => ['images/site/help/chat-report.webp', 480, 794],
    'member-settings' => ['images/site/help/member-settings.webp', 480, 911],
    'difficulty' => ['images/site/help/difficulty.webp', 480, 246],
    'notifications' => ['images/site/help/notifications.webp', 480, 898],
    'family-tab' => ['images/site/help/family-tab.webp', 480, 652],
    'lockout' => ['images/site/help/lockout.webp', 480, 812],
];
