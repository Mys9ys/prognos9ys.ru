<?php

/**
 * Дамп события 60020 (старая РПЛ): свойства, клубы, схема number/дат матчей.
 *
 * Usage: php dump_event_60020.php [eventId]
 */

define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);

$eventId = (int)($argv[1] ?? 60020);

$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

\Bitrix\Main\Loader::includeModule('iblock');

$say = static function (string $line): void {
    echo $line . PHP_EOL;
};

$eventsIb = 0;
$matchesIb = 0;
$countriesIb = 0;
$rs = \CIBlock::GetList([], ['TYPE' => 'content', 'CHECK_PERMISSIONS' => 'N']);
while ($ib = $rs->Fetch()) {
    $code = (string)($ib['CODE'] ?? '');
    if ($code === 'events') {
        $eventsIb = (int)$ib['ID'];
    } elseif ($code === 'matches') {
        $matchesIb = (int)$ib['ID'];
    } elseif ($code === 'countries') {
        $countriesIb = (int)$ib['ID'];
    }
}

$say('=== Event dump #' . $eventId . ' ===');
$say('iblocks: events=' . $eventsIb . ' matches=' . $matchesIb . ' countries=' . $countriesIb);

if ($eventsIb <= 0) {
    $say('ERROR: events iblock not found');
    exit(1);
}

$el = \CIBlockElement::GetList(
    [],
    ['IBLOCK_ID' => $eventsIb, 'ID' => $eventId],
    false,
    false,
    [
        'ID', 'NAME', 'ACTIVE', 'XML_ID', 'CODE',
        'DATE_ACTIVE_FROM', 'DATE_ACTIVE_TO',
        'PREVIEW_PICTURE', 'DETAIL_PICTURE',
        'PROPERTY_E_TYPE', 'PROPERTY_TABLE',
    ]
)->GetNextElement();

if (!$el) {
    $say('ERROR: event not found');
    exit(1);
}

$fields = $el->GetFields();
$props = $el->GetProperties();

$say('NAME: ' . ($fields['NAME'] ?? ''));
$say('ACTIVE: ' . ($fields['ACTIVE'] ?? ''));
$say('XML_ID: ' . ($fields['XML_ID'] ?? ''));
$say('CODE: ' . ($fields['CODE'] ?? ''));
$say('DATE_ACTIVE_FROM: ' . ($fields['DATE_ACTIVE_FROM'] ?? ''));
$say('DATE_ACTIVE_TO: ' . ($fields['DATE_ACTIVE_TO'] ?? ''));
$say('PREVIEW_PICTURE: ' . ($fields['PREVIEW_PICTURE'] ?? ''));
$say('DETAIL_PICTURE: ' . ($fields['DETAIL_PICTURE'] ?? ''));

foreach ($props as $code => $prop) {
    $val = $prop['VALUE'] ?? null;
    if (is_array($val)) {
        $val = json_encode($val, JSON_UNESCAPED_UNICODE);
    }
    $say('PROP ' . $code . ': ' . (string)$val);
}

if ($matchesIb <= 0) {
    $say('ERROR: matches iblock not found');
    exit(1);
}

$matchRs = \CIBlockElement::GetList(
    ['PROPERTY_NUMBER' => 'ASC', 'DATE_ACTIVE_FROM' => 'ASC', 'ID' => 'ASC'],
    ['IBLOCK_ID' => $matchesIb, 'PROPERTY_EVENTS' => $eventId],
    false,
    false,
    [
        'ID', 'NAME', 'ACTIVE', 'XML_ID', 'DATE_ACTIVE_FROM',
        'PROPERTY_NUMBER', 'PROPERTY_HOME', 'PROPERTY_GUEST',
        'PROPERTY_EVENTS', 'PROPERTY_GROUP', 'PROPERTY_STADIUM',
    ]
);

$clubIds = [];
$numbers = [];
$sample = [];
$count = 0;
$minDate = null;
$maxDate = null;

while ($row = $matchRs->Fetch()) {
    $count++;
    $num = (int)($row['PROPERTY_NUMBER_VALUE'] ?? 0);
    $home = (int)($row['PROPERTY_HOME_VALUE'] ?? 0);
    $guest = (int)($row['PROPERTY_GUEST_VALUE'] ?? 0);
    $date = (string)($row['DATE_ACTIVE_FROM'] ?? '');

    if ($num > 0) {
        $numbers[$num] = ($numbers[$num] ?? 0) + 1;
    }
    if ($home > 0) {
        $clubIds[$home] = true;
    }
    if ($guest > 0) {
        $clubIds[$guest] = true;
    }
    if ($date !== '') {
        if ($minDate === null || $date < $minDate) {
            $minDate = $date;
        }
        if ($maxDate === null || $date > $maxDate) {
            $maxDate = $date;
        }
    }

    if (count($sample) < 12) {
        $sample[] = [
            'id' => (int)$row['ID'],
            'name' => (string)$row['NAME'],
            'number' => $num,
            'date' => $date,
            'home' => $home,
            'guest' => $guest,
            'group' => (string)($row['PROPERTY_GROUP_VALUE'] ?? ''),
            'stadium' => (string)($row['PROPERTY_STADIUM_VALUE'] ?? ''),
            'xml_id' => (string)($row['XML_ID'] ?? ''),
            'active' => (string)($row['ACTIVE'] ?? ''),
        ];
    }
}

$say('');
$say('=== Matches ===');
$say('count: ' . $count);
$say('date_range: ' . ($minDate ?? '-') . ' .. ' . ($maxDate ?? '-'));
$say('unique_numbers: ' . count($numbers));
ksort($numbers);
$numberKeys = array_keys($numbers);
$say('number_min_max: ' . ($numberKeys ? ($numberKeys[0] . '..' . end($numberKeys)) : '-'));
$say('numbers_with_multiplicity: ' . json_encode(array_filter($numbers, static function ($c) {
    return $c > 1;
}), JSON_UNESCAPED_UNICODE));

$say('');
$say('=== Sample matches (first 12) ===');
foreach ($sample as $m) {
    $say(sprintf(
        '#%d num=%d date=%s home=%d guest=%d group=%s stadium=%s xml=%s name=%s',
        $m['id'],
        $m['number'],
        $m['date'],
        $m['home'],
        $m['guest'],
        $m['group'] !== '' ? $m['group'] : '-',
        $m['stadium'] !== '' ? $m['stadium'] : '-',
        $m['xml_id'] !== '' ? $m['xml_id'] : '-',
        $m['name']
    ));
}

// Numbering pattern: matches per tour if number is tour, or sequential
$byTourGuess = [];
foreach ($numbers as $num => $c) {
    // If many matches share same number → number is tour
    // If unique → number is match sequence
}

$say('');
$say('=== Numbering analysis ===');
$multi = array_filter($numbers, static function ($c) {
    return $c > 1;
});
$unique = array_filter($numbers, static function ($c) {
    return $c === 1;
});
if (count($multi) > count($unique)) {
    $say('scheme: NUMBER = tour (multiple matches share number)');
    $say('tours: ' . count($numbers) . ', matches_per_tour_avg: ' . round(array_sum($numbers) / max(1, count($numbers)), 2));
} else {
    $say('scheme: NUMBER = sequential match index (mostly unique)');
}

$say('');
$say('=== Clubs (' . count($clubIds) . ') ===');
$clubList = array_keys($clubIds);
sort($clubList);
foreach ($clubList as $clubId) {
    $club = \CIBlockElement::GetList(
        [],
        ['IBLOCK_ID' => $countriesIb, 'ID' => $clubId],
        false,
        false,
        ['ID', 'NAME', 'XML_ID', 'CODE', 'PREVIEW_PICTURE', 'ACTIVE']
    )->Fetch();
    if (!$club) {
        $say('club #' . $clubId . ' NOT FOUND');
        continue;
    }
    $say(sprintf(
        'club #%d active=%s xml=%s code=%s name=%s pic=%s',
        (int)$club['ID'],
        (string)$club['ACTIVE'],
        (string)($club['XML_ID'] ?? ''),
        (string)($club['CODE'] ?? ''),
        (string)$club['NAME'],
        (string)($club['PREVIEW_PICTURE'] ?? '')
    ));
}

$say('');
$say('DONE');
