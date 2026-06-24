<?php

namespace Prognos9ys\Main\Service\Football;

use Prognos9ys\Main\Service\Game\BetService;
use Prognos9ys\Main\Service\Game\GameEconomyConfig;

class FootballPrognosisService
{
    public function send(string $userToken, array $fields, ?bool $withBet = null): array
    {
        $normalizedFields = [];
        foreach ($fields as $key => $value) {
            $normalizedFields[is_numeric($key) ? (int)$key : $key] = $value;
        }

        if ($withBet !== null) {
            $normalizedFields[GameEconomyConfig::PROGNOSIS_PROP_BET_ENABLED] = $withBet
                ? GameEconomyConfig::PROGNOSIS_BET_ENABLED_YES
                : GameEconomyConfig::PROGNOSIS_BET_ENABLED_NO;
        }

        $handler = new \FootballSendPrognosis([
            'userToken' => $userToken,
            'fields' => $normalizedFields,
        ]);

        $result = $handler->result();

        if (($result['status'] ?? '') === 'ok') {
            $userId = (int)((new \GetUserIdForToken($userToken))->getId() ?: 0);
            if ($userId > 0) {
                try {
                    $betService = new BetService();
                    $resolvedWithBet = $withBet;
                    if ($resolvedWithBet === null) {
                        $resolvedWithBet = $betService->canUserAffordStake($userId);
                    }

                    if ($resolvedWithBet) {
                        $betService->upsertBetFromPrognosis($userId, $normalizedFields);
                    } else {
                        $matchId = (int)($normalizedFields[17] ?? 0);
                        if ($matchId > 0) {
                            $betService->cancelPendingBet($userId, $matchId);
                        }
                    }
                } catch (\Throwable $exception) {
                    // Не блокируем сохранение прогноза, если ставка не проставилась.
                }
            }
        }

        return $result;
    }
}
