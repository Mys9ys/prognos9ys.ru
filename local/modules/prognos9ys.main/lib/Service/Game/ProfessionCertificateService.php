<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class ProfessionCertificateService
{
    public const CERT_CODE = 'cert_profession';

    private GameEconomyRepository $repository;
    private ProfessionRepository $professionRepository;

    public function __construct(
        ?GameEconomyRepository $repository = null,
        ?ProfessionRepository $professionRepository = null
    ) {
        $this->repository = $repository ?? new GameEconomyRepository();
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
    }

    /**
     * @return array{
     *   lines:array<int, array{text:string,status:string}>,
     *   certificate_bonus:int,
     *   profession_slots:array<string, mixed>,
     *   farm:array<string, mixed>
     * }
     */
    public function activate(int $userId): array
    {
        if ($userId <= 0) {
            throw new \InvalidArgumentException('Некорректный пользователь');
        }

        $this->repository->ensureProfessionCertSlotsSchema();

        $available = $this->repository->getEventAgnosticLootItemCount(
            $userId,
            self::CERT_CODE,
            ChestLootConfig::CATEGORY_CERT
        );
        if ($available <= 0) {
            throw new \RuntimeException('Сертификат на профессию не найден в инвентаре');
        }

        $farmService = new ProfessionFarmService(
            $this->professionRepository,
            null,
            $this->repository
        );
        $slotsBefore = $farmService->getState($userId)['slots'] ?? [];
        $maxBefore = (int)($slotsBefore['max'] ?? ProfessionMaterialConfig::STARTER_PROFESSION_SLOTS);

        $certificateBonus = 0;
        $certConsumed = false;

        try {
            $certificateBonus = $this->repository->incrementProfessionCertSlots($userId);
            $slotsAfterCheck = $farmService->getState($userId)['slots'] ?? [];
            $maxAfter = (int)($slotsAfterCheck['max'] ?? $maxBefore);

            if ($maxAfter <= $maxBefore) {
                throw new \RuntimeException(
                    'Слот профессии не открылся. Администратору: php7.4 local/modules/prognos9ys.main/install_profession_certificate_hl.php'
                );
            }

            $this->repository->decrementEventAgnosticLootItem(
                $userId,
                self::CERT_CODE,
                ChestLootConfig::CATEGORY_CERT,
                1
            );
            $certConsumed = true;
        } catch (\Throwable $exception) {
            if (!$certConsumed && $certificateBonus > 0) {
                try {
                    $this->repository->decrementProfessionCertSlots($userId, 1);
                } catch (\Throwable $rollbackException) {
                    // оставляем исходную ошибку
                }
            }

            throw $exception;
        }

        $farmState = $farmService->getState($userId);
        $slotsAfter = $farmState['slots'] ?? [];
        $lines = [
            [
                'text' => 'Активирован: Сертификат на профессию',
                'status' => 'ok',
            ],
            [
                'text' => 'Слотов профессий: ' . $maxBefore . ' → ' . (int)($slotsAfter['max'] ?? $maxBefore),
                'status' => 'ok',
            ],
        ];

        if ((int)($slotsAfter['available'] ?? 0) > 0) {
            $lines[] = [
                'text' => 'На вкладке «Фарм» → «Профессии» можно выбрать ещё одну профессию',
                'status' => 'ok',
            ];
        } elseif ((int)($slotsAfter['needs_pick'] ?? 0) === 1) {
            $lines[] = [
                'text' => 'На вкладке «Фарм» → «Профессии» выберите стартовые профессии',
                'status' => 'ok',
            ];
        }

        return [
            'lines' => $lines,
            'certificate_bonus' => $certificateBonus,
            'profession_slots' => $slotsAfter,
            'farm' => $farmState,
        ];
    }
}
