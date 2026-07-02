<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class AlbumRecipeService
{
    private GameEconomyRepository $repository;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
    }

    /**
     * @return array{
     *   lines:array<int, array{text:string,status:string}>,
     *   learned_recipes:array<int, string>
     * }
     */
    public function learn(int $userId, string $recipeCode = AlbumConfig::RECIPE_ITEM_CODE): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        $recipeCode = trim($recipeCode);
        if (!ProfessionRecipeConfig::isKnownRecipe($recipeCode)) {
            throw new \InvalidArgumentException('Неизвестный рецепт');
        }

        $this->repository->ensureLearnedRecipesSchema();

        if ($this->repository->hasLearnedRecipe($userId, $recipeCode)) {
            throw new \RuntimeException('Рецепт уже изучен');
        }

        $available = $this->repository->getEventAgnosticLootItemCount(
            $userId,
            $recipeCode,
            ChestLootConfig::CATEGORY_RECIPE
        );
        if ($available <= 0) {
            throw new \RuntimeException('Рецепт не найден в инвентаре');
        }

        $this->repository->addLearnedRecipe($userId, $recipeCode);
        $this->repository->decrementEventAgnosticLootItem(
            $userId,
            $recipeCode,
            ChestLootConfig::CATEGORY_RECIPE,
            1
        );

        return [
            'lines' => [
                [
                    'text' => 'Изучен: ' . ProfessionRecipeConfig::getRecipeLabel($recipeCode),
                    'status' => 'ok',
                ],
            ],
            'learned_recipes' => $this->repository->getLearnedRecipes($userId),
        ];
    }
}
