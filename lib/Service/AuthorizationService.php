<?php

declare(strict_types=1);

namespace OCA\NcAclManager\Service;

use OCA\NcAclManager\AppInfo\Application;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUser;

class AuthorizationService
{
    /** @var string[] */
    private array $adminGroups;

    /** @var string[] NC uid пользователей с правами ACL (только Test режим) */
    private array $ncAdminUsers;

    private bool $ownerModeEnabled;

    public function __construct(
        private readonly IConfig       $config,
        private readonly IGroupManager $groupManager,
    ) {
        $this->adminGroups = json_decode(
            $config->getAppValue(Application::APP_ID, 'admin_groups', '[]'), true) ?? [];

        $this->ncAdminUsers = json_decode(
            $config->getAppValue(Application::APP_ID, 'nc_admin_users', '[]'), true) ?? [];

        $this->ownerModeEnabled = $config->getAppValue(
            Application::APP_ID, 'owner_mode_enabled', 'false') === 'true';
    }

    /**
     * Является ли пользователь ACL-администратором.
     * Проверяем:
     * 1. Входит ли в одну из admin_groups (AD группы синхронизированные в NC)
     * 2. Входит ли в список nc_admin_users (только если агент в Test режиме)
     */
    public function isAclAdmin(IUser $user): bool
    {
        // Проверка по AD группам
        foreach ($this->adminGroups as $groupId) {
            if ($this->groupManager->isInGroup($user->getUID(), $groupId)) {
                return true;
            }
        }

        // Проверка по NC пользователям (только Test режим)
        $agentMode = $this->config->getAppValue(Application::APP_ID, 'agent_mode', '');
        if (strtolower($agentMode) === 'test') {
            if (in_array($user->getUID(), $this->ncAdminUsers, true)) {
                return true;
            }
        }

        return false;
    }

    public function isOwnerModeEnabled(): bool
    {
        return $this->ownerModeEnabled;
    }

    /**
     * Получить AD группы пользователя в формате для заголовка X-Nc-User-Groups
     */
    public function getUserAdGroupsHeader(IUser $user): string
    {
        $groups = $this->groupManager->getUserGroups($user);
        $names  = array_map(fn($g) => $g->getDisplayName(), $groups);
        return implode(',', $names);
    }

    /**
     * Получить AD группы пользователя как массив
     */
    public function getUserAdGroups(IUser $user): array
    {
        $groups = $this->groupManager->getUserGroups($user);
        return array_map(fn($g) => $g->getDisplayName(), $groups);
    }
}
