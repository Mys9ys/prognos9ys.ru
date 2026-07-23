<?php

/**
 * Seed РПЛ 2026/27: клубы (countries), событие, матчи туров 1–4 (Sport24).
 *
 * Идемпотентно по XML_ID события/матчей и CODE клубов.
 *
 * Usage:
 *   php seed_rpl_2026_27.php
 *   php seed_rpl_2026_27.php --tours=1-4
 *   php seed_rpl_2026_27.php --tours=5-9
 *   php seed_rpl_2026_27.php --dry-run
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

use Prognos9ys\Main\Service\Game\RplSeasonConfig;

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('iblock');
\Bitrix\Main\Loader::includeModule('prognos9ys.main');

$say = static function (string $line): void {
    echo $line . PHP_EOL;
};

$dryRun = in_array('--dry-run', $argv ?? [], true);
$toursFrom = 1;
$toursTo = 4;
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--tours=(\d+)-(\d+)$/', $arg, $m)) {
        $toursFrom = (int)$m[1];
        $toursTo = (int)$m[2];
    }
}

const IB_EVENTS = 1;
const IB_MATCHES = 2;
const IB_COUNTRIES = 3;

/** @var array<string, array{name:string,code:string,xml_id?:string,existing_id?:int}> */
$clubDefs = [
    'tsska' => ['name' => 'ЦСКА', 'code' => 'tsska', 'existing_id' => 12161],
    'baltika' => ['name' => 'Балтика', 'code' => 'baltika', 'existing_id' => 12147],
    'dinamo_m' => ['name' => 'Динамо М', 'code' => 'dinamo-m', 'existing_id' => 12148],
    'krylya' => ['name' => 'Крылья Советов', 'code' => 'krylya-sovetov', 'existing_id' => 12151],
    'akron' => ['name' => 'Акрон', 'code' => 'akron', 'existing_id' => 60017],
    'zenit' => ['name' => 'Зенит', 'code' => 'zenit', 'existing_id' => 12149],
    'fakel' => ['name' => 'Факел', 'code' => 'fakel', 'existing_id' => 12160],
    'dinamo_mh' => ['name' => 'Динамо Махачкала', 'code' => 'dinamo-makhachkala', 'existing_id' => 60019],
    'spartak' => ['name' => 'Спартак М', 'code' => 'spartak-m', 'existing_id' => 12158],
    'rodina' => ['name' => 'Родина', 'code' => 'rodina', 'xml_id' => 'rpl_rodina'],
    'orenburg' => ['name' => 'Оренбург', 'code' => 'orenburg', 'existing_id' => 12153],
    'rostov' => ['name' => 'Ростов', 'code' => 'rostov', 'existing_id' => 12155],
    'lokomotiv' => ['name' => 'Локомотив М', 'code' => 'lokomotiv-m', 'existing_id' => 12152],
    'akhmat' => ['name' => 'Ахмат', 'code' => 'akhmat', 'existing_id' => 12146],
    'rubin' => ['name' => 'Рубин', 'code' => 'rubin', 'existing_id' => 12156],
    'krasnodar' => ['name' => 'Краснодар', 'code' => 'krasnodar', 'existing_id' => 12150],
];

/**
 * Календарь Sport24: round => list of [date 'd.m.Y H:i:s', homeKey, guestKey].
 * number = сквозной (как у 60020), round = тур.
 *
 * @return array<int, array<int, array{0:string,1:string,2:string}>>
 */
$calendarAll = static function (): array {
    return [
        1 => [
            ['24.07.2026 20:00:00', 'tsska', 'baltika'],
            ['25.07.2026 14:00:00', 'dinamo_m', 'krylya'],
            ['25.07.2026 16:15:00', 'akron', 'zenit'],
            ['25.07.2026 18:30:00', 'fakel', 'dinamo_mh'],
            ['25.07.2026 20:45:00', 'spartak', 'rodina'],
            ['26.07.2026 14:30:00', 'orenburg', 'rostov'],
            ['26.07.2026 17:00:00', 'lokomotiv', 'akhmat'],
            ['26.07.2026 19:30:00', 'rubin', 'krasnodar'],
        ],
        2 => [
            ['31.07.2026 20:00:00', 'rodina', 'rostov'],
            ['01.08.2026 14:00:00', 'akron', 'rubin'],
            ['01.08.2026 16:15:00', 'tsska', 'krylya'],
            ['01.08.2026 18:30:00', 'dinamo_mh', 'lokomotiv'],
            ['01.08.2026 20:45:00', 'baltika', 'dinamo_m'],
            ['02.08.2026 16:00:00', 'orenburg', 'zenit'],
            ['02.08.2026 18:15:00', 'krasnodar', 'fakel'],
            ['02.08.2026 20:30:00', 'akhmat', 'spartak'],
        ],
        3 => [
            ['08.08.2026 15:30:00', 'krylya', 'baltika'],
            ['08.08.2026 18:00:00', 'lokomotiv', 'akron'],
            ['08.08.2026 20:30:00', 'rostov', 'tsska'],
            ['09.08.2026 14:30:00', 'dinamo_m', 'dinamo_mh'],
            ['09.08.2026 17:00:00', 'zenit', 'rodina'],
            ['09.08.2026 20:30:00', 'spartak', 'krasnodar'],
            ['09.08.2026 20:30:00', 'rubin', 'orenburg'],
            ['10.08.2026 19:30:00', 'fakel', 'akhmat'],
        ],
        4 => [
            ['14.08.2026 17:00:00', 'orenburg', 'lokomotiv'],
            ['15.08.2026 14:00:00', 'rodina', 'akron'],
            ['15.08.2026 16:15:00', 'tsska', 'fakel'],
            ['15.08.2026 18:30:00', 'rostov', 'rubin'],
            ['15.08.2026 20:45:00', 'krasnodar', 'akhmat'],
            ['16.08.2026 14:30:00', 'zenit', 'dinamo_m'],
            ['16.08.2026 17:00:00', 'krylya', 'dinamo_mh'],
            ['16.08.2026 19:30:00', 'baltika', 'spartak'],
        ],
        5 => [
            ['22.08.2026 15:30:00', 'fakel', 'orenburg'],
            ['22.08.2026 18:00:00', 'akhmat', 'rostov'],
            ['22.08.2026 20:30:00', 'tsska', 'lokomotiv'],
            ['23.08.2026 13:00:00', 'akron', 'krylya'],
            ['23.08.2026 15:15:00', 'dinamo_m', 'rodina'],
            ['23.08.2026 17:30:00', 'dinamo_mh', 'krasnodar'],
            ['23.08.2026 20:00:00', 'spartak', 'zenit'],
            ['24.08.2026 20:30:00', 'baltika', 'rubin'],
        ],
        6 => [
            ['28.08.2026 18:00:00', 'akron', 'tsska'],
            ['29.08.2026 15:00:00', 'fakel', 'zenit'],
            ['29.08.2026 17:30:00', 'krasnodar', 'rostov'],
            ['29.08.2026 20:00:00', 'lokomotiv', 'dinamo_m'],
            ['29.08.2026 20:00:00', 'akhmat', 'krylya'],
            ['30.08.2026 15:00:00', 'rodina', 'baltika'],
            ['30.08.2026 17:30:00', 'spartak', 'orenburg'],
            ['30.08.2026 20:00:00', 'rubin', 'dinamo_mh'],
        ],
        7 => [
            ['05.09.2026 14:00:00', 'krylya', 'krasnodar'],
            ['05.09.2026 16:30:00', 'zenit', 'tsska'],
            ['05.09.2026 19:00:00', 'rostov', 'fakel'],
            ['06.09.2026 14:00:00', 'orenburg', 'akron'],
            ['06.09.2026 16:15:00', 'dinamo_mh', 'rodina'],
            ['06.09.2026 18:30:00', 'dinamo_m', 'spartak'],
            ['06.09.2026 20:45:00', 'baltika', 'lokomotiv'],
            ['07.09.2026 19:30:00', 'rubin', 'akhmat'],
        ],
        8 => [
            ['11.09.2026 18:00:00', 'krylya', 'rodina'],
            ['12.09.2026 14:00:00', 'fakel', 'baltika'],
            ['12.09.2026 16:15:00', 'zenit', 'lokomotiv'],
            ['12.09.2026 18:30:00', 'dinamo_m', 'orenburg'],
            ['12.09.2026 20:45:00', 'tsska', 'rubin'],
            ['13.09.2026 14:30:00', 'akhmat', 'dinamo_mh'],
            ['13.09.2026 17:00:00', 'spartak', 'rostov'],
            ['13.09.2026 19:30:00', 'krasnodar', 'akron'],
        ],
        9 => [
            ['16.09.2026 18:30:00', 'spartak', 'fakel'],
            ['16.09.2026 18:30:00', 'rodina', 'rubin'],
            ['16.09.2026 20:45:00', 'baltika', 'zenit'],
            ['16.09.2026 20:45:00', 'lokomotiv', 'krylya'],
            ['17.09.2026 16:15:00', 'orenburg', 'krasnodar'],
            ['17.09.2026 18:30:00', 'rostov', 'dinamo_m'],
            ['17.09.2026 18:30:00', 'akron', 'akhmat'],
            ['17.09.2026 20:45:00', 'dinamo_mh', 'tsska'],
        ],
    ];
};

$findClubId = static function (array $def) use ($say): int {
    if (!empty($def['existing_id'])) {
        $id = (int)$def['existing_id'];
        $row = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => IB_COUNTRIES, 'ID' => $id],
            false,
            ['nTopCount' => 1],
            ['ID', 'NAME']
        )->Fetch();
        if ($row) {
            return $id;
        }
        $say('WARN: expected club #' . $id . ' missing, resolve by CODE');
    }

    $byCode = \CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => IB_COUNTRIES, '=CODE' => $def['code']],
        false,
        ['nTopCount' => 1],
        ['ID']
    )->Fetch();
    if ($byCode) {
        return (int)$byCode['ID'];
    }

    if (!empty($def['xml_id'])) {
        $byXml = \CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => IB_COUNTRIES, '=XML_ID' => $def['xml_id']],
            false,
            ['nTopCount' => 1],
            ['ID']
        )->Fetch();
        if ($byXml) {
            return (int)$byXml['ID'];
        }
    }

    return 0;
};

$ensureClub = static function (string $key, array $def) use ($findClubId, $dryRun, $say): int {
    $id = $findClubId($def);
    if ($id > 0) {
        $say("club ok {$key} #{$id} ({$def['name']})");

        return $id;
    }

    $say(($dryRun ? '[dry] ' : '') . "create club {$key} {$def['name']}");
    if ($dryRun) {
        return 0;
    }

    $el = new \CIBlockElement();
    $fields = [
        'IBLOCK_ID' => IB_COUNTRIES,
        'NAME' => $def['name'],
        'CODE' => $def['code'],
        'XML_ID' => $def['xml_id'] ?? ('rpl_' . $def['code']),
        'ACTIVE' => 'Y',
        'SORT' => 500,
    ];
    $newId = (int)$el->Add($fields);
    if ($newId <= 0) {
        throw new \RuntimeException('Failed to create club ' . $key . ': ' . $el->LAST_ERROR);
    }
    $say("club created {$key} #{$newId}");

    return $newId;
};

$tableEnumId = 0;
$enumRs = \CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => IB_EVENTS, 'CODE' => 'table', 'XML_ID' => RplSeasonConfig::TABLE_ENUM_XML_ID]);
if ($enum = $enumRs->Fetch()) {
    $tableEnumId = (int)$enum['ID'];
}
if ($tableEnumId <= 0) {
    // fallback by value
    $enumRs = \CIBlockPropertyEnum::GetList([], ['IBLOCK_ID' => IB_EVENTS, 'CODE' => 'table', 'VALUE' => 'Простая']);
    if ($enum = $enumRs->Fetch()) {
        $tableEnumId = (int)$enum['ID'];
    }
}
if ($tableEnumId <= 0) {
    throw new \RuntimeException('table enum «Простая» not found');
}

$say('=== RPL 2026/27 seed tours ' . $toursFrom . '-' . $toursTo . ($dryRun ? ' (dry-run)' : '') . ' ===');

$clubIds = [];
foreach ($clubDefs as $key => $def) {
    $clubIds[$key] = $ensureClub($key, $def);
}

RplSeasonConfig::resetCache();
$eventId = RplSeasonConfig::getEventId();
if ($eventId <= 0) {
    $say(($dryRun ? '[dry] ' : '') . 'create event ' . RplSeasonConfig::EVENT_NAME);
    if (!$dryRun) {
        $el = new \CIBlockElement();
        $eventId = (int)$el->Add([
            'IBLOCK_ID' => IB_EVENTS,
            'NAME' => RplSeasonConfig::EVENT_NAME,
            'CODE' => RplSeasonConfig::EVENT_CODE,
            'XML_ID' => RplSeasonConfig::EVENT_XML_ID,
            'ACTIVE' => 'Y',
            'DATE_ACTIVE_FROM' => RplSeasonConfig::ACTIVE_FROM,
            'DATE_ACTIVE_TO' => RplSeasonConfig::ACTIVE_TO,
            'SORT' => 100,
            'PROPERTY_VALUES' => [
                'e_type' => RplSeasonConfig::FOOTBALL_TYPE_ELEMENT_ID,
                'table' => $tableEnumId,
            ],
        ]);
        if ($eventId <= 0) {
            throw new \RuntimeException('Failed to create event: ' . $el->LAST_ERROR);
        }
        RplSeasonConfig::resetCache();
    }
} else {
    $say('event ok #' . $eventId);
    if (!$dryRun) {
        $el = new \CIBlockElement();
        $el->Update($eventId, [
            'NAME' => RplSeasonConfig::EVENT_NAME,
            'CODE' => RplSeasonConfig::EVENT_CODE,
            'ACTIVE' => 'Y',
            'DATE_ACTIVE_FROM' => RplSeasonConfig::ACTIVE_FROM,
            'DATE_ACTIVE_TO' => RplSeasonConfig::ACTIVE_TO,
        ]);
        \CIBlockElement::SetPropertyValuesEx($eventId, IB_EVENTS, [
            'e_type' => RplSeasonConfig::FOOTBALL_TYPE_ELEMENT_ID,
            'table' => $tableEnumId,
        ]);
    }
}

if ($dryRun && $eventId <= 0) {
    $eventId = 0;
}

$all = $calendarAll();
$matchNumber = 0;
// Precompute sequential numbers across all tours so --tours=5-9 continues numbering.
$flat = [];
foreach ($all as $round => $matches) {
    foreach ($matches as $m) {
        $matchNumber++;
        $flat[] = [
            'number' => $matchNumber,
            'round' => $round,
            'date' => $m[0],
            'home' => $m[1],
            'guest' => $m[2],
        ];
    }
}

$created = 0;
$updated = 0;
$skipped = 0;

foreach ($flat as $match) {
    if ($match['round'] < $toursFrom || $match['round'] > $toursTo) {
        continue;
    }

    $homeId = (int)($clubIds[$match['home']] ?? 0);
    $guestId = (int)($clubIds[$match['guest']] ?? 0);
    $xmlId = 'rpl_2026_27_m' . $match['number'];
    $homeName = (string)($clubDefs[$match['home']]['name'] ?? $match['home']);
    $guestName = (string)($clubDefs[$match['guest']]['name'] ?? $match['guest']);
    $matchName = $homeName . ' — ' . $guestName;

    if ($homeId <= 0 || $guestId <= 0) {
        $say("SKIP #{$match['number']}: missing club {$match['home']}/{$match['guest']}");
        $skipped++;
        continue;
    }

    $existing = \CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => IB_MATCHES, '=XML_ID' => $xmlId],
        false,
        ['nTopCount' => 1],
        ['ID']
    )->Fetch();

    $fields = [
        'IBLOCK_ID' => IB_MATCHES,
        'NAME' => $matchName,
        'XML_ID' => $xmlId,
        'ACTIVE' => 'Y',
        'DATE_ACTIVE_FROM' => $match['date'],
        'SORT' => $match['number'],
    ];
    $props = [
        'events' => $eventId,
        'home' => $homeId,
        'guest' => $guestId,
        'number' => $match['number'],
        'round' => $match['round'],
    ];

    if ($existing) {
        $id = (int)$existing['ID'];
        $say(($dryRun ? '[dry] ' : '') . "update match #{$match['number']} id={$id} round={$match['round']} {$match['date']}");
        if (!$dryRun) {
            $el = new \CIBlockElement();
            $el->Update($id, [
                'NAME' => $matchName,
                'ACTIVE' => 'Y',
                'DATE_ACTIVE_FROM' => $match['date'],
                'SORT' => $match['number'],
            ]);
            \CIBlockElement::SetPropertyValuesEx($id, IB_MATCHES, $props);
        }
        $updated++;
        continue;
    }

    $say(($dryRun ? '[dry] ' : '') . "create match #{$match['number']} round={$match['round']} {$match['home']}-{$match['guest']} {$match['date']}");
    if (!$dryRun) {
        if ($eventId <= 0) {
            throw new \RuntimeException('Event id missing');
        }
        $el = new \CIBlockElement();
        $fields['PROPERTY_VALUES'] = $props;
        $newId = (int)$el->Add($fields);
        if ($newId <= 0) {
            throw new \RuntimeException('Failed match #' . $match['number'] . ': ' . $el->LAST_ERROR);
        }
    }
    $created++;
}

$say('');
$say("DONE eventId={$eventId} created={$created} updated={$updated} skipped={$skipped}");
