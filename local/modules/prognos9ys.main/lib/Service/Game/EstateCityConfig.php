<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * 48 городов ЧМ-2026: поселения, основанные в эпоху чемпионата.
 *
 * Улица = дома друг напротив друга: по 5 усадеб на сторону (10 участков).
 * Нечётные номера — одна сторона, чётные — другая.
 */
class EstateCityConfig
{
    public const FOUNDING_EVENT = 'wc26';

    /** Усадеб на одну сторону улицы. */
    public const PLOTS_PER_SIDE = 5;

    /** Всего участков в городе (5 + 5). */
    public const TOTAL_PLOTS = 10;

    /** Разовый символический взнос за «присутствие» у филиала банка в городе. */
    public const BRANCH_PRESENCE_FEE = 50.0;

    /**
     * Все 48 городов ЧМ-2026 (slug => название поселения).
     *
     * @var array<string, string>
     */
    private const CITY_NAMES = [
        'alg' => 'Алжирбург',
        'arg' => 'Аргентинск',
        'aus' => 'Австралополис',
        'aut' => 'Австривальд',
        'bel' => 'Бельбург',
        'bih' => 'Боснланд',
        'bra' => 'Бразилтон',
        'can' => 'Канадвель',
        'civ' => 'Ивуарвиль',
        'cod' => 'Конгоград',
        'col' => 'Колумбвиль',
        'cpv' => 'Кабовердянск',
        'cro' => 'Хорватвальд',
        'cze' => 'Чехск',
        'cuw' => 'Кюрасаолунд',
        'ecu' => 'Эквадорград',
        'egy' => 'Египтоград',
        'eng' => 'Англтон',
        'esp' => 'Испанвиль',
        'fra' => 'Франсвиль',
        'ger' => 'Германштадт',
        'gha' => 'Ганатон',
        'hai' => 'Гаитиабад',
        'irq' => 'Иракбург',
        'irn' => 'Иранабад',
        'jor' => 'Иорданск',
        'jpn' => 'Японск',
        'kor' => 'Корейск',
        'ksa' => 'Аравитон',
        'mar' => 'Мароккобург',
        'mex' => 'Мексикбург',
        'ned' => 'Нидерландск',
        'nor' => 'Норвегштадт',
        'nzl' => 'Зеланвилль',
        'pan' => 'Панамланд',
        'par' => 'Парагвайск',
        'por' => 'Портувиль',
        'qat' => 'Катарбург',
        'rsa' => 'Юартон',
        'sco' => 'Шотландск',
        'sen' => 'Сенегалтон',
        'sui' => 'Швейцбург',
        'swe' => 'Шведфьорд',
        'tun' => 'Тунисвиль',
        'tur' => 'Турквиль',
        'uru' => 'Уругвайтон',
        'usa' => 'Американск',
        'uzb' => 'Узбекланд',
    ];

    /**
     * @return array<string, array{
     *   slug:string,
     *   city_name:string,
     *   country_label:string,
     *   founding_event:string,
     *   plots_per_side:int,
     *   total_plots:int
     * }>
     */
    public static function all(): array
    {
        $rows = [];
        foreach (Wc26CollectibleConfig::teamSlugs() as $slug => $countryLabel) {
            $rows[$slug] = self::row($slug, $countryLabel);
        }

        return $rows;
    }

    /**
     * @return array<string, string> slug => city_name
     */
    public static function cityNames(): array
    {
        return self::CITY_NAMES;
    }

    public static function getCityName(string $slug): string
    {
        $slug = strtolower(trim($slug));
        if (isset(self::CITY_NAMES[$slug])) {
            return self::CITY_NAMES[$slug];
        }

        return Wc26CollectibleConfig::teamLabel($slug);
    }

    public static function isCityNameFinalized(string $slug): bool
    {
        return isset(self::CITY_NAMES[strtolower(trim($slug))]);
    }

    public static function getCountryLabel(string $slug): string
    {
        return Wc26CollectibleConfig::teamLabel($slug);
    }

    public static function hasCity(string $slug): bool
    {
        return isset(self::CITY_NAMES[strtolower(trim($slug))]);
    }

    /**
     * Номер участка на стороне улицы: нечётные 1,3,5,7,9 — сторона A; чётные 2,4,6,8,10 — сторона B.
     *
     * @return 'odd'|'even'
     */
    public static function plotSide(int $plotNumber): string
    {
        return ($plotNumber % 2 === 0) ? 'even' : 'odd';
    }

    public static function isValidPlotNumber(int $plotNumber): bool
    {
        return $plotNumber >= 1 && $plotNumber <= self::TOTAL_PLOTS;
    }

    /**
     * @return array{slug:string,city_name:string,country_label:string,founding_event:string,plots_per_side:int,total_plots:int}
     */
    private static function row(string $slug, string $countryLabel): array
    {
        return [
            'slug' => $slug,
            'city_name' => self::getCityName($slug),
            'country_label' => $countryLabel,
            'founding_event' => self::FOUNDING_EVENT,
            'plots_per_side' => self::PLOTS_PER_SIDE,
            'total_plots' => self::TOTAL_PLOTS,
        ];
    }
}
