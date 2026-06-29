<?php

namespace Prognos9ys\Main\Service\Game;

/**
 * 48 сборных ЧМ-2026 для вымпелов и шарфов из паков.
 */
class Wc26CollectibleConfig
{
    /** @var array<string, string> slug => название сборной */
    private const TEAMS = [
        'aus' => 'Австралия',
        'aut' => 'Австрия',
        'alg' => 'Алжир',
        'eng' => 'Англия',
        'arg' => 'Аргентина',
        'bel' => 'Бельгия',
        'bih' => 'Босния и Герцеговина',
        'bra' => 'Бразилия',
        'hai' => 'Гаити',
        'gha' => 'Гана',
        'ger' => 'Германия',
        'cod' => 'ДР Конго',
        'egy' => 'Египет',
        'jor' => 'Иордания',
        'irq' => 'Ирак',
        'irn' => 'Иран',
        'esp' => 'Испания',
        'cpv' => 'Кабо-Верде',
        'can' => 'Канада',
        'qat' => 'Катар',
        'col' => 'Колумбия',
        'civ' => 'Кот-д\'Ивуар',
        'cuw' => 'Кюрасао',
        'mar' => 'Марокко',
        'mex' => 'Мексика',
        'ned' => 'Нидерланды',
        'nzl' => 'Новая Зеландия',
        'nor' => 'Норвегия',
        'pan' => 'Панама',
        'par' => 'Парагвай',
        'por' => 'Португалия',
        'ksa' => 'С. Аравия',
        'usa' => 'США',
        'sen' => 'Сенегал',
        'tun' => 'Тунис',
        'tur' => 'Турция',
        'uzb' => 'Узбекистан',
        'uru' => 'Уругвай',
        'fra' => 'Франция',
        'cro' => 'Хорватия',
        'cze' => 'Чехия',
        'sui' => 'Швейцария',
        'swe' => 'Швеция',
        'sco' => 'Шотландия',
        'ecu' => 'Эквадор',
        'kor' => 'Ю. Корея',
        'rsa' => 'ЮАР',
        'jpn' => 'Япония',
    ];

    /**
     * @return array<string, string>
     */
    public static function teamSlugs(): array
    {
        return self::TEAMS;
    }

    public static function teamLabel(string $slug): string
    {
        $slug = strtolower(trim($slug));

        return self::TEAMS[$slug] ?? $slug;
    }

    public static function pennantCode(string $slug): string
    {
        return 'pennant_wc26_' . strtolower(trim($slug));
    }

    public static function scarfCode(string $slug): string
    {
        return 'scarf_wc26_' . strtolower(trim($slug));
    }

    public static function rollTeamSlug(): string
    {
        $slugs = array_keys(self::TEAMS);
        if (!$slugs) {
            throw new \RuntimeException('Пул сборных ЧМ-26 пуст');
        }

        return $slugs[random_int(0, count($slugs) - 1)];
    }

    public static function parsePennantSlug(string $code): ?string
    {
        $code = trim($code);
        if (!preg_match('/^pennant_wc26_([a-z0-9]+)$/', $code, $matches)) {
            return null;
        }

        $slug = (string)$matches[1];

        return isset(self::TEAMS[$slug]) ? $slug : null;
    }

    public static function parseScarfSlug(string $code): ?string
    {
        $code = trim($code);
        if (!preg_match('/^scarf_wc26_([a-z0-9]+)$/', $code, $matches)) {
            return null;
        }

        $slug = (string)$matches[1];

        return isset(self::TEAMS[$slug]) ? $slug : null;
    }

    public static function getPennantLabel(string $code): string
    {
        $slug = self::parsePennantSlug($code);

        return $slug !== null
            ? 'Вымпел: ' . self::teamLabel($slug)
            : $code;
    }

    public static function getScarfLabel(string $code): string
    {
        $slug = self::parseScarfSlug($code);

        return $slug !== null
            ? 'Шарф: ' . self::teamLabel($slug)
            : $code;
    }

    public static function extractTeamSlugFromCollectibleCode(string $code): ?string
    {
        return self::parsePennantSlug($code) ?? self::parseScarfSlug($code);
    }
}
