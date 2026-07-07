<?php

namespace Prognos9ys\Main\Model\Repository;

use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Main\Application;
use Bitrix\Main\Loader;
use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Service\Game\GameEconomyHlInstaller;
use Prognos9ys\Main\Service\Game\LaborExchangeConfig;
use Prognos9ys\Main\Service\Game\ProfessionMaterialConfig;

class LaborOrderRepository
{
    private ?string $laborOrderDataClass = null;

    private static bool $estateOrderFieldsReady = false;

    private ProfessionRepository $professionRepository;

    public function __construct(?ProfessionRepository $professionRepository = null)
    {
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
    }

    public function getOrderById(int $orderId): ?array
    {
        if ($orderId <= 0) {
            return null;
        }

        $dataClass = $this->getLaborOrderDataClass();

        return $dataClass::getList([
            'filter' => ['=ID' => $orderId],
            'limit' => 1,
        ])->fetch() ?: null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOpenOrders(int $limit = 50, int $offset = 0): array
    {
        $dataClass = $this->getLaborOrderDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => ['=UF_STATUS' => LaborExchangeConfig::STATUS_OPEN],
            'order' => ['ID' => 'DESC'],
            'limit' => $limit,
            'offset' => $offset,
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOrdersByPosterUserId(int $userId, int $limit = 50): array
    {
        if ($userId <= 0) {
            return [];
        }

        $dataClass = $this->getLaborOrderDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => ['=UF_POSTER_USER_ID' => $userId],
            'order' => ['ID' => 'DESC'],
            'limit' => $limit,
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOrdersByPosterKind(string $posterKind, int $limit = 50): array
    {
        $dataClass = $this->getLaborOrderDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => ['=UF_POSTER_KIND' => $posterKind],
            'order' => ['ID' => 'DESC'],
            'limit' => $limit,
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOpenOrdersByPurpose(string $purpose, int $limit = 50, int $offset = 0): array
    {
        $dataClass = $this->getLaborOrderDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => [
                '=UF_STATUS' => LaborExchangeConfig::STATUS_OPEN,
                '=UF_ORDER_PURPOSE' => $purpose,
            ],
            'order' => ['ID' => 'DESC'],
            'limit' => $limit,
            'offset' => $offset,
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getOrdersByPosterUserIdAndPurpose(int $userId, string $purpose, int $limit = 50): array
    {
        if ($userId <= 0) {
            return [];
        }

        $dataClass = $this->getLaborOrderDataClass();
        $rows = [];
        $response = $dataClass::getList([
            'filter' => [
                '=UF_POSTER_USER_ID' => $userId,
                '=UF_ORDER_PURPOSE' => $purpose,
            ],
            'order' => ['ID' => 'DESC'],
            'limit' => $limit,
        ]);

        while ($row = $response->fetch()) {
            $rows[] = $row;
        }

        return $rows;
    }

    public static function resolveOrderPurpose(array $order): string
    {
        $purpose = trim((string)($order['UF_ORDER_PURPOSE'] ?? ''));

        return $purpose !== '' ? $purpose : LaborExchangeConfig::ORDER_PURPOSE_LABOR;
    }

    public static function isEstateOrder(array $order): bool
    {
        return self::resolveOrderPurpose($order) === LaborExchangeConfig::ORDER_PURPOSE_ESTATE;
    }

    public function addOrder(array $fields): int
    {
        $dataClass = $this->getLaborOrderDataClass();
        $result = $dataClass::add($fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }

        return (int)$result->getId();
    }

    public function updateOrder(int $orderId, array $fields): void
    {
        $dataClass = $this->getLaborOrderDataClass();
        $result = $dataClass::update($orderId, $fields);

        if (!$result->isSuccess()) {
            throw new \RuntimeException(implode('; ', $result->getErrorMessages()));
        }
    }

    public function orderHasActiveSession(int $orderId): bool
    {
        if ($orderId <= 0) {
            return false;
        }

        return $this->getActiveSessionIterationsSum($orderId) > 0;
    }

    public function getActiveSessionIterationsSum(int $orderId): int
    {
        if ($orderId <= 0) {
            return 0;
        }

        $dataClass = $this->professionRepository->getProfessionSessionDataClass();
        $sum = 0;
        $response = $dataClass::getList([
            'filter' => [
                '=UF_LABOR_ORDER_ID' => $orderId,
                '=UF_STATUS' => ProfessionMaterialConfig::SESSION_STATUS_ACTIVE,
            ],
            'select' => ['UF_ITERATIONS_TOTAL'],
        ]);

        while ($row = $response->fetch()) {
            $sum += (int)($row['UF_ITERATIONS_TOTAL'] ?? 0);
        }

        return $sum;
    }

    /**
     * Резервирует чанк циклов на заказе (атомарно).
     *
     * @return array{order:array<string,mixed>,chunk:int}|null
     */
    public function reserveOrderChunk(int $orderId, int $maxChunk, int $requestedChunk = 0): ?array
    {
        if ($orderId <= 0 || $maxChunk <= 0) {
            return null;
        }

        if ($requestedChunk < 0) {
            return null;
        }

        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $lockName = $helper->forSql('p9_labor_order_' . $orderId);
        $lockRow = $connection->query("SELECT GET_LOCK('{$lockName}', 5) AS L")->fetch();
        if ((int)($lockRow['L'] ?? 0) !== 1) {
            return null;
        }

        try {
            $order = $this->getOrderById($orderId);
            if (!$order) {
                return null;
            }

            if ((string)($order['UF_STATUS'] ?? '') !== LaborExchangeConfig::STATUS_OPEN) {
                return null;
            }

            $total = (int)($order['UF_ITERATIONS_TOTAL'] ?? 0);
            $done = (int)($order['UF_ITERATIONS_DONE'] ?? 0);
            $reserved = $this->getActiveSessionIterationsSum($orderId);
            $remaining = $total - $done - $reserved;
            if ($remaining <= 0) {
                return null;
            }

            $chunk = min($maxChunk, $remaining);
            $inputCode = (string)($order['UF_INPUT_CODE'] ?? '');
            if ($inputCode !== '') {
                $inputEscrow = (int)($order['UF_INPUT_ESCROW_QTY'] ?? 0);
                if ($inputEscrow < $chunk) {
                    $chunk = $inputEscrow;
                }
            }

            if ($requestedChunk > 0) {
                $chunk = min($chunk, $requestedChunk);
            }

            if ($chunk <= 0) {
                return null;
            }

            return [
                'order' => $order,
                'chunk' => $chunk,
            ];
        } finally {
            $connection->query("SELECT RELEASE_LOCK('{$lockName}')");
        }
    }

    /**
     * Фиксирует выполненный чанк на заказе.
     */
    public function applyOrderChunkCompletion(
        int $orderId,
        int $iterations,
        int $inputConsumed,
        float $coinPaid
    ): array {
        if ($orderId <= 0 || $iterations <= 0) {
            throw new \InvalidArgumentException('Некорректный заказ');
        }

        $connection = Application::getConnection();
        $helper = $connection->getSqlHelper();
        $lockName = $helper->forSql('p9_labor_order_' . $orderId);
        $lockRow = $connection->query("SELECT GET_LOCK('{$lockName}', 5) AS L")->fetch();
        if ((int)($lockRow['L'] ?? 0) !== 1) {
            throw new \RuntimeException('Не удалось обновить заказ');
        }

        try {
            $order = $this->getOrderById($orderId);
            if (!$order) {
                throw new \RuntimeException('Заказ не найден');
            }

            $status = (string)($order['UF_STATUS'] ?? '');
            if ($status !== LaborExchangeConfig::STATUS_OPEN) {
                throw new \RuntimeException('Заказ уже закрыт');
            }

            $done = (int)($order['UF_ITERATIONS_DONE'] ?? 0) + $iterations;
            $total = (int)($order['UF_ITERATIONS_TOTAL'] ?? 0);
            $inputEscrow = max(0, (int)($order['UF_INPUT_ESCROW_QTY'] ?? 0) - $inputConsumed);
            $coinEscrow = max(0, round((float)($order['UF_COIN_ESCROW'] ?? 0) - $coinPaid, 1));
            $now = new DateTime();

            $updates = [
                'UF_ITERATIONS_DONE' => $done,
                'UF_INPUT_ESCROW_QTY' => $inputEscrow,
                'UF_COIN_ESCROW' => $coinEscrow,
                'UF_UPDATED_AT' => $now,
            ];

            if ($done >= $total) {
                $updates['UF_STATUS'] = LaborExchangeConfig::STATUS_COMPLETED;
            }

            $this->updateOrder($orderId, $updates);

            return $this->getOrderById($orderId) ?? $order;
        } finally {
            $connection->query("SELECT RELEASE_LOCK('{$lockName}')");
        }
    }

    private function getLaborOrderDataClass(): string
    {
        $this->ensureEstateOrderFields();

        return $this->laborOrderDataClass
            ??= $this->compileDataClass(GameEconomyHlInstaller::TABLE_LABOR_ORDER);
    }

    private function ensureEstateOrderFields(): void
    {
        if (self::$estateOrderFieldsReady) {
            return;
        }

        (new GameEconomyHlInstaller())->upgradeLaborExchangeHl();
        $this->laborOrderDataClass = null;
        self::$estateOrderFieldsReady = true;
    }

    private function compileDataClass(string $tableName): string
    {
        if (!Loader::includeModule('highloadblock')) {
            throw new \RuntimeException('Модуль highloadblock не установлен');
        }

        $hlblock = HighloadBlockTable::getList([
            'filter' => ['=TABLE_NAME' => $tableName],
        ])->fetch();

        if (!$hlblock) {
            throw new \RuntimeException(
                'HL-блок не найден: ' . $tableName . '. Запустите install_labor_exchange_hl.php'
            );
        }

        $entity = HighloadBlockTable::compileEntity($hlblock);

        return $entity->getDataClass();
    }
}
