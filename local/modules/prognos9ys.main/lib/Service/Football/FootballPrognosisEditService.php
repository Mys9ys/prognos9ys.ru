<?php

namespace Prognos9ys\Main\Service\Football;

use Bitrix\Main\Loader;
use Prognos9ys\Main\Model\Repository\MatchesRepository;
use Prognos9ys\Main\Service\Game\PremiumEconomyConfig;
use Prognos9ys\Main\Service\Game\PremiumService;

class FootballPrognosisEditService
{
    /** @var int[] */
    public const EDITABLE_FIELD_IDS = [15, 16, 21, 22, 23, 45, 46];

    /**
     * @param array<string, mixed> $matchPayload
     * @return array<string, mixed>
     */
    public function buildState(int $userId, array $matchPayload): array
    {
        $matchId = (int)($matchPayload['id'] ?? 0);
        $match = $matchId > 0 ? (new MatchesRepository())->findNormalizedById($matchId) : null;
        $hasPrognosis = $this->matchPayloadHasPrognosis($matchPayload);

        $premium = $userId > 0 && (new PremiumService())->hasActivePremium($userId);
        $acceptOpen = ($match['active'] ?? $matchPayload['active'] ?? 'Y') === 'Y';
        $kickoffTs = $this->resolveKickoffTimestamp($match);
        $now = time();
        $windowSeconds = PremiumEconomyConfig::PROGNOSIS_EDIT_WINDOW_MINUTES * 60;
        $windowEnd = $kickoffTs > 0 ? $kickoffTs + $windowSeconds : 0;
        $inPremiumWindow = $kickoffTs > 0 && $now >= $kickoffTs && $now < $windowEnd;

        $premiumEditActive = $premium
            && $hasPrognosis
            && !$acceptOpen
            && $inPremiumWindow;

        return [
            'random_available' => $premium && $acceptOpen,
            'premium_edit_active' => $premiumEditActive,
            'premium_edit_remaining_seconds' => $premiumEditActive ? max(0, $windowEnd - $now) : 0,
            'editable_fields' => $premiumEditActive ? self::EDITABLE_FIELD_IDS : [],
        ];
    }

    /**
     * @param array<int|string, mixed> $fields
     * @return array<int|string, mixed>
     */
    public function prepareFieldsForSubmit(int $userId, int $matchId, array $fields): array
    {
        if ($matchId <= 0) {
            throw new \RuntimeException('Матч не найден');
        }

        $match = (new MatchesRepository())->findNormalizedById($matchId);
        if ($match === null) {
            throw new \RuntimeException('Матч не найден');
        }

        $acceptOpen = ($match['active'] ?? 'Y') === 'Y';
        if ($acceptOpen) {
            return $fields;
        }

        $hasPrognosis = $this->userHasPrognosis($userId, $matchId);
        $state = $this->buildStateForMatch($userId, $match, $hasPrognosis);
        if (!$state['premium_edit_active']) {
            throw new \RuntimeException('Приём прогнозов закрыт');
        }

        if (!$hasPrognosis) {
            throw new \RuntimeException('Нельзя отправить новый прогноз после старта матча');
        }

        $existing = $this->loadPrognosisFields($userId, $matchId);
        $merged = $existing;
        foreach (self::EDITABLE_FIELD_IDS as $propId) {
            if (array_key_exists($propId, $fields)) {
                $merged[$propId] = $fields[$propId];
            } elseif (array_key_exists((string)$propId, $fields)) {
                $merged[$propId] = $fields[(string)$propId];
            }
        }

        $home = (int)($merged[15] ?? 0);
        $guest = (int)($merged[16] ?? 0);
        $merged[19] = $home - $guest;
        $merged[28] = $home + $guest;
        if ($home > $guest) {
            $merged[18] = 'п1';
        } elseif ($home < $guest) {
            $merged[18] = 'п2';
        } else {
            $merged[18] = 'н';
        }

        $playoffError = (new FootballPrognosisValidator())->validate($matchId, $merged);
        if ($playoffError !== null) {
            throw new \RuntimeException($playoffError);
        }

        return $merged;
    }

    /**
     * @param array<string, mixed> $match
     * @return array<string, mixed>
     */
    private function buildStateForMatch(int $userId, array $match, bool $hasPrognosis): array
    {
        return $this->buildState($userId, [
            'id' => (int)($match['id'] ?? 0),
            'active' => (string)($match['active'] ?? 'Y'),
            'prognosis' => $hasPrognosis ? ['time_send' => '1'] : [],
        ]);
    }

    /**
     * @param array<string, mixed> $matchPayload
     */
    private function matchPayloadHasPrognosis(array $matchPayload): bool
    {
        $prognosis = is_array($matchPayload['prognosis'] ?? null) ? $matchPayload['prognosis'] : [];

        return !empty($prognosis['time_send'])
            || ($prognosis['goal_home'] ?? null) !== null
            || ($prognosis['goal_guest'] ?? null) !== null;
    }

    private function userHasPrognosis(int $userId, int $matchId): bool
    {
        if ($userId <= 0 || $matchId <= 0) {
            return false;
        }

        return $this->loadPrognosisFields($userId, $matchId) !== [];
    }

    /**
     * @return array<int, mixed>
     */
    private function loadPrognosisFields(int $userId, int $matchId): array
    {
        if ($userId <= 0 || $matchId <= 0 || !Loader::includeModule('iblock')) {
            return [];
        }

        $prognIb = (int)(\CIBlock::GetList([], ['CODE' => 'prognosis'], false)->Fetch()['ID'] ?? 6);
        $row = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => $prognIb,
                'PROPERTY_USER_ID' => $userId,
                'PROPERTY_MATCH_ID' => $matchId,
            ],
            false,
            ['nTopCount' => 1],
            [
                'ID',
                'PROPERTY_goal_home',
                'PROPERTY_goal_guest',
                'PROPERTY_result',
                'PROPERTY_diff',
                'PROPERTY_sum',
                'PROPERTY_corner',
                'PROPERTY_yellow',
                'PROPERTY_red',
                'PROPERTY_penalty',
                'PROPERTY_domination',
                'PROPERTY_otime',
                'PROPERTY_spenalty',
                'PROPERTY_offside',
            ]
        )->GetNext();

        if (!$row) {
            return [];
        }

        return [
            15 => (int)($row['PROPERTY_GOAL_HOME_VALUE'] ?? 0),
            16 => (int)($row['PROPERTY_GOAL_GUEST_VALUE'] ?? 0),
            18 => (string)($row['PROPERTY_RESULT_VALUE'] ?? ''),
            19 => (int)($row['PROPERTY_DIFF_VALUE'] ?? 0),
            20 => (int)($row['PROPERTY_CORNER_VALUE'] ?? 0),
            21 => (int)($row['PROPERTY_YELLOW_VALUE'] ?? 0),
            22 => (int)($row['PROPERTY_RED_VALUE'] ?? 0),
            23 => (int)($row['PROPERTY_PENALTY_VALUE'] ?? 0),
            28 => (int)($row['PROPERTY_SUM_VALUE'] ?? 0),
            29 => (string)($row['PROPERTY_OFFSIDE_VALUE'] ?? ''),
            32 => (int)($row['PROPERTY_DOMINATION_VALUE'] ?? 50),
            45 => (string)($row['PROPERTY_OTIME_VALUE'] ?? ''),
            46 => (string)($row['PROPERTY_SPENALTY_VALUE'] ?? ''),
        ];
    }

    /**
     * @param array<string, mixed>|null $match
     */
    private function resolveKickoffTimestamp(?array $match): int
    {
        $dateFrom = trim((string)($match['date_active_from'] ?? ''));
        if ($dateFrom === '') {
            return 0;
        }

        $formats = ['d.m.Y H:i:s', 'd.m.Y H:i', 'd.m.Y G:i:s', 'd.m.Y G:i'];
        foreach ($formats as $format) {
            $dt = \DateTime::createFromFormat($format, $dateFrom);
            if ($dt instanceof \DateTime) {
                return $dt->getTimestamp();
            }
        }

        return 0;
    }
}
