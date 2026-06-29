<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class XpBankService
{
    private const MAX_OPEN_PER_REQUEST = 30;

    private GameEconomyRepository $repository;
    private UserProgressService $progressService;
    private ProfessionRepository $professionRepository;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?UserProgressService $progressService = null,
        ?ProfessionRepository $professionRepository = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->progressService = $progressService ?? new UserProgressService($this->repository);
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
    }

    /**
     * @return array{
     *   code:string,
     *   label:string,
     *   opened_count:int,
     *   xp_gained:float,
     *   lines:array<int, array{text:string,status:string}>,
     *   level_rewards?:array<int, array<string, mixed>>,
     *   profession_level_rewards?:array<int, array<string, mixed>>,
     *   progress?:array<string, mixed>,
     *   profession?:array<string, mixed>
     * }
     */
    public function open(int $userId, string $code, int $qty = 1): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        $definition = ChestLootConfig::parseXpBankCode($code);
        if (!$definition) {
            throw new \InvalidArgumentException('Неизвестная банка опыта');
        }

        $available = $this->repository->getEventAgnosticLootItemCount(
            $userId,
            $code,
            ChestLootConfig::CATEGORY_XP_BANK
        );
        if ($available <= 0) {
            throw new \RuntimeException('Банка опыта не найдена в инвентаре');
        }

        $qty = max(1, min($qty, self::MAX_OPEN_PER_REQUEST, $available));
        $xpPerUnit = (float)$definition['xp'];
        $totalXp = round($xpPerUnit * $qty, 1);

        $this->repository->decrementEventAgnosticLootItem(
            $userId,
            $code,
            ChestLootConfig::CATEGORY_XP_BANK,
            $qty
        );

        $lines = [
            [
                'text' => 'Открыто: ' . $definition['label'] . ' ×' . $qty,
                'status' => 'ok',
            ],
            [
                'text' => '+' . $totalXp . ' XP',
                'status' => 'ok',
            ],
        ];

        $result = [
            'code' => $code,
            'label' => (string)$definition['label'],
            'opened_count' => $qty,
            'xp_gained' => $totalXp,
            'lines' => $lines,
        ];

        $professionCode = '';

        if ($definition['kind'] === 'player') {
            $oldProgress = $this->progressService->getSummary($userId);
            $newProgress = $this->progressService->addXp($userId, $totalXp);
            $levelRewards = (new LevelUpRewardService($this->repository))
                ->grantForLevelRange($userId, (int)$oldProgress['level'], (int)$newProgress['level']);

            $result['progress'] = $newProgress;
            $result['level_rewards'] = $levelRewards;
            $result['lines'] = array_merge($lines, $this->buildPlayerRewardLines(
                (int)$oldProgress['level'],
                (int)$newProgress['level'],
                $levelRewards
            ));
        } else {
            $professionResult = $this->applyProfessionXp($userId, (string)$definition['kind'], (int)round($totalXp));
            $professionCode = (string)($professionResult['profession']['code'] ?? '');
            $result['profession'] = $professionResult['profession'];
            $result['profession_level_rewards'] = $professionResult['level_rewards'];
            $result['lines'] = array_merge($lines, $professionResult['lines']);
        }

        $this->repository->addXpBankDrinkLog([
            'UF_USER_ID' => $userId,
            'UF_ITEM_CODE' => $code,
            'UF_BANK_KIND' => (string)$definition['kind'],
            'UF_PROFESSION_CODE' => $professionCode,
            'UF_QTY' => $qty,
            'UF_XP_GAINED' => $totalXp,
            'UF_CREATED_AT' => new DateTime(),
        ]);

        return $result;
    }

    /**
     * @return array{
     *   profession:array<string, mixed>,
     *   level_rewards:array<int, array<string, mixed>>,
     *   lines:array<int, array{text:string,status:string}>
     * }
     */
    private function applyProfessionXp(int $userId, string $kind, int $totalXpGain): array
    {
        $professionRow = $this->resolveProfessionRowForXpBank($userId, $kind);
        if (!$professionRow) {
            $hint = $kind === 'mining'
                ? 'Сначала изучите профессию добычи'
                : 'Сначала изучите профессию крафта';

            throw new \RuntimeException($hint);
        }

        $professionCode = (string)($professionRow['UF_PROFESSION_CODE'] ?? '');
        $definition = ProfessionMaterialConfig::getProfession($professionCode);
        $playerLevel = (int)($this->progressService->getSummary($userId)['level'] ?? 0);

        $xpResult = $this->professionRepository->addProfessionXp(
            (int)$professionRow['ID'],
            $totalXpGain,
            $playerLevel,
            $professionCode
        );

        if (!$xpResult) {
            throw new \RuntimeException('Не удалось начислить опыт профессии');
        }

        $levelRewards = [];
        if ($xpResult['new_level'] > $xpResult['old_level']) {
            $levelRewards = (new ProfessionLevelRewardService($this->repository, $this->professionRepository))
                ->grantForLevelRange(
                    $userId,
                    $professionCode,
                    (int)$xpResult['old_level'],
                    (int)$xpResult['new_level']
                );
        }

        $lines = [
            [
                'text' => ($definition['label'] ?? $professionCode)
                    . ': ур. ' . (int)$xpResult['old_level'] . ' → ' . (int)$xpResult['new_level'],
                'status' => 'ok',
            ],
        ];

        foreach ($levelRewards as $reward) {
            $level = (int)($reward['level'] ?? 0);
            $coins = round((float)($reward['prognobaks'] ?? 0), 1);
            if ($level > 0 && $coins > 0) {
                $lines[] = [
                    'text' => 'Награда за ур. ' . $level . ': +' . $coins . ' 🪙',
                    'status' => 'ok',
                ];
            }
        }

        return [
            'profession' => [
                'code' => $professionCode,
                'label' => (string)($definition['label'] ?? $professionCode),
                'old_level' => (int)$xpResult['old_level'],
                'new_level' => (int)$xpResult['new_level'],
                'xp' => (float)$xpResult['xp'],
            ],
            'level_rewards' => $levelRewards,
            'lines' => $lines,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveProfessionRowForXpBank(int $userId, string $kind): ?array
    {
        $pool = $kind === 'mining'
            ? ProfessionMaterialConfig::gatheringProfessions()
            : ProfessionMaterialConfig::processingProfessions();

        $best = null;
        foreach ($this->professionRepository->getProfessionsByUserId($userId) as $row) {
            $code = (string)($row['UF_PROFESSION_CODE'] ?? '');
            if ($code === '' || !isset($pool[$code])) {
                continue;
            }

            if (
                !$best
                || (int)($row['UF_LEVEL'] ?? 0) > (int)($best['UF_LEVEL'] ?? 0)
                || (
                    (int)($row['UF_LEVEL'] ?? 0) === (int)($best['UF_LEVEL'] ?? 0)
                    && (float)($row['UF_XP'] ?? 0) > (float)($best['UF_XP'] ?? 0)
                )
            ) {
                $best = $row;
            }
        }

        return $best;
    }

    /**
     * @param array<int, array<string, mixed>> $levelRewards
     * @return array<int, array{text:string,status:string}>
     */
    private function buildPlayerRewardLines(int $oldLevel, int $newLevel, array $levelRewards): array
    {
        $lines = [];

        if ($newLevel > $oldLevel) {
            $lines[] = [
                'text' => 'Уровень игрока: ' . $oldLevel . ' → ' . $newLevel,
                'status' => 'ok',
            ];
        }

        foreach ($levelRewards as $reward) {
            $level = (int)($reward['level'] ?? 0);
            $coins = round((float)($reward['prognobaks'] ?? 0), 1);
            $rublius = round((float)($reward['rublius'] ?? 0), 1);
            $chests = (int)($reward['chests'] ?? 0);

            if ($level <= 0) {
                continue;
            }

            $parts = [];
            if ($coins > 0) {
                $parts[] = '+' . $coins . ' 🪙';
            }
            if ($rublius > 0) {
                $parts[] = '+' . $rublius . ' 💎';
            }
            if ($chests > 0) {
                $parts[] = '+1 сундук за уровень';
            }

            if ($parts) {
                $lines[] = [
                    'text' => 'Награда за ур. ' . $level . ': ' . implode(', ', $parts),
                    'status' => 'ok',
                ];
            }
        }

        return $lines;
    }
}
