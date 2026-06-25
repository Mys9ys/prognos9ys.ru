<?php

namespace Prognos9ys\Main\Service\Auth;

use Bitrix\Main\UserTable;
use Prognos9ys\Main\Controller\ApiException;

class ImpersonationService
{
    public function canImpersonate(int $userId): bool
    {
        $groups = array_map('intval', (array)\CUser::GetUserGroup($userId));
        foreach (ImpersonationConfig::allowedGroupIds() as $groupId) {
            if (in_array((int)$groupId, $groups, true)) {
                return true;
            }
        }

        return false;
    }

    public function canImpersonateByToken(string $token): bool
    {
        $userId = (new TokenAuthService())->getUserIdByToken($token);

        return $userId && $this->canImpersonate($userId);
    }

    /**
     * @return array<int, array{id:int, name:string, email:string}>
     */
    public function searchUsers(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return [];
        }

        $filter = [
            'LOGIC' => 'OR',
            ['%EMAIL' => $query],
            ['%NAME' => $query],
            ['%LAST_NAME' => $query],
            ['%LOGIN' => $query],
        ];

        if (ctype_digit($query)) {
            $filter[] = ['=ID' => (int)$query];
        }

        $rows = UserTable::getList([
            'select' => ['ID', 'NAME', 'LAST_NAME', 'EMAIL', 'LOGIN'],
            'filter' => $filter,
            'order' => ['ID' => 'ASC'],
            'limit' => min(max($limit, 1), 50),
        ]);

        $result = [];
        while ($row = $rows->fetch()) {
            $name = trim(($row['NAME'] ?? '') . ' ' . ($row['LAST_NAME'] ?? ''));
            if ($name === '') {
                $name = (string)($row['LOGIN'] ?? ('#' . $row['ID']));
            }

            $result[] = [
                'id' => (int)$row['ID'],
                'name' => $name,
                'email' => (string)($row['EMAIL'] ?? ''),
            ];
        }

        return $result;
    }

    public function start(int $actorUserId, int $targetUserId): array
    {
        if (!$this->canImpersonate($actorUserId)) {
            throw new ApiException('Недостаточно прав для входа за другого пользователя', 403);
        }

        if ($actorUserId === $targetUserId) {
            throw new ApiException('Нельзя войти за самого себя', 422);
        }

        $target = UserTable::getRow([
            'filter' => ['=ID' => $targetUserId, '=ACTIVE' => 'Y'],
            'select' => ['ID'],
        ]);

        if (!$target) {
            throw new ApiException('Пользователь не найден или неактивен', 404);
        }

        if ($this->isAdminUser($targetUserId)) {
            throw new ApiException('Нельзя войти за администратора', 403);
        }

        $token = $this->ensureUserToken($targetUserId);

        return $this->buildAuthPayload($token, true, $actorUserId, $targetUserId);
    }

    public function stop(string $moderatorToken): array
    {
        if (!$this->canImpersonateByToken($moderatorToken)) {
            throw new ApiException('Недостаточно прав', 403);
        }

        return $this->buildAuthPayload($moderatorToken, false);
    }

    private function ensureUserToken(int $userId): string
    {
        $row = UserTable::getRow([
            'filter' => ['=ID' => $userId],
            'select' => ['UF_TOKEN'],
        ]);

        if (!empty($row['UF_TOKEN'])) {
            return (string)$row['UF_TOKEN'];
        }

        $token = implode('-', str_split(bin2hex(random_bytes(16)), 4));
        $user = new \CUser();
        $user->Update($userId, ['UF_TOKEN' => $token]);

        return $token;
    }

    private function isAdminUser(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        $groups = array_map('intval', (array)\CUser::GetUserGroup($userId));

        return in_array(ImpersonationConfig::ADMIN_GROUP_ID, $groups, true);
    }

    private function buildAuthPayload(
        string $token,
        bool $impersonating,
        ?int $actorUserId = null,
        ?int $targetUserId = null
    ): array {
        $auth = new \Prognos9ysAuthClass([
            'type' => 'tokenLogin',
            'token' => $token,
        ]);
        $result = $auth->result();

        if (($result['status'] ?? '') !== 'ok' || empty($result['info'])) {
            throw new ApiException('Не удалось загрузить профиль пользователя', 500);
        }

        $info = $result['info'];
        $info['can_impersonate'] = $this->canImpersonate((int)$info['ID']);

        return [
            'user' => $info,
            'token' => $token,
            'impersonation' => [
                'active' => $impersonating,
                'actor_user_id' => $actorUserId,
                'target_user_id' => $targetUserId,
            ],
        ];
    }
}
