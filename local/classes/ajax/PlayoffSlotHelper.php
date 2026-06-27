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

    public static function isThirdPlaceMatch(array $match): bool
    {
        $code = strtoupper(trim((string)($match['bracket_code'] ?? '')));
        if ($code === 'F3') {
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

    public static function isPlayoffMatchRow(array $res): bool
    {
        $stage = mb_strtolower(trim((string)($res['PROPERTY_STAGE_VALUE'] ?? '')));
        if (in_array($stage, ['плей-офф', 'play-off', 'playoff'], true)) {
            return true;
        }
        if (in_array($stage, ['групповой', 'group'], true)) {
            return false;
        }

        if (!empty($res['PROPERTY_BRACKET_CODE_VALUE'])) {
            return true;
        }

        $group = $res['PROPERTY_GROUP_VALUE'] ?? null;
        if ($group === 'N') {
            return true;
        }

        if ((int)($res['PROPERTY_ROUND_VALUE'] ?? 0) > 0 && self::isEmptyGroup($group)) {
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

    protected static function isEmptyGroup($group): bool
    {
        return $group === null || $group === '' || $group === false || $group === 0 || $group === '0';
    }
}
