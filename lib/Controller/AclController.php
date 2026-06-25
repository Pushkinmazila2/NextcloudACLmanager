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

class AclController extends Controller
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
     * GET /apps/ncaclmanager/api/acl?path=...
     */
    #[NoAdminRequired]
    public function get(string $path): JSONResponse
    {
        $user = $this->currentUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Не авторизован'], Http::STATUS_UNAUTHORIZED);
        }

        // Только admin или owner (проверка делегирования — на стороне агента)
        if (!$this->authService->isAclAdmin($user) && !$this->authService->isOwnerModeEnabled()) {
            return new JSONResponse(['error' => 'Недостаточно прав'], Http::STATUS_FORBIDDEN);
        }

        try {
            $this->agentService->setContext($user, $this->authService->getUserAdGroupsHeader($user));
            $result = $this->agentService->getAcl($path);
            $this->logger->info('ACL прочитан: ' . $path, ['app' => 'ncaclmanager', 'user' => $user->getUID()]);
            return new JSONResponse($result);
        } catch (\Throwable $e) {
            return $this->agentError($e, 'getAcl');
        }
    }

    /**
     * POST /apps/ncaclmanager/api/acl
     */
    #[NoAdminRequired]
    public function set(string $path, string $groupIdentity, string $permission,
                        string $action = 'Allow', ?string $comment = null): JSONResponse
    {
        $user = $this->currentUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Не авторизован'], Http::STATUS_UNAUTHORIZED);
        }

        if (!$this->authService->isAclAdmin($user)) {
            return new JSONResponse(['error' => 'Недостаточно прав'], Http::STATUS_FORBIDDEN);
        }

        try {
            $this->agentService->setContext($user, $this->authService->getUserAdGroupsHeader($user));
            $result = $this->agentService->setAcl($path, $groupIdentity, $permission, $action,
                $user->getUID(), $comment);
            $this->logger->info("ACL установлен: {$path} | {$groupIdentity} | {$permission}",
                ['app' => 'ncaclmanager', 'user' => $user->getUID()]);
            return new JSONResponse($result);
        } catch (\Throwable $e) {
            return $this->agentError($e, 'setAcl');
        }
    }

    /**
     * DELETE /apps/ncaclmanager/api/acl
     */
    #[NoAdminRequired]
    public function remove(string $path, string $groupIdentity, ?string $comment = null): JSONResponse
    {
        $user = $this->currentUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Не авторизован'], Http::STATUS_UNAUTHORIZED);
        }

        if (!$this->authService->isAclAdmin($user)) {
            return new JSONResponse(['error' => 'Недостаточно прав'], Http::STATUS_FORBIDDEN);
        }

        try {
            $this->agentService->setContext($user, $this->authService->getUserAdGroupsHeader($user));
            $result = $this->agentService->removeAcl($path, $groupIdentity, $user->getUID(), $comment);
            $this->logger->info("ACL удалён: {$path} | {$groupIdentity}",
                ['app' => 'ncaclmanager', 'user' => $user->getUID()]);
            return new JSONResponse($result);
        } catch (\Throwable $e) {
            return $this->agentError($e, 'removeAcl');
        }
    }

    // ── Утилиты ───────────────────────────────────────────────────────

    private function currentUser(): ?\OCP\IUser
    {
        return $this->userSession->getUser();
    }

    private function agentError(\Throwable $e, string $op): JSONResponse
    {
        $this->logger->error("Ошибка агента [{$op}]: " . $e->getMessage(), [
            'app'       => 'ncaclmanager',
            'exception' => $e,
        ]);
        return new JSONResponse(
            ['error' => 'Ошибка соединения с агентом: ' . $e->getMessage()],
            Http::STATUS_BAD_GATEWAY
        );
    }
}
