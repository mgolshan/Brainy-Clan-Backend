<?php

/**
 * French chat filter list (docs/API.md "Chat safety"): whole words and short phrases only.
 * Matching is case- and accent-insensitive (so "enculé" also covers "encule"), catches common
 * disguises and never matches inside a longer word (député, dispute, culture). Keep ordinary words
 * out (e.g. "con", "bite", "chatte", "baiser", "retard", "douche", "pipe", "pédale", "crève" have
 * everyday meanings). `allow`: phrases that are never masked.
 *
 * @return array{words: list<string>, allow: list<string>}
 */
return [
    'words' => [
        // Jurons et insultes
        'merde', 'merdes', 'merdique', 'merdeux', 'merdeuse', 'emmerde', 'emmerder', 'emmerdeur',
        'emmerdeuse', 'emmerdeurs', 'sous-merde', 'bordel de merde',
        'putain', 'putains', 'ptain', 'ptn', 'pute', 'putes', 'fils de pute', 'fdp',
        'salope', 'salopes', 'saloperie', 'salaud', 'salauds', 'salopard', 'salopards',
        'connard', 'connards', 'connasse', 'connasses', 'conne', 'connes', 'pauvre con', 'gros con',
        'sale con', 'espèce de con',
        'enculé', 'enculés', 'enculée', 'enculées', 'enculer', 'enculeur',
        'nique', 'niquer', 'niqué', 'niquée', 'nique ta mère', 'nique ta race', 'ntm',
        'ta gueule', 'ferme ta gueule', 'ftg', 'fermez vos gueules', 'ta mère la pute',
        'bâtard', 'bâtards', 'bâtarde',
        'foutre', 'va te faire foutre', 'allez vous faire foutre',
        'chier', 'fais chier', 'faire chier', 'va chier', 'chieur', 'chieuse',
        'couille', 'couilles', 'couillon', 'couillonne', 'casse-couilles',
        'branleur', 'branleurs', 'branleuse', 'branler', 'branlette', 'se branler',
        'pétasse', 'pétasses', 'pouffiasse', 'pouffiasses', 'poufiasse', 'garce', 'garces',
        'trou du cul', 'trouduc', 'cul', 'culs', 'tête de nœud', 'enfoiré', 'enfoirés', 'enfoirée',
        'raclure', 'ducon', 'tocard', 'tocards',
        // Sexuel
        'nichons', 'nibards', 'teub', 'zob', 'queutard', 'partouze', 'porno', 'pornos',
        'suceuse', 'suce ma bite', 'baise-moi', 'nique ta sœur', 'turlute',
        'godemichet', 'sodomie', 'sodomiser', 'chaudasse', 'grognasse', 'catin', 'catins',
        // Insultes discriminatoires
        'pédé', 'pédés', 'tarlouze', 'tarlouzes', 'tafiole', 'tafioles',
        'fiotte', 'fiottes', 'gouine', 'gouines', 'négro', 'négros', 'bougnoule', 'bougnoules',
        'bicot', 'bicots', 'youpin', 'youpine', 'youpins', 'chinetoque', 'chinetoques', 'bamboula',
        'triso', 'mongolito', 'gogole', 'sale arabe', 'sale noir', 'sale juif', 'sale gitan',
        // Menaces
        'va mourir', 'suicide-toi',
    ],
    'allow' => [
        'cul-de-sac', 'culs-de-sac', 'cul de sac', 'cul-de-jatte', 'cul sec',
    ],
];
