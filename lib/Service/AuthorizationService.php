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

    private bool $ownerModeEnabled;

    public function __construct(
        private readonly IConfig       $config,
        private readonly IGroupManager $groupManager,
        private readonly AgentService  $agentService,
    ) {
        $raw = $this->config->getAppValue(Application::APP_ID, 'admin_groups', '[]');
        $this->adminGroups      = json_decode($raw, true) ?? [];
        $this->ownerModeEnabled = $this->config->getAppValue(
            Application::APP_ID, 'owner_mode_enabled', 'false') === 'true';
    }

    /**
     * Является ли пользователь ACL-администратором
     * (входит хотя бы в одну из настроенных adminGroups)
     */
    public function isAclAdmin(IUser $user): bool
    {
        foreach ($this->adminGroups as $groupId) {
            if ($this->groupManager->isInGroup($user->getUID(), $groupId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Включён ли режим owner-делегирования
     */
    public function isOwnerModeEnabled(): bool
    {
        return $this->ownerModeEnabled;
    }

    /**
     * Получить AD группы пользователя для передачи агенту
     * Агент использует их для проверки NcAdminGroups
     */
    public function getUserAdGroups(IUser $user): array
    {
        $groups = $this->groupManager->getUserGroups($user);
        return array_map(fn($g) => $g->getDisplayName(), $groups);
    }

    /**
     * Получить AD группы пользователя в формате DOMAIN\GroupName
     * для передачи в заголовок X-Nc-User-Groups
     */
    public function getUserAdGroupsHeader(IUser $user): string
    {
        $groups = $this->getUserAdGroups($user);
        return implode(',', $groups);
    }
}
