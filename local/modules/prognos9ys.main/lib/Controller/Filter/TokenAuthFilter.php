<?php

namespace Prognos9ys\Main\Controller\Filter;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;
use Bitrix\Main\HttpRequest;
use Prognos9ys\Main\Service\Auth\TokenAuthService;

class TokenAuthFilter extends Base
{
    public function onBeforeAction(Event $event): ?EventResult
    {
        $token = $this->resolveToken();

        if (!$token) {
            $this->addError(new Error('Требуется токен авторизации', 'AUTH_TOKEN_REQUIRED'));

            return new EventResult(EventResult::ERROR, null, null, $this);
        }

        $userId = (new TokenAuthService())->getUserIdByToken($token);

        if (!$userId) {
            $this->addError(new Error('Неверный токен авторизации', 'AUTH_TOKEN_INVALID'));

            return new EventResult(EventResult::ERROR, null, null, $this);
        }

        TokenAuthService::setCurrentUserId($userId);

        return null;
    }

    private function resolveToken(): ?string
    {
        $request = $this->getAction()->getController()->getRequest();

        $token = $request->get('userToken')
            ?: $request->get('token')
            ?: $request->getPost('userToken')
            ?: $request->getPost('token');

        if ($token) {
            return (string)$token;
        }

        $json = $this->getRequestJsonData();
        $token = $json['userToken'] ?? $json['token'] ?? null;

        if ($token) {
            return (string)$token;
        }

        $authHeader = $request->getHeader('Authorization') ?: $request->getHeader('X-Auth-Token');

        if ($authHeader && strpos($authHeader, 'Bearer ') === 0) {
            return trim(substr($authHeader, 7));
        }

        return $authHeader ? (string)$authHeader : null;
    }

    private function getRequestJsonData(): array
    {
        $request = $this->getAction()->getController()->getRequest();

        if (method_exists($request, 'getJsonList')) {
            return $request->getJsonList()->toArray();
        }

        $raw = HttpRequest::getInput();
        if (!$raw) {
            return [];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
