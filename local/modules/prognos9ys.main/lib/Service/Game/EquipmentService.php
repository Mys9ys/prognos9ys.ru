<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class EquipmentService
{
    private GameEconomyRepository $repository;
    private ProfessionRepository $professionRepository;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?ProfessionRepository $professionRepository = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
    }

    /**
     * @return array{
     *   equipped_caftan:?string,
     *   equipped_label:?string,
     *   combo_x2_bonus_pp:float,
     *   combo_x3_bonus_pp:float,
     *   premium_bonus_pp:float,
     *   slots:array<int, array<string, mixed>>,
     *   lines:array<int, array{text:string,status:string}>
     * }
     */
    public function equipCaftan(int $userId, string $equipmentCode): array
    {
        $equipmentCode = trim($equipmentCode);
        if ($userId <= 0 || !EquipmentConfig::isCaftanCode($equipmentCode)) {
            throw new \InvalidArgumentException('Неизвестный кафтан');
        }

        $caftanProfession = EquipmentConfig::getCaftanProfessionCode($equipmentCode);
        $playerProfessions = $this->getPlayerProfessionCodes($userId);
        if ($caftanProfession === null || !in_array($caftanProfession, $playerProfessions, true)) {
            $professionLabel = $caftanProfession !== null
                ? (string)(ProfessionMaterialConfig::getProfession($caftanProfession)['label'] ?? $caftanProfession)
                : '';
            $hint = $professionLabel !== ''
                ? 'Сначала изучите профессию: ' . $professionLabel
                : 'Кафтан не привязан к изученной профессии';

            throw new \RuntimeException($hint);
        }

        $category = ChestLootConfig::CATEGORY_EQUIPMENT;
        $available = $this->repository->getEventAgnosticLootItemCount($userId, $equipmentCode, $category);
        if ($available <= 0) {
            throw new \RuntimeException('Кафтан не найден в инвентаре');
        }

        $current = $this->repository->getEquippedCaftanCode($userId);
        if ($current === $equipmentCode) {
            throw new \RuntimeException('Этот кафтан уже надет');
        }

        $this->repository->decrementEventAgnosticLootItem($userId, $equipmentCode, $category, 1);

        if ($current !== '' && EquipmentConfig::isCaftanCode($current)) {
            $this->repository->incrementLootItem(
                $userId,
                ChestLootConfig::LOOT_EVENT_GLOBAL,
                $current,
                $category,
                1,
                'N'
            );
        }

        $this->repository->setEquippedCaftanCode($userId, $equipmentCode);
        GameProfileService::invalidateSummaryCache($userId);

        $label = EquipmentConfig::getCaftanLabel($equipmentCode);
        $lines = [
            ['text' => 'Надет: ' . $label, 'status' => 'ok'],
        ];
        if ($current !== '') {
            $lines[] = [
                'text' => 'Снят: ' . EquipmentConfig::getCaftanLabel($current),
                'status' => 'ok',
            ];
        }

        return array_merge(EquipmentConfig::buildSummary($equipmentCode), ['lines' => $lines]);
    }

    /**
     * @return array{
     *   equipped_caftan:?string,
     *   equipped_label:?string,
     *   combo_x2_bonus_pp:float,
     *   combo_x3_bonus_pp:float,
     *   premium_bonus_pp:float,
     *   slots:array<int, array<string, mixed>>,
     *   lines:array<int, array{text:string,status:string}>
     * }
     */
    public function unequipCaftan(int $userId): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        $current = $this->repository->getEquippedCaftanCode($userId);
        if ($current === '' || !EquipmentConfig::isCaftanCode($current)) {
            throw new \RuntimeException('Кафтан не надет');
        }

        $this->repository->setEquippedCaftanCode($userId, '');
        $this->repository->incrementLootItem(
            $userId,
            ChestLootConfig::LOOT_EVENT_GLOBAL,
            $current,
            ChestLootConfig::CATEGORY_EQUIPMENT,
            1,
            'N'
        );
        GameProfileService::invalidateSummaryCache($userId);

        return array_merge(EquipmentConfig::buildSummary(''), [
            'lines' => [
                ['text' => 'Снят: ' . EquipmentConfig::getCaftanLabel($current), 'status' => 'ok'],
            ],
        ]);
    }

    /**
     * @return array{
     *   equipped_caftan:?string,
     *   equipped_label:?string,
     *   combo_x2_bonus_pp:float,
     *   combo_x3_bonus_pp:float,
     *   premium_bonus_pp:float,
     *   slots:array<int, array<string, mixed>>
     * }
     */
    public function getSummary(int $userId): array
    {
        if ($userId <= 0) {
            return array_merge(EquipmentConfig::buildSummary(''), [
                'player_profession_codes' => [],
            ]);
        }

        $playerProfessions = $this->getPlayerProfessionCodes($userId);
        $equipped = $this->normalizeEquippedCaftan($userId, $playerProfessions);

        return array_merge(EquipmentConfig::buildSummary($equipped), [
            'player_profession_codes' => $playerProfessions,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function getPlayerProfessionCodes(int $userId): array
    {
        $codes = [];
        foreach ($this->professionRepository->getProfessionsByUserId($userId) as $row) {
            $code = trim((string)($row['UF_PROFESSION_CODE'] ?? ''));
            if ($code !== '') {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * @param array<int, string> $playerProfessions
     */
    private function normalizeEquippedCaftan(int $userId, array $playerProfessions): string
    {
        $equipped = $this->repository->getEquippedCaftanCode($userId);
        if ($equipped === '') {
            return '';
        }

        if (!EquipmentConfig::isCaftanCode($equipped)) {
            $this->repository->setEquippedCaftanCode($userId, '');
            GameProfileService::invalidateSummaryCache($userId);

            return '';
        }

        $caftanProfession = EquipmentConfig::getCaftanProfessionCode($equipped);
        if ($caftanProfession === null || !in_array($caftanProfession, $playerProfessions, true)) {
            $this->repository->setEquippedCaftanCode($userId, '');
            $this->repository->incrementLootItem(
                $userId,
                ChestLootConfig::LOOT_EVENT_GLOBAL,
                $equipped,
                ChestLootConfig::CATEGORY_EQUIPMENT,
                1,
                'N'
            );
            GameProfileService::invalidateSummaryCache($userId);

            return '';
        }

        return $equipped;
    }
}
