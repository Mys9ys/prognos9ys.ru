<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Вымпелы достижений (не ЧМ-26): вклеиваются в альбом достижений, не продаются на бирже.
 */
class AchievementPennantConfig
{
    public const CODE_SITE = 'site';
    public const CODE_CHM2026 = 'chm2026';

    /** @var array<string, int> */
    private const KNOWN_SYNTHETIC_MATCH_IDS = [
        self::CODE_SITE => -3000001,
        self::CODE_CHM2026 => -3000002,
    ];

    public static function professionPennantCode(string $professionCode): string
    {
        return 'prof_' . trim($professionCode);
    }

    public static function professionPremiumPennantCode(string $professionCode): string
    {
        return 'prof_' . trim($professionCode) . '_premium';
    }

    /**
     * @return string[]
     */
    public static function allCodes(): array
    {
        $codes = [
            self::CODE_SITE,
            self::CODE_CHM2026,
        ];

        foreach (ProfessionMaterialConfig::allProfessions() as $professionCode => $profession) {
            unset($profession);
            $codes[] = self::professionPennantCode($professionCode);
            $codes[] = self::professionPremiumPennantCode($professionCode);
        }

        return array_values(array_unique($codes));
    }

    public static function slotCount(): int
    {
        return count(self::allCodes());
    }

    public static function isAchievementPennantCode(string $code): bool
    {
        $code = trim($code);

        return $code !== '' && in_array($code, self::allCodes(), true);
    }

    public static function resolveSyntheticMatchId(string $pennantCode): int
    {
        $pennantCode = trim($pennantCode);
        if (isset(self::KNOWN_SYNTHETIC_MATCH_IDS[$pennantCode])) {
            return self::KNOWN_SYNTHETIC_MATCH_IDS[$pennantCode];
        }

        return -3000000 - abs((int)crc32($pennantCode));
    }

    public static function resolveCodeFromSyntheticMatchId(int $matchId): ?string
    {
        foreach (self::KNOWN_SYNTHETIC_MATCH_IDS as $code => $id) {
            if ($id === $matchId) {
                return $code;
            }
        }

        foreach (self::allCodes() as $code) {
            if (self::resolveSyntheticMatchId($code) === $matchId) {
                return $code;
            }
        }

        return null;
    }

    public static function getLabel(string $code): string
    {
        $code = trim($code);
        if ($code === self::CODE_SITE) {
            return 'Вымпел сайта';
        }
        if ($code === self::CODE_CHM2026) {
            return 'Вымпел ЧМ-2026';
        }

        if (preg_match('/^prof_(.+)_premium$/', $code, $matches)) {
            $profession = ProfessionMaterialConfig::getProfession($matches[1]);

            return ($profession['label'] ?? $matches[1]) . ' · премиум';
        }

        if (preg_match('/^prof_(.+)$/', $code, $matches)) {
            $profession = ProfessionMaterialConfig::getProfession($matches[1]);

            return ($profession['label'] ?? $matches[1]) . ' · мастерство';
        }

        return $code;
    }

    /**
     * @return array<int, array{code:string,label:string}>
     */
    public static function slotDefinitions(): array
    {
        $slots = [];
        foreach (self::allCodes() as $code) {
            $slots[] = [
                'code' => $code,
                'label' => self::getLabel($code),
            ];
        }

        return $slots;
    }
}
