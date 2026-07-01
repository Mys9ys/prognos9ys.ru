<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\Type\DateTime;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class PremiumWorkQueueService
{
    private GameEconomyRepository $repository;
    private ProfessionRepository $professionRepository;
    private PremiumService $premiumService;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?ProfessionRepository $professionRepository = null,
        ?PremiumService $premiumService = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
        $this->premiumService = $premiumService ?? new PremiumService($this->repository);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function enqueue(int $userId, string $taskType, array $payload): array
    {
        $this->assertPremiumActive($userId);
        $taskType = trim($taskType);
        if (!in_array($taskType, PremiumWorkQueueConfig::TASK_TYPES, true)) {
            throw new \InvalidArgumentException('Неизвестный тип задачи');
        }

        $payload = $this->normalizePayload($taskType, $payload);
        $this->validatePayloadForEnqueue($userId, $taskType, $payload);

        $now = new DateTime();
        $label = $this->buildTaskLabel($taskType, $payload);
        $id = $this->repository->addPremiumWorkQueueItem([
            'UF_USER_ID' => $userId,
            'UF_SORT' => $this->repository->getNextPremiumWorkQueueSort($userId),
            'UF_TASK_TYPE' => $taskType,
            'UF_STATUS' => PremiumWorkQueueConfig::STATUS_PENDING,
            'UF_LABEL' => $label,
            'UF_SESSION_ID' => 0,
            'UF_PAYLOAD_JSON' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'UF_RESULT_JSON' => '',
            'UF_ERROR_TEXT' => '',
            'UF_CREATED_AT' => $now,
            'UF_UPDATED_AT' => $now,
        ]);

        $this->processUser($userId);

        return [
            'item' => $this->formatQueueRow($this->repository->getPremiumWorkQueueItemById($id) ?? []),
            'state' => $this->getStateForUser($userId),
        ];
    }

    /**
     * @param array<int, array{task_type:string, payload:array<string, mixed>}> $tasks
     * @return array{queued:int, tasks:array<int, array<string, mixed>>, state:array<string, mixed>}
     */
    public function enqueueMany(int $userId, array $tasks): array
    {
        $this->assertPremiumActive($userId);
        if (!$tasks) {
            throw new \InvalidArgumentException('Пустой план задач');
        }

        $added = [];
        $now = new DateTime();

        foreach ($tasks as $task) {
            $taskType = trim((string)($task['task_type'] ?? ''));
            if (!in_array($taskType, PremiumWorkQueueConfig::TASK_TYPES, true)) {
                throw new \InvalidArgumentException('Неизвестный тип задачи');
            }

            $payload = $this->normalizePayload($taskType, (array)($task['payload'] ?? []));
            $this->validatePayloadForEnqueue($userId, $taskType, $payload);

            $id = $this->repository->addPremiumWorkQueueItem([
                'UF_USER_ID' => $userId,
                'UF_SORT' => $this->repository->getNextPremiumWorkQueueSort($userId),
                'UF_TASK_TYPE' => $taskType,
                'UF_STATUS' => PremiumWorkQueueConfig::STATUS_PENDING,
                'UF_LABEL' => $this->buildTaskLabel($taskType, $payload),
                'UF_SESSION_ID' => 0,
                'UF_PAYLOAD_JSON' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'UF_RESULT_JSON' => '',
                'UF_ERROR_TEXT' => '',
                'UF_CREATED_AT' => $now,
                'UF_UPDATED_AT' => $now,
            ]);
            $added[] = $this->formatQueueRow($this->repository->getPremiumWorkQueueItemById($id) ?? []);
        }

        $this->processUser($userId);

        return [
            'queued' => count($added),
            'tasks' => $added,
            'state' => $this->getStateForUser($userId),
        ];
    }

    public function updatePendingExchangeSellMode(int $userId, int $taskId, string $sellMode): array
    {
        $this->assertPremiumActive($userId);
        $sellMode = trim($sellMode);
        if (!in_array($sellMode, ['listing', 'consign'], true)) {
            throw new \InvalidArgumentException('Некорректный режим продажи');
        }

        $row = $this->repository->getPremiumWorkQueueItemById($taskId);
        if (!$row || (int)($row['UF_USER_ID'] ?? 0) !== $userId) {
            throw new \RuntimeException('Задача не найдена');
        }
        if ((string)($row['UF_STATUS'] ?? '') !== PremiumWorkQueueConfig::STATUS_PENDING) {
            throw new \RuntimeException('Режим продажи можно менять только у ожидающих задач');
        }
        if ((string)($row['UF_TASK_TYPE'] ?? '') !== PremiumWorkQueueConfig::TASK_EXCHANGE_LIST) {
            throw new \RuntimeException('Режим продажи доступен только для задач биржи');
        }

        $payload = $this->decodePayload((string)($row['UF_PAYLOAD_JSON'] ?? ''));
        $payload['sell_mode'] = $sellMode;
        $payload = $this->normalizePayload(PremiumWorkQueueConfig::TASK_EXCHANGE_LIST, $payload);

        $now = new DateTime();
        $this->repository->updatePremiumWorkQueueItem($taskId, [
            'UF_PAYLOAD_JSON' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'UF_LABEL' => $this->buildTaskLabel(PremiumWorkQueueConfig::TASK_EXCHANGE_LIST, $payload),
            'UF_UPDATED_AT' => $now,
        ]);

        return [
            'item' => $this->formatQueueRow($this->repository->getPremiumWorkQueueItemById($taskId) ?? []),
            'state' => $this->getStateForUser($userId),
        ];
    }

    public function cancel(int $userId, int $taskId): array
    {
        $row = $this->repository->getPremiumWorkQueueItemById($taskId);
        if (!$row || (int)($row['UF_USER_ID'] ?? 0) !== $userId) {
            throw new \RuntimeException('Задача не найдена');
        }

        $status = (string)($row['UF_STATUS'] ?? '');
        if ($status === PremiumWorkQueueConfig::STATUS_PENDING) {
            $now = new DateTime();
            $this->repository->updatePremiumWorkQueueItem($taskId, [
                'UF_STATUS' => PremiumWorkQueueConfig::STATUS_CANCELLED,
                'UF_UPDATED_AT' => $now,
                'UF_FINISHED_AT' => $now,
                'UF_ERROR_TEXT' => 'Отменено пользователем',
            ]);
        } elseif ($status === PremiumWorkQueueConfig::STATUS_ACTIVE
            && (string)($row['UF_TASK_TYPE'] ?? '') === PremiumWorkQueueConfig::TASK_FARM) {
            $sessionId = (int)($row['UF_SESSION_ID'] ?? 0);
            if ($sessionId > 0) {
                $this->professionRepository->updateProfessionSession($sessionId, [
                    'UF_STATUS' => ProfessionMaterialConfig::SESSION_STATUS_CANCELLED,
                    'UF_UPDATED_AT' => new DateTime(),
                ]);
            }
            $now = new DateTime();
            $this->repository->updatePremiumWorkQueueItem($taskId, [
                'UF_STATUS' => PremiumWorkQueueConfig::STATUS_CANCELLED,
                'UF_UPDATED_AT' => $now,
                'UF_FINISHED_AT' => $now,
                'UF_ERROR_TEXT' => 'Отменено пользователем',
            ]);
            $this->processUser($userId);
        } else {
            throw new \RuntimeException('Эту задачу нельзя отменить');
        }

        return ['state' => $this->getStateForUser($userId)];
    }

    /**
     * @return array<string, mixed>
     */
    public function getStateForUser(int $userId): array
    {
        if ($userId <= 0) {
            return $this->emptyState();
        }

        try {
            $this->repository->ensurePremiumWorkQueueSchema();
        } catch (\Throwable $exception) {
            return $this->emptyState();
        }

        $premiumActive = $this->premiumService->hasActivePremium($userId);
        $pending = [];
        $active = [];
        $rawPending = $this->repository->getPremiumWorkQueueItemsForUser($userId, [
            PremiumWorkQueueConfig::STATUS_PENDING,
        ]);
        $rawActive = $this->repository->getPremiumWorkQueueItemsForUser($userId, [
            PremiumWorkQueueConfig::STATUS_ACTIVE,
        ]);
        foreach (array_merge($rawActive, $rawPending) as $row) {
            $formatted = $this->formatQueueRow($row);
            if ($formatted['status'] === PremiumWorkQueueConfig::STATUS_ACTIVE) {
                $active[] = $formatted;
            } else {
                $pending[] = $formatted;
            }
        }

        $eta = $this->buildFarmQueueEta($userId, array_merge($rawActive, $rawPending));

        $terminalRows = $this->repository->getPremiumWorkQueueItemsForUser($userId, PremiumWorkQueueConfig::TERMINAL_STATUSES);
        usort($terminalRows, static function (array $a, array $b): int {
            $tsA = ($a['UF_FINISHED_AT'] ?? null) instanceof DateTime ? $a['UF_FINISHED_AT']->getTimestamp() : 0;
            $tsB = ($b['UF_FINISHED_AT'] ?? null) instanceof DateTime ? $b['UF_FINISHED_AT']->getTimestamp() : 0;
            if ($tsA !== $tsB) {
                return $tsB <=> $tsA;
            }

            return ((int)($b['ID'] ?? 0)) <=> ((int)($a['ID'] ?? 0));
        });

        $log = [];
        foreach (array_slice($terminalRows, 0, PremiumWorkQueueConfig::LOG_LIMIT) as $row) {
            $log[] = $this->formatQueueRow($row);
        }

        return [
            'premium_active' => $premiumActive,
            'pending' => $pending,
            'active' => $active,
            'log' => $log,
            'pending_count' => count($pending),
            'eta_cycles' => (int)($eta['eta_cycles'] ?? 0),
            'eta_minutes' => (int)($eta['eta_minutes'] ?? 0),
            'eta_label' => (string)($eta['eta_label'] ?? ''),
        ];
    }

    public function processUser(int $userId): void
    {
        if ($userId <= 0 || !$this->premiumService->hasActivePremium($userId)) {
            return;
        }

        try {
            $this->repository->ensurePremiumWorkQueueSchema();
        } catch (\Throwable $exception) {
            return;
        }

        (new ProfessionFarmService($this->professionRepository, null, $this->repository))->processDueTicksPublic($userId);
        $this->syncActiveFarmTasks($userId);

        if ($this->professionRepository->getActiveSessionByUserId($userId)) {
            return;
        }

        $this->dispatchNext($userId);
    }

    public function processAllUsers(): int
    {
        $userIds = $this->repository->getPremiumWorkQueueUserIdsWithDueWork();
        foreach ($userIds as $userId) {
            if ($this->premiumService->hasActivePremium($userId)) {
                $this->processUser($userId);
            }
        }

        return count($userIds);
    }

    private function dispatchNext(int $userId): void
    {
        $pending = $this->repository->getPremiumWorkQueueItemsForUser($userId, [
            PremiumWorkQueueConfig::STATUS_PENDING,
        ]);
        if (!$pending) {
            return;
        }

        $row = $pending[0];
        $taskId = (int)($row['ID'] ?? 0);
        $taskType = (string)($row['UF_TASK_TYPE'] ?? '');
        $payload = $this->decodePayload((string)($row['UF_PAYLOAD_JSON'] ?? ''));

        try {
            $this->validatePayloadForStart($userId, $taskType, $payload);
        } catch (\Throwable $exception) {
            $this->failTask($taskId, $exception->getMessage());
            $this->dispatchNext($userId);

            return;
        }

        try {
            if ($taskType === PremiumWorkQueueConfig::TASK_FARM) {
                $this->startFarmTask($userId, $taskId, $payload);
            } elseif ($taskType === PremiumWorkQueueConfig::TASK_ALBUM_CRAFT) {
                $this->completeInstantTask(
                    $userId,
                    $taskId,
                    (new AlbumCraftService($this->professionRepository, $this->repository))->craft(
                        $userId,
                        (string)($payload['profession_code'] ?? '')
                    )
                );
                $this->dispatchNext($userId);
            } elseif ($taskType === PremiumWorkQueueConfig::TASK_EXCHANGE_LIST) {
                $exchange = new ExchangeService($this->repository);
                $sellMode = (string)($payload['sell_mode'] ?? 'listing');
                if ($sellMode === 'consign') {
                    $result = $exchange->consignToBank(
                        $userId,
                        (string)($payload['kind'] ?? ''),
                        (string)($payload['code'] ?? ''),
                        (int)($payload['qty'] ?? 0),
                        (string)($payload['category'] ?? ''),
                        (int)($payload['event_id'] ?? 0),
                        (string)($payload['team_code'] ?? '')
                    );
                } else {
                    $result = $exchange->createListing(
                        $userId,
                        (string)($payload['kind'] ?? ''),
                        (string)($payload['code'] ?? ''),
                        (int)($payload['qty'] ?? 0),
                        (float)($payload['price_per_unit'] ?? 0),
                        (string)($payload['category'] ?? ''),
                        (int)($payload['event_id'] ?? 0),
                        (string)($payload['team_code'] ?? '')
                    );
                }
                $this->completeInstantTask($userId, $taskId, $result);
                $this->dispatchNext($userId);
            }
        } catch (\Throwable $exception) {
            $this->failTask($taskId, $exception->getMessage());
            $this->dispatchNext($userId);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function startFarmTask(int $userId, int $taskId, array $payload): void
    {
        $farm = new ProfessionFarmService($this->professionRepository, null, $this->repository);
        $farm->startWork(
            $userId,
            (string)($payload['profession_code'] ?? ''),
            (string)($payload['work_mode'] ?? ''),
            (int)($payload['iterations'] ?? 0)
        );

        $session = $this->professionRepository->getActiveSessionByUserId($userId);
        if (!$session) {
            throw new \RuntimeException('Не удалось запустить смену');
        }

        $now = new DateTime();
        $this->repository->updatePremiumWorkQueueItem($taskId, [
            'UF_STATUS' => PremiumWorkQueueConfig::STATUS_ACTIVE,
            'UF_SESSION_ID' => (int)($session['ID'] ?? 0),
            'UF_UPDATED_AT' => $now,
        ]);
    }

    /**
     * @param array<string, mixed> $result
     */
    private function completeInstantTask(int $userId, int $taskId, array $result): void
    {
        $now = new DateTime();
        $this->repository->updatePremiumWorkQueueItem($taskId, [
            'UF_STATUS' => PremiumWorkQueueConfig::STATUS_COMPLETED,
            'UF_RESULT_JSON' => json_encode($result, JSON_UNESCAPED_UNICODE),
            'UF_ERROR_TEXT' => '',
            'UF_UPDATED_AT' => $now,
            'UF_FINISHED_AT' => $now,
        ]);
        GameProfileService::invalidateSummaryCache($userId);
    }

    private function failTask(int $taskId, string $message): void
    {
        $now = new DateTime();
        $this->repository->updatePremiumWorkQueueItem($taskId, [
            'UF_STATUS' => PremiumWorkQueueConfig::STATUS_FAILED,
            'UF_ERROR_TEXT' => mb_substr(trim($message), 0, 500),
            'UF_UPDATED_AT' => $now,
            'UF_FINISHED_AT' => $now,
        ]);
    }

    private function syncActiveFarmTasks(int $userId): void
    {
        foreach ($this->repository->getPremiumWorkQueueItemsForUser($userId, [
            PremiumWorkQueueConfig::STATUS_ACTIVE,
        ]) as $row) {
            if ((string)($row['UF_TASK_TYPE'] ?? '') !== PremiumWorkQueueConfig::TASK_FARM) {
                continue;
            }

            $taskId = (int)($row['ID'] ?? 0);
            $sessionId = (int)($row['UF_SESSION_ID'] ?? 0);
            if ($sessionId <= 0) {
                $this->failTask($taskId, 'Смена не найдена');
                continue;
            }

            $session = $this->professionRepository->getProfessionSessionById($sessionId);
            if (!$session) {
                $this->failTask($taskId, 'Смена не найдена');
                continue;
            }

            $status = (string)($session['UF_STATUS'] ?? '');
            if ($status === ProfessionMaterialConfig::SESSION_STATUS_ACTIVE) {
                continue;
            }

            $now = new DateTime();
            if ($status === ProfessionMaterialConfig::SESSION_STATUS_COMPLETED) {
                $resultJson = (string)($session['UF_LAST_RESULT_JSON'] ?? '');
                $this->repository->updatePremiumWorkQueueItem($taskId, [
                    'UF_STATUS' => PremiumWorkQueueConfig::STATUS_COMPLETED,
                    'UF_RESULT_JSON' => $resultJson,
                    'UF_ERROR_TEXT' => '',
                    'UF_UPDATED_AT' => $now,
                    'UF_FINISHED_AT' => $now,
                ]);
                GameProfileService::invalidateSummaryCache($userId);
            } else {
                $error = '';
                $decoded = json_decode((string)($session['UF_LAST_RESULT_JSON'] ?? ''), true);
                if (is_array($decoded) && !empty($decoded['error'])) {
                    $error = (string)$decoded['error'];
                }
                if ($error === '') {
                    $error = $status === ProfessionMaterialConfig::SESSION_STATUS_CANCELLED
                        ? 'Смена отменена'
                        : 'Смена завершилась с ошибкой';
                }
                $this->failTask($taskId, $error);
            }
        }
    }

    private function assertPremiumActive(int $userId): void
    {
        if (!$this->premiumService->hasActivePremium($userId)) {
            throw new \RuntimeException('Очередь работ доступна только с активным Premium');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(string $taskType, array $payload): array
    {
        if ($taskType === PremiumWorkQueueConfig::TASK_FARM) {
            return [
                'profession_code' => trim((string)($payload['profession_code'] ?? '')),
                'work_mode' => trim((string)($payload['work_mode'] ?? ProfessionMaterialConfig::WORK_MODE_TREASURY)),
                'iterations' => max(0, (int)($payload['iterations'] ?? 0)),
            ];
        }

        if ($taskType === PremiumWorkQueueConfig::TASK_ALBUM_CRAFT) {
            return [
                'profession_code' => trim((string)($payload['profession_code'] ?? '')),
            ];
        }

        return [
            'kind' => trim((string)($payload['kind'] ?? '')),
            'code' => trim((string)($payload['code'] ?? '')),
            'qty' => max(0, (int)($payload['qty'] ?? 0)),
            'price_per_unit' => (float)($payload['price_per_unit'] ?? 0),
            'category' => trim((string)($payload['category'] ?? '')),
            'event_id' => (int)($payload['event_id'] ?? 0),
            'team_code' => trim((string)($payload['team_code'] ?? '')),
            'sell_mode' => in_array((string)($payload['sell_mode'] ?? 'listing'), ['listing', 'consign'], true)
                ? (string)($payload['sell_mode'] ?? 'listing')
                : 'listing',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validatePayloadForEnqueue(int $userId, string $taskType, array $payload): void
    {
        if ($taskType === PremiumWorkQueueConfig::TASK_FARM) {
            $this->assertFarmPayloadShape($payload);
            if (!$this->professionRepository->getProfessionByUserAndCode($userId, $payload['profession_code'])) {
                throw new \InvalidArgumentException('Профессия не изучена');
            }

            $this->validateFarmQueueAffordability($userId, $payload);

            return;
        }

        if ($taskType === PremiumWorkQueueConfig::TASK_ALBUM_CRAFT) {
            $code = (string)($payload['profession_code'] ?? '');
            if (!in_array($code, AlbumConfig::CRAFT_PROFESSION_CODES, true)) {
                throw new \InvalidArgumentException('Некорректная профессия для крафта альбомов');
            }

            return;
        }

        if ($payload['kind'] === '' || $payload['code'] === '' || $payload['qty'] <= 0) {
            throw new \InvalidArgumentException('Некорректные параметры лота');
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validatePayloadForStart(int $userId, string $taskType, array $payload): void
    {
        $this->validatePayloadForEnqueue($userId, $taskType, $payload);

        if ($this->professionRepository->getActiveSessionByUserId($userId)) {
            throw new \RuntimeException('Уже есть активная смена');
        }

        if ($taskType === PremiumWorkQueueConfig::TASK_FARM) {
            $iterations = (int)($payload['iterations'] ?? 0);
            if ($iterations <= 0) {
                $iterations = ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION;
            }
            $planner = new PremiumFarmQueueProjectionService($this->professionRepository, $this->repository);
            $resolved = $planner->resolveMaxFarmIterationsAfterQueue(
                $userId,
                (string)$payload['profession_code'],
                (string)$payload['work_mode'],
                $iterations
            );
            if ($resolved <= 0) {
                throw new \RuntimeException('Недостаточно ресурсов для смены');
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function validateFarmQueueAffordability(int $userId, array $payload): void
    {
        $iterations = (int)($payload['iterations'] ?? 0);
        if ($iterations <= 0) {
            $iterations = ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION;
        }

        $planner = new PremiumFarmQueueProjectionService($this->professionRepository, $this->repository);
        $resolved = $planner->resolveMaxFarmIterationsAfterQueue(
            $userId,
            (string)$payload['profession_code'],
            (string)$payload['work_mode'],
            $iterations
        );
        if ($resolved <= 0) {
            throw new \InvalidArgumentException('Недостаточно ресурсов с учётом очереди Premium');
        }

        if (($payload['work_mode'] ?? '') !== ProfessionMaterialConfig::WORK_MODE_SELF) {
            return;
        }

        $preview = $planner->buildPreview($userId);
        $planner->assertSelfFarmAffordable(
            $userId,
            $resolved,
            (float)($preview['reserved_prognobaks'] ?? 0)
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function buildTaskLabel(string $taskType, array $payload): string
    {
        if ($taskType === PremiumWorkQueueConfig::TASK_FARM) {
            $definition = ProfessionMaterialConfig::getProfession((string)($payload['profession_code'] ?? ''));
            $mode = (string)($payload['work_mode'] ?? '');
            $modeLabel = $mode === ProfessionMaterialConfig::WORK_MODE_SELF ? 'для себя' : 'на казну';
            $iterations = (int)($payload['iterations'] ?? 0);
            if ($iterations <= 0) {
                $iterations = ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION;
            }

            return ($definition['label'] ?? 'Профессия') . ' · ' . $modeLabel . ' · ' . $iterations . ' цикл.';
        }

        if ($taskType === PremiumWorkQueueConfig::TASK_ALBUM_CRAFT) {
            $definition = ProfessionMaterialConfig::getProfession((string)($payload['profession_code'] ?? ''));

            return 'Крафт альбомов · ' . ($definition['label'] ?? 'профессия');
        }

        $inventory = new ExchangeInventoryService($this->repository);
        $label = $inventory->buildItemLabel(
            (string)($payload['kind'] ?? ''),
            (string)($payload['code'] ?? ''),
            (string)($payload['category'] ?? ''),
            ((string)($payload['team_code'] ?? '')) ?: null
        );
        $modeLabel = ($payload['sell_mode'] ?? 'listing') === 'consign' ? 'комиссия' : 'листинг';

        return 'Биржа · ' . $label . ' ×' . (int)($payload['qty'] ?? 0) . ' · ' . $modeLabel;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array{eta_cycles:int,eta_minutes:int,eta_label:string}
     */
    private function buildFarmQueueEta(int $userId, array $rows): array
    {
        $cycles = 0;
        $activeSession = $this->professionRepository->getActiveSessionByUserId($userId);
        $activeSessionId = $activeSession ? (int)($activeSession['ID'] ?? 0) : 0;

        foreach ($rows as $row) {
            if ((string)($row['UF_TASK_TYPE'] ?? '') !== PremiumWorkQueueConfig::TASK_FARM) {
                continue;
            }

            $status = (string)($row['UF_STATUS'] ?? '');
            $sessionId = (int)($row['UF_SESSION_ID'] ?? 0);
            if ($status === PremiumWorkQueueConfig::STATUS_ACTIVE
                && $activeSessionId > 0
                && $sessionId === $activeSessionId
                && $activeSession
            ) {
                $total = (int)($activeSession['UF_ITERATIONS_TOTAL'] ?? 0);
                $done = (int)($activeSession['UF_ITERATIONS_DONE'] ?? 0);
                $cycles += max(0, $total - $done);

                continue;
            }

            $payload = $this->decodePayload((string)($row['UF_PAYLOAD_JSON'] ?? ''));
            $iterations = (int)($payload['iterations'] ?? 0);
            if ($iterations <= 0) {
                $iterations = ProfessionEconomyConfig::FREE_ITERATIONS_PER_SESSION;
            }
            $cycles += $iterations;
        }

        $minutes = $cycles * ProfessionEconomyConfig::ITERATION_MINUTES;

        return [
            'eta_cycles' => $cycles,
            'eta_minutes' => $minutes,
            'eta_label' => self::formatEtaLabel($minutes),
        ];
    }

    private static function formatEtaLabel(int $minutes): string
    {
        if ($minutes <= 0) {
            return '';
        }

        if ($minutes < 60) {
            return '~' . $minutes . ' мин';
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;
        if ($rest === 0) {
            return '~' . $hours . ' ч';
        }

        return '~' . $hours . ' ч ' . $rest . ' мин';
    }

    /**
     * @return array<string, mixed>
     */
    private function formatQueueRow(array $row): array
    {
        if (!$row) {
            return [];
        }

        $finishedAt = $row['UF_FINISHED_AT'] ?? null;
        $createdAt = $row['UF_CREATED_AT'] ?? null;
        $result = [];
        $json = (string)($row['UF_RESULT_JSON'] ?? '');
        if ($json !== '') {
            $decoded = json_decode($json, true);
            if (is_array($decoded)) {
                $result = $decoded;
            }
        }

        $payload = $this->decodePayload((string)($row['UF_PAYLOAD_JSON'] ?? ''));

        return [
            'id' => (int)($row['ID'] ?? 0),
            'task_type' => (string)($row['UF_TASK_TYPE'] ?? ''),
            'status' => (string)($row['UF_STATUS'] ?? ''),
            'label' => (string)($row['UF_LABEL'] ?? ''),
            'payload' => $payload,
            'session_id' => (int)($row['UF_SESSION_ID'] ?? 0),
            'error' => (string)($row['UF_ERROR_TEXT'] ?? ''),
            'result' => $result,
            'created_at' => $createdAt instanceof DateTime ? $createdAt->format('d.m.Y H:i') : '',
            'finished_at' => $finishedAt instanceof DateTime ? $finishedAt->format('d.m.Y H:i') : '',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodePayload(string $json): array
    {
        if ($json === '') {
            return [];
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function assertFarmPayloadShape(array $payload): void
    {
        if ($payload['profession_code'] === '') {
            throw new \InvalidArgumentException('Укажите профессию');
        }

        if (!in_array($payload['work_mode'], [
            ProfessionMaterialConfig::WORK_MODE_SELF,
            ProfessionMaterialConfig::WORK_MODE_TREASURY,
        ], true)) {
            throw new \InvalidArgumentException('Некорректный режим работы');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyState(): array
    {
        return [
            'premium_active' => false,
            'pending' => [],
            'active' => [],
            'log' => [],
            'pending_count' => 0,
            'eta_cycles' => 0,
            'eta_minutes' => 0,
            'eta_label' => '',
        ];
    }
}
