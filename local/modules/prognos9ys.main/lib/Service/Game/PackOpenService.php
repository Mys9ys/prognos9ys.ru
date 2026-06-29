<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class PackOpenService
{
    private const MAX_OPEN_PER_REQUEST = 30;

    private GameEconomyRepository $repository;
    private GameEventScopeService $scopeService;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?GameEventScopeService $scopeService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->scopeService = $scopeService ?? new GameEventScopeService();
    }

    /**
     * @return array{
     *   code:string,
     *   label:string,
     *   opened_count:int,
     *   rewards:array<int, array{code:string,category:string,label:string,team_slug:string}>,
     *   lines:array<int, array{text:string,status:string}>
     * }
     */
    public function open(int $userId, string $packCode, int $qty = 1): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        $packCode = trim($packCode);
        if (PackOpenConfig::isStubPack($packCode)) {
            throw new \RuntimeException(PackOpenConfig::getStubMessage($packCode));
        }
        if (!PackOpenConfig::isFullyOpenable($packCode)) {
            throw new \InvalidArgumentException('Этот пак пока нельзя распаковать');
        }

        $available = $this->repository->getSealedPackCount($userId, $packCode);
        if ($available <= 0) {
            throw new \RuntimeException('Запечатанный пак не найден в инвентаре');
        }

        $qty = max(1, min($qty, self::MAX_OPEN_PER_REQUEST, $available));
        $packLabel = ChestLootConfig::getLabel($packCode);
        $rewardEventId = $this->resolveRewardEventId($packCode);
        $rewards = [];

        for ($i = 0; $i < $qty; $i++) {
            $this->repository->decrementSealedPack($userId, $packCode, 1);
            $reward = PackOpenConfig::rollReward($packCode);
            $this->repository->incrementLootItem(
                $userId,
                $rewardEventId,
                $reward['code'],
                $reward['category'],
                1,
                'N'
            );
            $rewards[] = $reward;

            if (PackOpenConfig::isSouvenirPack($packCode)) {
                $bonusReward = PackOpenConfig::rollAlbumRecipeBonus();
                if ($bonusReward !== null) {
                    $this->repository->incrementLootItem(
                        $userId,
                        ChestLootConfig::LOOT_EVENT_GLOBAL,
                        $bonusReward['code'],
                        $bonusReward['category'],
                        1,
                        'N'
                    );
                    $rewards[] = $bonusReward;
                }
            }
        }

        $lines = [
            [
                'text' => 'Открыто: ' . $packLabel . ' ×' . $qty,
                'status' => 'ok',
            ],
        ];

        $grouped = [];
        foreach ($rewards as $reward) {
            $key = $reward['code'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = ['reward' => $reward, 'count' => 0];
            }
            $grouped[$key]['count']++;
        }

        foreach ($grouped as $group) {
            $reward = $group['reward'];
            $count = (int)$group['count'];
            $suffix = $count > 1 ? ' ×' . $count : '';
            $lines[] = [
                'text' => $reward['label'] . $suffix,
                'status' => 'ok',
            ];
        }

        return [
            'code' => $packCode,
            'label' => $packLabel,
            'opened_count' => $qty,
            'rewards' => $rewards,
            'lines' => $lines,
        ];
    }

    private function resolveRewardEventId(string $packCode): int
    {
        if (!PackOpenConfig::usesAnchorEvent($packCode)) {
            return ChestLootConfig::LOOT_EVENT_GLOBAL;
        }

        $anchorEventId = $this->scopeService->getAnchorEventId();

        return $anchorEventId > 0 ? $anchorEventId : ChestLootConfig::LOOT_EVENT_GLOBAL;
    }
}
