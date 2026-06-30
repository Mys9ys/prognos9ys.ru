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
        $rows = $this->repository->getWalletRowsByUserId($userId);

        if ($rows) {
            $row = count($rows) > 1
                ? $this->repository->mergeWalletDuplicatesForUser($userId, $rows)
                : $rows[0];

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

    /**
     * Стартовый пакет для аккаунтов без registration_bonus (например, созданных в админке).
     */
    public function grantStarterPackIfMissing(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        if ($this->repository->hasWalletTx($userId, 'registration_bonus', 'user', $userId)) {
            return false;
        }

        $wallet = $this->repository->getWalletByUserId($userId);

        if (!$wallet) {
            $this->grantStarterPack($userId);

            return true;
        }

        $prognobaks = round((float)($wallet['UF_PROGNOBAKS'] ?? 0), 1);
        $rublius = round((float)($wallet['UF_RUBLIUS'] ?? 0), 1);

        if ($prognobaks > 0 || $rublius > 0) {
            return false;
        }

        $this->credit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            GameEconomyConfig::START_PROGNOBAKS,
            'registration_bonus',
            'user',
            $userId
        );

        $this->credit(
            $userId,
            GameEconomyConfig::CURRENCY_RUBLIUS,
            GameEconomyConfig::START_RUBLIUS,
            'registration_bonus',
            'user',
            $userId
        );

        (new UserProgressService($this->repository))->ensureProgress($userId);

        return true;
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

        GameProfileService::invalidateSummaryCache($userId);

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

        GameProfileService::invalidateSummaryCache($userId);

        return $this->getWalletSummary($userId);
    }

    public function tryDebitPrognobaks(
        int $userId,
        float $amount,
        string $reason,
        ?string $refType = null,
        ?int $refId = null
    ): float {
        if ($amount <= 0) {
            return 0.0;
        }

        $wallet = $this->getWalletSummary($userId);
        $actual = round(min($amount, (float)$wallet['prognobaks']), 1);
        if ($actual <= 0) {
            return 0.0;
        }

        $this->debit(
            $userId,
            GameEconomyConfig::CURRENCY_PROGNOBAKS,
            $actual,
            $reason,
            $refType,
            $refId
        );

        return $actual;
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
        $rows = $this->repository->getWalletRowsByUserId($userId);

        if (!$rows) {
            $this->ensureWallet($userId);
            $rows = $this->repository->getWalletRowsByUserId($userId);
        }

        if (!$rows) {
            throw new \RuntimeException('Не удалось создать кошелёк пользователя');
        }

        if (count($rows) > 1) {
            return $this->repository->mergeWalletDuplicatesForUser($userId, $rows);
        }

        return $rows[0];
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
