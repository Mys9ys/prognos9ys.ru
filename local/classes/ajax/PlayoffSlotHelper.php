<?php

class PlayoffSlotHelper
{
    public static function bracketCodeOrder(string $code): int
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            return 9999;
        }
        if (preg_match('/^(?:A|B)(\d+)$/u', $code, $m)) {
            return (int)$m[1];
        }
        if (preg_match('/^QF(\d+)$/u', $code, $m)) {
            return (int)$m[1];
        }
        if (preg_match('/^SF(\d+)$/u', $code, $m)) {
            return (int)$m[1];
        }
        if (preg_match('/^LSF(\d+)$/u', $code, $m)) {
            return (int)$m[1];
        }
        if ($code === 'F1') {
            return 1;
        }
        if ($code === 'F3') {
            return 2;
        }

        return 9999;
    }

    /**
     * Стадия сетки плей-офф (1/16 … финал). Не путать с PROPERTY_round (тур группы).
     */
    public static function bracketStageFromCode(string $code): int
    {
        $code = strtoupper(trim($code));
        if (preg_match('/^A\d+$/u', $code)) {
            return 1;
        }
        if (preg_match('/^B\d+$/u', $code)) {
            return 2;
        }
        if (preg_match('/^QF\d$/u', $code)) {
            return 3;
        }
        if (preg_match('/^SF\d$/u', $code)) {
            return 4;
        }
        if ($code === 'F3' || preg_match('/^LSF\d$/u', $code)) {
            return 5;
        }
        if ($code === 'F1') {
            return 6;
        }

        return 0;
    }

    /**
     * Стадия сетки из «Этап расширенный» (список в админке матчей).
     */
    public static function bracketStageFromDetail(string $detail): int
    {
        $value = mb_strtolower(trim($detail));
        if ($value === '') {
            return 0;
        }
        if (strpos($value, '1/16') !== false) {
            return 1;
        }
        if (strpos($value, '1/8') !== false) {
            return 2;
        }
        if (strpos($value, '1/4') !== false) {
            return 3;
        }
        if (strpos($value, 'полуфинал') !== false || strpos($value, '1/2') !== false) {
            return 4;
        }
        if (strpos($value, '3') !== false && strpos($value, 'место') !== false) {
            return 5;
        }
        if ($value === 'финал') {
            return 6;
        }

        return 0;
    }

    public static function mapStageDetailLabel(string $stageLabel, string $bracketCode = ''): string
    {
        $code = strtoupper(trim($bracketCode));
        if ($code === 'F3') {
            return '3е место';
        }
        if ($code === 'F1') {
            return 'Финал';
        }

        $map = [
            '1/16' => '1/16 финала',
            '1/8' => '1/8 финала',
            '1/4' => '1/4 финала',
            '1/2' => 'Полуфинал',
            '3-е место' => '3е место',
            'За 3-е место' => '3е место',
            'Финал' => 'Финал',
        ];

        return $map[trim($stageLabel)] ?? trim($stageLabel);
    }

    public static function readPropertyValue(array $res, ?string $code): string
    {
        if (!$code) {
            return '';
        }

        $key = 'PROPERTY_' . strtoupper($code) . '_VALUE';

        return trim((string)($res[$key] ?? ''));
    }

    public static function isPlayoffStageDetail(string $value): bool
    {
        $value = mb_strtolower(trim($value));
        if ($value === '') {
            return false;
        }

        foreach ([
            '1/16 финала',
            '1/8 финала',
            '1/4 финала',
            'полуфинал',
            '3е место',
            '3-е место',
            'за 3-е место',
            'финал',
        ] as $needle) {
            if ($value === mb_strtolower($needle)) {
                return true;
            }
        }

        return false;
    }

    public static function isGroupStageDetail(string $value): bool
    {
        $value = mb_strtolower(trim($value));
        if ($value === '') {
            return false;
        }

        if (strpos($value, 'группа') === 0) {
            return true;
        }

        return in_array($value, ['отборочный', 'чемпионат', 'дома-гости', 'выживание'], true);
    }

    public static function isThirdPlaceMatch(array $match): bool
    {
        $code = strtoupper(trim((string)($match['bracket_code'] ?? '')));
        if ($code === 'F3') {
            return true;
        }

        $detail = mb_strtolower(trim((string)($match['stage_detail'] ?? '')));
        if (strpos($detail, '3') !== false && strpos($detail, 'место') !== false) {
            return true;
        }

        return ($match['card_title'] ?? '') === '3-е место';
    }

    public static function isFinalMatch(array $match): bool
    {
        $code = strtoupper(trim((string)($match['bracket_code'] ?? '')));
        if ($code === 'F1') {
            return true;
        }

        if (mb_strtolower(trim((string)($match['stage_detail'] ?? ''))) === 'финал') {
            return true;
        }

        return ($match['card_title'] ?? '') === 'Финал';
    }

    public static function compareBracketCodes(?string $a, ?string $b): int
    {
        $order = self::bracketCodeOrder((string)$a) <=> self::bracketCodeOrder((string)$b);
        if ($order !== 0) {
            return $order;
        }

        return strcmp((string)$a, (string)$b);
    }

    public static function isPlayoffMatchRow(array $res, ?string $stageDetailCode = null): bool
    {
        $stageDetail = self::readPropertyValue($res, $stageDetailCode);
        if ($stageDetail !== '') {
            if (self::isPlayoffStageDetail($stageDetail)) {
                return true;
            }
            if (self::isGroupStageDetail($stageDetail)) {
                return false;
            }
        }

        $stage = mb_strtolower(trim((string)($res['PROPERTY_STAGE_VALUE'] ?? '')));
        if (in_array($stage, ['плей-офф', 'play-off', 'playoff'], true)) {
            return true;
        }
        if (in_array($stage, ['групповой', 'group', 'чемпионат', 'дома-гости'], true)) {
            return false;
        }

        if (!empty($res['PROPERTY_BRACKET_CODE_VALUE'])) {
            return true;
        }

        return false;
    }

    public static function formatSlotLabel(string $code): string
    {
        $code = trim($code);
        if ($code === '') {
            return '';
        }

        if (preg_match('/^3([A-L]+)$/u', $code, $m)) {
            return '3-е ' . implode('/', preg_split('//u', $m[1], -1, PREG_SPLIT_NO_EMPTY));
        }
        if (preg_match('/^1([A-L])$/u', $code, $m)) {
            return '1-е ' . $m[1];
        }
        if (preg_match('/^2([A-L])$/u', $code, $m)) {
            return '2-е ' . $m[1];
        }
        if (preg_match('/^[AB]\d{2}$/u', $code) || preg_match('/^QF\d$/u', $code)
            || preg_match('/^SF\d$/u', $code) || preg_match('/^LSF\d$/u', $code)
            || in_array($code, ['F1', 'F3'], true)) {
            return 'Поб. ' . $code;
        }

        return $code;
    }

    public static function teamPayload($teamId, ?array $teamInfo, $goals, string $slotLabel): array
    {
        if ((int)$teamId > 0 && is_array($teamInfo) && !empty($teamInfo['NAME'])) {
            return [
                'flag' => $teamInfo['flag'] ?? ($teamInfo['img'] ?? ''),
                'name' => $teamInfo['NAME'],
                'goals' => $goals ?? 0,
                'is_slot' => false,
            ];
        }

        $label = trim($slotLabel);
        if ($label === '') {
            $label = 'TBD';
        } else {
            $label = self::formatSlotLabel($label);
        }

        return [
            'flag' => '',
            'name' => $label,
            'goals' => $goals ?? 0,
            'is_slot' => true,
        ];
    }
}
