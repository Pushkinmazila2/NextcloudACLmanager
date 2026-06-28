<?php

declare(strict_types=1);

namespace OCA\NcAclManager\Controller;

use OCA\NcAclManager\AppInfo\Application;
use OCA\NcAclManager\Service\AgentService;
use OCA\NcAclManager\Service\AuthorizationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\JSONResponse;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserManager;
use OCP\IUserSession;
use Psr\Log\LoggerInterface;

class SettingsController extends Controller
{
    private const ALLOWED_KEYS = [
        'agent_url', 'bearer_token', 'client_cert', 'cert_password',
        'admin_groups', 'nc_admin_users', 'owner_mode_enabled', 'timeout', 'verify_ssl',
    ];

    public function __construct(
        string                              $appName,
        IRequest                            $request,
        private readonly IConfig            $config,
        private readonly AgentService       $agentService,
        private readonly AuthorizationService $authService,
        private readonly IUserSession       $userSession,
        private readonly IUserManager       $userManager,
        private readonly IGroupManager      $groupManager,
        private readonly LoggerInterface    $logger,
    ) {
        parent::__construct($appName, $request);
    }

    #[NoAdminRequired]
    public function getSettings(): JSONResponse
    {
        $user      = $this->userSession->getUser();
        $agentMode = $this->config->getAppValue(Application::APP_ID, 'agent_mode', '');

        return new JSONResponse([
            'agent_url'          => $this->config->getAppValue(Application::APP_ID, 'agent_url',      ''),
            'bearer_token_set'   => $this->config->getAppValue(Application::APP_ID, 'bearer_token',   '') !== '',
            'client_cert'        => $this->config->getAppValue(Application::APP_ID, 'client_cert',    ''),
            'admin_groups'       => json_decode(
                $this->config->getAppValue(Application::APP_ID, 'admin_groups', '[]'), true) ?? [],
            // NC пользователи с правами ACL (только Test режим)
            'nc_admin_users'     => json_decode(
                $this->config->getAppValue(Application::APP_ID, 'nc_admin_users', '[]'), true) ?? [],
            'owner_mode_enabled' => $this->config->getAppValue(
                Application::APP_ID, 'owner_mode_enabled', 'false') === 'true',
            'timeout'            => (int)$this->config->getAppValue(Application::APP_ID, 'timeout', '10'),
            'is_admin'           => $user !== null && $this->authService->isAclAdmin($user),
            'owner_mode'         => $this->config->getAppValue(
                Application::APP_ID, 'owner_mode_enabled', 'false') === 'true',
            // Режим агента — кэшируем после успешного теста
            'agent_mode'         => $agentMode,
            'verify_ssl'         => $this->config->getAppValue(
                Application::APP_ID, 'verify_ssl', 'true') === 'true',
        ]);
    }

    public function saveSettings(): JSONResponse
    {
        $params = $this->request->getParams();
        $saved  = [];

        foreach (self::ALLOWED_KEYS as $key) {
            if (!array_key_exists($key, $params)) continue;

            $value = $params[$key];

            if (in_array($key, ['admin_groups', 'nc_admin_users'], true)) {
                $value = is_array($value) ? json_encode(array_values($value)) : '[]';
            }

            if (in_array($key, ['bearer_token', 'cert_password'], true)
                && empty($value)) continue;

            $this->config->setAppValue(Application::APP_ID, $key, (string)$value);
            $saved[] = $key;
        }

        $this->logger->info('NcAclManager: настройки сохранены: ' . implode(', ', $saved), [
            'app' => Application::APP_ID,
        ]);

        return new JSONResponse(['success' => true, 'saved_keys' => $saved]);
    }

    public function testAgent(): JSONResponse
    {
        $params = $this->request->getParams();

        $agentUrl    = $params['agent_url']     ?? $this->config->getAppValue(Application::APP_ID, 'agent_url',     '');
        $bearerToken = !empty($params['bearer_token'])
            ? $params['bearer_token']
            : $this->config->getAppValue(Application::APP_ID, 'bearer_token', '');
        $certPath    = $params['client_cert']   ?? $this->config->getAppValue(Application::APP_ID, 'client_cert',   '');
        $certPass    = !empty($params['cert_password'])
            ? $params['cert_password']
            : $this->config->getAppValue(Application::APP_ID, 'cert_password', '');
        $timeout     = (int)($params['timeout'] ?? $this->config->getAppValue(Application::APP_ID, 'timeout', '10'));

        $user = $this->userSession->getUser();
        if ($user !== null) {
            $this->agentService->setContext($user,
                $this->authService->getUserAdGroupsHeader($user));
        }

        $verifySsl = isset($params['verify_ssl'])
            ? filter_var($params['verify_ssl'], FILTER_VALIDATE_BOOLEAN)
            : ($this->config->getAppValue(Application::APP_ID, 'verify_ssl', 'true') === 'true');

        $this->agentService->initWithParams($agentUrl, $bearerToken, $certPath, $certPass, $timeout, $verifySsl);

        $diagnostics = $this->agentService->buildDiagnostics();
        $curlCommand = $this->agentService->buildCurlCommand('GET', '/api/acl/health');

        try {
            $result = $this->agentService->healthCheck();

            // Кэшируем режим агента (Test/Prod) — используем в настройках
            if (!empty($result['result']['mode'])) {
                $this->config->setAppValue(
                    Application::APP_ID, 'agent_mode', $result['result']['mode']);
            }

            return new JSONResponse([
                'success'      => $result['success'] ?? false,
                'result'       => $result['result']  ?? null,
                'error'        => $result['error']   ?? null,
                'diagnostics'  => $diagnostics,
                'curl_command' => $curlCommand,
                'agent_mode'   => $result['result']['mode'] ?? null,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('NcAclManager testAgent: ' . $e->getMessage(), [
                'app' => Application::APP_ID,
            ]);
            return new JSONResponse([
                'success'      => false,
                'error'        => $e->getMessage(),
                'diagnostics'  => $diagnostics,
                'curl_command' => $curlCommand,
                'agent_mode'   => null,
            ]);
        }
    }

    /**
     * Поиск NC пользователей для добавления в список ACL-admin (только Test режим)
     */
    #[NoAdminRequired]
    public function searchNcUsers(string $q = ''): JSONResponse
    {
        if (strlen($q) < 2) {
            return new JSONResponse(['users' => []]);
        }

        $users = $this->userManager->searchDisplayName($q, 20);
        $result = array_map(fn($u) => [
            'uid'         => $u->getUID(),
            'displayName' => $u->getDisplayName(),
            'email'       => $u->getEMailAddress() ?? '',
        ], $users);

        return new JSONResponse(['users' => array_values($result)]);
    }

    public function uploadCert(): JSONResponse
    {
        $file = $this->request->getUploadedFile('cert');

        if (empty($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return new JSONResponse(
                ['error' => $this->uploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE)],
                Http::STATUS_BAD_REQUEST
            );
        }

        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, ['pfx', 'p12'], true)) {
            return new JSONResponse(
                ['error' => 'Допустимы только файлы .pfx или .p12'],
                Http::STATUS_BAD_REQUEST
            );
        }

        $certDir  = \OC::$SERVERROOT . '/data/ncaclmanager/certs';
        $certPath = $certDir . '/client.pfx';

        if (!is_dir($certDir) && !mkdir($certDir, 0750, true)) {
            return new JSONResponse(
                ['error' => "Не удалось создать директорию: {$certDir}"],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        if (!move_uploaded_file($file['tmp_name'], $certPath)) {
            return new JSONResponse(
                ['error' => "Не удалось сохранить файл в: {$certPath}"],
                Http::STATUS_INTERNAL_SERVER_ERROR
            );
        }

        $this->config->setAppValue(Application::APP_ID, 'client_cert', $certPath);

        return new JSONResponse([
            'success'   => true,
            'cert_path' => $certPath,
            'size'      => $file['size'],
        ]);
    }

    private function uploadErrorMessage(int $code): string
    {
        return match($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Файл слишком большой',
            UPLOAD_ERR_NO_FILE    => 'Файл не выбран',
            UPLOAD_ERR_NO_TMP_DIR => 'Нет временной директории на сервере',
            UPLOAD_ERR_CANT_WRITE => 'Ошибка записи на диск',
            default               => "Ошибка загрузки (код {$code})",
        };
    }
}
