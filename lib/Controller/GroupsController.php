<?php

declare(strict_types=1);

namespace OCA\NcAclManager\Controller;

use OCA\NcAclManager\Service\AgentService;
use OCA\NcAclManager\Service\AuthorizationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IRequest;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class GroupsController extends Controller
{
    public function __construct(
        string                              $appName,
        IRequest                            $request,
        private readonly AgentService       $agentService,
        private readonly AuthorizationService $authService,
        private readonly IUserSession       $userSession,
        private readonly LoggerInterface    $logger,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * GET /api/groups?path=...
     * Доступно всем у кого есть права (admin или owner)
     */
    #[NoAdminRequired]
    public function getFolderGroups(string $path): JSONResponse
    {
        $user = $this->currentUser();
        if ($user === null) return $this->unauthorized();
        if (!$this->canManage($user)) return $this->forbidden();

        try {
            $this->agentService->setContext($user, $this->authService->getUserAdGroupsHeader($user));
            return new JSONResponse($this->agentService->getFolderGroups($path));
        } catch (\Throwable $e) {
            return $this->agentError($e, 'getFolderGroups');
        }
    }

    /**
     * POST /api/groups — создать комплект RO/RX/RW для папки
     * Только admin
     */
    #[NoAdminRequired]
    public function createFolderGroups(string $path, ?array $suffixes = null): JSONResponse
    {
        $user = $this->currentUser();
        if ($user === null) return $this->unauthorized();
        if (!$this->authService->isAclAdmin($user)) return $this->forbidden();

        try {
            $this->agentService->setContext($user, $this->authService->getUserAdGroupsHeader($user));
            $result = $this->agentService->createFolderGroups(
                $path,
                $user->getUID(),
                $suffixes ?? ['RO', 'RX', 'RW']
            );
            $this->logger->info("Группы созданы для: {$path}",
                ['app' => 'ncaclmanager', 'user' => $user->getUID()]);
            return new JSONResponse($result);
        } catch (\Throwable $e) {
            return $this->agentError($e, 'createFolderGroups');
        }
    }

    /**
     * DELETE /api/groups?path=...
     * Только admin
     */
    #[NoAdminRequired]
    public function deleteFolderGroups(string $path): JSONResponse
    {
        $user = $this->currentUser();
        if ($user === null) return $this->unauthorized();
        if (!$this->authService->isAclAdmin($user)) return $this->forbidden();

        try {
            $this->agentService->setContext($user, $this->authService->getUserAdGroupsHeader($user));
            $result = $this->agentService->deleteFolderGroups($path, $user->getUID());
            $this->logger->info("Группы удалены для: {$path}",
                ['app' => 'ncaclmanager', 'user' => $user->getUID()]);
            return new JSONResponse($result);
        } catch (\Throwable $e) {
            return $this->agentError($e, 'deleteFolderGroups');
        }
    }

    /**
     * GET /api/groups/{groupName}/members
     */
    #[NoAdminRequired]
    public function getMembers(string $groupName): JSONResponse
    {
        $user = $this->currentUser();
        if ($user === null) return $this->unauthorized();
        if (!$this->canManage($user)) return $this->forbidden();

        try {
            $this->agentService->setContext($user, $this->authService->getUserAdGroupsHeader($user));
            return new JSONResponse($this->agentService->getGroupMembers($groupName));
        } catch (\Throwable $e) {
            return $this->agentError($e, 'getMembers');
        }
    }

    /**
     * POST /api/groups/{groupName}/members
     */
    #[NoAdminRequired]
    public function addMember(string $groupName, string $userSam, ?string $comment = null): JSONResponse
    {
        $user = $this->currentUser();
        if ($user === null) return $this->unauthorized();
        if (!$this->canManage($user)) return $this->forbidden();

        try {
            $this->agentService->setContext($user, $this->authService->getUserAdGroupsHeader($user));
            $result = $this->agentService->addGroupMember($groupName, $userSam, $user->getUID(), $comment);
            $this->logger->info("Добавлен {$userSam} в {$groupName}",
                ['app' => 'ncaclmanager', 'user' => $user->getUID()]);
            return new JSONResponse($result);
        } catch (\Throwable $e) {
            return $this->agentError($e, 'addMember');
        }
    }

    /**
     * DELETE /api/groups/{groupName}/members/{userSam}
     */
    #[NoAdminRequired]
    public function removeMember(string $groupName, string $userSam, ?string $comment = null): JSONResponse
    {
        $user = $this->currentUser();
        if ($user === null) return $this->unauthorized();
        if (!$this->canManage($user)) return $this->forbidden();

        try {
            $this->agentService->setContext($user, $this->authService->getUserAdGroupsHeader($user));
            $result = $this->agentService->removeGroupMember($groupName, $userSam, $user->getUID(), $comment);
            $this->logger->info("Удалён {$userSam} из {$groupName}",
                ['app' => 'ncaclmanager', 'user' => $user->getUID()]);
            return new JSONResponse($result);
        } catch (\Throwable $e) {
            return $this->agentError($e, 'removeMember');
        }
    }

    // ── Утилиты ───────────────────────────────────────────────────────

    private function currentUser(): ?\OCP\IUser
    {
        return $this->userSession->getUser();
    }

    private function canManage(\OCP\IUser $user): bool
    {
        return $this->authService->isAclAdmin($user) || $this->authService->isOwnerModeEnabled();
    }

    private function unauthorized(): JSONResponse
    {
        return new JSONResponse(['error' => 'Не авторизован'], Http::STATUS_UNAUTHORIZED);
    }

    private function forbidden(): JSONResponse
    {
        return new JSONResponse(['error' => 'Недостаточно прав'], Http::STATUS_FORBIDDEN);
    }

    private function agentError(\Throwable $e, string $op): JSONResponse
    {
        $this->logger->error("Ошибка агента [{$op}]: " . $e->getMessage(),
            ['app' => 'ncaclmanager', 'exception' => $e]);
        return new JSONResponse(
            ['error' => 'Ошибка соединения с агентом: ' . $e->getMessage()],
            Http::STATUS_BAD_GATEWAY
        );
    }
}
