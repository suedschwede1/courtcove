<?php

// Help page "How does it work?", one key per heading/paragraph/list item (same keys as de/info.php).
// `*_term` is the bold lead of a list item or paragraph.
return [
    'title' => 'How does the mixed/fun tournament work?',
    'subtitle' => 'Americano and Mexicano with changing partners',
    'toc' => 'Contents',
    'admin_link' => 'To the administration',

    'formats' => [
        'title' => 'Two formats: Americano and Mexicano',
        'p1' => 'The format is chosen when an event is created; it then applies to the whole tournament day.',
        'americano_term' => 'Americano',
        'americano' => 'the groups are reshuffled before every round, and within a group the full rotation is played: three matches in which everybody partners everybody once. A round takes correspondingly long; a day adds up to 3–4 rounds.',
        'mexicano_term' => 'Mexicano (elsewhere also called Mêlée — the same format)',
        'mexicano' => 'the pairings follow from the current standings: in each group of four, place 1 with place 4 plays place 2 with place 3, exactly one match. Then the order is updated and the players are paired again. This way you automatically meet opponents of similar strength, and the leaders play each other at the top. A round is short; 10–15 rounds per day are common.',
        'note1' => 'In Mexicano everybody plays exactly one match per round. If the number of participants is not divisible by four, the rest sit out — in turns, so it evens out over the day. A round has to be fully entered and completed before the next one can be created: without results there are no standings for the new pairings to build on.',
    ],

    'basics' => [
        'title' => 'The basic idea',
        'p1' => 'Several rounds are played on a tournament day. Before every round the participants are divided into new groups of 4 (or 4 and 5) - so you do not play with the same partners all day, but get as many different playing partners as possible over the day.',
    ],

    'group' => [
        'title' => 'Within the group: everybody with and against everybody',
        'p1' => 'Doubles are played within a group, with a fixed change of partners:',
        'four_term' => 'Group of 4',
        'four' => '3 matches. Everybody partners everybody exactly once and plays against everybody exactly twice.',
        'five_term' => 'Group of 5',
        'five' => '5 matches in which one player sits out in turns. Here too everybody partners everybody exactly once.',
        'p2' => 'Every match is always played to the end - exactly 4 games, regardless of the score. The result is entered as the games score (e.g. 3:1).',
    ],

    'extra' => [
        'title' => 'Remaining players and the extra group',
        'p1' => 'If only groups of 4 are played (mode "groups of 4 only") and the number of participants is not divisible by 4, 1-3 players are left over. An additional group of 4 is formed for them: the remaining players plus "fillers" borrowed from the groups already formed - one each from a different group, never two from the same one (otherwise that group\'s own match would be blocked). These fillers therefore play twice in this round: once in their home group, once in the extra group.',
        'note1' => 'The mode "groups of 4 and 5" avoids this from the start where possible by forming single groups of 5 instead of 4. "Groups of 4 only" is therefore recommended only for more than 20 players - with fewer participants a filler playing twice soon becomes unavoidable.',
    ],

    'mixing' => [
        'title' => 'Mixing over the whole day',
        'p1' => 'When forming the groups the system remembers who has already played with whom (from all confirmed rounds) and prefers combinations that have not occurred yet or less often in every new round. This works in two steps:',
        'initial_term' => 'Initial distribution',
        'initial' => 'by the selected distribution mode (see below) - regardless of the history so far.',
        'improve_term' => 'Improvement by swapping:',
        'improve' => 'for the current grouping the system counts how many partnerships/oppositions would repeat compared to earlier rounds and how many opponent blocks would be violated (which weighs much more). Starting from there, two players are repeatedly swapped between two groups on trial - a swap is only kept if it lowers the number of repetitions/block violations. This continues until either no repetition is left or a maximum number of attempts is reached.',
        'p2' => 'How well the mixing over the whole day actually worked is shown by the mixing tab as a matrix: the number of shared partnerships for each pair of participants, and a mixing score (good/medium/many repetitions) based on the repetition rate.',
    ],

    'distribution' => [
        'title' => 'Distribution modes',
        'p1' => 'For every new round ("+ round") a distribution strategy is chosen that determines HOW the participants are divided into the groups:',
        'random_term' => 'Random',
        'random' => 'purely random distribution.',
        'itn_term' => 'ITN mode',
        'itn' => 'players of similar strength end up in the same group (group 1 = the strongest players, group 2 = the next level, etc.) - close, balanced matches within every group.',
        'itn_balanced_term' => 'ITN balanced',
        'itn_balanced' => 'playing strength is spread evenly over all groups (no group is stronger on average than another), so strong and weaker players play with and against each other in the same group.',
        'age_term' => 'Age mode',
        'age' => 'like the ITN mode, but by age instead of ITN: participants of similar age end up in the same group (group 1 = the youngest players). Requires a stored date of birth.',
        'ladies_apart_term' => 'Ladies kept apart',
        'ladies_apart' => 'ladies are distributed over the groups first, before the men are filled in, so they do not cluster in one group.',
        'note1' => 'Whatever mode is chosen, the system then still tries to reduce repetitions from earlier rounds and opponent blocks by targeted swaps (see above).',
    ],

    'avoid' => [
        'title' => 'Opponent blocks',
        'p1' => 'For an event, pairs can be marked who should preferably not play against each other (e.g. with a large difference in strength). This is a soft rule: the system tries to avoid it in every grouping, but does not guarantee it in every case.',
    ],

    'standings' => [
        'title' => 'Standings',
        'p1' => 'The overall standings are sorted by the average points per match played (win = 2, draw = 1, loss = 0), on a tie by the average games per match - both the average instead of the sum, because not every participant plays the same number of matches on a tournament day.',
        'note1' => 'Only participants who have played at most one round fewer than the participant with the most rounds are ranked. Anyone who missed two or more rounds (e.g. joined late or left early) is shown as "not ranked" in the table and cannot lead it.',
        'note2' => 'Rounds are counted on purpose, not single matches: a filler of an extra group who plays a round twice has more matches but the same number of rounds - so they do not raise the bar for everyone else.',
    ],

    'rights' => [
        'title' => 'Who sees and may do what?',
        'all_term' => 'All logged-in users',
        'all' => 'see the participant list, rounds with groups/results, the mixing matrix and the standings.',
        'score_term' => 'Score entry',
        'score' => 'can be granted to single users per tournament: they enter and correct results, but change nothing about participants or rounds.',
        'admins_term' => 'Admins',
        'admins' => 'additionally manage participants (create, edit, pause, remove) and opponent blocks, create and confirm rounds, swap players before confirmation and enter results. This can also be granted per tournament - a tournament director then manages exactly their tournament and no other. Global settings, test mode and granting rights remain reserved for global mixed administrators.',
        'note1' => 'Both rights are granted at the respective tournament under "Configuration".',
    ],
];
