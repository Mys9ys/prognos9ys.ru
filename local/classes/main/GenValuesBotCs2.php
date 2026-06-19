<?php

class GenValuesBotCs2
{
    protected $arFields = [];

    protected string $boFormat;

    public function __construct(string $boFormat = 'bo3')
    {
        $this->boFormat = $this->normalizeBoFormat($boFormat);
        $this->setSeriesMaps();
        $this->setOpeningPct();
        $this->setPistolPct();
        $this->setClutches();
        $this->setMapScores();
    }

    public function getArFields(): array
    {
        return $this->arFields;
    }

    protected function setSeriesMaps(): void
    {
        $maxWin = $this->boFormat === 'bo1' ? 1 : ($this->boFormat === 'bo5' ? 3 : 2);
        $maxTotal = $this->boFormat === 'bo1' ? 1 : ($this->boFormat === 'bo5' ? 5 : 3);

        $home = random_int(0, $maxWin);
        $guest = random_int(0, $maxWin);

        if ($home + $guest > $maxTotal) {
            if ($home >= $guest) {
                $home = max(0, $home - 1);
            } else {
                $guest = max(0, $guest - 1);
            }
        }

        if ($home === $guest && $home > 0 && $this->boFormat !== 'bo1') {
            $guest = max(0, $guest - 1);
        }

        $this->arFields[15] = $home;
        $this->arFields[16] = $guest;
        $this->arFields[28] = $home + $guest;
        $this->arFields[19] = $home - $guest;

        if ($this->arFields[19] > 0) {
            $this->arFields[18] = 'п1';
        } elseif ($this->arFields[19] < 0) {
            $this->arFields[18] = 'п2';
        } else {
            $this->arFields[18] = '';
        }
    }

    protected function setOpeningPct(): void
    {
        $this->arFields[32] = random_int(35, 65);
    }

    protected function setPistolPct(): void
    {
        $this->arFields[20] = random_int(35, 65);
    }

    protected function setClutches(): void
    {
        $this->arFields[21] = random_int(0, 4);
        $this->arFields[22] = random_int(0, 4);
    }

    protected function setMapScores(): void
    {
        $slotCount = $this->boFormat === 'bo1' ? 1 : ($this->boFormat === 'bo5' ? 3 : 2);
        $maps = [];

        for ($i = 0; $i < $slotCount; $i++) {
            $homeWins = (bool)random_int(0, 1);
            $winnerRounds = random_int(13, 16);
            $loserRounds = random_int(6, 12);

            $maps[] = [
                'slot' => $i + 1,
                'map_code' => '',
                'rounds_home' => $homeWins ? $winnerRounds : $loserRounds,
                'rounds_guest' => $homeWins ? $loserRounds : $winnerRounds,
            ];
        }

        $this->arFields[29] = json_encode($maps, JSON_UNESCAPED_UNICODE);
    }

    protected function normalizeBoFormat(string $value): string
    {
        $value = strtolower(trim($value));

        if (in_array($value, ['bo1', 'bo3', 'bo5'], true)) {
            return $value;
        }

        return 'bo3';
    }
}
