<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\UserTable;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class BotFarmService
{
    private ProfessionRepository $professionRepository;
    private ProfessionFarmService $farmService;

    public function __construct(
        ?ProfessionRepository $professionRepository = null,
        ?ProfessionFarmService $farmService = null
    ) {
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
        $this->farmService = $farmService ?? new ProfessionFarmService($this->professionRepository);
    }

    /**
     * @return int[]
     */
    public function listSeedUserIds(): array
    {
        $ids = [];
        $response = UserTable::getList([
            'filter' => [
                'LOGIC' => 'OR',
                ['%EMAIL' => '@prognos9ys.ru'],
                ['%LOGIN' => 'gk'],
                ['%LOGIN' => 'coach'],
                ['%LOGIN' => 'fanm'],
                ['%LOGIN' => 'fanf'],
                ['%LOGIN' => 'ruler'],
                ['%LOGIN' => 'cs2p_'],
                ['%LOGIN' => 'cs2c_'],
            ],
            'select' => ['ID', 'LOGIN', 'EMAIL'],
            'order' => ['ID' => 'ASC'],
        ]);

        while ($row = $response->fetch()) {
            if (!SeedUserGroupService::isSeedAccount($row)) {
                continue;
            }

            $id = (int)($row['ID'] ?? 0);
            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Все пользователи с игровым кошельком (для массовых действий).
     *
     * @return int[]
     */
    public function listWalletUserIds(): array
    {
        $ids = [];
        foreach ((new GameEconomyRepository())->getAllWallets() as $wallet) {
            $userId = (int)($wallet['user_id'] ?? 0);
            if ($userId > 0) {
                $ids[] = $userId;
            }
        }

        return array_values(array_unique($ids));
    }

    public function isSeedUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $row = UserTable::getList([
            'filter' => ['=ID' => $userId],
            'select' => ['ID', 'LOGIN', 'EMAIL'],
            'limit' => 1,
        ])->fetch();

        return $row && SeedUserGroupService::isSeedAccount($row);
    }

    public function userHasGatherProfession(int $userId): bool
    {
        return count($this->professionRepository->getProfessionsByUserId($userId)) > 0;
    }

    /**
     * @return array{status:string,profession_code?:string,label?:string,message:string}
     */
    public function pickGatherProfessionIfMissing(int $userId, string $profile = BotProfessionPickConfig::DEFAULT_PROFILE): array
    {
        if ($userId <= 0) {
            return ['status' => 'skipped', 'message' => 'Некорректный пользователь'];
        }

        if ($this->userHasGatherProfession($userId)) {
            $existing = $this->professionRepository->getProfessionsByUserId($userId);
            $code = (string)($existing[0]['UF_PROFESSION_CODE'] ?? '');

            return [
                'status' => 'skipped',
                'profession_code' => $code,
                'message' => 'Профессия уже есть',
            ];
        }

        $code = BotProfessionPickConfig::pickGatheringCodeForUser($userId, $profile);
        $definition = ProfessionMaterialConfig::getProfession($code);
        $this->farmService->pickProfessions($userId, [$code]);

        return [
            'status' => 'success',
            'profession_code' => $code,
            'label' => $definition['label'] ?? $code,
            'message' => ($definition['label'] ?? $code) . ' (' . $code . ')',
        ];
    }

    /**
     * Мгновенная смена на казну (все циклы сразу) — для модераторского массового действия.
     *
     * @return array{status:string,message:string,profession_code?:string,ticks?:int}
     */
    public function runInstantTreasuryGather(
        int $userId,
        int $iterations = 0,
        string $profile = BotProfessionPickConfig::DEFAULT_PROFILE
    ): array {
        if ($userId <= 0) {
            return ['status' => 'skipped', 'message' => 'Некорректный пользователь'];
        }

        if ($this->professionRepository->getActiveSessionByUserId($userId)) {
            return ['status' => 'skipped', 'message' => 'Уже идёт смена'];
        }

        if (!$this->userHasGatherProfession($userId)) {
            $picked = $this->pickGatherProfessionIfMissing($userId, $profile);
            if ($picked['status'] !== 'success') {
                return $picked;
            }
        }

        $professions = $this->professionRepository->getProfessionsByUserId($userId);
        $professionCode = (string)($professions[0]['UF_PROFESSION_CODE'] ?? '');
        if ($professionCode === '') {
            return ['status' => 'failed', 'message' => 'Нет профессии'];
        }

        $iterations = $iterations > 0
            ? min(ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION, $iterations)
            : ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION;

        $payTotal = $iterations * ProfessionEconomyConfig::PAY_TREASURY_PER_ITERATION;
        $treasury = (new TreasuryService())->getSummary();
        if ((float)($treasury['prognobaks'] ?? 0) < $payTotal) {
            return [
                'status' => 'skipped',
                'message' => 'В казне мало 🪙 (нужно ' . $payTotal . ')',
            ];
        }

        $definition = ProfessionMaterialConfig::getProfession($professionCode);
        $this->farmService->startWork(
            $userId,
            $professionCode,
            ProfessionMaterialConfig::WORK_MODE_TREASURY,
            $iterations
        );
        $ticks = $this->farmService->forceRunAllSessionTicks($userId);

        return [
            'status' => 'success',
            'profession_code' => $professionCode,
            'ticks' => $ticks,
            'message' => ($definition['label'] ?? $professionCode)
                . ': ' . $ticks . ' циклов на казну',
        ];
    }
}
