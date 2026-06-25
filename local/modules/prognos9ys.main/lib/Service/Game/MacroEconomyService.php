<?php

namespace Prognos9ys\Main\Service\Game;

use Bitrix\Main\UserTable;
use Prognos9ys\Main\Model\Repository\GameEconomyRepository;

class MacroEconomyService
{
    private GameEconomyRepository $repository;

    public function __construct(?GameEconomyRepository $repository = null)
    {
        $this->repository = $repository ?? new GameEconomyRepository();
    }

    public function getSummary(): array
    {
        $wallets = $this->repository->sumWalletBalances();
        $userBanks = $this->repository->sumActiveUserBankBalances();
        $parimutuel = $this->repository->getGameBankByCode(GameEconomyConfig::GAME_BANK_CODE_FOOTBALL_PARIMUTUEL);
        $treasury = $this->repository->getGameBankByCode(GameEconomyConfig::GAME_BANK_CODE_STATE_TREASURY);

        $parimutuelP = round((float)($parimutuel['UF_PROGNOBAKS'] ?? 0), 1);
        $treasuryP = round((float)($treasury['UF_PROGNOBAKS'] ?? 0), 1);
        $treasuryR = round((float)($treasury['UF_RUBLIUS'] ?? 0), 1);

        $handsP = round((float)($wallets['prognobaks'] ?? 0), 1);
        $handsR = round((float)($wallets['rublius'] ?? 0), 1);
        $banksP = round((float)($userBanks['prognobaks'] ?? 0) + $parimutuelP, 1);
        $banksR = round((float)($userBanks['rublius'] ?? 0), 1);

        $totalP = round($handsP + $banksP + $treasuryP, 1);
        $totalR = round($handsR + $banksR + $treasuryR, 1);

        $users = $this->countRegisteredUsers();
        $avgP = $users > 0 ? round($totalP / $users, 1) : 0.0;
        $avgR = $users > 0 ? round($totalR / $users, 1) : 0.0;

        $scopeService = new GameEventScopeService();
        $lastSettled = (new MatchEconomySettlementService($this->repository, $scopeService))
            ->getLastSettledMatchForEvent($scopeService->getAnchorEventId());
        $lastMatchNumber = (int)($lastSettled['number'] ?? 0);
        $lastMatchId = (int)($lastSettled['id'] ?? 0);

        $shopPurchases = $this->repository->sumTreasuryShopPurchaseTotals();
        $govSupport = $this->repository->sumGovSupportDepositStats();

        return [
            'registered_users' => $users,
            'last_settled_match_id' => $lastMatchId,
            'last_settled_match_number' => $lastMatchNumber,
            'last_settled_match_label' => $scopeService->formatMatchLabelByNumber($lastMatchNumber),
            'prognobaks' => array_merge([
                'total' => $totalP,
                'hands' => $handsP,
                'banks' => $banksP,
                'treasury' => $treasuryP,
                'avg_per_user' => $avgP,
                'parimutuel' => $parimutuelP,
                'user_banks' => round((float)($userBanks['prognobaks'] ?? 0), 1),
            ], $this->buildCurrencyRatios($handsP, $banksP, $treasuryP, $totalP)),
            'rublius' => array_merge([
                'total' => $totalR,
                'hands' => $handsR,
                'banks' => $banksR,
                'treasury' => $treasuryR,
                'avg_per_user' => $avgR,
            ], $this->buildCurrencyRatios($handsR, $banksR, $treasuryR, $totalR)),
            'flows' => [
                'shop' => $shopPurchases,
                'shop_wallet_outflow' => [
                    'prognobaks' => $this->repository->sumWalletTxAmountByReasons(
                        ['treasury_shop_chest'],
                        GameEconomyConfig::CURRENCY_PROGNOBAKS,
                        'debit'
                    ),
                    'rublius' => $this->repository->sumWalletTxAmountByReasons(
                        ['treasury_shop_chest', 'treasury_shop_premium'],
                        GameEconomyConfig::CURRENCY_RUBLIUS,
                        'debit'
                    ),
                ],
                'chest_mint' => [
                    'prognobaks' => $this->repository->sumWalletTxAmountByReasons(
                        ['chest_open_loot'],
                        GameEconomyConfig::CURRENCY_PROGNOBAKS,
                        'credit'
                    ),
                    'rublius' => $this->repository->sumWalletTxAmountByReasons(
                        ['chest_open_loot'],
                        GameEconomyConfig::CURRENCY_RUBLIUS,
                        'credit'
                    ),
                ],
                'gov_support' => $govSupport,
            ],
        ];
    }

    /**
     * @return array{treasury_share:float,hands_share:float,banks_share:float,velocity:float}
     */
    private function buildCurrencyRatios(float $hands, float $banks, float $treasury, float $total): array
    {
        if ($total <= 0) {
            return [
                'treasury_share' => 0.0,
                'hands_share' => 0.0,
                'banks_share' => 0.0,
                'velocity' => 0.0,
            ];
        }

        $circulation = $hands + $banks;

        return [
            'treasury_share' => round($treasury / $total * 100, 1),
            'hands_share' => round($hands / $total * 100, 1),
            'banks_share' => round($banks / $total * 100, 1),
            'velocity' => round($circulation / $total * 100, 1),
        ];
    }

    private function countRegisteredUsers(): int
    {
        return (int)UserTable::getCount([
            '=ACTIVE' => 'Y',
        ]);
    }
}
