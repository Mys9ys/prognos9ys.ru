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

        $this->repository->decrementEventAgnosticLootItem(
            $userId,
            self::CERT_CODE,
            ChestLootConfig::CATEGORY_CERT,
            1
        );
        $certificateBonus = $this->repository->incrementProfessionCertSlots($userId);

        $farmState = $farmService->getState($userId);
        $slotsAfter = $farmState['slots'] ?? [];
        $lines = [
            [
                'text' => 'Активирован: Сертификат на профессию',
                'status' => 'ok',
            ],
            [
                'text' => 'Слотов профессий: ' . (int)$slotsBefore['max'] . ' → ' . (int)$slotsAfter['max'],
                'status' => 'ok',
            ],
        ];

        if ((int)$slotsAfter['available'] > 0) {
            $lines[] = [
                'text' => 'На вкладке «Фарм» → «Профессии» можно выбрать ещё одну профессию',
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
