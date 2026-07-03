<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Карта поселений ЧМ-26: 12 групп × 4 города (48).
 * Порядок slug совпадает с {@see Wc26CollectibleConfig::teamSlugs()}.
 */
class EstateWorldMapConfig
{
    /** @var array<string, array<int, string>> */
    private const GROUPS = [
        'A' => ['aus', 'aut', 'alg', 'eng'],
        'B' => ['arg', 'bel', 'bih', 'bra'],
        'C' => ['hai', 'gha', 'ger', 'cod'],
        'D' => ['egy', 'jor', 'irq', 'irn'],
        'E' => ['esp', 'cpv', 'can', 'qat'],
        'F' => ['col', 'civ', 'cuw', 'mar'],
        'G' => ['mex', 'ned', 'nzl', 'nor'],
        'H' => ['pan', 'par', 'por', 'ksa'],
        'I' => ['usa', 'sen', 'tun', 'tur'],
        'J' => ['uzb', 'uru', 'fra', 'cro'],
        'K' => ['cze', 'sui', 'swe', 'sco'],
        'L' => ['ecu', 'kor', 'rsa', 'jpn'],
    ];

    /**
     * @return array<string, array<int, string>>
     */
    public static function groups(): array
    {
        return self::GROUPS;
    }

    public static function groupForSlug(string $slug): ?string
    {
        $slug = strtolower(trim($slug));
        foreach (self::GROUPS as $groupId => $slugs) {
            if (in_array($slug, $slugs, true)) {
                return (string)$groupId;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function groupIds(): array
    {
        return array_keys(self::GROUPS);
    }
}
