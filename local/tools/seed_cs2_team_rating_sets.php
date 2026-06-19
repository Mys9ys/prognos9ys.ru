<?php
declare(strict_types=1);

/**
 * Публичные сборники рейтингов CS2 по командам IEM (8 × 6 участников).
 *
 *   php local/tools/seed_cs2_team_rating_sets.php --dry-run
 *   php local/tools/seed_cs2_team_rating_sets.php --confirm
 *   php local/tools/seed_cs2_team_rating_sets.php --confirm --event=12345
 *
 * HTTP (после деплоя):
 *   GET /mob_app/ajax/maintenance/seed_cs2_rating_sets/?key=wc2026_seed_group6&confirm=1
 */

require_once __DIR__ . '/cs2_iem_roster_data.php';

const CS2_RATING_SET_EVENT_XML = 'cs2_iem_cologne_2026';
const CS2_RATING_SET_OWNER_MAIL = 'donk@prognos9ys.ru';

/**
 * @return array{event_id:int,owner_id:int,created:int,skipped:int,sets:array<int,array<string,mixed>>}
 */
function seed_cs2_team_rating_sets(int $eventId = 0, bool $dryRun = false): array
{
    if (!\Bitrix\Main\Loader::includeModule('prognos9ys.main')) {
        throw new RuntimeException('Модуль prognos9ys.main не загружен');
    }

    if ($eventId <= 0) {
        $eventId = resolve_cs2_rating_event_id();
    }
    if ($eventId <= 0) {
        throw new RuntimeException('Не найдено событие ' . CS2_RATING_SET_EVENT_XML);
    }

    $ownerId = resolve_user_id_by_email(CS2_RATING_SET_OWNER_MAIL);
    if ($ownerId <= 0) {
        throw new RuntimeException('Не найден владелец сборников: ' . CS2_RATING_SET_OWNER_MAIL);
    }

    $service = new \Prognos9ys\Main\Service\Rating\RatingSetService();
    $existingPublic = $service->listPublic('cs2', $eventId)['sets'] ?? [];
    $existingByTitle = [];
    foreach ($existingPublic as $set) {
        $title = trim((string)($set['title'] ?? ''));
        if ($title !== '') {
            $existingByTitle[mb_strtolower($title)] = $set;
        }
    }

    $created = 0;
    $skipped = 0;
    $resultSets = [];

    foreach (cs2_iem_team_rating_sets() as $team) {
        $title = (string)$team['title'];
        $titleKey = mb_strtolower($title);

        if (isset($existingByTitle[$titleKey])) {
            $skipped++;
            $resultSets[] = [
                'title' => $title,
                'status' => 'exists',
                'setId' => (int)$existingByTitle[$titleKey]['id'],
                'membersCount' => (int)($existingByTitle[$titleKey]['membersCount'] ?? 0),
            ];
            continue;
        }

        $userIds = resolve_user_ids_by_emails($team['mails']);
        if (count($userIds) !== 6) {
            $foundMails = array_values($userIds);
            $missing = array_values(array_diff($team['mails'], $foundMails));
            throw new RuntimeException(
                'Команда ' . $title . ': нужно 6 участников, найдено ' . count($userIds)
                . ($missing ? ' (нет: ' . implode(', ', $missing) . ')' : '')
            );
        }

        if ($dryRun) {
            $created++;
            $resultSets[] = [
                'title' => $title,
                'status' => 'would_create',
                'userIds' => array_keys($userIds),
                'mails' => $team['mails'],
            ];
            continue;
        }

        $response = $service->create($ownerId, [
            'visibility' => \Prognos9ys\Main\Service\Rating\RatingSetService::VISIBILITY_OPEN,
            'sport' => 'cs2',
            'title' => $title,
            'userIds' => array_keys($userIds),
            'eventIds' => [$eventId],
        ]);

        $set = $response['set'] ?? [];
        $created++;
        $resultSets[] = [
            'title' => $title,
            'status' => 'created',
            'setId' => (int)($set['id'] ?? 0),
            'membersCount' => (int)($set['membersCount'] ?? 6),
        ];
    }

    return [
        'event_id' => $eventId,
        'owner_id' => $ownerId,
        'created' => $created,
        'skipped' => $skipped,
        'sets' => $resultSets,
    ];
}

function resolve_cs2_rating_event_id(): int
{
    if (!\Bitrix\Main\Loader::includeModule('iblock')) {
        return 0;
    }

    foreach (['content', ''] as $type) {
        $filter = ['CODE' => 'events', 'CHECK_PERMISSIONS' => 'N'];
        if ($type !== '') {
            $filter['TYPE'] = $type;
        }
        $iblock = CIBlock::GetList([], $filter)->Fetch();
        $iblockId = (int)($iblock['ID'] ?? 0);
        if ($iblockId <= 0) {
            continue;
        }

        $el = CIBlockElement::GetList(
            [],
            ['IBLOCK_ID' => $iblockId, '=XML_ID' => CS2_RATING_SET_EVENT_XML],
            false,
            ['nTopCount' => 1],
            ['ID']
        )->Fetch();

        $id = (int)($el['ID'] ?? 0);
        if ($id > 0) {
            return $id;
        }
    }

    return 0;
}

function resolve_user_id_by_email(string $email): int
{
    $email = strtolower(trim($email));
    if ($email === '') {
        return 0;
    }

    if (\Bitrix\Main\Loader::includeModule('main')) {
        $row = \Bitrix\Main\UserTable::getList([
            'select' => ['ID'],
            'filter' => [
                'LOGIC' => 'OR',
                ['=EMAIL' => $email],
                ['=LOGIN' => $email],
            ],
            'limit' => 1,
        ])->fetch();
        if (!empty($row['ID'])) {
            return (int)$row['ID'];
        }
    }

    $row = CUser::GetList($by = 'id', $order = 'asc', ['EMAIL' => $email])->Fetch();
    if (!empty($row['ID'])) {
        return (int)$row['ID'];
    }

    $row = CUser::GetList($by = 'id', $order = 'asc', ['LOGIN' => $email])->Fetch();

    return (int)($row['ID'] ?? 0);
}

/**
 * @param list<string> $emails
 * @return array<int, string> userId => email
 */
function resolve_user_ids_by_emails(array $emails): array
{
    $map = [];
    foreach ($emails as $email) {
        $email = strtolower(trim($email));
        $id = resolve_user_id_by_email($email);
        if ($id > 0) {
            $map[$id] = $email;
        }
    }

    return $map;
}

if (PHP_SAPI === 'cli' && realpath($argv[0] ?? '') === __FILE__) {
    $docRoot = dirname(__DIR__, 2);
    $_SERVER['DOCUMENT_ROOT'] = $docRoot;
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? 'prognos9ys.ru';
    $_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? 'prognos9ys.ru';

    define('NO_KEEP_STATISTIC', true);
    define('NO_AGENT_STATISTIC', true);
    define('NOT_CHECK_PERMISSIONS', true);

    require_once $docRoot . '/bitrix/modules/main/include/prolog_before.php';

    $argv = $argv ?? [];
    $dryRun = in_array('--dry-run', $argv, true) || !in_array('--confirm', $argv, true);
    $eventArg = null;
    foreach ($argv as $arg) {
        if (str_starts_with($arg, '--event=')) {
            $eventArg = (int)substr($arg, 8);
        }
    }

    if (!in_array('--dry-run', $argv, true) && !in_array('--confirm', $argv, true)) {
        echo "Dry-run по умолчанию. Запуск: php seed_cs2_team_rating_sets.php --confirm\n";
    }

    try {
        $result = seed_cs2_team_rating_sets($eventArg ?? 0, $dryRun);
        echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . PHP_EOL;
        exit($result['created'] > 0 || $result['skipped'] > 0 ? 0 : 1);
    } catch (Throwable $e) {
        fwrite(STDERR, $e->getMessage() . PHP_EOL);
        exit(1);
    }
}
