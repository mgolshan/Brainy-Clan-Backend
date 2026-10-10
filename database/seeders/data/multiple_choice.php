<?php

/*
 * Hand-written multiple-choice seed items (original content).
 * Shape: [category slug, difficulty 1-10, question, correct answer, [wrong answers], explanation|null].
 * Capitals, continents, times tables, mental math and anagrams are generated in the seeder.
 */

return [
    // Flags
    ['flags', 1, "Which country's flag has a red maple leaf?", 'Canada', ['Japan', 'Switzerland', 'Peru'], 'The maple leaf flag became Canada\'s flag in 1965.'],
    ['flags', 2, "Which country's flag is a red circle on a white background?", 'Japan', ['China', 'South Korea', 'Bangladesh'], 'The red circle stands for the sun.'],
    ['flags', 2, "Which country's flag has 50 stars?", 'United States', ['Australia', 'Brazil', 'China'], 'One star for each of the 50 states.'],
    ['flags', 4, 'How many stripes are on the flag of the United States?', '13', ['10', '12', '50'], 'The 13 stripes stand for the original 13 colonies.'],
    ['flags', 4, 'Which country has a flag with plain green, white and red vertical stripes and no emblem?', 'Italy', ['France', 'Ireland', 'Germany'], null],
    ['flags', 5, 'Which country has a square red flag with a white cross in the middle?', 'Switzerland', ['Austria', 'Japan', 'Norway'], null],
    ['flags', 5, 'What is the main background colour of the flag of Brazil?', 'Green', ['Blue', 'Red', 'Yellow'], null],
    ['flags', 6, 'Which country has a flag that is not a rectangle?', 'Nepal', ['India', 'Peru', 'Canada'], 'Nepal\'s flag is made of two stacked triangles.'],
    ['flags', 7, "Which country's flag shows a dragon?", 'Bhutan', ['China', 'Japan', 'Nepal'], null],
    ['flags', 8, "Which country's flag shows a green cedar tree?", 'Lebanon', ['Canada', 'Cyprus', 'Kenya'], null],

    // Landmarks
    ['landmarks', 1, 'In which city is the Eiffel Tower?', 'Paris', ['London', 'Rome', 'Berlin'], null],
    ['landmarks', 1, 'In which country is the Great Wall?', 'China', ['Japan', 'India', 'Mongolia'], null],
    ['landmarks', 2, 'In which country are the Great Pyramids of Giza?', 'Egypt', ['Mexico', 'Peru', 'Greece'], null],
    ['landmarks', 2, 'In which city is the Colosseum?', 'Rome', ['Athens', 'Paris', 'Madrid'], null],
    ['landmarks', 2, 'In which city is the Statue of Liberty?', 'New York', ['Washington, D.C.', 'Boston', 'Chicago'], null],
    ['landmarks', 2, 'In which country is the Sydney Opera House?', 'Australia', ['New Zealand', 'United Kingdom', 'South Africa'], null],
    ['landmarks', 2, 'In which city is the clock tower known as Big Ben?', 'London', ['Paris', 'Dublin', 'Edinburgh'], null],
    ['landmarks', 3, 'In which country is the Taj Mahal?', 'India', ['Pakistan', 'Iran', 'Turkey'], null],
    ['landmarks', 3, 'In which city is the CN Tower?', 'Toronto', ['Vancouver', 'Montreal', 'Ottawa'], null],
    ['landmarks', 5, 'In which country is Machu Picchu?', 'Peru', ['Chile', 'Mexico', 'Bolivia'], null],
    ['landmarks', 5, 'Above which city does the Christ the Redeemer statue stand?', 'Rio de Janeiro', ['São Paulo', 'Lisbon', 'Buenos Aires'], null],
    ['landmarks', 5, 'In which country is Stonehenge?', 'United Kingdom', ['Ireland', 'France', 'Norway'], null],
    ['landmarks', 8, 'In which country is the ancient city of Petra?', 'Jordan', ['Egypt', 'Israel', 'Saudi Arabia'], null],
    ['landmarks', 8, 'In which country is the temple of Angkor Wat?', 'Cambodia', ['Thailand', 'Vietnam', 'Laos'], null],

    // History - Americas
    ['history-americas', 2, 'Who was the first President of the United States?', 'George Washington', ['Abraham Lincoln', 'Thomas Jefferson', 'John Adams'], null],
    ['history-americas', 3, 'In which year was the US Declaration of Independence signed?', '1776', ['1492', '1812', '1867'], null],
    ['history-americas', 4, 'In which year did Christopher Columbus first reach the Americas?', '1492', ['1066', '1607', '1776'], null],
    ['history-americas', 5, 'Which civilization built Machu Picchu?', 'The Inca', ['The Aztecs', 'The Maya', 'The Olmecs'], null],
    ['history-americas', 5, 'Which US president issued the Emancipation Proclamation?', 'Abraham Lincoln', ['George Washington', 'Thomas Jefferson', 'Ulysses S. Grant'], null],
    ['history-americas', 6, 'In which year did Canada become a country through Confederation?', '1867', ['1776', '1812', '1901'], null],
    ['history-americas', 6, 'Who was the first Prime Minister of Canada?', 'John A. Macdonald', ['Wilfrid Laurier', 'Robert Borden', 'Alexander Mackenzie'], null],
    ['history-americas', 7, 'Which civilization built the city of Tenochtitlan?', 'The Aztecs', ['The Inca', 'The Maya', 'The Olmecs'], 'Tenochtitlan stood where Mexico City is today.'],

    // History - Europe
    ['history-europe', 3, 'Which famous ship sank on its first voyage in 1912?', 'Titanic', ['Mayflower', 'Lusitania', 'Santa Maria'], null],
    ['history-europe', 3, 'Who painted the Mona Lisa?', 'Leonardo da Vinci', ['Michelangelo', 'Raphael', 'Vincent van Gogh'], null],
    ['history-europe', 4, 'From which region did the Vikings mainly come?', 'Scandinavia', ['Italy', 'Spain', 'Greece'], null],
    ['history-europe', 5, 'Which French emperor was defeated at Waterloo in 1815?', 'Napoleon Bonaparte', ['Louis XIV', 'Charlemagne', 'Julius Caesar'], null],
    ['history-europe', 6, 'In which year did the Berlin Wall fall?', '1989', ['1945', '1961', '2001'], null],
    ['history-europe', 6, 'Who built the first European printing press with movable type?', 'Johannes Gutenberg', ['Isaac Newton', 'Galileo Galilei', 'Martin Luther'], null],
    ['history-europe', 7, 'Which famous battle took place in England in 1066?', 'The Battle of Hastings', ['The Battle of Waterloo', 'The Battle of Trafalgar', 'The Battle of Agincourt'], null],

    // History - Asia
    ['history-asia', 3, 'Samurai were warriors in which country?', 'Japan', ['China', 'Korea', 'India'], null],
    ['history-asia', 4, 'Mahatma Gandhi led a peaceful movement for the independence of which country?', 'India', ['Pakistan', 'Nepal', 'Sri Lanka'], null],
    ['history-asia', 5, 'Who founded the Mongol Empire?', 'Genghis Khan', ['Kublai Khan', 'Attila', 'Alexander the Great'], null],
    ['history-asia', 5, 'The Great Wall of China was built mainly to protect against what?', 'Invaders from the north', ['Floods', 'Wild animals', 'Storms from the sea'], null],
    ['history-asia', 6, 'The Silk Road connected China with which regions?', 'The Middle East and Europe', ['Australia', 'The Americas', 'Antarctica'], null],
    ['history-asia', 8, 'Who was the first emperor of a unified China?', 'Qin Shi Huang', ['Kublai Khan', 'Confucius', 'Sun Yat-sen'], null],

    // Ancient world
    ['ancient-world', 2, 'Which ancient people built the pyramids of Giza?', 'The Egyptians', ['The Romans', 'The Greeks', 'The Vikings'], null],
    ['ancient-world', 3, 'Where did the ancient Olympic Games begin?', 'Greece', ['Rome', 'Egypt', 'China'], null],
    ['ancient-world', 4, 'Which language did the ancient Romans speak?', 'Latin', ['Greek', 'Italian', 'English'], null],
    ['ancient-world', 4, 'Julius Caesar was a leader of which ancient state?', 'Rome', ['Greece', 'Egypt', 'Persia'], null],
    ['ancient-world', 4, 'Cleopatra was the queen of which country?', 'Egypt', ['Greece', 'Rome', 'Persia'], null],
    ['ancient-world', 5, 'What is the picture writing of ancient Egypt called?', 'Hieroglyphs', ['Cuneiform', 'Runes', 'Braille'], null],
    ['ancient-world', 5, 'In which city is the Parthenon temple?', 'Athens', ['Rome', 'Cairo', 'Istanbul'], null],
    ['ancient-world', 6, 'Which Roman city was buried by the eruption of Mount Vesuvius in 79 AD?', 'Pompeii', ['Athens', 'Sparta', 'Carthage'], null],

    // Animals
    ['animals', 1, 'What do caterpillars turn into?', 'Butterflies or moths', ['Bees', 'Beetles', 'Spiders'], null],
    ['animals', 1, 'How many legs does a spider have?', '8', ['6', '10', '4'], 'Insects have 6 legs; spiders are not insects.'],
    ['animals', 1, 'Which animal is often called the "King of the Jungle"?', 'Lion', ['Tiger', 'Elephant', 'Gorilla'], null],
    ['animals', 2, 'What is the largest animal on Earth?', 'Blue whale', ['African elephant', 'Giraffe', 'Great white shark'], null],
    ['animals', 2, 'What is the fastest land animal?', 'Cheetah', ['Lion', 'Horse', 'Gazelle'], null],
    ['animals', 3, 'How do fish breathe?', 'With gills', ['With lungs', 'Through their fins', 'They do not breathe'], null],
    ['animals', 4, 'What is a baby kangaroo called?', 'A joey', ['A cub', 'A kit', 'A calf'], null],
    ['animals', 4, 'Which is the only mammal that can truly fly?', 'The bat', ['The flying squirrel', 'The penguin', 'The ostrich'], null],
    ['animals', 4, 'What do we call animals that eat only plants?', 'Herbivores', ['Carnivores', 'Omnivores', 'Predators'], null],
    ['animals', 5, 'Which bird can fly backwards?', 'Hummingbird', ['Eagle', 'Penguin', 'Ostrich'], null],

    // Space
    ['space', 1, 'Which planet is known as the Red Planet?', 'Mars', ['Venus', 'Jupiter', 'Saturn'], null],
    ['space', 2, 'What is the largest planet in our solar system?', 'Jupiter', ['Saturn', 'Earth', 'Neptune'], null],
    ['space', 2, 'Which planet is famous for its bright rings?', 'Saturn', ['Mars', 'Mercury', 'Venus'], null],
    ['space', 3, 'What is the closest star to Earth?', 'The Sun', ['Polaris', 'Sirius', 'The Moon'], 'The Moon is not a star; it reflects sunlight.'],
    ['space', 3, 'How many planets are in our solar system?', '8', ['7', '9', '10'], null],
    ['space', 3, 'About how long does Earth take to travel once around the Sun?', 'One year', ['One day', 'One month', 'One week'], null],
    ['space', 4, 'Who was the first person to walk on the Moon?', 'Neil Armstrong', ['Buzz Aldrin', 'Yuri Gagarin', 'John Glenn'], null],
    ['space', 4, 'What is the name of our galaxy?', 'The Milky Way', ['Andromeda', 'Orion', 'The Big Dipper'], null],
    ['space', 5, 'What causes day and night on Earth?', 'Earth spinning on its axis', ['Earth travelling around the Sun', 'The Moon blocking the Sun', 'Clouds covering the Sun'], null],

    // Human body
    ['human-body', 1, 'Which organ pumps blood around your body?', 'The heart', ['The lungs', 'The liver', 'The brain'], null],
    ['human-body', 1, 'Which organs do you use to breathe?', 'The lungs', ['The kidneys', 'The stomach', 'The heart'], null],
    ['human-body', 2, 'Which organ is the control centre of your body?', 'The brain', ['The heart', 'The stomach', 'The skin'], null],
    ['human-body', 5, 'What do red blood cells carry around the body?', 'Oxygen', ['Food', 'Germs', 'Sound'], null],
    ['human-body', 5, 'How many teeth does an adult usually have?', '32', ['20', '28', '40'], 'Children have 20 baby teeth.'],
    ['human-body', 6, 'How many bones does an adult human have?', '206', ['106', '306', '150'], null],
    ['human-body', 6, 'What is the largest organ of the human body?', 'The skin', ['The liver', 'The heart', 'The brain'], null],
    ['human-body', 7, 'Where is the smallest bone in the human body?', 'In the ear', ['In the nose', 'In the toe', 'In the finger'], null],

    // Fractions
    ['fractions', 2, 'What is half of 10?', '5', ['2', '4', '20'], null],
    ['fractions', 3, 'What is 1/4 of 100?', '25', ['4', '50', '40'], null],
    ['fractions', 3, 'Which fraction is the biggest?', '1/3', ['1/4', '1/5', '1/6'], 'Splitting into fewer parts gives bigger parts.'],
    ['fractions', 4, 'What is 1/3 + 1/3?', '2/3', ['2/6', '1/9', '1/6'], null],
    ['fractions', 4, 'Which fraction is the same as 50%?', '1/2', ['1/5', '1/4', '1/50'], null],
    ['fractions', 5, 'What is 4/8 in simplest form?', '1/2', ['2/4', '1/4', '4/2'], null],
    ['fractions', 5, 'What is 1/2 + 1/4?', '3/4', ['2/6', '1/6', '2/4'], null],
    ['fractions', 5, 'What is 2/5 of 20?', '8', ['5', '10', '4'], null],
    ['fractions', 6, 'What is 3/4 written as a decimal?', '0.75', ['0.34', '0.43', '0.25'], null],
    ['fractions', 6, 'What is 6/9 in simplest form?', '2/3', ['3/4', '1/3', '6/9 cannot be simplified'], null],

    // Synonyms
    ['synonyms', 1, 'Which word means the same as "big"?', 'Large', ['Small', 'Thin', 'Short'], null],
    ['synonyms', 1, 'Which word means the same as "quick"?', 'Fast', ['Slow', 'Late', 'Heavy'], null],
    ['synonyms', 2, 'Which word means the same as "happy"?', 'Joyful', ['Angry', 'Sleepy', 'Tiny'], null],
    ['synonyms', 2, 'Which word means the same as "begin"?', 'Start', ['Finish', 'Stop', 'Wait'], null],
    ['synonyms', 2, 'Which word means the same as "silent"?', 'Quiet', ['Loud', 'Bright', 'Busy'], null],
    ['synonyms', 2, 'Which word means the same as "difficult"?', 'Hard', ['Easy', 'Soft', 'Simple'], null],
    ['synonyms', 4, 'Which word means the same as "enormous"?', 'Huge', ['Tiny', 'Narrow', 'Light'], null],
    ['synonyms', 4, 'Which word means the same as "brave"?', 'Courageous', ['Fearful', 'Careless', 'Lazy'], null],
    ['synonyms', 5, 'Which word means the same as "ancient"?', 'Very old', ['Brand new', 'Very fast', 'Very small'], null],
    ['synonyms', 6, 'Which word means the same as "rapid"?', 'Swift', ['Gentle', 'Sluggish', 'Calm'], null],

    // Idioms
    ['idioms', 4, 'What does "It\'s raining cats and dogs" mean?', 'It is raining very heavily', ['Pets are falling from the sky', 'It is a little cloudy', 'The weather is perfect'], null],
    ['idioms', 4, 'What does "a piece of cake" mean?', 'Something very easy', ['A tasty dessert', 'A small part of something', 'A birthday party'], null],
    ['idioms', 5, 'What does "under the weather" mean?', 'Feeling sick', ['Standing in the rain', 'Feeling very happy', 'Being outside'], null],
    ['idioms', 5, 'What does "hit the books" mean?', 'Study hard', ['Throw books', 'Sell old books', 'Read a comic'], null],
    ['idioms', 6, 'What does "break a leg" mean when said to a performer?', 'Good luck', ['Be careful', 'Go home', 'Dance faster'], null],
    ['idioms', 6, 'What does "spill the beans" mean?', 'Tell a secret', ['Make a mess while cooking', 'Plant a garden', 'Waste food'], null],
    ['idioms', 6, 'What does "once in a blue moon" mean?', 'Very rarely', ['Every night', 'During winter', 'At midnight'], null],
    ['idioms', 6, 'What does "it costs an arm and a leg" mean?', 'It is very expensive', ['It is dangerous', 'It is free', 'It is broken'], null],

    // Spelling
    ['spelling', 2, 'Which word is spelled correctly?', 'because', ['becuase', 'becouse', 'beacause'], null],
    ['spelling', 3, 'Which word is spelled correctly?', 'friend', ['freind', 'frend', 'friand'], 'Remember: a friend is with you to the "end".'],
    ['spelling', 4, 'Which word is spelled correctly?', 'beautiful', ['beutiful', 'beautifull', 'beatiful'], null],
    ['spelling', 4, 'Which word is spelled correctly?', 'library', ['libary', 'liberry', 'librery'], null],
    ['spelling', 5, 'Which word is spelled correctly?', 'Wednesday', ['Wensday', 'Wednsday', 'Wedensday'], null],
    ['spelling', 5, 'Which word is spelled correctly?', 'tomorrow', ['tommorow', 'tomorow', 'tommorrow'], null],
    ['spelling', 7, 'Which word is spelled correctly?', 'necessary', ['neccessary', 'necesary', 'neccesary'], 'One collar (c) and two sleeves (ss).'],
    ['spelling', 7, 'Which word is spelled correctly?', 'separate', ['seperate', 'separete', 'saparate'], 'There is "a rat" in separate.'],

    // Grade levels
    ['grades-1-3', 1, 'What does "tiny" mean?', 'Very small', ['Very big', 'Very loud', 'Very fast'], null],
    ['grades-1-3', 1, 'What is the opposite of "hot"?', 'Cold', ['Warm', 'Wet', 'Sunny'], null],
    ['grades-1-3', 1, 'What is a puppy?', 'A young dog', ['A young cat', 'A small toy', 'A kind of bird'], null],
    ['grades-1-3', 2, 'What is the opposite of "up"?', 'Down', ['Over', 'Left', 'Top'], null],
    ['grades-4-6', 4, 'What does "curious" mean?', 'Eager to know or learn', ['Very tired', 'Easily scared', 'Always angry'], null],
    ['grades-4-6', 4, 'What does "generous" mean?', 'Happy to give and share', ['Unwilling to share', 'Very loud', 'Very tall'], null],
    ['grades-4-6', 4, 'What is the opposite of "ancient"?', 'Modern', ['Old', 'Broken', 'Huge'], null],
    ['grades-4-6', 5, 'What does "fragile" mean?', 'Easily broken', ['Very heavy', 'Very strong', 'Brightly coloured'], null],
    ['grades-7-9', 7, 'What does "meticulous" mean?', 'Very careful about details', ['Very messy', 'Very fast', 'Very funny'], null],
    ['grades-7-9', 7, 'What does "reluctant" mean?', 'Unwilling', ['Excited', 'Proud', 'Confused'], null],
    ['grades-7-9', 8, 'What does "benevolent" mean?', 'Kind and generous', ['Cruel and harsh', 'Shy and quiet', 'Loud and rude'], null],
    ['grades-7-9', 8, 'What does "ambiguous" mean?', 'Having more than one possible meaning', ['Perfectly clear', 'Very large', 'Moving quickly'], null],

    // Missing letter
    ['missing-letter', 2, 'Fill in the missing letter: EL_PHANT', 'E', ['A', 'I', 'O'], 'ELEPHANT'],
    ['missing-letter', 2, 'Fill in the missing letter: B_TTERFLY', 'U', ['A', 'E', 'O'], 'BUTTERFLY'],
    ['missing-letter', 2, 'Fill in the missing letter: RAINB_W', 'O', ['A', 'E', 'U'], 'RAINBOW'],
    ['missing-letter', 2, 'Fill in the missing letter: SCH_OL', 'O', ['U', 'E', 'A'], 'SCHOOL'],
    ['missing-letter', 3, 'Fill in the missing letter: GIR_FFE', 'A', ['E', 'I', 'O'], 'GIRAFFE'],
    ['missing-letter', 3, 'Fill in the missing letter: DIN_SAUR', 'O', ['A', 'E', 'U'], 'DINOSAUR'],
    ['missing-letter', 3, 'Fill in the missing letter: PEN_UIN', 'G', ['Q', 'C', 'J'], 'PENGUIN'],
    ['missing-letter', 4, 'Fill in the missing letter: TRIAN_LE', 'G', ['C', 'K', 'Q'], 'TRIANGLE'],
    ['missing-letter', 4, 'Fill in the missing letter: KANGA_OO', 'R', ['L', 'N', 'W'], 'KANGAROO'],
    ['missing-letter', 5, 'Fill in the missing letter: CHOC_LATE', 'O', ['A', 'U', 'E'], 'CHOCOLATE'],
];
