<?php

namespace Prognos9ys\Main\Service\Cs2;

use Prognos9ys\Main\Service\Game\BetService;

class Cs2PrognosisService
{
    public function send(string $userToken, array $fields, ?string $mapScoresJson = null, ?bool $withBet = null): array
    {
        $normalizedFields = [];
        foreach ($fields as $key => $value) {
            $normalizedFields[is_numeric($key) ? (int)$key : $key] = $value;
        }

        if ($mapScoresJson) {
            $normalizedFields[29] = $mapScoresJson;
        } elseif (!empty($fields['map_scores_json'])) {
            $normalizedFields[29] = (string)$fields['map_scores_json'];
        }

        $handler = new \Cs2SendPrognosis([
            'userToken' => $userToken,
            'fields' => $normalizedFields,
            'map_scores_json' => $mapScoresJson,
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
                        $betFields = $normalizedFields;
                        $betFields[17] = $betFields[17] ?? $fields['match_id'] ?? null;
                        $betFields[18] = $betFields[18] ?? $fields['result'] ?? null;
                        $betService->upsertBetFromPrognosis($userId, $betFields);
                    }
                } catch (\Throwable $exception) {
                }
            }
        }

        return $result;
    }
}
