<?php

declare(strict_types=1);

namespace OCA\NcAclManager\Controller;

use OCA\NcAclManager\AppInfo\Application;
use OCA\NcAclManager\Service\AgentService;
use OCA\NcAclManager\Service\AuthorizationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IRequest;

class SettingsController extends Controller
{
    /** Ключи которые разрешено сохранять */
    private const ALLOWED_KEYS = [
        'agent_url', 'bearer_token', 'client_cert', 'cert_password',
        'admin_groups', 'owner_mode_enabled', 'timeout',
    ];

    public function __construct(
        string                              $appName,
        IRequest                            $request,
        private readonly IConfig            $config,
        private readonly AgentService       $agentService,
        private readonly AuthorizationService $authService,
        private readonly \OCP\IUserSession $userSession,
    ) {
        parent::__construct($appName, $request);
    }

    public function getSettings(): JSONResponse
    {
        $user = $this->userSession->getUser();
        return new JSONResponse([
            'agent_url'          => $this->config->getAppValue(Application::APP_ID, 'agent_url', ''),
            'bearer_token_set'   => $this->config->getAppValue(Application::APP_ID, 'bearer_token', '') !== '',
            'client_cert'        => $this->config->getAppValue(Application::APP_ID, 'client_cert', ''),
            'admin_groups'       => json_decode(
                $this->config->getAppValue(Application::APP_ID, 'admin_groups', '[]'), true),
            'owner_mode_enabled' => $this->config->getAppValue(
                Application::APP_ID, 'owner_mode_enabled', 'false') === 'true',
            'timeout'            => (int)$this->config->getAppValue(Application::APP_ID, 'timeout', '10'),
            // Роль текущего пользователя — используется в files.js
            'is_admin'           => $user !== null && $this->authService->isAclAdmin($user),
            'owner_mode'         => $this->config->getAppValue(
                Application::APP_ID, 'owner_mode_enabled', 'false') === 'true',
        ]);
    }

    public function saveSettings(array $settings): JSONResponse
    {
        foreach ($settings as $key => $value) {
            if (!in_array($key, self::ALLOWED_KEYS, true)) continue;

            // admin_groups хранится как JSON массив
            if ($key === 'admin_groups') {
                $value = json_encode(array_values((array)$value));
            }

            // Пустой bearer_token — не перезаписываем (оставляем старый)
            if ($key === 'bearer_token' && empty($value)) continue;

            $this->config->setAppValue(Application::APP_ID, $key, (string)$value);
        }

        return new JSONResponse(['success' => true]);
    }

    public function testAgent(): JSONResponse
    {
        try {
            $result = $this->agentService->healthCheck();
            $ok     = isset($result['status']) && $result['status'] === 'ok';
            return new JSONResponse([
                'success' => $ok,
                'result'  => $result,
            ]);
        } catch (\Throwable $e) {
            return new JSONResponse([
                'success' => false,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
