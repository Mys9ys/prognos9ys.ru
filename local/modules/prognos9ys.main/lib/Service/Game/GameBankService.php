<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class GameBankService
{
    private GameEconomyRepository $repository;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
    }

    public function getSummary(): array
    {
        $bank = $this->repository->ensureGameBank(GameEconomyConfig::GAME_BANK_CODE_FOOTBALL_PARIMUTUEL);

        return [
            'code' => (string)($bank['UF_CODE'] ?? GameEconomyConfig::GAME_BANK_CODE_FOOTBALL_PARIMUTUEL),
            'title' => 'Госбанк (ставки ЧМ)',
            'prognobaks' => round((float)($bank['UF_PROGNOBAKS'] ?? 0), 1),
        ];
    }
}
