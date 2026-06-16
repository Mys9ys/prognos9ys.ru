<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class WalletService
{
    private GameEconomyRepository $repository;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
    }

    public function ensureWallet(int $userId): array
    {
        $row = $this->repository->getWalletByUserId($userId);

        if ($row) {
            return $this->formatWallet($row);
        }

        $this->repository->addWallet([
            'UF_USER_ID' => $userId,
            'UF_PROGNOBAKS' => 0,
            'UF_RUBLIUS' => 0,
        ]);

        return [
            'prognobaks' => 0.0,
            'rublius' => 0.0,
        ];
    }

    public function grantStarterPack(int $userId): void
    {
        $wallet = $this->repository->getWalletByUserId($userId);

        if ($wallet) {
            return;
        }

        $this->repository->addWallet([
            'UF_USER_ID' => $userId,
            'UF_PROGNOBAKS' => GameEconomyConfig::START_PROGNOBAKS,
            'UF_RUBLIUS' => GameEconomyConfig::START_RUBLIUS,
        ]);

        $this->repository->addWalletTx([
            'UF_USER_ID' => $userId,
            'UF_CURRENCY' => GameEconomyConfig::CURRENCY_PROGNOBAKS,
            'UF_AMOUNT' => GameEconomyConfig::START_PROGNOBAKS,
            'UF_BALANCE_AFTER' => GameEconomyConfig::START_PROGNOBAKS,
            'UF_REASON' => 'registration_bonus',
            'UF_REF_TYPE' => 'user',
            'UF_REF_ID' => $userId,
            'UF_CREATED_AT' => new DateTime(),
        ]);

        $this->repository->addWalletTx([
            'UF_USER_ID' => $userId,
            'UF_CURRENCY' => GameEconomyConfig::CURRENCY_RUBLIUS,
            'UF_AMOUNT' => GameEconomyConfig::START_RUBLIUS,
            'UF_BALANCE_AFTER' => GameEconomyConfig::START_RUBLIUS,
            'UF_REASON' => 'registration_bonus',
            'UF_REF_TYPE' => 'user',
            'UF_REF_ID' => $userId,
            'UF_CREATED_AT' => new DateTime(),
        ]);

        (new UserProgressService($this->repository))->ensureProgress($userId);
    }

    public function credit(
        int $userId,
        string $currency,
        float $amount,
        string $reason,
        ?string $refType = null,
        ?int $refId = null
    ): array {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Сумма начисления должна быть положительной');
        }

        $wallet = $this->ensureWalletRow($userId);
        $field = $this->currencyField($currency);
        $newBalance = round((float)$wallet[$field] + $amount, 1);

        $this->repository->updateWallet((int)$wallet['ID'], [
            $field => $newBalance,
        ]);

        $this->repository->addWalletTx([
            'UF_USER_ID' => $userId,
            'UF_CURRENCY' => $currency,
            'UF_AMOUNT' => $amount,
            'UF_BALANCE_AFTER' => $newBalance,
            'UF_REASON' => $reason,
            'UF_REF_TYPE' => $refType,
            'UF_REF_ID' => $refId,
            'UF_CREATED_AT' => new DateTime(),
        ]);

        return $this->getWalletSummary($userId);
    }

    public function debit(
        int $userId,
        string $currency,
        float $amount,
        string $reason,
        ?string $refType = null,
        ?int $refId = null
    ): array {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Сумма списания должна быть положительной');
        }

        $wallet = $this->ensureWalletRow($userId);
        $field = $this->currencyField($currency);
        $currentBalance = round((float)$wallet[$field], 1);
        $newBalance = round($currentBalance - $amount, 1);

        if ($newBalance < 0) {
            throw new \RuntimeException('Недостаточно средств');
        }

        $this->repository->updateWallet((int)$wallet['ID'], [
            $field => $newBalance,
        ]);

        $this->repository->addWalletTx([
            'UF_USER_ID' => $userId,
            'UF_CURRENCY' => $currency,
            'UF_AMOUNT' => -$amount,
            'UF_BALANCE_AFTER' => $newBalance,
            'UF_REASON' => $reason,
            'UF_REF_TYPE' => $refType,
            'UF_REF_ID' => $refId,
            'UF_CREATED_AT' => new DateTime(),
        ]);

        return $this->getWalletSummary($userId);
    }

    public function getWalletSummary(int $userId): array
    {
        $wallet = $this->ensureWallet($userId);

        return [
            'prognobaks' => $wallet['prognobaks'],
            'rublius' => $wallet['rublius'],
            'rublius_rate' => GameEconomyConfig::RUBLIUS_TO_PROGNOBAKS,
        ];
    }

    private function ensureWalletRow(int $userId): array
    {
        $row = $this->repository->getWalletByUserId($userId);

        if (!$row) {
            $this->ensureWallet($userId);
            $row = $this->repository->getWalletByUserId($userId);
        }

        if (!$row) {
            throw new \RuntimeException('Не удалось создать кошелёк пользователя');
        }

        return $row;
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

    private function formatWallet(array $row): array
    {
        return [
            'prognobaks' => round((float)($row['UF_PROGNOBAKS'] ?? 0), 1),
            'rublius' => round((float)($row['UF_RUBLIUS'] ?? 0), 1),
        ];
    }
}
