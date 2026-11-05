<?php

return [
    'title' => 'Rules of the challenge ladder',
    'toc' => 'Contents',

    'right' => [
        'title' => 'Right to challenge',
        'text' => 'You can challenge anyone in the same row to your left or in the row above. Someone on place 3 can therefore challenge place 1 and place 2.',
    ],
    'mode' => [
        'title' => 'Match format',
        'text' => 'Best of three sets including tie-breaks, the third set as a champions tie-break to 10. If both players agree, a full third set can be played instead.',
    ],
    'flow' => [
        'title' => 'Procedure',
        'item_1' => 'The challenger arranges the date themselves, as soon as possible.',
        'item_2' => 'No more than :days_to_play days should pass between the challenge and the match.',
        'item_3' => 'While a challenge is open, neither player may issue or accept another challenge.',
        'item_4' => 'Interrupted or rained-off matches are made up within a week; an interrupted match continues at the last score.',
    ],
    'after' => [
        'title' => 'After the match',
        'item_1' => 'One of the two players enters the result here.',
        'item_2' => 'If the challenger wins, they move to the loser\'s place. The loser and everyone in between drop back one place.',
        'item_3' => 'If the challenger loses, nothing changes. They may challenge again only after :loser_cooldown_days days.',
        'item_4' => 'The winner of a challenge cannot be challenged for :winner_protected_days days.',
    ],
    'decline' => [
        'title' => 'Declining',
        'text' => 'A challenge may be declined if another challenge match is still open or because of injury or illness. Anyone who declines without a good reason or does not show up on time (15 minutes of waiting) has lost the match.',
    ],
    'entry' => [
        'title' => 'New players',
        'text' => 'New players register and are admitted by the administration, either at the end of the ranking or through an entry match against a player of their choice. If they win it, the newcomer takes that player\'s place; if they lose, they join at the end.',
    ],
    'fair' => [
        'title' => 'Fair play',
        'text' => 'The highest principle is fair play, on the court and off it. In case of questions, misunderstandings or disputes, the person in charge of the ranking decides.',
    ],
];
