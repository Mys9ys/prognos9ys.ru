<?php
declare(strict_types=1);

/**
 * Ростер IEM Cologne Major 2026 (плей-офф): 8 команд × (5 игроков + тренер).
 * Почта как у Месси: {slug}@prognos9ys.ru
 */

function cs2_iem_slug_nick(string $nick): string
{
    return strtolower(preg_replace('/[^a-z0-9]+/i', '', $nick) ?? '');
}

/** @return list<array{team:string,tag:string,nick:string,login:string,role:string,mail:string,display:string}> */
function cs2_iem_roster_people(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $roster = [
        ['team' => 'Team Spirit', 'tag' => 'SPI', 'players' => ['donk', 'sh1ro', 'chopper', 'zont1x', 'magixx'], 'coach' => 'hally'],
        ['team' => 'FURIA', 'tag' => 'FUR', 'players' => ['FalleN', 'yuurih', 'KSCERATO', 'YEKINDAR', 'molodoy'], 'coach' => 'sidde'],
        ['team' => 'Aurora', 'tag' => 'AUR', 'players' => ['MAJ3R', 'XANTARES', 'woxic', 'Wicadia', 'jottAAA'], 'coach' => 'casN'],
        ['team' => 'Vitality', 'tag' => 'VIT', 'players' => ['apEX', 'ZywOo', 'flameZ', 'mezii', 'ropz'], 'coach' => 'XTQZZZ'],
        ['team' => 'Falcons', 'tag' => 'FLC', 'players' => ['NiKo', 'm0NESY', 'TeSeS', 'kyxsan', 'kyousuke'], 'coach' => 'zonic'],
        ['team' => 'BetBoom', 'tag' => 'BB', 'players' => ['Boombl4', 'Magnojez', 'zorte', 'd1Ledez', 'S1ren'], 'coach' => 'hooch'],
        ['team' => '9z', 'tag' => '9Z', 'players' => ['max', 'Luken', 'urban0', 'levi', 'HUASOPEEK'], 'coach' => 'taao'],
        ['team' => 'G2', 'tag' => 'G2', 'players' => ['huNter-', 'malbsMd', 'SunPayus', 'HeavyGod', 'MATYS'], 'coach' => 'bLitz'],
    ];

    $list = [];
    foreach ($roster as $row) {
        foreach ($row['players'] as $nick) {
            $login = cs2_iem_slug_nick($nick);
            $list[] = [
                'team' => $row['team'],
                'tag' => $row['tag'],
                'nick' => $nick,
                'login' => $login,
                'role' => 'ИГ',
                'mail' => $login . '@prognos9ys.ru',
                'display' => sprintf('%s (%s) [ИГ]', $nick, $row['tag']),
            ];
        }
        $coach = (string)$row['coach'];
        $login = cs2_iem_slug_nick($coach);
        $list[] = [
            'team' => $row['team'],
            'tag' => $row['tag'],
            'nick' => $coach,
            'login' => $login,
            'role' => 'ТР',
            'mail' => $login . '@prognos9ys.ru',
            'display' => sprintf('%s (%s) [ТР]', $coach, $row['tag']),
        ];
    }

    $cache = $list;

    return $cache;
}

/** @return array<string, true> */
function cs2_iem_seed_mail_map(): array
{
    $map = [];
    foreach (cs2_iem_roster_people() as $person) {
        $map[strtolower($person['mail'])] = true;
    }

    return $map;
}
