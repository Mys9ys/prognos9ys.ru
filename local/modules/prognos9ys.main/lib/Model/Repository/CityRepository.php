<?php

namespace Prognos9ys\Main\Model\Repository;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Service\Game\EstateCityConfig;
use Prognos9ys\Main\Service\Game\GameEconomyHlInstaller;

class CityRepository
{
    private ?string $cityDataClass = null;
    private ?string $cityPlotDataClass = null;

    /**
     * @return array<string, array<string, mixed>> slug => row
     */
    public function getAllCitiesIndexedBySlug(): array
    {
        $dataClass = $this->getCityDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'order' => ['ID' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            $slug = strtolower(trim((string)($row['UF_TEAM_SLUG'] ?? '')));
            if ($slug !== '') {
                $rows[$slug] = $row;
            }
        }

        return $rows;
    }

    public function getCityBySlug(string $slug): ?array
    {
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            return null;
        }

        $dataClass = $this->getCityDataClass();

        return $dataClass::getList([
            'filter' => ['=UF_TEAM_SLUG' => $slug],
            'limit' => 1,
        ])->fetch() ?: null;
    }

    public function getCityById(int $cityId): ?array
    {
        if ($cityId <= 0) {
            return null;
        }

        $dataClass = $this->getCityDataClass();

        return $dataClass::getById($cityId)->fetch() ?: null;
    }

    public function createCity(string $slug, int $foundedByUserId): int
    {
        $slug = strtolower(trim($slug));
        if ($slug === '' || !EstateCityConfig::hasCity($slug)) {
            throw new \InvalidArgumentException('Неизвестный город');
        }

        if ($this->getCityBySlug($slug)) {
            throw new \RuntimeException('Город уже основан');
        }

        $now = new DateTime();
        $dataClass = $this->getCityDataClass();
        $result = $dataClass::add([
            'UF_TEAM_SLUG' => $slug,
            'UF_STATUS' => EstateCityConfig::STATUS_FOUNDING,
            'UF_FOUNDED_BY_USER_ID' => $foundedByUserId,
            'UF_FOUNDED_AT' => $now,
        ]);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function updateCity(int $cityId, array $fields): void
    {
        $dataClass = $this->getCityDataClass();
        $result = $dataClass::update($cityId, $fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getPlotsByCityId(int $cityId): array
    {
        if ($cityId <= 0) {
            return [];
        }

        $dataClass = $this->getCityPlotDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => ['=UF_CITY_ID' => $cityId],
            'order' => ['UF_PLOT_NUMBER' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function countClaimedPlots(int $cityId): int
    {
        if ($cityId <= 0) {
            return 0;
        }

        $dataClass = $this->getCityPlotDataClass();

        return (int)$dataClass::getCount([
            '=UF_CITY_ID' => $cityId,
            '>UF_OWNER_USER_ID' => 0,
        ]);
    }

    public function ensureCityPlots(int $cityId): void
    {
        if ($cityId <= 0) {
            return;
        }

        $existing = $this->getPlotsByCityId($cityId);
        $existingNumbers = [];
        foreach ($existing as $row) {
            $existingNumbers[(int)($row['UF_PLOT_NUMBER'] ?? 0)] = true;
        }

        $dataClass = $this->getCityPlotDataClass();
        for ($plot = 1; $plot <= EstateCityConfig::TOTAL_PLOTS; $plot++) {
            if (isset($existingNumbers[$plot])) {
                continue;
            }

            $result = $dataClass::add([
                'UF_CITY_ID' => $cityId,
                'UF_PLOT_NUMBER' => $plot,
                'UF_OWNER_USER_ID' => 0,
            ]);

            if (!$result->isSuccess()) {
                throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
            }
        }
    }

    private function getCityDataClass(): string
    {
        return $this->cityDataClass
            ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_CITY);
    }

    private function getCityPlotDataClass(): string
    {
        return $this->cityPlotDataClass
            ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_CITY_PLOT);
    }

    private function compileDataClass(string $tableName): string
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock не установлен');
        }

        $hlblock = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => $tableName],
        ])->fetch();

        if (!$hlblock) {
            throw new \RuntimeException(
                'HL-блок не найден: ' . $tableName . '. Запустите install_city_hl.php'
            );
        }

        $entity = HighloadBlockTable::compileEntity($hlblock);

        return $entity->getDataClass();
    }
}
