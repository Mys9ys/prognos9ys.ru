<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * Карта пангеи ЧМ-26: регионы, позиции городов, соседство для UI и будущих битв болельщиков.
 *
 * @phpstan-type Point array{x:float,y:float}
 * @phpstan-type CityDef array{local:Point,neighbors:array<int,string>}
 * @phpstan-type RegionDef array{
 *   label:string,
 *   world:Point,
 *   zone_polygon:array<int,Point>,
 *   neighbor_regions:array<int,string>,
 *   cities:array<string,CityDef>
 * }
 */
class EstateWorldMapConfig
{
    public const LAYOUT_PANGAEA_V1 = 'pangaea_v1';

    /** @var array<string, RegionDef> */
    private const REGIONS = [
        'iberia_maghreb' => [
            'label' => 'Иберия и Магриб',
            'world' => ['x' => 36.0, 'y' => 40.0],
            'zone_polygon' => [
                ['x' => 30.0, 'y' => 34.0],
                ['x' => 44.0, 'y' => 32.0],
                ['x' => 46.0, 'y' => 50.0],
                ['x' => 32.0, 'y' => 52.0],
            ],
            'neighbor_regions' => ['western_europe', 'west_africa', 'levant_gulf'],
            'cities' => [
                'esp' => ['local' => ['x' => 68.0, 'y' => 38.0], 'neighbors' => ['por', 'fra']],
                'por' => ['local' => ['x' => 42.0, 'y' => 42.0], 'neighbors' => ['esp']],
                'mar' => ['local' => ['x' => 52.0, 'y' => 58.0], 'neighbors' => ['esp', 'alg', 'tun', 'civ']],
                'alg' => ['local' => ['x' => 58.0, 'y' => 72.0], 'neighbors' => ['mar', 'tun']],
                'tun' => ['local' => ['x' => 78.0, 'y' => 68.0], 'neighbors' => ['alg', 'mar', 'egy']],
            ],
        ],
        'western_europe' => [
            'label' => 'Западная Европа',
            'world' => ['x' => 44.0, 'y' => 24.0],
            'zone_polygon' => [
                ['x' => 42.0, 'y' => 16.0],
                ['x' => 56.0, 'y' => 14.0],
                ['x' => 58.0, 'y' => 34.0],
                ['x' => 44.0, 'y' => 36.0],
            ],
            'neighbor_regions' => ['iberia_maghreb', 'central_europe', 'north_america'],
            'cities' => [
                'fra' => ['local' => ['x' => 38.0, 'y' => 52.0], 'neighbors' => ['esp', 'bel', 'ger', 'eng']],
                'bel' => ['local' => ['x' => 48.0, 'y' => 38.0], 'neighbors' => ['fra', 'ned', 'ger']],
                'ned' => ['local' => ['x' => 52.0, 'y' => 22.0], 'neighbors' => ['bel', 'eng', 'ger']],
                'eng' => ['local' => ['x' => 28.0, 'y' => 28.0], 'neighbors' => ['sco', 'fra', 'ned']],
                'sco' => ['local' => ['x' => 22.0, 'y' => 14.0], 'neighbors' => ['eng']],
            ],
        ],
        'central_europe' => [
            'label' => 'Центр и Север Европы',
            'world' => ['x' => 52.0, 'y' => 18.0],
            'zone_polygon' => [
                ['x' => 50.0, 'y' => 10.0],
                ['x' => 64.0, 'y' => 12.0],
                ['x' => 62.0, 'y' => 30.0],
                ['x' => 48.0, 'y' => 28.0],
            ],
            'neighbor_regions' => ['western_europe', 'iberia_maghreb', 'levant_gulf'],
            'cities' => [
                'ger' => ['local' => ['x' => 42.0, 'y' => 48.0], 'neighbors' => ['aut', 'cze', 'ned', 'bel', 'fra']],
                'aut' => ['local' => ['x' => 58.0, 'y' => 58.0], 'neighbors' => ['ger', 'sui', 'cze', 'cro']],
                'sui' => ['local' => ['x' => 38.0, 'y' => 62.0], 'neighbors' => ['aut', 'fra', 'ger']],
                'cze' => ['local' => ['x' => 62.0, 'y' => 42.0], 'neighbors' => ['ger', 'aut', 'cro']],
                'cro' => ['local' => ['x' => 72.0, 'y' => 62.0], 'neighbors' => ['cze', 'bih', 'aut']],
                'bih' => ['local' => ['x' => 78.0, 'y' => 72.0], 'neighbors' => ['cro']],
                'swe' => ['local' => ['x' => 58.0, 'y' => 14.0], 'neighbors' => ['nor', 'ger']],
                'nor' => ['local' => ['x' => 42.0, 'y' => 10.0], 'neighbors' => ['swe']],
            ],
        ],
        'levant_gulf' => [
            'label' => 'Левант и Залив',
            'world' => ['x' => 58.0, 'y' => 36.0],
            'zone_polygon' => [
                ['x' => 54.0, 'y' => 28.0],
                ['x' => 74.0, 'y' => 26.0],
                ['x' => 72.0, 'y' => 48.0],
                ['x' => 52.0, 'y' => 46.0],
            ],
            'neighbor_regions' => ['iberia_maghreb', 'central_europe', 'west_africa', 'east_asia'],
            'cities' => [
                'tur' => ['local' => ['x' => 28.0, 'y' => 38.0], 'neighbors' => ['egy', 'irq', 'ger']],
                'egy' => ['local' => ['x' => 42.0, 'y' => 58.0], 'neighbors' => ['tur', 'jor', 'tun']],
                'jor' => ['local' => ['x' => 52.0, 'y' => 48.0], 'neighbors' => ['egy', 'irq', 'ksa']],
                'ksa' => ['local' => ['x' => 68.0, 'y' => 62.0], 'neighbors' => ['qat', 'jor', 'irq', 'irn']],
                'qat' => ['local' => ['x' => 78.0, 'y' => 68.0], 'neighbors' => ['ksa', 'irn']],
                'irn' => ['local' => ['x' => 82.0, 'y' => 42.0], 'neighbors' => ['irq', 'ksa', 'qat', 'uzb']],
                'irq' => ['local' => ['x' => 62.0, 'y' => 42.0], 'neighbors' => ['irn', 'jor', 'tur', 'ksa']],
            ],
        ],
        'west_africa' => [
            'label' => 'Западная Африка',
            'world' => ['x' => 40.0, 'y' => 52.0],
            'zone_polygon' => [
                ['x' => 32.0, 'y' => 48.0],
                ['x' => 50.0, 'y' => 46.0],
                ['x' => 48.0, 'y' => 60.0],
                ['x' => 34.0, 'y' => 62.0],
            ],
            'neighbor_regions' => ['iberia_maghreb', 'levant_gulf', 'southern_africa'],
            'cities' => [
                'cpv' => ['local' => ['x' => 18.0, 'y' => 28.0], 'neighbors' => ['sen']],
                'civ' => ['local' => ['x' => 48.0, 'y' => 52.0], 'neighbors' => ['gha', 'sen', 'mar']],
                'gha' => ['local' => ['x' => 62.0, 'y' => 58.0], 'neighbors' => ['civ', 'cod']],
                'sen' => ['local' => ['x' => 32.0, 'y' => 42.0], 'neighbors' => ['civ', 'cpv']],
                'cod' => ['local' => ['x' => 78.0, 'y' => 68.0], 'neighbors' => ['gha', 'rsa']],
            ],
        ],
        'southern_africa' => [
            'label' => 'Южная Африка',
            'world' => ['x' => 48.0, 'y' => 64.0],
            'zone_polygon' => [
                ['x' => 46.0, 'y' => 60.0],
                ['x' => 58.0, 'y' => 58.0],
                ['x' => 56.0, 'y' => 72.0],
                ['x' => 44.0, 'y' => 74.0],
            ],
            'neighbor_regions' => ['west_africa', 'south_america'],
            'cities' => [
                'rsa' => ['local' => ['x' => 50.0, 'y' => 50.0], 'neighbors' => ['cod', 'arg']],
            ],
        ],
        'north_america' => [
            'label' => 'Северная Америка',
            'world' => ['x' => 13.0, 'y' => 28.0],
            'zone_polygon' => [
                ['x' => 4.0, 'y' => 10.0],
                ['x' => 30.0, 'y' => 8.0],
                ['x' => 32.0, 'y' => 42.0],
                ['x' => 8.0, 'y' => 44.0],
            ],
            'neighbor_regions' => ['south_america', 'western_europe'],
            'cities' => [
                'usa' => ['local' => ['x' => 52.0, 'y' => 42.0], 'neighbors' => ['can', 'mex', 'cuw']],
                'can' => ['local' => ['x' => 48.0, 'y' => 18.0], 'neighbors' => ['usa']],
                'mex' => ['local' => ['x' => 38.0, 'y' => 58.0], 'neighbors' => ['usa', 'pan']],
                'pan' => ['local' => ['x' => 58.0, 'y' => 68.0], 'neighbors' => ['mex', 'cuw', 'col']],
                'cuw' => ['local' => ['x' => 68.0, 'y' => 62.0], 'neighbors' => ['pan', 'mex', 'usa']],
                'hai' => ['local' => ['x' => 72.0, 'y' => 48.0], 'neighbors' => ['usa', 'cuw']],
            ],
        ],
        'south_america' => [
            'label' => 'Южная Америка',
            'world' => ['x' => 18.0, 'y' => 58.0],
            'zone_polygon' => [
                ['x' => 10.0, 'y' => 42.0],
                ['x' => 34.0, 'y' => 40.0],
                ['x' => 30.0, 'y' => 74.0],
                ['x' => 12.0, 'y' => 76.0],
            ],
            'neighbor_regions' => ['north_america', 'southern_africa'],
            'cities' => [
                'arg' => ['local' => ['x' => 58.0, 'y' => 78.0], 'neighbors' => ['bra', 'uru', 'par', 'rsa']],
                'bra' => ['local' => ['x' => 62.0, 'y' => 52.0], 'neighbors' => ['arg', 'col', 'par', 'uru', 'ecu']],
                'col' => ['local' => ['x' => 42.0, 'y' => 38.0], 'neighbors' => ['ecu', 'bra', 'pan']],
                'ecu' => ['local' => ['x' => 32.0, 'y' => 32.0], 'neighbors' => ['col', 'bra']],
                'par' => ['local' => ['x' => 52.0, 'y' => 62.0], 'neighbors' => ['arg', 'bra', 'uru']],
                'uru' => ['local' => ['x' => 72.0, 'y' => 72.0], 'neighbors' => ['arg', 'par', 'bra']],
            ],
        ],
        'east_asia' => [
            'label' => 'Восточная Азия',
            'world' => ['x' => 72.0, 'y' => 30.0],
            'zone_polygon' => [
                ['x' => 68.0, 'y' => 18.0],
                ['x' => 90.0, 'y' => 20.0],
                ['x' => 88.0, 'y' => 46.0],
                ['x' => 66.0, 'y' => 44.0],
            ],
            'neighbor_regions' => ['levant_gulf', 'oceania'],
            'cities' => [
                'jpn' => ['local' => ['x' => 82.0, 'y' => 42.0], 'neighbors' => ['kor']],
                'kor' => ['local' => ['x' => 62.0, 'y' => 38.0], 'neighbors' => ['jpn', 'uzb']],
                'uzb' => ['local' => ['x' => 28.0, 'y' => 48.0], 'neighbors' => ['kor', 'irn']],
            ],
        ],
        'oceania' => [
            'label' => 'Океания',
            'world' => ['x' => 80.0, 'y' => 62.0],
            'zone_polygon' => [
                ['x' => 70.0, 'y' => 50.0],
                ['x' => 94.0, 'y' => 52.0],
                ['x' => 90.0, 'y' => 74.0],
                ['x' => 68.0, 'y' => 72.0],
            ],
            'neighbor_regions' => ['east_asia', 'south_america'],
            'cities' => [
                'aus' => ['local' => ['x' => 38.0, 'y' => 58.0], 'neighbors' => ['nzl']],
                'nzl' => ['local' => ['x' => 72.0, 'y' => 72.0], 'neighbors' => ['aus']],
            ],
        ],
    ];

    /**
     * @return array<string, RegionDef>
     */
    public static function regions(): array
    {
        return self::REGIONS;
    }

    /**
     * @return array<int, string>
     */
    public static function regionIds(): array
    {
        return array_keys(self::REGIONS);
    }

    public static function getRegionLabel(string $regionId): string
    {
        return (string)(self::REGIONS[$regionId]['label'] ?? $regionId);
    }

    /**
     * @return RegionDef|null
     */
    public static function getRegion(string $regionId): ?array
    {
        return self::REGIONS[$regionId] ?? null;
    }

    public static function regionForSlug(string $slug): ?string
    {
        $slug = strtolower(trim($slug));
        foreach (self::REGIONS as $regionId => $region) {
            if (isset($region['cities'][$slug])) {
                return (string)$regionId;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public static function getCityNeighbors(string $slug): array
    {
        $slug = strtolower(trim($slug));
        foreach (self::REGIONS as $region) {
            if (!isset($region['cities'][$slug])) {
                continue;
            }

            return array_values($region['cities'][$slug]['neighbors'] ?? []);
        }

        return [];
    }

    /**
     * @return array<int, string>
     */
    public static function getRegionNeighbors(string $regionId): array
    {
        return array_values(self::REGIONS[$regionId]['neighbor_regions'] ?? []);
    }

    /**
     * @return array<int, array{0:string,1:string}>
     */
    public static function allCityNeighborPairs(): array
    {
        $pairs = [];
        $seen = [];

        foreach (self::REGIONS as $region) {
            foreach ($region['cities'] as $slug => $cityDef) {
                foreach ($cityDef['neighbors'] ?? [] as $neighbor) {
                    $pair = self::normalizePair((string)$slug, (string)$neighbor);
                    if (isset($seen[$pair])) {
                        continue;
                    }
                    $seen[$pair] = true;
                    $parts = explode('|', $pair);

                    $pairs[] = [$parts[0], $parts[1]];
                }
            }
        }

        usort($pairs, static function (array $a, array $b): int {
            $cmp = strcmp($a[0], $b[0]);

            return $cmp !== 0 ? $cmp : strcmp($a[1], $b[1]);
        });

        return $pairs;
    }

    /**
     * @return array<int, array{0:string,1:string}>
     */
    public static function allRegionNeighborPairs(): array
    {
        $pairs = [];
        $seen = [];

        foreach (self::REGIONS as $regionId => $region) {
            foreach ($region['neighbor_regions'] ?? [] as $neighborId) {
                $pair = self::normalizePair((string)$regionId, (string)$neighborId);
                if (isset($seen[$pair])) {
                    continue;
                }
                $seen[$pair] = true;
                $parts = explode('|', $pair);

                $pairs[] = [$parts[0], $parts[1]];
            }
        }

        usort($pairs, static function (array $a, array $b): int {
            $cmp = strcmp($a[0], $b[0]);

            return $cmp !== 0 ? $cmp : strcmp($a[1], $b[1]);
        });

        return $pairs;
    }

    /**
     * @return array<int, array{from:string,to:string,from_region:string,to_region:string}>
     */
    public static function crossRegionCityLinks(): array
    {
        $links = [];

        foreach (self::allCityNeighborPairs() as [$a, $b]) {
            $regionA = self::regionForSlug($a);
            $regionB = self::regionForSlug($b);
            if ($regionA === null || $regionB === null || $regionA === $regionB) {
                continue;
            }

            $links[] = [
                'from' => $a,
                'to' => $b,
                'from_region' => $regionA,
                'to_region' => $regionB,
            ];
        }

        return $links;
    }

    /**
     * @deprecated Группы ЧМ больше не используются на карте.
     *
     * @return array<string, array<int, string>>
     */
    public static function groups(): array
    {
        $legacy = [];
        foreach (self::REGIONS as $regionId => $region) {
            $legacy[$regionId] = array_keys($region['cities']);
        }

        return $legacy;
    }

    /**
     * @deprecated
     */
    public static function groupForSlug(string $slug): ?string
    {
        return self::regionForSlug($slug);
    }

    /**
     * @deprecated
     *
     * @return array<int, string>
     */
    public static function groupIds(): array
    {
        return self::regionIds();
    }

    private static function normalizePair(string $a, string $b): string
    {
        $a = strtolower(trim($a));
        $b = strtolower(trim($b));
        if (strcmp($a, $b) > 0) {
            return $b . '|' . $a;
        }

        return $a . '|' . $b;
    }
}
