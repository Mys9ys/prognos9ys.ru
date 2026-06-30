<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class AlbumCraftService
{
    private ProfessionRepository $professionRepository;
    private GameEconomyRepository $economyRepository;
    private LevelService $levelService;
    private ProfessionLevelRewardService $levelRewardService;

    public function __construct(
        ?ProfessionRepository $professionRepository = null,
        ?GameEconomyRepository $economyRepository = null,
        ?LevelService $levelService = null,
        ?ProfessionLevelRewardService $levelRewardService = null
    ) {
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
        $this->economyRepository = $economyRepository ?? new GameEconomyRepository();
        $this->levelService = $levelService ?? new LevelService();
        $this->levelRewardService = $levelRewardService ?? new ProfessionLevelRewardService();
    }

    /**
     * @return array<string, mixed>
     */
    public function getCraftState(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $this->economyRepository->ensureLearnedRecipesSchema();

        $recipeLearned = $this->economyRepository->hasLearnedRecipe($userId, AlbumConfig::RECIPE_ITEM_CODE);
        $plankHave = $this->professionRepository->getUserMaterialQty($userId, 'plank', false);
        $clothHave = $this->professionRepository->getUserMaterialQty($userId, 'cloth', false);
        $professionCodes = $this->getOwnedCraftProfessions($userId);
        $hasActiveSession = $this->professionRepository->getActiveSessionByUserId($userId) !== null;

        $canCraft = $recipeLearned
            && $professionCodes !== []
            && !$hasActiveSession
            && $plankHave >= AlbumConfig::RECIPE_PLANK
            && $clothHave >= AlbumConfig::RECIPE_CLOTH;

        return [
            'recipe_learned' => $recipeLearned,
            'plank_need' => AlbumConfig::RECIPE_PLANK,
            'cloth_need' => AlbumConfig::RECIPE_CLOTH,
            'plank_have' => $plankHave,
            'cloth_have' => $clothHave,
            'output_count' => AlbumConfig::CRAFT_OUTPUT_COUNT,
            'xp_gain' => AlbumConfig::CRAFT_XP_GAIN,
            'profession_codes' => $professionCodes,
            'has_active_session' => $hasActiveSession,
            'can_craft' => $canCraft,
        ];
    }

    /**
     * @return array{
     *   crafted:int,
     *   xp_gain:int,
     *   profession_code:string,
     *   profession_level_rewards:array<int, array<string, mixed>>,
     *   lines:array<int, array{text:string,status:string}>
     * }
     */
    public function craft(int $userId, string $professionCode): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        $professionCode = trim($professionCode);
        if (!in_array($professionCode, AlbumConfig::CRAFT_PROFESSION_CODES, true)) {
            throw new \InvalidArgumentException('Альбомы может крафтить столяр или ткач');
        }

        if ($this->professionRepository->getActiveSessionByUserId($userId)) {
            throw new \RuntimeException('Сначала завершите или остановите текущую смену');
        }

        $professionRow = $this->professionRepository->getProfessionByUserAndCode($userId, $professionCode);
        if (!$professionRow) {
            throw new \RuntimeException('Профессия не изучена');
        }

        $plankNeed = AlbumConfig::RECIPE_PLANK;
        $clothNeed = AlbumConfig::RECIPE_CLOTH;
        $plankHave = $this->professionRepository->getUserMaterialQty($userId, 'plank', false);
        $clothHave = $this->professionRepository->getUserMaterialQty($userId, 'cloth', false);

        if ($plankHave < $plankNeed) {
            throw new \RuntimeException('Нужно досок: ' . $plankNeed . ' (есть ' . $plankHave . ')');
        }
        if ($clothHave < $clothNeed) {
            throw new \RuntimeException('Нужно ткани: ' . $clothNeed . ' (есть ' . $clothHave . ')');
        }

        $this->economyRepository->ensureLearnedRecipesSchema();
        if (!$this->economyRepository->hasLearnedRecipe($userId, AlbumConfig::RECIPE_ITEM_CODE)) {
            throw new \RuntimeException('Сначала изучите рецепт альбома в инвентаре');
        }

        $this->professionRepository->consumeUserMaterialQty($userId, 'plank', $plankNeed, false);
        $this->professionRepository->consumeUserMaterialQty($userId, 'cloth', $clothNeed, false);

        $output = AlbumConfig::CRAFT_OUTPUT_COUNT;
        $this->economyRepository->incrementLootItem(
            $userId,
            ChestLootConfig::LOOT_EVENT_GLOBAL,
            AlbumConfig::ITEM_CODE,
            ChestLootConfig::CATEGORY_ALBUM,
            $output,
            'N'
        );

        $playerLevel = (int)((new UserProgressService($this->economyRepository, $this->levelService))->getSummary($userId)['level'] ?? 0);
        $xpGain = AlbumConfig::CRAFT_XP_GAIN;
        $xpResult = $this->professionRepository->addProfessionXp(
            (int)$professionRow['ID'],
            $xpGain,
            $playerLevel,
            $professionCode
        );

        $levelRewards = [];
        if ($xpResult && $xpResult['new_level'] > $xpResult['old_level']) {
            $levelRewards = $this->levelRewardService->grantForLevelRange(
                $userId,
                $professionCode,
                $xpResult['old_level'],
                $xpResult['new_level']
            );
        }

        $professionLabel = ProfessionMaterialConfig::getProfession($professionCode)['label'] ?? $professionCode;

        $lines = [
            ['text' => 'Списано: доска ×' . $plankNeed . ', ткань ×' . $clothNeed, 'status' => 'ok'],
            ['text' => AlbumConfig::itemLabel() . ' ×' . $output, 'status' => 'ok'],
            ['text' => $professionLabel . ': +' . $xpGain . ' опыта', 'status' => 'ok'],
        ];

        foreach ($levelRewards as $reward) {
            $bits = [($reward['profession_label'] ?? 'Профессия') . ' ур. ' . ($reward['level'] ?? '')];
            if ((float)($reward['prognobaks'] ?? 0) > 0) {
                $bits[] = '+' . $reward['prognobaks'] . ' 🪙';
            }
            if ((float)($reward['rublius'] ?? 0) > 0) {
                $bits[] = '+' . $reward['rublius'] . ' 💎';
            }
            if ((int)($reward['material_qty'] ?? 0) > 0) {
                $bits[] = '+' . $reward['material_qty'] . ' ' . ($reward['material_label'] ?? 'рес.');
            }
            if ((int)($reward['chests'] ?? 0) > 0) {
                $bits[] = '+' . $reward['chests'] . ' сунд. проф.';
            }
            if (!empty($reward['title'])) {
                $bits[] = (string)$reward['title'];
            }
            $lines[] = ['text' => implode(' · ', $bits), 'status' => 'ok'];
        }

        $this->economyRepository->incrementAlbumCraftRunCount($userId);

        return [
            'crafted' => $output,
            'xp_gain' => $xpGain,
            'profession_code' => $professionCode,
            'profession_level_rewards' => $levelRewards,
            'lines' => $lines,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getOwnedCraftProfessions(int $userId): array
    {
        $codes = [];
        foreach (AlbumConfig::CRAFT_PROFESSION_CODES as $code) {
            if ($this->professionRepository->getProfessionByUserAndCode($userId, $code)) {
                $codes[] = $code;
            }
        }

        return $codes;
    }
}
