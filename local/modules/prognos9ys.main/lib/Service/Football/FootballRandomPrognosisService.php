<?php

namespace Prognos9ys\Main\Service\Football;

use Prognos9ys\Main\Model\Repository\MatchesRepository;
use Prognos9ys\Main\Service\Championship\PlayoffSlotHelper;
use Prognos9ys\Main\Service\Game\PremiumService;

class FootballRandomPrognosisService
{
    /**
     * @return array<string, mixed>
     */
    public function generateForUser(int $userId, int $matchId): array
    {
        if ($userId <= 0) {
            throw new \RuntimeException('Требуется авторизация');
        }

        if (!(new PremiumService())->hasActivePremium($userId)) {
            throw new \RuntimeException('Рандом доступен только с активным Premium');
        }

        if ($matchId <= 0) {
            throw new \RuntimeException('Матч не найден');
        }

        $match = (new MatchesRepository())->findNormalizedById($matchId);
        if ($match === null) {
            throw new \RuntimeException('Матч не найден');
        }

        if (($match['active'] ?? 'Y') !== 'Y') {
            throw new \RuntimeException('Приём прогнозов на этот матч закрыт');
        }

        $playoff = PlayoffSlotHelper::isPlayoffMatch($match);
        $generator = new \GenValuesBotFootball($playoff);
        $raw = $generator->getArFields();

        return [
            'goal_home' => (int)($raw[15] ?? 0),
            'goal_guest' => (int)($raw[16] ?? 0),
            'result' => (string)($raw[18] ?? ''),
            'diff' => (int)($raw[19] ?? 0),
            'sum' => (int)($raw[28] ?? 0),
            'domination' => (int)($raw[32] ?? 50),
            'yellow' => (int)($raw[21] ?? 0),
            'red' => (int)($raw[22] ?? 0),
            'corner' => (int)($raw[20] ?? 0),
            'penalty' => (int)($raw[23] ?? 0),
            'otime' => (string)($raw[45] ?? ''),
            'spenalty' => (string)($raw[46] ?? ''),
        ];
    }
}
