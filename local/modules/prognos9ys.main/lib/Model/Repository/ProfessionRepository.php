<?php

namespace Prognos9ys\Main\Model\Repository;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Service\Game\GameEconomyHlInstaller;
use Prognos9ys\Main\Service\Game\LevelService;
use Prognos9ys\Main\Service\Game\ProfessionAchievementConfig;
use Prognos9ys\Main\Service\Game\ProfessionEconomyConfig;
use Prognos9ys\Main\Service\Game\ProfessionMaterialConfig;

class ProfessionRepository
{
    private ?string $userProfessionDataClass = null;
    private ?string $professionSessionDataClass = null;
    private ?string $userMaterialDataClass = null;
    private ?string $govWarehouseDataClass = null;
    private ?string $constructionProjectDataClass = null;
    private ?LevelService $levelService = null;

    private function levelService(): LevelService
    {
        return $this->levelService ??= new LevelService();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getProfessionsByUserId(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $dataClass = $this->getUserProfessionDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => ['=UF_USER_ID' => $userId],
            'order' => ['UF_SLOT_INDEX' => 'ASC', 'ID' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return int[]
     */
    public function getDistinctProfessionUserIds(int $limit = 0, int $offset = 0): array
    {
        $limit = max(0, $limit);
        $offset = max(0, $offset);
        $dataClass = $this->getUserProfessionDataClass();
        $query = [
            'select' => ['UF_USER_ID'],
            'order' => ['UF_USER_ID' => 'ASC'],
            'group' => ['UF_USER_ID'],
        ];
        if ($limit > 0) {
            $query['limit'] = $limit;
            $query['offset'] = $offset;
        }

        $rows = [];
        $response = $dataClass::getList($query);
        while ($row = $response->fetch()) {
            $userId = (int)($row['UF_USER_ID'] ?? 0);
            if ($userId > 0) {
                $rows[] = $userId;
            }
        }

        return $rows;
    }

    public function getProfessionByUserAndCode(int $userId, string $code): ?array
    {
        if ($userId <= 0 || $code === '') {
            return null;
        }

        $dataClass = $this->getUserProfessionDataClass();

        return $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_PROFESSION_CODE' => $code,
            ],
            'limit' => 1,
        ])->fetch() ?: null;
    }

    public function addUserProfession(int $userId, string $code, int $slotIndex): int
    {
        $now = new DateTime();
        $dataClass = $this->getUserProfessionDataClass();
        $result = $dataClass::add([
            'UF_USER_ID' => $userId,
            'UF_PROFESSION_CODE' => $code,
            'UF_LEVEL' => 0,
            'UF_XP' => 0,
            'UF_NORMAL_YIELD' => 0,
            'UF_PREMIUM_YIELD' => 0,
            'UF_SLOT_INDEX' => $slotIndex,
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function calcProfessionLevel(int $xp, int $playerLevelCap = 0): int
    {
        $level = $this->levelService()->getLevelFromXp((float)$xp);
        $level = min(ProfessionEconomyConfig::PROFESSION_LEVEL_ABSOLUTE_MAX, max(0, $level));

        if ($playerLevelCap > 0) {
            $level = min($level, $playerLevelCap);
        }

        return $level;
    }

    public function maxXpForProfessionLevel(int $playerLevelCap): float
    {
        if ($playerLevelCap <= 0) {
            return 0.0;
        }

        $tiers = $this->levelService()->getTiers();
        $nextLevel = $playerLevelCap + 1;
        if (isset($tiers[$nextLevel])) {
            return (float)$tiers[$nextLevel]['min_xp'] - 0.1;
        }

        $capLevel = min($playerLevelCap, ProfessionEconomyConfig::PROFESSION_LEVEL_ABSOLUTE_MAX);
        if (!isset($tiers[$capLevel])) {
            return 0.0;
        }

        return (float)$tiers[$capLevel]['min_xp'] + 999999.0;
    }

    /**
     * @return array{old_level:int,new_level:int,profession_code:string,xp:float}|null
     */
    public function addProfessionXp(int $professionRowId, int $xpGain, int $playerLevelCap, string $professionCode): ?array
    {
        $dataClass = $this->getUserProfessionDataClass();
        $row = $dataClass::getList([
            'filter' => ['=ID' => $professionRowId],
            'limit' => 1,
        ])->fetch();

        if (!$row || $xpGain <= 0 || $playerLevelCap <= 0) {
            return null;
        }

        $currentXp = (float)($row['UF_XP'] ?? 0);
        $maxXp = $this->maxXpForProfessionLevel($playerLevelCap);
        $oldLevel = $this->calcProfessionLevel((int)round($currentXp), $playerLevelCap);

        if ($currentXp >= $maxXp) {
            return [
                'old_level' => $oldLevel,
                'new_level' => $oldLevel,
                'profession_code' => $professionCode,
                'xp' => $currentXp,
            ];
        }

        $xp = min($currentXp + $xpGain, $maxXp);
        $newLevel = $this->calcProfessionLevel((int)round($xp), $playerLevelCap);

        $dataClass::update($professionRowId, [
            'UF_XP' => (int)round($xp),
            'UF_LEVEL' => $newLevel,
            'UF_UPDATED_AT' => new DateTime(),
        ]);

        return [
            'old_level' => $oldLevel,
            'new_level' => $newLevel,
            'profession_code' => $professionCode,
            'xp' => $xp,
        ];
    }

    public function incrementYield(int $professionRowId, int $normalQty, int $premiumQty): void
    {
        if ($professionRowId <= 0 || ($normalQty <= 0 && $premiumQty <= 0)) {
            return;
        }

        $dataClass = $this->getUserProfessionDataClass();
        $row = $dataClass::getList([
            'filter' => ['=ID' => $professionRowId],
            'limit' => 1,
        ])->fetch();

        if (!$row) {
            return;
        }

        $fields = ['UF_UPDATED_AT' => new DateTime()];
        if ($normalQty > 0) {
            $fields['UF_NORMAL_YIELD'] = (int)($row['UF_NORMAL_YIELD'] ?? 0) + $normalQty;
        }
        if ($premiumQty > 0) {
            $fields['UF_PREMIUM_YIELD'] = (int)($row['UF_PREMIUM_YIELD'] ?? 0) + $premiumQty;
        }

        $dataClass::update($professionRowId, $fields);
    }

    /**
     * @return array<string, int>
     */
    public function getYieldStatsByUserId(int $userId): array
    {
        $stats = [];
        foreach ($this->getProfessionsByUserId($userId) as $row) {
            $code = (string)($row['UF_PROFESSION_CODE'] ?? '');
            if ($code === '') {
                continue;
            }
            $stats[ProfessionAchievementConfig::statKeyNormal($code)] = (int)($row['UF_NORMAL_YIELD'] ?? 0);
            $stats[ProfessionAchievementConfig::statKeyPremium($code)] = (int)($row['UF_PREMIUM_YIELD'] ?? 0);
        }

        return $stats;
    }

    /**
     * @return array<int, array<string, int>>
     */
    public function getYieldStatsMapForAllUsers(): array
    {
        $map = [];
        $dataClass = $this->getUserProfessionDataClass();
        $response = $dataClass::getList([
            'select' => ['UF_USER_ID', 'UF_PROFESSION_CODE', 'UF_NORMAL_YIELD', 'UF_PREMIUM_YIELD'],
        ]);

        while ($row = $response->fetch()) {
            $userId = (int)($row['UF_USER_ID'] ?? 0);
            $code = (string)($row['UF_PROFESSION_CODE'] ?? '');
            if ($userId <= 0 || $code === '') {
                continue;
            }

            if (!isset($map[$userId])) {
                $map[$userId] = [];
            }

            $map[$userId][ProfessionAchievementConfig::statKeyNormal($code)] = (int)($row['UF_NORMAL_YIELD'] ?? 0);
            $map[$userId][ProfessionAchievementConfig::statKeyPremium($code)] = (int)($row['UF_PREMIUM_YIELD'] ?? 0);
        }

        return $map;
    }

    public function getActiveSessionByUserId(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $dataClass = $this->getProfessionSessionDataClass();

        return $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_STATUS' => ProfessionMaterialConfig::SESSION_STATUS_ACTIVE,
            ],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ])->fetch() ?: null;
    }

    public function getLastSessionByUserId(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        $dataClass = $this->getProfessionSessionDataClass();

        return $dataClass::getList([
            'filter' => ['=UF_USER_ID' => $userId],
            'order' => ['ID' => 'DESC'],
            'limit' => 1,
        ])->fetch() ?: null;
    }

    public function addProfessionSession(array $fields): int
    {
        $dataClass = $this->getProfessionSessionDataClass();
        $result = $dataClass::add($fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function updateProfessionSession(int $id, array $fields): void
    {
        $dataClass = $this->getProfessionSessionDataClass();
        $result = $dataClass::update($id, $fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    public function getProfessionSessionById(int $sessionId): ?array
    {
        if ($sessionId <= 0) {
            return null;
        }

        $dataClass = $this->getProfessionSessionDataClass();

        return $dataClass::getList([
            'filter' => ['=ID' => $sessionId],
            'limit' => 1,
        ])->fetch() ?: null;
    }

    /**
     * Атомарно резервирует один цикл смены (защита от двойной обработки при параллельных запросах).
     *
     * @return array<string, mixed>|null
     */
    public function tryReserveDueSessionTick(int $sessionId): ?array
    {
        if ($sessionId <= 0) {
            return null;
        }

        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $lockName = $helper->forSql('p9_prof_tick_' . $sessionId);
        $lockRow = $connection->query("SELECT GET_LOCK('{$lockName}', 5) AS L")->fetch();
        if ((int)($lockRow['L'] ?? 0) !== 1) {
            return null;
        }

        try {
            $row = $this->getProfessionSessionById($sessionId);
            if (!$row) {
                return null;
            }

            if ((string)($row['UF_STATUS'] ?? '') !== ProfessionMaterialConfig::SESSION_STATUS_ACTIVE) {
                return null;
            }

            $nextTick = $row['UF_NEXT_TICK_AT'] ?? null;
            if (!$nextTick instanceof DateTime || $nextTick->getTimestamp() > time()) {
                return null;
            }

            $done = (int)($row['UF_ITERATIONS_DONE'] ?? 0);
            $total = (int)($row['UF_ITERATIONS_TOTAL'] ?? 0);
            if ($done >= $total || $total <= 0) {
                return null;
            }

            $now = new DateTime();
            $update = [
                'UF_ITERATIONS_DONE' => $total,
                'UF_STATUS' => ProfessionMaterialConfig::SESSION_STATUS_COMPLETED,
                'UF_NEXT_TICK_AT' => null,
                'UF_UPDATED_AT' => $now,
            ];

            $this->updateProfessionSession($sessionId, $update);

            return array_merge($row, $update);
        } finally {
            $connection->query("SELECT RELEASE_LOCK('{$lockName}')");
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getMaterialsByUserId(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $dataClass = $this->getUserMaterialDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => ['=UF_USER_ID' => $userId],
            'order' => ['UF_MATERIAL_CODE' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function addUserMaterialQty(
        int $userId,
        string $materialCode,
        int $qty,
        bool $isPremium = false
    ): void {
        if ($userId <= 0 || $materialCode === '' || $qty <= 0) {
            return;
        }

        $dataClass = $this->getUserMaterialDataClass();
        $row = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_MATERIAL_CODE' => $materialCode,
                '=UF_IS_PREMIUM' => $isPremium ? 'Y' : 'N',
            ],
            'limit' => 1,
        ])->fetch();

        $now = new DateTime();

        if ($row) {
            $dataClass::update((int)$row['ID'], [
                'UF_QTY' => (int)($row['UF_QTY'] ?? 0) + $qty,
                'UF_UPDATED_AT' => $now,
            ]);

            return;
        }

        $dataClass::add([
            'UF_USER_ID' => $userId,
            'UF_MATERIAL_CODE' => $materialCode,
            'UF_QTY' => $qty,
            'UF_IS_PREMIUM' => $isPremium ? 'Y' : 'N',
            'UF_UPDATED_AT' => $now,
        ]);
    }

    public function getUserMaterialQty(int $userId, string $materialCode, bool $isPremium = false): int
    {
        if ($userId <= 0 || $materialCode === '') {
            return 0;
        }

        $dataClass = $this->getUserMaterialDataClass();
        $row = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_MATERIAL_CODE' => $materialCode,
                '=UF_IS_PREMIUM' => $isPremium ? 'Y' : 'N',
            ],
            'limit' => 1,
        ])->fetch();

        return (int)($row['UF_QTY'] ?? 0);
    }

    public function consumeUserMaterialQty(
        int $userId,
        string $materialCode,
        int $qty,
        bool $isPremium = false
    ): void {
        if ($userId <= 0 || $materialCode === '' || $qty <= 0) {
            throw new \InvalidArgumentException('Некорректное количество материала');
        }

        $dataClass = $this->getUserMaterialDataClass();
        $row = $dataClass::getList([
            'filter' => [
                '=UF_USER_ID' => $userId,
                '=UF_MATERIAL_CODE' => $materialCode,
                '=UF_IS_PREMIUM' => $isPremium ? 'Y' : 'N',
            ],
            'limit' => 1,
        ])->fetch();

        if (!$row) {
            throw new \RuntimeException('Недостаточно материалов');
        }

        $current = (int)($row['UF_QTY'] ?? 0);
        if ($current < $qty) {
            throw new \RuntimeException('Недостаточно материалов');
        }

        $left = $current - $qty;
        if ($left > 0) {
            $dataClass::update((int)$row['ID'], [
                'UF_QTY' => $left,
                'UF_UPDATED_AT' => new DateTime(),
            ]);

            return;
        }

        $dataClass::delete((int)$row['ID']);
    }

    public function addGovWarehouseQty(string $materialCode, int $qty): void
    {
        if ($materialCode === '' || $qty <= 0) {
            return;
        }

        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $lockName = $helper->forSql('p9_gov_wh_' . md5($materialCode));
        $lockRow = $connection->query("SELECT GET_LOCK('{$lockName}', 5) AS L")->fetch();
        if ((int)($lockRow['L'] ?? 0) !== 1) {
            throw new \RuntimeException('Не удалось заблокировать госсклад');
        }

        try {
            $dataClass = $this->getGovWarehouseDataClass();
            $rows = [];
            $response = $dataClass::getList([
                'filter' => ['=UF_MATERIAL_CODE' => $materialCode],
                'order' => ['ID' => 'ASC'],
            ]);

            while ($row = $response->fetch()) {
                $rows[] = $row;
            }

            $now = new DateTime();

            if (!$rows) {
                $dataClass::add([
                    'UF_MATERIAL_CODE' => $materialCode,
                    'UF_QTY' => $qty,
                    'UF_UPDATED_AT' => $now,
                ]);

                return;
            }

            $existingTotal = 0;
            foreach ($rows as $row) {
                $existingTotal += (int)($row['UF_QTY'] ?? 0);
            }

            $dataClass::update((int)$rows[0]['ID'], [
                'UF_QTY' => $existingTotal + $qty,
                'UF_UPDATED_AT' => $now,
            ]);

            for ($i = 1, $count = count($rows); $i < $count; $i++) {
                $dataClass::delete((int)$rows[$i]['ID']);
            }
        } finally {
            $connection->query("SELECT RELEASE_LOCK('{$lockName}')");
        }
    }

    public function getGovWarehouseQty(string $materialCode): int
    {
        if ($materialCode === '') {
            return 0;
        }

        return (int)($this->getGovWarehouseQtyMap()[$materialCode] ?? 0);
    }

    public function consumeGovWarehouseQty(string $materialCode, int $qty): void
    {
        if ($materialCode === '' || $qty <= 0) {
            throw new \InvalidArgumentException('Некорректное количество материала');
        }

        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $lockName = $helper->forSql('p9_gov_wh_' . md5($materialCode));
        $lockRow = $connection->query("SELECT GET_LOCK('{$lockName}', 5) AS L")->fetch();
        if ((int)($lockRow['L'] ?? 0) !== 1) {
            throw new \RuntimeException('Не удалось заблокировать госсклад');
        }

        try {
            $dataClass = $this->getGovWarehouseDataClass();
            $rows = [];
            $response = $dataClass::getList([
                'filter' => ['=UF_MATERIAL_CODE' => $materialCode],
                'order' => ['ID' => 'ASC'],
            ]);

            while ($row = $response->fetch()) {
                $rows[] = $row;
            }

            $current = 0;
            foreach ($rows as $row) {
                $current += (int)($row['UF_QTY'] ?? 0);
            }

            if ($current < $qty) {
                throw new \RuntimeException('Недостаточно материалов на госскладе');
            }

            $left = $current - $qty;
            $now = new DateTime();

            if (!$rows) {
                throw new \RuntimeException('Недостаточно материалов на госскладе');
            }

            if ($left > 0) {
                $dataClass::update((int)$rows[0]['ID'], [
                    'UF_QTY' => $left,
                    'UF_UPDATED_AT' => $now,
                ]);

                for ($i = 1, $count = count($rows); $i < $count; $i++) {
                    $dataClass::delete((int)$rows[$i]['ID']);
                }

                return;
            }

            foreach ($rows as $row) {
                $dataClass::delete((int)$row['ID']);
            }
        } finally {
            $connection->query("SELECT RELEASE_LOCK('{$lockName}')");
        }
    }

    /**
     * @return array<string, int>
     */
    public function getGovWarehouseQtyMap(): array
    {
        $map = [];
        foreach ($this->getGovWarehouseRows() as $row) {
            $code = (string)($row['UF_MATERIAL_CODE'] ?? '');
            if ($code === '') {
                continue;
            }
            $map[$code] = ($map[$code] ?? 0) + (int)($row['UF_QTY'] ?? 0);
        }

        return $map;
    }

    /**
     * Сумма материалов у всех игроков по коду (инвентарь фарма).
     *
     * @return array<string, int>
     */
    public function getGlobalUserMaterialQtyByCode(): array
    {
        $dataClass = $this->getUserMaterialDataClass();
        $map = [];
        $response = $dataClass::getList([
            'select' => ['UF_MATERIAL_CODE', 'UF_QTY'],
            'filter' => ['>UF_QTY' => 0],
        ]);

        while ($row = $response->fetch()) {
            $code = (string)($row['UF_MATERIAL_CODE'] ?? '');
            if ($code === '') {
                continue;
            }
            $map[$code] = ($map[$code] ?? 0) + (int)($row['UF_QTY'] ?? 0);
        }

        return $map;
    }

    /**
     * Схлопывает дубли UF_MATERIAL_CODE (после гонок при параллельной добыче).
     *
     * @return array<string, int> сколько строк удалено по коду
     */
    public function mergeDuplicateGovWarehouseRows(): array
    {
        $dataClass = $this->getGovWarehouseDataClass();
        $byCode = [];
        $response = $dataClass::getList([
            'order' => ['UF_MATERIAL_CODE' => 'ASC', 'ID' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            $code = (string)($row['UF_MATERIAL_CODE'] ?? '');
            if ($code === '') {
                continue;
            }
            $byCode[$code][] = $row;
        }

        $removed = [];
        $now = new DateTime();

        foreach ($byCode as $code => $rows) {
            if (count($rows) <= 1) {
                continue;
            }

            $total = 0;
            foreach ($rows as $row) {
                $total += (int)($row['UF_QTY'] ?? 0);
            }

            $dataClass::update((int)$rows[0]['ID'], [
                'UF_QTY' => $total,
                'UF_UPDATED_AT' => $now,
            ]);

            for ($i = 1, $count = count($rows); $i < $count; $i++) {
                $dataClass::delete((int)$rows[$i]['ID']);
            }

            $removed[$code] = count($rows) - 1;
        }

        return $removed;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getGovWarehouseRows(): array
    {
        $dataClass = $this->getGovWarehouseDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'order' => ['UF_MATERIAL_CODE' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getConstructionProjectsByOwner(int $ownerUserId): array
    {
        $dataClass = $this->getConstructionProjectDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => ['=UF_OWNER_USER_ID' => $ownerUserId],
            'order' => ['ID' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function getCivicConstructionProject(string $recipeCode): ?array
    {
        $dataClass = $this->getConstructionProjectDataClass();

        return $dataClass::getList([
            'filter' => [
                '=UF_OWNER_USER_ID' => 0,
                '=UF_RECIPE_CODE' => $recipeCode,
                '=UF_CITY_SLUG' => false,
            ],
            'limit' => 1,
        ])->fetch() ?: null;
    }

    public function getCityConstructionProject(string $citySlug, string $recipeCode): ?array
    {
        $citySlug = strtolower(trim($citySlug));
        $recipeCode = trim($recipeCode);
        if ($citySlug === '' || $recipeCode === '') {
            return null;
        }

        $dataClass = $this->getConstructionProjectDataClass();

        return $dataClass::getList([
            'filter' => [
                '=UF_OWNER_USER_ID' => 0,
                '=UF_CITY_SLUG' => $citySlug,
                '=UF_RECIPE_CODE' => $recipeCode,
            ],
            'limit' => 1,
        ])->fetch() ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getConstructionProjectsByCity(string $citySlug): array
    {
        $citySlug = strtolower(trim($citySlug));
        if ($citySlug === '') {
            return [];
        }

        $dataClass = $this->getConstructionProjectDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => [
                '=UF_OWNER_USER_ID' => 0,
                '=UF_CITY_SLUG' => $citySlug,
            ],
            'order' => ['ID' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getActiveCityConstructionProjects(): array
    {
        $dataClass = $this->getConstructionProjectDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => [
                '=UF_OWNER_USER_ID' => 0,
                '!UF_CITY_SLUG' => false,
                '=UF_STATUS' => 'building',
            ],
            'order' => ['UF_CITY_SLUG' => 'ASC', 'ID' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCompleteCityConstructionProjectsByRecipe(string $recipeCode): array
    {
        $recipeCode = trim($recipeCode);
        if ($recipeCode === '') {
            return [];
        }

        $dataClass = $this->getConstructionProjectDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => [
                '=UF_OWNER_USER_ID' => 0,
                '!UF_CITY_SLUG' => false,
                '=UF_RECIPE_CODE' => $recipeCode,
                '@UF_STATUS' => ['complete', 'pending_fee'],
            ],
            'order' => ['UF_CITY_SLUG' => 'ASC', 'ID' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function ensureCityConstructionProject(
        string $citySlug,
        string $recipeCode,
        string $kind,
        float $coinEscrow = 0.0
    ): array {
        $existing = $this->getCityConstructionProject($citySlug, $recipeCode);
        if ($existing) {
            return $existing;
        }

        $now = new DateTime();
        $dataClass = $this->getConstructionProjectDataClass();
        $result = $dataClass::add([
            'UF_OWNER_USER_ID' => 0,
            'UF_CITY_SLUG' => strtolower(trim($citySlug)),
            'UF_RECIPE_CODE' => $recipeCode,
            'UF_KIND' => $kind,
            'UF_PROGRESS' => 0,
            'UF_STATUS' => 'building',
            'UF_STASH_JSON' => '{}',
            'UF_BRIGADE_JSON' => '[]',
            'UF_COIN_ESCROW' => max(0, round($coinEscrow, 1)),
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return $dataClass::getById((int)$result->getId())->fetch();
    }

    public function getEstateConstructionProject(
        int $ownerUserId,
        string $citySlug,
        int $plotNumber,
        string $recipeCode
    ): ?array {
        if ($ownerUserId <= 0 || $plotNumber <= 0 || $citySlug === '' || $recipeCode === '') {
            return null;
        }

        $dataClass = $this->getConstructionProjectDataClass();

        return $dataClass::getList([
            'filter' => [
                '=UF_OWNER_USER_ID' => $ownerUserId,
                '=UF_CITY_SLUG' => strtolower(trim($citySlug)),
                '=UF_RECIPE_CODE' => $recipeCode,
                '=UF_KIND' => $this->estateKind($citySlug, $plotNumber),
            ],
            'limit' => 1,
        ])->fetch() ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getEstateConstructionProjectsByPlot(int $ownerUserId, string $citySlug, int $plotNumber): array
    {
        if ($ownerUserId <= 0 || $plotNumber <= 0 || $citySlug === '') {
            return [];
        }

        $dataClass = $this->getConstructionProjectDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => [
                '=UF_OWNER_USER_ID' => $ownerUserId,
                '=UF_CITY_SLUG' => strtolower(trim($citySlug)),
                '=UF_KIND' => $this->estateKind($citySlug, $plotNumber),
            ],
            'order' => ['ID' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public function ensureEstateConstructionProject(
        int $ownerUserId,
        string $citySlug,
        int $plotNumber,
        string $recipeCode
    ): array {
        $existing = $this->getEstateConstructionProject($ownerUserId, $citySlug, $plotNumber, $recipeCode);
        if ($existing) {
            return $existing;
        }

        $now = new DateTime();
        $dataClass = $this->getConstructionProjectDataClass();
        $result = $dataClass::add([
            'UF_OWNER_USER_ID' => $ownerUserId,
            'UF_CITY_SLUG' => strtolower(trim($citySlug)),
            'UF_RECIPE_CODE' => $recipeCode,
            'UF_KIND' => $this->estateKind($citySlug, $plotNumber),
            'UF_PROGRESS' => 0,
            'UF_STATUS' => 'building',
            'UF_STASH_JSON' => '{}',
            'UF_BRIGADE_JSON' => '[]',
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return $dataClass::getById((int)$result->getId())->fetch();
    }

    /**
     * @param array<string, int> $stash
     */
    public function updateConstructionProject(int $projectId, array $fields): void
    {
        if ($projectId <= 0) {
            throw new \InvalidArgumentException('Некорректный проект');
        }

        $dataClass = $this->getConstructionProjectDataClass();
        $result = $dataClass::update($projectId, $fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    public function decodeStashJson(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $stash = [];
        foreach ($decoded as $code => $qty) {
            $qty = (int)$qty;
            if ($qty > 0) {
                $stash[(string)$code] = $qty;
            }
        }

        return $stash;
    }

    public function encodeStashJson(array $stash): string
    {
        ksort($stash);

        return json_encode($stash, JSON_UNESCAPED_UNICODE);
    }

    public function ensureCivicConstructionProject(string $recipeCode, string $kind): array
    {
        $existing = $this->getCivicConstructionProject($recipeCode);
        if ($existing) {
            return $existing;
        }

        $now = new DateTime();
        $dataClass = $this->getConstructionProjectDataClass();
        $result = $dataClass::add([
            'UF_OWNER_USER_ID' => 0,
            'UF_RECIPE_CODE' => $recipeCode,
            'UF_KIND' => $kind,
            'UF_PROGRESS' => 0,
            'UF_STATUS' => 'building',
            'UF_STASH_JSON' => '{}',
            'UF_BRIGADE_JSON' => '[]',
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return $dataClass::getById((int)$result->getId())->fetch();
    }

    private function getUserProfessionDataClass(): string
    {
        return $this->userProfessionDataClass
            ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_USER_PROFESSION);
    }

    public function getProfessionSessionDataClass(): string
    {
        return $this->professionSessionDataClass
            ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_PROFESSION_SESSION);
    }

    private function getUserMaterialDataClass(): string
    {
        return $this->userMaterialDataClass
            ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_USER_MATERIAL);
    }

    private function getGovWarehouseDataClass(): string
    {
        return $this->govWarehouseDataClass
            ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_GOV_WAREHOUSE);
    }

    private function getConstructionProjectDataClass(): string
    {
        return $this->constructionProjectDataClass
            ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_CONSTRUCTION_PROJECT);
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
                'HL-блок не найден: ' . $tableName . '. Запустите install_profession_hl.php'
            );
        }

        $entity = HighloadBlockTable::compileEntity($hlblock);

        return $entity->getDataClass();
    }

    private function estateKind(string $citySlug, int $plotNumber): string
    {
        return 'player_estate_plot:' . strtolower(trim($citySlug)) . ':' . max(1, $plotNumber);
    }
}
