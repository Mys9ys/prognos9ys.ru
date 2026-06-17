<?php

namespace Prognos9ys\Main\Service\Profile;

use Prognos9ys\Main\Controller\ApiException;

class PublicProfileService
{
    public function getByUserId(int $userId): array
    {
        if ($userId <= 0) {
            throw new ApiException('Некорректный ID пользователя', 400);
        }

        $handler = new \ProfileHandlerClass(['userId' => $userId]);
        $result = $handler->result();

        if (($result['status'] ?? '') !== 'ok') {
            throw new ApiException($result['mes'] ?? 'Пользователь не найден', 404);
        }

        $profile = $result['profile'] ?? [];

        return [
            'user' => [
                'id' => (int)($profile['info']['ID'] ?? $userId),
                'name' => (string)($profile['info']['NAME'] ?? ''),
                'avatar' => (string)($profile['info']['img'] ?? ''),
                'registered_at' => (string)($profile['info']['reg'] ?? ''),
            ],
            'rank' => $profile['rank_info'] ?? [],
            'game' => (new \Prognos9ys\Main\Service\Game\GameProfileService())->getSummary($userId, false),
            'football' => $profile['football'] ?? [],
            'race' => $profile['race'] ?? [],
            'racers' => $profile['racers'] ?? [],
        ];
    }

    public function toLegacyFormat(array $profile): array
    {
        return [
            'info' => [
                'ID' => $profile['user']['id'] ?? null,
                'NAME' => $profile['user']['name'] ?? '',
                'img' => $profile['user']['avatar'] ?? '',
                'reg' => $profile['user']['registered_at'] ?? '',
            ],
            'rank_info' => $profile['rank'] ?? [],
            'game_info' => $profile['game'] ?? [],
            'football' => $profile['football'] ?? [],
            'race' => $profile['race'] ?? [],
            'racers' => $profile['racers'] ?? [],
        ];
    }
}
