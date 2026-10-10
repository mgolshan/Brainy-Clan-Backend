<?php

/*
 * Country dataset used to generate geography multiple-choice items (capitals, continents).
 * Shape: [country, capital, continent, capital-scope slug, difficulty 1-10].
 * Facts are public knowledge; no licensed dataset is copied.
 */

return [
    // Europe
    ['France', 'Paris', 'Europe', 'capitals-europe', 1],
    ['Italy', 'Rome', 'Europe', 'capitals-europe', 1],
    ['United Kingdom', 'London', 'Europe', 'capitals-europe', 1],
    ['Germany', 'Berlin', 'Europe', 'capitals-europe', 2],
    ['Spain', 'Madrid', 'Europe', 'capitals-europe', 2],
    ['Greece', 'Athens', 'Europe', 'capitals-europe', 3],
    ['Portugal', 'Lisbon', 'Europe', 'capitals-europe', 4],
    ['Netherlands', 'Amsterdam', 'Europe', 'capitals-europe', 4],
    ['Ireland', 'Dublin', 'Europe', 'capitals-europe', 4],
    ['Sweden', 'Stockholm', 'Europe', 'capitals-europe', 5],
    ['Norway', 'Oslo', 'Europe', 'capitals-europe', 5],
    ['Poland', 'Warsaw', 'Europe', 'capitals-europe', 5],
    ['Austria', 'Vienna', 'Europe', 'capitals-europe', 5],
    ['Belgium', 'Brussels', 'Europe', 'capitals-europe', 5],
    ['Hungary', 'Budapest', 'Europe', 'capitals-europe', 6],
    ['Finland', 'Helsinki', 'Europe', 'capitals-europe', 6],
    ['Czechia', 'Prague', 'Europe', 'capitals-europe', 6],
    ['Denmark', 'Copenhagen', 'Europe', 'capitals-europe', 6],
    ['Switzerland', 'Bern', 'Europe', 'capitals-europe', 7],
    ['Romania', 'Bucharest', 'Europe', 'capitals-europe', 7],

    // Asia
    ['Japan', 'Tokyo', 'Asia', 'capitals-asia', 1],
    ['China', 'Beijing', 'Asia', 'capitals-asia', 2],
    ['India', 'New Delhi', 'Asia', 'capitals-asia', 3],
    ['South Korea', 'Seoul', 'Asia', 'capitals-asia', 4],
    ['Thailand', 'Bangkok', 'Asia', 'capitals-asia', 4],
    ['Vietnam', 'Hanoi', 'Asia', 'capitals-asia', 6],
    ['Indonesia', 'Jakarta', 'Asia', 'capitals-asia', 6],
    ['Philippines', 'Manila', 'Asia', 'capitals-asia', 6],
    ['Malaysia', 'Kuala Lumpur', 'Asia', 'capitals-asia', 6],
    ['Turkey', 'Ankara', 'Asia', 'capitals-asia', 7],
    ['Saudi Arabia', 'Riyadh', 'Asia', 'capitals-asia', 7],
    ['Iran', 'Tehran', 'Asia', 'capitals-asia', 7],
    ['Pakistan', 'Islamabad', 'Asia', 'capitals-asia', 7],
    ['Nepal', 'Kathmandu', 'Asia', 'capitals-asia', 7],
    ['Mongolia', 'Ulaanbaatar', 'Asia', 'capitals-asia', 8],

    // Africa
    ['Egypt', 'Cairo', 'Africa', 'capitals-africa', 2],
    ['Kenya', 'Nairobi', 'Africa', 'capitals-africa', 4],
    ['Ghana', 'Accra', 'Africa', 'capitals-africa', 6],
    ['Algeria', 'Algiers', 'Africa', 'capitals-africa', 6],
    ['Nigeria', 'Abuja', 'Africa', 'capitals-africa', 7],
    ['Morocco', 'Rabat', 'Africa', 'capitals-africa', 7],
    ['Ethiopia', 'Addis Ababa', 'Africa', 'capitals-africa', 7],
    ['Senegal', 'Dakar', 'Africa', 'capitals-africa', 7],
    ['South Africa', 'Pretoria', 'Africa', 'capitals-africa', 7],
    ['Tanzania', 'Dodoma', 'Africa', 'capitals-africa', 9],

    // Americas
    ['United States', 'Washington, D.C.', 'North America', 'capitals-americas', 1],
    ['Canada', 'Ottawa', 'North America', 'capitals-americas', 2],
    ['Mexico', 'Mexico City', 'North America', 'capitals-americas', 2],
    ['Argentina', 'Buenos Aires', 'South America', 'capitals-americas', 4],
    ['Peru', 'Lima', 'South America', 'capitals-americas', 4],
    ['Brazil', 'Brasília', 'South America', 'capitals-americas', 5],
    ['Chile', 'Santiago', 'South America', 'capitals-americas', 5],
    ['Colombia', 'Bogotá', 'South America', 'capitals-americas', 5],
    ['Cuba', 'Havana', 'North America', 'capitals-americas', 5],
    ['Jamaica', 'Kingston', 'North America', 'capitals-americas', 6],
    ['Venezuela', 'Caracas', 'South America', 'capitals-americas', 6],
    ['Ecuador', 'Quito', 'South America', 'capitals-americas', 7],

    // Oceania
    ['Australia', 'Canberra', 'Oceania', 'capitals-oceania', 5],
    ['New Zealand', 'Wellington', 'Oceania', 'capitals-oceania', 5],
    ['Fiji', 'Suva', 'Oceania', 'capitals-oceania', 8],
    ['Papua New Guinea', 'Port Moresby', 'Oceania', 'capitals-oceania', 8],
    ['Samoa', 'Apia', 'Oceania', 'capitals-oceania', 9],
];
