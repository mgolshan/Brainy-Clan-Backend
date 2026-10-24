# Content audit — 2026-10-24

Audit of all published content and user-facing copy against the binding decisions in
`docs/PLAN.md`: **Mission (2026-10-24, Moe)** and **Content neutrality rules (2026-10-24, Moe)**.
Nothing was deleted or changed in content or copy; every row is a proposal for Moe.

**Status (2026-10-24):** the 11 **reword** findings (A1–A6, B1–B2, W1–W3) are **applied 2026-10-24**
(app `en.json`/`fr.json`, push texts, `words.php`; word puzzles re-seeded). The side notes on push
localization, the help-center purchase statement and the French plan term are fixed too. The 36
**keep** items below are unchanged and remain open for Moe.

Severity:

- **remove** — breaks a rule; archive the item.
- **reword** — keep the item, change the wording (proposed text given).
- **keep** — neutral, well-established fact or harmless wording; listed only because it touches a
  sensitive area (history, religion-adjacent, politics-adjacent) so Moe can decide.

## Summary

| Area | Scanned | remove | reword | keep (borderline, for Moe) |
| --- | --- | ---: | ---: | ---: |
| Seed bank: `data/questions/*.php` (13 files, 1,707 items, en + fr) plus `multiple_choice.php`, `wisdom_cards.php`, `countries.php`, `categories.php` | every item / row, en and fr-only | 0 | 0 | 31 |
| Word lists (`words.php`, `words_fr.php`) and nonogram titles | every word + hint, every title | 0 | 3 | 0 |
| App copy (`Brainy-Clan-Frontend/src/i18n/locales/en.json` + `fr.json`, 1,025 keys) | notifications, reminders, streaks, worlds, play/resume, paywall, celebrations, errors | 0 | 6 | 3 |
| Backend push texts (`app/Modules/Notifications`) | all 6 push types | 0 | 2 | 1 |
| Emails, website home/help/support summaries | subjects, home copy, help articles (skimmed) | 0 | 0 | 1 |
| **Total** | | **0** | **11** | **36** |

Overall the content is in good shape: **no item needs removal**. There is no current politics,
no politicians in office, no culture-war topic, no sexuality or gender-identity content, nothing
demeaning, and no moralising toward a worldview. Climate change, elections, wars' details and
religious beliefs are absent. History is stated as plain who/what/when facts. Wisdom cards teach
universal habits (patience, kindness, saving money) from public-domain authors and do not push a
worldview.

The real gaps are in **app copy and push texts** (mission: no pull to play for its own sake, no
purchase nudges aimed at children) and **three word-puzzle hints** that state folk "facts".

Side notes (not rule breaks, for the backlog):

- Push texts for `daily_reminder`, `family_activity`, `goal_reached` and `period_closed` are
  **English only** (hard-coded in the listeners); French members get English pushes. Chat pushes
  are already en/fr. **Fixed 2026-10-24:** all push types follow the recipient's locale (en/fr).
- Help center says "Children never see purchase screens" (true) but children do see the upsell
  lines in findings A1 and A6. **Fixed 2026-10-24:** A1/A6 are neutral "comes with the Family plan"
  labels; the help tip (en/fr) now says children see no purchase screens or upgrade prompts.
- `fr.json` uses both "formule Famille" and "forfait Famille" (`chat.upsell`). **Fixed 2026-10-24:**
  "forfait Famille" everywhere (the term the app, help center, website, store listing and legal pages
  already used most); "formule" stays only for choosing a monthly/yearly option.
- Streak-gated worlds (Sea 3 days, Lo-fi 7 days) are a mild streak mechanic. They never take
  anything away (worlds stay unlocked), and no copy pressures a broken streak — acceptable under
  the mission; Moe may still prefer "points only" unlocks.

## Top 10 findings

1. **A1** `worlds.askParent` — "Ask a parent to get the Family plan" shown to children: a
   purchase nudge aimed at kids (pester power). Reword to a neutral statement.
2. **B1** daily reminder push — "Time for a challenge! / A quick round keeps your brain busy.
   Ready?": the pull is "keep busy", not "learn"; English only.
3. **B2** family activity push — "Sam scored 12 points. Your turn?": comparison + pull to play.
4. **A6** `play.lockedHint` — "Upgrade to the Family plan…" also shown to children on locked
   categories.
5. **A2** `worlds.howTo.other` — "Keep playing!": play for its own sake.
6. **A3** `play.resume.titleKid` — "Your game is waiting for you!": a mild pull-back hook.
7. **W1** `words.php` ZEBRA hint "A striped horse" — inaccurate (a zebra is not a horse).
8. **W2** `words.php` MOUSE hint "A small animal that likes cheese" — folk myth.
9. **C1** `sports.php` `olympics-017` — "held in honour of which Greek god?" — the only item whose
   answer is a religious figure (ancient Greek religion). Neutral history, but Moe's "no religion"
   rule may want it replaced.
10. **C2** `history.php` `history-americas-006` / `-007` / `-018` and `multiple_choice.php` Lincoln
    / Gandhi — civil-rights and emancipation history: neutral and factual today; listed because
    these topics are sometimes framed politically. Recommendation: keep.

## A. App copy (`Brainy-Clan-Frontend/src/i18n/locales/en.json` + `fr.json`)

Proposed exact replacements (A1–A6 applied 2026-10-24; French uses "forfait Famille", e.g. A1
« Fait partie du forfait Famille », A6 « Cette catégorie fait partie du forfait Famille. »). All keys
keep their placeholders and plural forms.

| # | Key | Current (en / fr) | Rule | Severity | Proposed en | Proposed fr |
| --- | --- | --- | --- | --- | --- | --- |
| A1 | `worlds.askParent` (shown when the member cannot buy, i.e. children) | "Ask a parent to get the Family plan" / "Demande à un parent de prendre la formule Famille" | Mission: no purchase pressure on kids (handbook §10.5 "no ask your parents to buy") | reword — **applied 2026-10-24** | Comes with the Family plan | Fait partie de la formule Famille |
| A2 | `worlds.howTo.other` | "{{name}} isn't unlocked yet. Keep playing!" / "… Continue à jouer !" | Mission: rewards tied to learning, not playing more | reword — **applied 2026-10-24** | {{name}} isn't unlocked yet. Every challenge you finish brings it closer. | {{name}} n'est pas encore débloqué. Chaque défi terminé t'en rapproche. |
| A3 | `play.resume.titleKid` | "Your game is waiting for you!" / "Ta partie t'attend !" | Mission: no pull-back hooks | reword — **applied 2026-10-24** | Pick up where you left off | Reprends ta partie là où tu l'avais laissée |
| A4 | `play.resume.continueKid` | "Keep playing!" / "On continue !" | Mission (mild) | reword — **applied 2026-10-24** | Let's continue | On continue ! (unchanged) |
| A5 | `play.resume.outOfLives` | "Out of lives, a second chance is waiting" / "Plus de vies, une seconde chance vous attend" | Mission (mild pull) | reword — **applied 2026-10-24** | Out of lives: try a second chance or end the game | Plus de vies : seconde chance possible ou fin de la partie |
| A6 | `play.lockedHint` (Categories screen, Goal wizard; shown to every member incl. children) | "Upgrade to the Family plan to unlock this category." / "Passez à la formule Famille pour débloquer cette catégorie." | Mission: no upsell aimed at kids | reword — **applied 2026-10-24** | This category comes with the Family plan. | Cette catégorie fait partie de la formule Famille. |
| A7 | `worlds.howTo.streak_one/_other` (`_many` fr) | "Play {{count}} days in a row to unlock {{name}}. Your best streak so far: {{progress}}." | Mission: streaks without pressure | keep | Optional: "Learn something {{count}} days in a row to unlock {{name}}. Your best streak so far: {{progress}}." | Optionnel : « Apprends quelque chose {{count}} jours d'affilée pour débloquer {{name}}. Ta meilleure série : {{progress}}. » |
| A8 | `home.leaderboard`, `goals.leaderProgress_*`, `goals.myProgress_*` (rank) | "This week's family ranking", "{{name}} leads with …" | Comparison is limited to the family (allowed by the Mission) | keep | — | — |
| A9 | `billing.paywall.perMonthEquivalent` | "Just {{price}} a month" | Adult-only paywall; honest price | keep | — | — |

No guilt or loss wording was found ("Don't lose your streak", "You'll miss…", countdowns aimed at
kids): the streak only appears as a neutral counter (`home.streak`, `home.longestStreak_*`).
Notification hints (`notifications.typeHints.*`) are factual. Celebrations ("Target reached!",
"Brilliant!", "New world unlocked!") are positive and tied to finishing a challenge.

## B. Backend push texts (`app/Modules/Notifications`)

| # | File / type | Current | Rule | Severity | Proposed en | Proposed fr |
| --- | --- | --- | --- | --- | --- | --- |
| B1 | `Actions/SendDailyReminders.php`, `daily_reminder` (title / body) | "Time for a challenge!" / "A quick round keeps your brain busy. Ready?" (en only) | Mission: invite to learn, not to keep busy | reword — **applied 2026-10-24** | Something new to learn today / A short challenge is ready when you are. | Quelque chose de nouveau à apprendre aujourd’hui (more natural than « Une nouvelle chose… ») / Un petit défi est prêt quand tu veux. |
| B2 | `Listeners/NotifyFamilyActivity.php`, `family_activity` (body) | "{nickname} scored {points}. Your turn?" (en only) | Mission: no comparison/pull nudges | reword — **applied 2026-10-24** | {nickname} earned {points} for the family. (title "{nickname} finished a challenge!" stays) | {nickname} a gagné {points} pour la famille. (titre : « {nickname} a terminé un défi ! ») |
| B3 | `NotifyGoalReached`, `NotifyPeriodClosed`, `NotifyFamilyChat`, `NotifyFamilyChatReport` | "Goal reached!", "Results are in", ":name sent a message" | factual | keep | — | — |

## C. Seed bank — borderline items (all **keep**, listed for Moe)

| # | File | Id | Excerpt (≤ 15 words) | Rule touched | Severity | Note / optional replacement |
| --- | --- | --- | --- | --- | --- | --- |
| C1 | questions/sports.php | olympics-017 | "The ancient Olympic Games were held in honour of which Greek god?" | religion | keep | Ancient-history fact. If Moe wants zero religious figures, archive it (a crown-of-olive-leaves question already exists as ancient-world-016). |
| C2 | questions/history.php | history-americas-006 | "Which civil rights leader gave the 'I Have a Dream' speech?" | politics (historical) | keep | Neutral fact; explanation "calling for equal rights for all" is descriptive. |
| C3 | questions/history.php | history-americas-007 | "Rosa Parks became famous for refusing to give up her seat" | politics (historical) | keep | Neutral. |
| C4 | questions/history.php | history-americas-018 | "Underground Railroad … helped enslaved people reach freedom" | violence/politics (historical) | keep | Plain fact, no detail. |
| C5 | multiple_choice.php | history-americas row 43 | "Which US president issued the Emancipation Proclamation?" | politics (historical) | keep | Historical figure, neutral. |
| C6 | multiple_choice.php | history-asia row 59 | "Mahatma Gandhi led a peaceful movement for the independence of…" | politics (historical) | keep | Neutral. |
| C7 | questions/history.php | history-americas-027 | "Who led the Métis during the Red River Resistance of 1869–1870?" | political bias (terminology) | keep | "Resistance" is the current Canadian reference term (older: "Rebellion"); neutral. |
| C8 | questions/history.php | history-americas-016 | "Nunavut … created in 1999 after a land claims agreement with Inuit" | politics | keep | Plain fact. |
| C9 | questions/history.php | history-europe-018, history-asia-021, history-americas-035; multiple_choice.php Washington, Macdonald | Churchill, Nehru, Laurier, first presidents / prime ministers | politicians | keep | Historical, not current, office holders. |
| C10 | questions/history.php | history-americas-035 (distractor) | wrong answer "Pierre Trudeau" | politicians (adjacent to current politics) | keep | Former PM (d. 2000). Optional: replace distractor with "Lester B. Pearson" to avoid a name linked to recent politics. |
| C11 | questions/history.php | history-europe-004 | "Which queen reigned over the United Kingdom for over 70 years, until 2022?" | current affairs | keep | Head of state, not politics; fact is final. |
| C12 | questions/history.php | history-europe-013, -017, -020, -033; multiple_choice.php Waterloo, Hastings, Berlin Wall | war years and battles | violence | keep | Dates/names only, no detail. |
| C13 | questions/history.php | ancient-world-012 | "birthplace of democracy?" — "Citizens could vote on laws" | politics | keep | Ancient history. |
| C14 | questions/history.php | history-europe-030 | "crowned on Christmas Day in the year 800" | religion | keep | Date reference only. |
| C15 | questions/history.php, multiple_choice.php, questions/geography.php | history-asia-027, ancient-world-025, Parthenon, Angkor Wat, landmarks-016/-019/-024 | temple, ziggurat, basilica, abbey, Hagia Sophia | religion (buildings) | keep | Architecture/landmarks, no beliefs taught. |
| C16 | multiple_choice.php | landmarks row 33 | "Above which city does the Christ the Redeemer statue stand?" | religion (landmark name) | keep | World landmark; answer is a city. |
| C17 | questions/geography.php | landmarks-009 | "Uluru is a sacred place for its Aboriginal owners." | religion | keep | Optional explanation: "Uluru is a very important place for its Aboriginal owners." |
| C18 | questions/sports.php | olympics-026 | "In which year did women first compete at the modern Olympic Games?" | gender (as advocacy) | keep | Plain historical fact, no commentary. |
| C19 | questions/science.php | space-024 | "the universe grew from a very hot, dense start" — The Big Bang | worldview | keep | Mainstream science stated as "the scientific idea"; listed only because some families hold other views. |
| C20 | questions/inventions.php | inventions-022 | "In 1796, Edward Jenner made the first vaccine." | culture war (vaccines today) | keep | 18th-century history, neutral. |
| C21 | questions/food.php | fruits-vegetables-009 | "What is often carved into a jack-o'-lantern at Halloween?" | culture/religion | keep | Cultural tradition; some families skip Halloween. Optional: "Which big orange vegetable grows on a vine in autumn?" |
| C22 | questions/art-music.php | art-artists-038 | "Which Austrian painter used real gold leaf in his famous painting The Kiss?" | sexual content | keep | Artwork title only; not sexual. |
| C23 | questions/geography.php | flags-014, flags-026 | Ukraine flag colours; Pakistan flag "white crescent and star" | politics / religion symbol | keep | Flag descriptions only. |
| C24 | questions/wisdom.php, wisdom_cards.php | stoicism-* (category "Stoicism") | Seneca, Epictetus, Marcus Aurelius sayings | persuasion toward a worldview | keep | Practical calm/self-control tips, no doctrine; category name is a historical school of thought. |
| C25 | questions/wisdom.php | kindness-011, stoicism-003, stoicism-009, kindness-004 | "gentle rain from heaven", "change of soul", "noble souls" | religion | keep | Verbatim public-domain literature; no belief taught. |
| C26 | wisdom_cards.php, questions/wisdom.php | habits "Early to bed…makes a man healthy", habits-003 "Men learn while they teach" | 18th-c. / ancient wording "man/men" | ideology (either direction) | keep | Verbatim quotes; do not modernise (copyright/accuracy rule). |
| C27 | wisdom_cards.php | kindness (Brainy Clan original) | "Including someone new can make their whole day." | ideology ("inclusion") | keep | About inviting a new child to play; plain kindness. |
| C28 | questions/life-skills.php | online-safety-019 | "Real news is usually reported by several reliable sources." | misinformation/politics | keep | Media-literacy basics, neutral. |
| C29 | multiple_choice.php | history-europe row 54 (distractor) | wrong answer "Martin Luther" | religion | keep | Distractor only. |
| C30 | questions/logic.php | riddles-020 | "A riddle from Greek myth" | religion | keep | Riddle of the Sphinx, folklore. |
| C31 | countries.php | South Africa row | capital "Pretoria" | misinformation | keep | South Africa has three capitals; Pretoria is the executive capital and the standard quiz answer; distractors never include Cape Town/Bloemfontein. Optional: explanation "Pretoria is the seat of government; Cape Town and Bloemfontein are also capitals." |

Checked and clean (no finding): all of `science.php`, `planet.php` (no climate-change or
activist framing; recycling items are practical), `inventions.php`, `logic.php` (en + fr-only),
`vocabulary.php` (en + fr-only), `language-swap.php` (en + fr-only), `life-skills.php`,
`food.php` (no alcohol, no diet moralising), `art-music.php`, `categories.php`, nonogram titles
(heart, star, house, tree, fish, cat face, sun, boat, umbrella, key, rocket, flower, apple, music
note, cup, smiling face, mushroom, butterfly, snail).

## W. Word-puzzle hints (`database/seeders/data/words.php`)

| # | Word | Current hint | Rule | Severity | Proposed hint |
| --- | --- | --- | --- | --- | --- |
| W1 | ZEBRA (Animals, grade 2) | "A striped horse" | misinformation (a zebra is a wild equid, not a horse) | reword — **applied 2026-10-24** | A wild animal with black and white stripes |
| W2 | MOUSE (Animals, grade 2) | "A small animal that likes cheese" | misinformation (folk myth; mice prefer grain and seeds) | reword — **applied 2026-10-24** | A small animal with a long tail |
| W3 | LION (Animals, grade 1) | "King of the jungle" | misinformation (lions live in savannas) | reword — **applied 2026-10-24** | A big cat with a mane |

The multiple-choice item "Which animal is often called the 'King of the Jungle'?" stays: it asks
about the nickname, not a fact. French word hints are clean.

## D. Website and emails

| # | File | Excerpt | Rule | Severity | Note |
| --- | --- | --- | --- | --- | --- |
| D1 | `resources/views/website/home.blade.php` "Inside the app" heading | "Real games, not homework" / "De vrais jeux, pas des devoirs" | Mission (sell learning, not games) | keep | Optional: "Real learning that feels like play" / « Apprendre en s'amusant pour de vrai ». |

Transactional emails (password, deletion, data export, sign-in attempts, web checkout) are
factual. The website home lists categories, worlds and safety features truthfully; no politics,
no pressure. Help-center articles describe streaks neutrally ("Your Streak grows each day you
answer at least one question"). The `/partners` page is out of scope (another session).

## Method

- Every `'en'` question/answer/explanation/text of `database/seeders/data/questions/*.php` was
  read item by item; French-only items (`*-fr-*`) read in French; French translations were
  checked by keyword scan (religion, politics, gender, violence, sexuality, current events) since
  they mirror the English items.
- `multiple_choice.php`, `wisdom_cards.php`, `countries.php`, `categories.php`, `words.php`,
  `words_fr.php`, `nonograms.php` titles read in full.
- App: `en.json` flattened (1,025 keys) and searched for streak, reminder, notification, paywall,
  upsell, resume, unlock, celebration, ranking and error strings; matching `fr.json` strings read.
- Backend: all push titles/bodies in `app/Modules/Notifications`, mail subjects, website home copy,
  help-center keyword scan.
