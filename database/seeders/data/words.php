<?php

/*
 * Kid-safe English word list for the word puzzles (anagram, missing letter, word search).
 * Shape: theme => grade => list of [WORD, hint]. Grade 1 = ages 5+ (short, common words),
 * grade 2 = ages 7+, grade 3 = ages 9+ (longer words). Hints are shown with anagrams and
 * missing-letter puzzles; the theme names a word search.
 */

return [
    'Animals' => [
        1 => [['CAT', 'It says meow'], ['DOG', 'It wags its tail'], ['COW', 'It gives us milk'], ['PIG', 'It rolls in mud'], ['HEN', 'It lays eggs'], ['FOX', 'A clever orange animal'], ['OWL', 'It hoots at night'], ['BEE', 'It makes honey'], ['ANT', 'A tiny, strong insect'], ['BAT', 'It flies at night'], ['DUCK', 'It quacks'], ['FROG', 'It hops and croaks'], ['BEAR', 'A big furry animal that loves honey'], ['LION', 'A big cat with a mane'], ['GOAT', 'It has horns and a beard']],
        2 => [['TIGER', 'A big cat with stripes'], ['ZEBRA', 'A wild animal with black and white stripes'], ['HORSE', 'You can ride it'], ['MOUSE', 'A small animal with a long tail'], ['SHEEP', 'It gives us wool'], ['RABBIT', 'It has long ears and hops'], ['MONKEY', 'It loves bananas'], ['TURTLE', 'It carries its home on its back'], ['PANDA', 'A black and white bear'], ['KOALA', 'It sleeps in eucalyptus trees'], ['CAMEL', 'It has a hump'], ['EAGLE', 'A big bird of prey'], ['SNAKE', 'It slithers'], ['WHALE', 'The biggest animal in the sea'], ['OTTER', 'It floats on its back']],
        3 => [['ELEPHANT', 'It has a trunk'], ['GIRAFFE', 'The tallest animal'], ['KANGAROO', 'It carries its baby in a pouch'], ['PENGUIN', 'A bird that cannot fly but swims'], ['DOLPHIN', 'A smart sea mammal'], ['CHEETAH', 'The fastest land animal'], ['SQUIRREL', 'It hides nuts'], ['OCTOPUS', 'It has eight arms'], ['HEDGEHOG', 'A small spiky animal'], ['BUTTERFLY', 'It was once a caterpillar'], ['FLAMINGO', 'A pink bird on one leg'], ['CROCODILE', 'A big reptile with strong jaws']],
    ],
    'Food' => [
        1 => [['EGG', 'Hens lay it'], ['PIE', 'A baked dessert with filling'], ['JAM', 'Fruit spread for toast'], ['BUN', 'A small round bread'], ['RICE', 'Small white grains'], ['CAKE', 'Birthday treat'], ['MILK', 'A white drink'], ['PEAR', 'A green or yellow fruit'], ['CORN', 'Yellow kernels on a cob'], ['SOUP', 'You eat it with a spoon']],
        2 => [['APPLE', 'Red or green fruit'], ['BREAD', 'Used for sandwiches'], ['LEMON', 'A sour yellow fruit'], ['PIZZA', 'Round, cheesy and sliced'], ['GRAPE', 'Small fruit in bunches'], ['HONEY', 'Bees make it'], ['PASTA', 'Spaghetti is one kind'], ['MANGO', 'A sweet tropical fruit'], ['CARROT', 'An orange vegetable'], ['CHEESE', 'Made from milk'], ['BANANA', 'A long yellow fruit'], ['ORANGE', 'A fruit and a colour']],
        3 => [['SANDWICH', 'Food between two slices of bread'], ['PANCAKE', 'Flat and served with syrup'], ['BROCCOLI', 'A vegetable like a tiny tree'], ['PINEAPPLE', 'A spiky tropical fruit'], ['STRAWBERRY', 'A red berry with seeds outside'], ['CUCUMBER', 'A long green vegetable'], ['AVOCADO', 'A green fruit with a big pit'], ['SPAGHETTI', 'Long thin pasta'], ['POPCORN', 'A snack at the movies'], ['BLUEBERRY', 'A small blue fruit']],
    ],
    'Nature' => [
        1 => [['SUN', 'It shines in the day'], ['SEA', 'Lots of salty water'], ['SKY', 'It is blue on a clear day'], ['MUD', 'Wet dirt'], ['TREE', 'It has leaves and a trunk'], ['LEAF', 'It falls in autumn'], ['RAIN', 'Water from clouds'], ['SNOW', 'Cold white flakes'], ['ROCK', 'A hard stone'], ['HILL', 'A small mountain']],
        2 => [['RIVER', 'Water that flows to the sea'], ['CLOUD', 'White and fluffy in the sky'], ['STORM', 'Wind, rain and thunder'], ['BEACH', 'Sand by the sea'], ['FLOWER', 'It blooms in spring'], ['FOREST', 'Lots of trees'], ['ISLAND', 'Land with water all around'], ['GARDEN', 'Where plants grow at home'], ['DESERT', 'A dry, sandy place'], ['PLANT', 'It grows from a seed']],
        3 => [['MOUNTAIN', 'A very tall hill'], ['RAINBOW', 'Colours in the sky after rain'], ['VOLCANO', 'A mountain that can erupt'], ['WATERFALL', 'Water falling over a cliff'], ['THUNDER', 'The loud sound in a storm'], ['SUNFLOWER', 'A tall yellow flower'], ['MEADOW', 'A field of grass and flowers'], ['GLACIER', 'A huge river of ice'], ['TORNADO', 'A spinning windstorm'], ['LIGHTNING', 'A flash in a storm']],
    ],
    'Home and School' => [
        1 => [['BED', 'You sleep in it'], ['CUP', 'You drink from it'], ['PEN', 'You write with it'], ['MAP', 'It shows where places are'], ['BAG', 'You carry things in it'], ['DOOR', 'You open it to go in'], ['BOOK', 'You read it'], ['DESK', 'A table for school work'], ['LAMP', 'It gives light'], ['SOFA', 'A comfy seat for many']],
        2 => [['CHAIR', 'You sit on it'], ['TABLE', 'You eat at it'], ['CLOCK', 'It tells the time'], ['PENCIL', 'You write and erase with it'], ['RULER', 'It measures lines'], ['SCHOOL', 'Where you learn'], ['WINDOW', 'Glass in a wall'], ['KITCHEN', 'Where food is cooked'], ['PILLOW', 'Soft, under your head'], ['TEACHER', 'Helps you learn']],
        3 => [['NOTEBOOK', 'Paper you write in'], ['BACKPACK', 'A bag on your back'], ['LIBRARY', 'A room full of books'], ['SCISSORS', 'They cut paper'], ['COMPUTER', 'You type and click on it'], ['BLANKET', 'Keeps you warm in bed'], ['CRAYONS', 'Colourful wax sticks'], ['DICTIONARY', 'A book of word meanings'], ['CLASSROOM', 'Where a class learns'], ['HOMEWORK', 'School work done at home']],
    ],
    'Space and Travel' => [
        1 => [['CAR', 'It has four wheels'], ['BUS', 'Many people ride it'], ['VAN', 'A box-shaped car'], ['JET', 'A fast plane'], ['MOON', 'It shines at night'], ['STAR', 'It twinkles'], ['SHIP', 'It sails on the sea'], ['BIKE', 'It has two wheels and pedals'], ['BOAT', 'It floats on water'], ['TRAIN', 'It runs on tracks']],
        2 => [['PLANE', 'It flies people far away'], ['TRUCK', 'A big vehicle for loads'], ['ROCKET', 'It goes to space'], ['PLANET', 'Earth is one'], ['COMET', 'An icy ball with a tail'], ['ORBIT', 'The path around a planet'], ['TICKET', 'You need it to ride'], ['SUBWAY', 'A train under the city'], ['TRAVEL', 'Going to new places'], ['GALAXY', 'Billions of stars together']],
        3 => [['ASTRONAUT', 'A person who goes to space'], ['TELESCOPE', 'It shows faraway stars'], ['SATELLITE', 'It circles Earth'], ['HELICOPTER', 'It flies with spinning blades'], ['SUBMARINE', 'A ship that goes underwater'], ['JOURNEY', 'A long trip'], ['UNIVERSE', 'Everything in space'], ['AIRPORT', 'Where planes take off'], ['SPACESHIP', 'A vehicle for space'], ['BICYCLE', 'Another word for bike']],
    ],
    'Colours and Shapes' => [
        1 => [['RED', 'The colour of a ripe tomato'], ['BLUE', 'The colour of the sky'], ['PINK', 'Light red'], ['GOLD', 'A shiny yellow metal'], ['GREY', 'Between black and white'], ['STAR', 'A shape with points'], ['OVAL', 'A stretched circle'], ['LINE', 'Straight and thin']],
        2 => [['GREEN', 'The colour of grass'], ['BLACK', 'The colour of night'], ['WHITE', 'The colour of snow'], ['BROWN', 'The colour of chocolate'], ['PURPLE', 'Red and blue mixed'], ['YELLOW', 'The colour of the sun'], ['CIRCLE', 'A round shape'], ['SQUARE', 'Four equal sides'], ['SILVER', 'A shiny grey metal'], ['HEART', 'A shape for love']],
        3 => [['TRIANGLE', 'A shape with three sides'], ['RECTANGLE', 'Four sides, like a door'], ['DIAMOND', 'A sparkly shape'], ['HEXAGON', 'A shape with six sides'], ['TURQUOISE', 'A blue-green colour'], ['PENTAGON', 'A shape with five sides'], ['CYLINDER', 'A shape like a can'], ['CRIMSON', 'A deep red colour']],
    ],
    'Sports and Play' => [
        1 => [['BALL', 'You kick or throw it'], ['GAME', 'Something you play'], ['KITE', 'It flies on a string'], ['SWIM', 'Move through water'], ['RUN', 'Faster than walking'], ['TOY', 'Something to play with'], ['GOAL', 'Score one in soccer'], ['SLED', 'Ride it down a snowy hill']],
        2 => [['SOCCER', 'Kick the ball into the net'], ['TENNIS', 'Hit a ball over a net with a racket'], ['HOCKEY', 'Played with sticks and a puck'], ['SKATE', 'Glide on ice'], ['PUZZLE', 'Fit the pieces together'], ['TEAM', 'Players who play together'], ['DANCE', 'Move to music'], ['CHESS', 'A game with kings and queens'], ['MEDAL', 'A prize for winning'], ['RACE', 'Who is the fastest?']],
        3 => [['BASKETBALL', 'Throw the ball through a hoop'], ['VOLLEYBALL', 'Hit the ball over a high net'], ['SKATEBOARD', 'A board with wheels'], ['GYMNASTICS', 'Flips, beams and rings'], ['BASEBALL', 'Hit the ball and run the bases'], ['SWIMMING', 'A sport in the pool'], ['CHAMPION', 'The winner'], ['TRAMPOLINE', 'Bounce high on it']],
    ],
];
