<?php

namespace Prognos9ys\Main\Service\Football;

use Prognos9ys\Main\Model\Repository\MatchesRepository;
use Prognos9ys\Main\Service\Championship\PlayoffSlotHelper;

class FootballPrognosisValidator
{
    public const PLAYOFF_DRAW_MESSAGE = 'В матче плей-офф нельзя ставить ничью и равный счёт — победитель определяется в доп. время или серии пенальти.';

    /**
     * @param array<int|string, mixed> $fields
     */
    public function validate(int $matchId, array $fields): ?string
    {
        if ($matchId <= 0) {
            return null;
        }

        $match = $this->loadMatch($matchId);
        if ($match === null || !PlayoffSlotHelper::isPlayoffMatch($match)) {
            return null;
        }

        $home = (int)($fields[15] ?? $fields['15'] ?? 0);
        $guest = (int)($fields[16] ?? $fields['16'] ?? 0);
        $outcome = mb_strtolower(trim((string)($fields[18] ?? $fields['18'] ?? '')));

        if ($home === $guest || $outcome === 'н') {
            return self::PLAYOFF_DRAW_MESSAGE;
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadMatch(int $matchId): ?array
    {
        try {
            return (new MatchesRepository())->findNormalizedById($matchId);
        } catch (\Throwable $exception) {
            return null;
        }
    }
}
