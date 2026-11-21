<?php

/**
 * English chat filter list (docs/API.md "Chat safety"): whole words and short phrases only.
 * Matching is case- and accent-insensitive, catches common disguises (f.u.c.k, sh1t, fuuuck) and
 * never matches inside a longer word (class, Scunthorpe, assassin, cocktail). Keep ordinary words
 * out (e.g. "tit", "hoe", "prick", "homo", "cum" and "retard" have everyday meanings). `allow`:
 * phrases that are never masked.
 *
 * @return array{words: list<string>, allow: list<string>}
 */
return [
    'words' => [
        // Swearing
        'fuck', 'fucks', 'fucked', 'fucker', 'fuckers', 'fucking', 'fuckin', 'fuckhead', 'fuckheads',
        'fuckface', 'fuckoff', 'fuckwit', 'fuckup', 'fucktard', 'motherfucker', 'motherfuckers',
        'motherfucking', 'mofo', 'fck', 'fcking', 'fuk', 'fuking', 'fking', 'phuck', 'phuk', 'stfu',
        'gtfo', 'dumbfuck', 'clusterfuck',
        'shit', 'shits', 'shitty', 'shitting', 'shitted', 'shithead', 'shitheads', 'shitface',
        'shitbag', 'shithole', 'shitload', 'shite', 'bullshit', 'horseshit', 'dipshit', 'apeshit',
        'batshit', 'shitstorm',
        'ass', 'asses', 'asshole', 'assholes', 'arse', 'arsehole', 'arseholes', 'asshat', 'asswipe',
        'assclown', 'dumbass', 'dumbasses', 'jackass', 'jackasses', 'smartass', 'fatass', 'kiss my ass',
        'bitch', 'bitches', 'bitchy', 'bitching', 'son of a bitch', 'sonofabitch',
        'bastard', 'bastards',
        'damn', 'goddamn', 'goddamnit', 'goddammit', 'dammit', 'damnit',
        'crap', 'crappy',
        'piss', 'pissed', 'pissing', 'piss off',
        'bollocks', 'bloody hell', 'wanker', 'wankers', 'wank', 'wanking', 'tosser', 'tossers',
        'bellend', 'knobhead', 'twat', 'twats',
        'douchebag', 'douchebags', 'scumbag', 'scumbags', 'screw you',
        // Sexual
        'dick', 'dicks', 'dickhead', 'dickheads', 'cock', 'cocks', 'cocksucker', 'cocksuckers',
        'pussy', 'pussies', 'cunt', 'cunts', 'boobs', 'boobies', 'tits', 'titties', 'titty',
        'slut', 'sluts', 'slutty', 'whore', 'whores', 'skank', 'skanks', 'thot', 'milf',
        'porn', 'porno', 'pornhub', 'blowjob', 'blowjobs', 'handjob', 'handjobs', 'rimjob',
        'dildo', 'dildos', 'jizz', 'cumshot', 'boner', 'horny', 'butthole', 'buttplug',
        'jerk off', 'jerkoff', 'jack off', 'suck my dick', 'send nudes', 'sexting', 'orgasm',
        'masturbate', 'masturbating', 'deepthroat', 'gangbang', 'hentai', 'nsfw',
        // Slurs used as insults
        'nigger', 'niggers', 'nigga', 'niggas', 'faggot', 'faggots', 'fag', 'fags', 'dyke', 'dykes',
        'lesbo', 'tranny', 'trannies', 'shemale', 'spic', 'spics', 'kike', 'kikes', 'gook', 'gooks',
        'wetback', 'wetbacks', 'paki', 'pakis', 'raghead', 'towelhead', 'beaner', 'beaners', 'honky',
        'spaz', 'spazz', 'retarded',
        // Threats
        'kill yourself', 'kys', 'go die',
    ],
    'allow' => [],
];
