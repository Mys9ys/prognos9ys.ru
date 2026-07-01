<?php

namespace Prognos9ys\Main\Service\Game;

use Prognos9ys\Main\Model\Repository\GameEconomyRepository;
use Prognos9ys\Main\Model\Repository\ProfessionRepository;

class GovWarehouseService
{
    private ProfessionRepository $professionRepository;
    private GameEconomyRepository $economyRepository;

    public function __construct(
        ?ProfessionRepository $professionRepository = null,
        ?GameEconomyRepository $economyRepository = null
    ) {
        $this->professionRepository = $professionRepository ?? new ProfessionRepository();
        $this->economyRepository = $economyRepository ?? new GameEconomyRepository();
    }

    public function getState(): array
    {
        $qtyMap = $this->professionRepository->getGovWarehouseQtyMap();
        $handsMap = $this->professionRepository->getGlobalUserMaterialQtyByCode();
        $exchangeMap = $this->economyRepository->getActiveExchangeMaterialQtyByCode();

        $groups = [];
        foreach ($this->buildGroups() as $group) {
            $items = [];
            $totalQty = 0;
            $totalHands = 0;
            $totalExchange = 0;
            foreach ($group['codes'] as $code) {
                $meta = ProfessionMaterialConfig::materialCatalog()[$code] ?? null;
                if (!$meta) {
                    continue;
                }
                $qty = (int)($qtyMap[$code] ?? 0);
                $handsQty = (int)($handsMap[$code] ?? 0);
                $exchangeQty = (int)($exchangeMap[$code] ?? 0);
                $totalQty += $qty;
                $totalHands += $handsQty;
                $totalExchange += $exchangeQty;
                $items[] = [
                    'code' => $code,
                    'label' => $meta['label'],
                    'qty' => $qty,
                    'hands_qty' => $handsQty,
                    'exchange_qty' => $exchangeQty,
                    'nominal' => (float)$meta['nominal'],
                    'is_premium' => (bool)$meta['is_premium'],
                    'emoji' => (string)($meta['emoji'] ?? ProfessionMaterialConfig::materialEmoji($code)),
                ];
            }

            $groups[] = [
                'id' => $group['id'],
                'label' => $group['label'],
                'total_qty' => $totalQty,
                'total_hands_qty' => $totalHands,
                'total_exchange_qty' => $totalExchange,
                'items' => $items,
            ];
        }

        $professionLedger = array_values(array_filter(
            (new TreasuryService($this->economyRepository))->getRecentLedger(60),
            static function (array $row): bool {
                $reason = (string)($row['reason'] ?? '');

                return strpos($reason, 'profession_') === 0;
            }
        ));

        return [
            'groups' => $groups,
            'totals' => [
                'items_with_stock' => count(array_filter($qtyMap, static fn(int $qty) => $qty > 0)),
                'total_units' => array_sum($qtyMap),
                'total_hands_units' => array_sum($handsMap),
                'total_exchange_units' => array_sum($exchangeMap),
            ],
            'treasury_exchange' => (new ExchangeService($this->economyRepository))->getTreasuryGovExchangeState(),
            'flows' => [
                'profession' => [
                    'treasury_out' => $this->economyRepository->sumTreasuryTxAmountByReasons(
                        ['profession_work_pay'],
                        GameEconomyConfig::CURRENCY_PROGNOBAKS,
                        'debit'
                    ),
                    'treasury_in' => $this->economyRepository->sumTreasuryTxAmountByReasons(
                        ['profession_work_fee'],
                        GameEconomyConfig::CURRENCY_PROGNOBAKS,
                        'credit'
                    ),
                    'hands_in' => $this->economyRepository->sumWalletTxAmountByReasons(
                        ['profession_work_pay'],
                        GameEconomyConfig::CURRENCY_PROGNOBAKS,
                        'credit'
                    ),
                ],
            ],
            'ledger' => $professionLedger,
        ];
    }

    /**
     * @return array<int, array{id:string,label:string,codes:string[]}>
     */
    private function buildGroups(): array
    {
        $gatherRaw = [];
        $gatherPremium = [];
        $processRaw = [];
        $processPremium = [];

        foreach (ProfessionMaterialConfig::allProfessions() as $profession) {
            $type = (string)($profession['type'] ?? '');
            $output = (string)($profession['output'] ?? '');
            $premium = (string)($profession['premium'] ?? '');

            if ($type === 'gather') {
                if ($output !== '') {
                    $gatherRaw[] = $output;
                }
                if ($premium !== '') {
                    $gatherPremium[] = $premium;
                }
            } elseif ($type === 'process') {
                if ($output !== '') {
                    $processRaw[] = $output;
                }
                if ($premium !== '') {
                    $processPremium[] = $premium;
                }
            }
        }

        return [
            ['id' => 'gather', 'label' => 'Добыча', 'codes' => array_values(array_unique($gatherRaw))],
            ['id' => 'gather_premium', 'label' => 'Премиум добычи', 'codes' => array_values(array_unique($gatherPremium))],
            ['id' => 'process', 'label' => 'Обработка', 'codes' => array_values(array_unique($processRaw))],
            ['id' => 'process_premium', 'label' => 'Премиум обработки', 'codes' => array_values(array_unique($processPremium))],
        ];
    }
}
