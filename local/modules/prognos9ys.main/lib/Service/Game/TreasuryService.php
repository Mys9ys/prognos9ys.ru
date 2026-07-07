<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UserTable;
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

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentLedger(int $limit = 40): array
    {
        $rows = $this->repository->getRecentTreasuryTx($limit);
        $items = [];

        foreach ($rows as $row) {
            $reason = (string)($row['UF_REASON'] ?? '');
            $userId = (int)($row['UF_USER_ID'] ?? 0);
            $createdAt = $row['UF_CREATED_AT'] ?? null;

            $items[] = [
                'id' => (int)($row['ID'] ?? 0),
                'currency' => (string)($row['UF_CURRENCY'] ?? ''),
                'amount' => round((float)($row['UF_AMOUNT'] ?? 0), 1),
                'balance_after' => round((float)($row['UF_BALANCE_AFTER'] ?? 0), 1),
                'reason' => $reason,
                'reason_label' => self::reasonLabel($reason),
                'ref_type' => (string)($row['UF_REF_TYPE'] ?? ''),
                'ref_id' => (int)($row['UF_REF_ID'] ?? 0),
                'user_id' => $userId,
                'user_name' => $userId > 0 ? $this->resolveUserName($userId) : '',
                'created_at' => $createdAt instanceof DateTime ? $createdAt->format('Y-m-d H:i:s') : '',
            ];
        }

        return $items;
    }

    public function credit(
        string $currency,
        float $amount,
        ?string $reason = null,
        ?int $refId = null,
        ?int $userId = null,
        ?string $refType = null
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

        $this->logTx(
            $currency,
            $amount,
            $newBalance,
            $reason,
            $refType,
            $refId,
            $userId
        );

        return $this->getSummary();
    }

    public function debit(
        string $currency,
        float $amount,
        ?string $reason = null,
        ?int $refId = null,
        ?int $userId = null,
        ?string $refType = null
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

        $this->logTx(
            $currency,
            -$amount,
            $newBalance,
            $reason,
            $refType,
            $refId,
            $userId
        );

        return $this->getSummary();
    }

    public function hasFunds(string $currency, float $amount): bool
    {
        $bank = $this->repository->ensureGameBank(GameEconomyConfig::GAME_BANK_CODE_STATE_TREASURY);
        $field = $this->currencyField($currency);

        return round((float)($bank[$field] ?? 0), 1) >= round($amount, 1);
    }

    public static function reasonLabel(string $reason): string
    {
        $map = [
            'profession_work_pay' => 'Оплата труда (казна → игрок)',
            'profession_work_fee' => 'Сбор за работу на себя (игрок → казна)',
            'treasury_shop_wave' => 'Лавка казны',
            'gov_support_deposit' => 'Гос. вклад (казна → банк)',
            'gov_support_interest' => 'Проценты гос. вклада',
            'gov_support_return' => 'Возврат тела гос. вклада',
            'gov_support_early_close' => 'Досрочное закрытие гос. вклада',
            'exchange_commission' => 'Комиссия биржи',
            'city_bank_branch_presence' => 'Оплата прописки филиала банка (50 🪙)',
        ];

        return $map[$reason] ?? $reason;
    }

    private function logTx(
        string $currency,
        float $signedAmount,
        float $balanceAfter,
        ?string $reason,
        ?string $refType,
        ?int $refId,
        ?int $userId
    ): void {
        $this->repository->addTreasuryTx([
            'UF_BANK_CODE' => GameEconomyConfig::GAME_BANK_CODE_STATE_TREASURY,
            'UF_CURRENCY' => $currency,
            'UF_AMOUNT' => round($signedAmount, 1),
            'UF_BALANCE_AFTER' => round($balanceAfter, 1),
            'UF_REASON' => $reason ?? '',
            'UF_REF_TYPE' => $refType ?? '',
            'UF_REF_ID' => $refId ?? 0,
            'UF_USER_ID' => $userId ?? 0,
            'UF_CREATED_AT' => new DateTime(),
        ]);
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

    private function resolveUserName(int $userId): string
    {
        if ($userId <= 0) {
            return '';
        }

        $row = UserTable::getList([
            'filter' => ['=ID' => $userId],
            'select' => ['ID', 'LOGIN', 'NAME', 'LAST_NAME'],
            'limit' => 1,
        ])->fetch();

        if (!$row) {
            return 'user#' . $userId;
        }

        $name = trim((string)($row['NAME'] ?? '') . ' ' . (string)($row['LAST_NAME'] ?? ''));

        return $name !== '' ? $name : (string)($row['LOGIN'] ?? ('user#' . $userId));
    }
}
