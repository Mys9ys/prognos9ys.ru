<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class TreasuryService
{
    private GameEconomyRepository $repository;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
    }

    public function getSummary(): array
    {
        $bank = $this->repository->ensureGameBank(GameEconomyConfig::GAME_BANK_CODE_STATE_TREASURY);

        return [
            'code' => GameEconomyConfig::GAME_BANK_CODE_STATE_TREASURY,
            'title' => 'Казна',
            'prognobaks' => round((float)($bank['UF_PROGNOBAKS'] ?? 0), 1),
            'rublius' => round((float)($bank['UF_RUBLIUS'] ?? 0), 1),
        ];
    }

    public function credit(
        string $currency,
        float $amount,
        ?string $refType = null,
        ?int $refId = null
    ): array {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Сумма начисления в казну должна быть положительной');
        }

        $bank = $this->repository->ensureGameBank(GameEconomyConfig::GAME_BANK_CODE_STATE_TREASURY);
        $field = $this->currencyField($currency);
        $newBalance = round((float)($bank[$field] ?? 0) + $amount, 1);

        $this->repository->updateGameBank((int)$bank['ID'], [
            $field => $newBalance,
        ]);

        return $this->getSummary();
    }

    public function debit(
        string $currency,
        float $amount,
        ?string $refType = null,
        ?int $refId = null
    ): array {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Сумма списания из казны должна быть положительной');
        }

        $bank = $this->repository->ensureGameBank(GameEconomyConfig::GAME_BANK_CODE_STATE_TREASURY);
        $field = $this->currencyField($currency);
        $current = round((float)($bank[$field] ?? 0), 1);
        $newBalance = round($current - $amount, 1);

        if ($newBalance < 0) {
            throw new \RuntimeException('Недостаточно средств в казне');
        }

        $this->repository->updateGameBank((int)$bank['ID'], [
            $field => $newBalance,
        ]);

        return $this->getSummary();
    }

    public function hasFunds(string $currency, float $amount): bool
    {
        $bank = $this->repository->ensureGameBank(GameEconomyConfig::GAME_BANK_CODE_STATE_TREASURY);
        $field = $this->currencyField($currency);

        return round((float)($bank[$field] ?? 0), 1) >= round($amount, 1);
    }

    private function currencyField(string $currency): string
    {
        if ($currency === GameEconomyConfig::CURRENCY_PROGNOBAKS) {
            return 'UF_PROGNOBAKS';
        }

        if ($currency === GameEconomyConfig::CURRENCY_RUBLIUS) {
            return 'UF_RUBLIUS';
        }

        throw new \InvalidArgumentException('Неизвестная валюта: ' . $currency);
    }
}
