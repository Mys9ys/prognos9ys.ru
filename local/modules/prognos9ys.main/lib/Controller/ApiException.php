<?php

namespace Prognos9ys\Main\Controller;

class ApiException extends \Exception
{
    public function __construct(string $message, int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
