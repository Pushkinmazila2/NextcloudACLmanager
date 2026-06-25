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

class UsersController extends Controller
{
    public function __construct(
        string                              $appName,
        IRequest                            $request,
        private readonly AgentService       $agentService,
        private readonly AuthorizationService $authService,
        private readonly IUserSession       $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    /**
     * GET /api/users/search?q=ivan
     */
    #[NoAdminRequired]
    public function search(string $q, int $max = 20): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Не авторизован'], Http::STATUS_UNAUTHORIZED);
        }

        if (!$this->authService->isAclAdmin($user) && !$this->authService->isOwnerModeEnabled()) {
            return new JSONResponse(['error' => 'Недостаточно прав'], Http::STATUS_FORBIDDEN);
        }

        if (mb_strlen($q) < 3) {
            return new JSONResponse(['users' => [], 'requestId' => '']);
        }

        try {
            $this->agentService->setContext($user, $this->authService->getUserAdGroupsHeader($user));
            return new JSONResponse($this->agentService->searchUsers($q, min($max, 50)));
        } catch (\Throwable $e) {
            return new JSONResponse(
                ['error' => 'Ошибка поиска: ' . $e->getMessage()],
                Http::STATUS_BAD_GATEWAY
            );
        }
    }

    /**
     * GET /api/users/{sam}/manager-chain
     */
    #[NoAdminRequired]
    public function managerChain(string $sam): JSONResponse
    {
        $user = $this->userSession->getUser();
        if ($user === null) {
            return new JSONResponse(['error' => 'Не авторизован'], Http::STATUS_UNAUTHORIZED);
        }

        try {
            $this->agentService->setContext($user, $this->authService->getUserAdGroupsHeader($user));
            return new JSONResponse($this->agentService->getManagerChain($sam));
        } catch (\Throwable $e) {
            return new JSONResponse(
                ['error' => $e->getMessage()],
                Http::STATUS_BAD_GATEWAY
            );
        }
    }
}
