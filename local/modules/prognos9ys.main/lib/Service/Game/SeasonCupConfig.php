<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Памятные кубки ЧМ-26 (сезонные награды): inventory-предметы в treasure HL.
 */
class SeasonCupConfig
{
    public const CODE_PREFIX = 'cup_wc26_';

    /**
     * @return array<string, array{
     *   code: string,
     *   selector: string,
     *   place: int,
     *   label: string,
     *   caption: string,
     *   icon: string
     * }>
     */
    public static function getCatalog(): array
    {
        $catalog = [];
        $placeIcons = [1 => '🥇', 2 => '🥈', 3 => '🥉'];

        foreach (SeasonAwardConfig::getNominations() as $selector => $meta) {
            $title = (string)($meta['title'] ?? $selector);
            for ($place = 1; $place <= 3; $place++) {
                $code = self::buildCode($selector, $place);
                $catalog[$code] = [
                    'code' => $code,
                    'selector' => $selector,
                    'place' => $place,
                    'label' => 'Кубок: ' . $title . ' — ' . $place . '-е',
                    'caption' => $title,
                    'icon' => $placeIcons[$place] ?? '🏆',
                ];
            }
        }

        return $catalog;
    }

    public static function buildCode(string $selector, int $place): string
    {
        return self::CODE_PREFIX . trim($selector) . '_' . max(1, min(3, $place));
    }

    public static function isSeasonCupCode(string $code): bool
    {
        $code = trim($code);

        return $code !== '' && isset(self::getCatalog()[$code]);
    }

    /**
     * Synthetic UF_MATCH_ID в диапазоне -4000001… (не пересекается с pennant/scroll).
     */
    public static function resolveSyntheticMatchId(string $cupCode): int
    {
        $cupCode = trim($cupCode);
        $catalog = self::getCatalog();
        if (!isset($catalog[$cupCode])) {
            return -4000000 - (abs((int)crc32($cupCode)) % 999999);
        }

        $selector = (string)$catalog[$cupCode]['selector'];
        $place = (int)$catalog[$cupCode]['place'];
        $selectors = array_keys(SeasonAwardConfig::getNominations());
        $index = array_search($selector, $selectors, true);
        if ($index === false) {
            $index = 0;
        }

        // -4000001 … -4000024 для известных 8×3
        return -4000000 - ((int)$index * 3 + $place);
    }

    public static function resolveCodeFromSyntheticMatchId(int $matchId): ?string
    {
        foreach (self::getCatalog() as $code => $meta) {
            unset($meta);
            if (self::resolveSyntheticMatchId($code) === $matchId) {
                return $code;
            }
        }

        return null;
    }

    /**
     * @return array{code: string, label: string, caption: string, icon: string, place: int, selector: string}|null
     */
    public static function getMeta(string $cupCode): ?array
    {
        $cupCode = trim($cupCode);

        return self::getCatalog()[$cupCode] ?? null;
    }
}
