<?php

namespace Prognos9ys\Main\Service\Football;

class FootballPrognosisService
{
    public function send(string $userToken, array $fields): array
    {
        $normalizedFields = [];
        foreach ($fields as $key => $value) {
            $normalizedFields[(int)$key] = $value;
        }

        $handler = new \FootballSendPrognosis([
            'userToken' => $userToken,
            'fields' => $normalizedFields,
        ]);

        return $handler->result();
    }
}
