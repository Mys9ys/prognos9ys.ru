<?php

namespace Prognos9ys\Main\Service\Football;

use Prognos9ys\Main\Service\Game\BetService;

class FootballPrognosisService
{
    public function send(string $userToken, array $fields, ?bool $withBet = null): array
    {
        $normalizedFields = [];
        foreach ($fields as $key => $value) {
            $normalizedFields[(int)$key] = $value;
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
                    }
                } catch (\Throwable $exception) {
                    // Не блокируем сохранение прогноза, если ставка не проставилась.
                }
            }
        }

        return $result;
    }
}
