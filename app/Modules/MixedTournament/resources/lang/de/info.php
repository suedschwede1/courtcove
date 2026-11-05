<?php

// Help page "Wie funktioniert das?" (old index/info.phtml, repo version), one key per heading/paragraph/list item.
// `*_term` is the bold lead of a list item or paragraph.
return [
    'title' => 'Wie funktioniert das Mixed-/Fun-Turnier?',
    'subtitle' => 'Americano und Mexicano mit wechselnden Partnern',
    'toc' => 'Inhalt',
    'admin_link' => 'Zur Verwaltung',

    'formats' => [
        'title' => 'Zwei Formate: Americano und Mexicano',
        'p1' => 'Beim Anlegen eines Events wird das Format gewählt; es gilt dann für den ganzen Turniertag.',
        'americano_term' => 'Americano',
        'americano' => 'die Gruppen werden vor jeder Runde neu gemischt, und innerhalb einer Gruppe wird die volle Rotation gespielt: drei Matches, in denen jeder einmal mit jedem Partner ist. Eine Runde dauert entsprechend lange, an einem Tag kommen 3–4 Runden zusammen.',
        'mexicano_term' => 'Mexicano (andernorts auch Mêlée genannt — dieselbe Turnierform)',
        'mexicano' => 'die Paarungen ergeben sich aus dem aktuellen Zwischenstand: je Vierergruppe spielt Platz 1 mit Platz 4 gegen Platz 2 mit Platz 3, und zwar genau ein Match. Danach wird neu sortiert und neu gepaart. Dadurch trifft man automatisch auf ähnlich starke Gegner, und an der Spitze spielen die Führenden gegeneinander. Eine Runde ist kurz, an einem Tag sind 10–15 Runden üblich.',
        'note1' => 'Bei Mexicano spielt jeder pro Runde genau ein Match. Geht die Teilnehmerzahl nicht durch vier auf, setzen die Übrigen aus — reihum, sodass es sich über den Tag ausgleicht. Eine Runde muss vollständig eingetragen und abgeschlossen sein, bevor die nächste erzeugt werden kann: ohne Ergebnisse gibt es keinen Zwischenstand, auf dem die neuen Paarungen aufbauen könnten.',
    ],

    'basics' => [
        'title' => 'Das Grundprinzip',
        'p1' => 'An einem Turniertag werden mehrere Runden gespielt. Vor jeder Runde werden die Teilnehmer neu in Gruppen zu 4 (oder 4 und 5) eingeteilt - man spielt also nicht den ganzen Tag mit denselben Partnern, sondern bekommt über den Tag verteilt möglichst viele unterschiedliche Mitspieler.',
    ],

    'group' => [
        'title' => 'In der Gruppe: jeder mit/gegen jeden',
        'p1' => 'Innerhalb einer Gruppe wird Doppel gespielt, mit fest vorgegebenem Partnerwechsel:',
        'four_term' => '4er-Gruppe',
        'four' => '3 Matches. Jeder ist genau einmal Partner von jedem und genau zweimal Gegner von jedem.',
        'five_term' => '5er-Gruppe',
        'five' => '5 Matches, bei denen reihum jeweils einer pausiert. Auch hier ist jeder genau einmal Partner von jedem.',
        'p2' => 'Jedes Match wird immer vollständig zu Ende gespielt - genau 4 Games, unabhängig vom Zwischenstand. Das Ergebnis wird als Games-Stand eingetragen (z.B. 3:1).',
    ],

    'extra' => [
        'title' => 'Restspieler und die Zusatzgruppe',
        'p1' => 'Wenn nur 4er-Gruppen gespielt werden (Modus "nur 4er") und die Anzahl der Teilnehmer nicht durch 4 teilbar ist, bleiben 1-3 Spieler übrig. Für sie wird eine zusätzliche 4er-Gruppe gebildet: die Restspieler plus "Auffüller", die aus den bereits gebildeten Gruppen dazugeliehen werden - je einer aus einer anderen Gruppe, nie zwei aus derselben (sonst wäre deren eigenes Match blockiert). Diese Auffüller spielen in dieser Runde dadurch zweimal: einmal in ihrer Stammgruppe, einmal in der Zusatzgruppe.',
        'note1' => 'Im Modus "4er und 5er" wird das nach Möglichkeit von vornherein vermieden, indem einzelne Gruppen zu 5 statt zu 4 gebildet werden. "Nur 4er" wird daher erst ab mehr als 20 Spielern empfohlen - bei weniger Teilnehmern ist ein doppelt spielender Auffüller sonst schnell nicht mehr zu vermeiden.',
    ],

    'mixing' => [
        'title' => 'Durchmischung über den ganzen Tag',
        'p1' => 'Bei der Gruppeneinteilung merkt sich das System, wer mit wem schon zusammengespielt hat (aus allen bereits bestätigten Runden), und bevorzugt bei jeder neuen Runde Kombinationen, die noch nicht oder seltener vorkamen. Das läuft in zwei Schritten:',
        'initial_term' => 'Erstverteilung',
        'initial' => 'nach dem gewählten Verteilungsmodus (siehe unten) - ohne Rücksicht auf die bisherige Historie.',
        'improve_term' => 'Verbesserung durch Tauschen:',
        'improve' => 'das System zählt für die aktuelle Gruppeneinteilung, wie viele Partnerschaften/Gegnerschaften sich gegenüber vorigen Runden wiederholen würden, und wie viele Gegner-Sperren verletzt würden (was sehr viel schwerer wiegt). Ausgehend davon werden wiederholt zwei Spieler zwischen zwei Gruppen probeweise getauscht - ein Tausch wird nur übernommen, wenn er die Anzahl der Wiederholungen/Sperrverletzungen senkt. Das läuft so lange, bis entweder keine Wiederholung mehr übrig ist oder eine Obergrenze an Versuchen erreicht wird.',
        'p2' => 'Wie gut die Durchmischung über den ganzen Tag tatsächlich gelungen ist, zeigt der Durchmischung-Tab als Matrix: je Teilnehmerpaar die Anzahl gemeinsamer Partnerschaften, sowie ein Durchmischungswert (Gut/Mittel/Viele Wiederholungen) auf Basis der Wiederholungsquote.',
    ],

    'distribution' => [
        'title' => 'Verteilungsmodi',
        'p1' => 'Bei jeder neuen Runde ("+ Runde") wird eine Verteilungsstrategie gewählt, die bestimmt, WIE die Teilnehmer auf die Gruppen aufgeteilt werden:',
        'random_term' => 'Zufällig',
        'random' => 'reine Zufallsverteilung.',
        'itn_term' => 'ITN-Modus',
        'itn' => 'Spieler ähnlicher Spielstärke landen in derselben Gruppe (Gruppe 1 = die stärksten Spieler, Gruppe 2 = die nächste Stärkeklasse, usw.) - enge, ausgeglichene Matches innerhalb jeder Gruppe.',
        'itn_balanced_term' => 'ITN-ausgeglichen',
        'itn_balanced' => 'die Spielstärke wird gleichmäßig über alle Gruppen verteilt (keine Gruppe ist im Schnitt stärker als eine andere), starke und schwächere Spieler spielen also in derselben Gruppe gegen-/miteinander.',
        'age_term' => 'Alters-Modus',
        'age' => 'wie der ITN-Modus, nur nach Alter statt ITN: Teilnehmer ähnlichen Alters landen in derselben Gruppe (Gruppe 1 = die jüngsten Spieler). Setzt ein hinterlegtes Geburtsdatum voraus.',
        'ladies_apart_term' => 'Damen möglichst getrennt',
        'ladies_apart' => 'Damen werden zuerst auf die Gruppen verteilt, bevor die Herren aufgefüllt werden, damit sie sich nicht in einer Gruppe häufen.',
        'note1' => 'Unabhängig vom gewählten Modus versucht das System danach immer noch, Wiederholungen aus vorigen Runden und Gegner-Sperren durch gezielte Tausche zu reduzieren (siehe oben).',
    ],

    'avoid' => [
        'title' => 'Gegner-Sperren',
        'p1' => 'Für ein Event lassen sich Paare markieren, die möglichst nicht gegeneinander spielen sollen (z.B. bei großem Leistungsunterschied). Das ist eine weiche Regel: das System versucht sie bei jeder Rundeneinteilung zu vermeiden, garantiert es aber nicht in jedem Fall.',
    ],

    'standings' => [
        'title' => 'Wertung',
        'p1' => 'Die Gesamtwertung sortiert nach dem Punkte-Schnitt pro gespieltem Match (Sieg = 2, Unentschieden = 1, Niederlage = 0), bei Gleichstand nach dem Games-Schnitt pro Match - beides der Schnitt statt der Summe, weil an einem Turniertag nicht jeder Teilnehmer gleich viele Matches spielt.',
        'note1' => 'Gewertet (platziert) werden nur Teilnehmer, die höchstens eine Runde weniger gespielt haben als der Teilnehmer mit den meisten Runden. Wer zwei oder mehr Runden verpasst hat (z.B. erst spät dazugekommen oder früher gegangen), wird in der Tabelle als "nicht gewertet" angezeigt und kann sie nicht anführen.',
        'note2' => 'Gezählt werden bewusst Runden und nicht einzelne Matches: wer als Auffüller einer Zusatzgruppe eine Runde doppelt spielt, hat zwar mehr Matches, aber gleich viele Runden - er verschiebt die Messlatte also nicht für alle anderen.',
    ],

    'rights' => [
        'title' => 'Wer sieht/darf was?',
        'all_term' => 'Alle eingeloggten Benutzer',
        'all' => 'sehen Teilnehmerliste, Runden mit Gruppen/Ergebnissen, die Durchmischungs-Matrix und die Wertung.',
        'score_term' => 'Ergebniseingabe',
        'score' => 'kann pro Turnier an einzelne Benutzer vergeben werden: sie tragen Ergebnisse ein und korrigieren sie, ändern aber nichts an Teilnehmern oder Runden.',
        'admins_term' => 'Admins',
        'admins' => 'verwalten zusätzlich Teilnehmer (anlegen, bearbeiten, pausieren, entfernen), Gegner-Sperren, erzeugen und bestätigen Runden, tauschen Spieler vor der Bestätigung und tragen Ergebnisse ein. Auch das kann pro Turnier vergeben werden - ein Turnierleiter verwaltet dann genau sein Turnier und sonst keines. Globale Einstellungen, Testmodus und Rechtevergabe bleiben globalen Mixed-Administratoren vorbehalten.',
        'note1' => 'Vergeben werden beide Rechte beim jeweiligen Turnier unter "Konfiguration".',
    ],
];
