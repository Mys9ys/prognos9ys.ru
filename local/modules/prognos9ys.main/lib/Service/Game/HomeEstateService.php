<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\CityRepository;

class HomeEstateService
{
    private const OPTION_CATEGORY = 'prognos9ys.main';
    private const OPTION_NAME = 'home_estate';

    private CityRepository $cityRepository;

    public function __construct(?CityRepository $cityRepository = null)
    {
        $this->cityRepository = $cityRepository ?? new CityRepository();
    }

    public function getHomeEstate(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $raw = \CUserOptions::GetOption(self::OPTION_CATEGORY, self::OPTION_NAME, '', $userId);
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return null;
        }

        $citySlug = strtolower(trim((string)($decoded['city_slug'] ?? '')));
        $plotNumber = (int)($decoded['plot_number'] ?? 0);
        if ($citySlug === '' || $plotNumber <= 0) {
            return null;
        }

        if (!$this->userOwnsPlot($userId, $citySlug, $plotNumber)) {
            return null;
        }

        return $this->buildHomeEstatePayload($citySlug, $plotNumber);
    }

    public function ensureHomeEstate(int $userId): ?array
    {
        $existing = $this->getHomeEstate($userId);
        if ($existing !== null) {
            return $existing;
        }

        foreach ($this->getOwnedPlots($userId) as $row) {
            $home = $this->buildHomeEstatePayload($row['city_slug'], $row['plot_number']);
            $this->persistHomeEstate($userId, $home['city_slug'], $home['plot_number']);

            return $home;
        }

        return null;
    }

    public function setHomeEstate(int $userId, string $citySlug, int $plotNumber): array
    {
        $citySlug = strtolower(trim($citySlug));
        if ($userId <= 0 || $citySlug === '' || $plotNumber <= 0) {
            throw new \InvalidArgumentException('Некорректные параметры прописки');
        }

        if (!$this->userOwnsPlot($userId, $citySlug, $plotNumber)) {
            throw new \RuntimeException('Вы не владеете указанной усадьбой');
        }

        $this->persistHomeEstate($userId, $citySlug, $plotNumber);

        return $this->buildHomeEstatePayload($citySlug, $plotNumber);
    }

    public function clearHomeEstateIfMatches(int $userId, string $citySlug, int $plotNumber): void
    {
        $home = $this->getHomeEstate($userId);
        if (!$home) {
            return;
        }

        if (
            strtolower(trim((string)($home['city_slug'] ?? ''))) === strtolower(trim($citySlug))
            && (int)($home['plot_number'] ?? 0) === (int)$plotNumber
        ) {
            \CUserOptions::DeleteOption(self::OPTION_CATEGORY, self::OPTION_NAME, false, $userId);
        }
    }

    private function persistHomeEstate(int $userId, string $citySlug, int $plotNumber): void
    {
        \CUserOptions::SetOption(
            self::OPTION_CATEGORY,
            self::OPTION_NAME,
            json_encode([
                'city_slug' => $citySlug,
                'plot_number' => $plotNumber,
            ], JSON_UNESCAPED_UNICODE),
            false,
            $userId
        );
    }

    private function userOwnsPlot(int $userId, string $citySlug, int $plotNumber): bool
    {
        $city = $this->cityRepository->getCityBySlug($citySlug);
        if (!$city) {
            return false;
        }

        $plot = $this->cityRepository->getUserPlotInCity((int)($city['ID'] ?? 0), $userId);
        if (!$plot) {
            return false;
        }

        return (int)($plot['UF_PLOT_NUMBER'] ?? 0) === $plotNumber;
    }

    /**
     * @return array<int, array{city_slug:string,plot_number:int}>
     */
    private function getOwnedPlots(int $userId): array
    {
        $owned = [];
        foreach ($this->cityRepository->getAllCitiesIndexedBySlug() as $slug => $cityRow) {
            $cityId = (int)($cityRow['ID'] ?? 0);
            if ($cityId <= 0) {
                continue;
            }

            $plot = $this->cityRepository->getUserPlotInCity($cityId, $userId);
            if (!$plot) {
                continue;
            }

            $plotNumber = (int)($plot['UF_PLOT_NUMBER'] ?? 0);
            if ($plotNumber <= 0) {
                continue;
            }

            $owned[] = [
                'city_slug' => (string)$slug,
                'plot_number' => $plotNumber,
            ];
        }

        return $owned;
    }

    private function buildHomeEstatePayload(string $citySlug, int $plotNumber): array
    {
        return [
            'city_slug' => $citySlug,
            'city_name' => EstateCityConfig::getCityName($citySlug),
            'plot_number' => $plotNumber,
        ];
    }
}
