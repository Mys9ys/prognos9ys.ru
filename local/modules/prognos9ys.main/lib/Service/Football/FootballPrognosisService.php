<?php

namespace Prognos9ys\Main\Service\Football;

class FootballPrognosisService
{
    public function send(string $userToken, array $fields): array
    {
        $handler = new \FootballSendPrognosis([
            'userToken' => $userToken,
            'fields' => $fields,
        ]);

        return $handler->result();
    }
}
