<?php

declare(strict_types=1);

namespace OCA\NcAclManager\Service;

use OCA\NcAclManager\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;

class AgentService
{
    private string  $agentUrl;
    private string  $bearerToken;
    private string  $certPath;
    private string  $certPassword;
    private int     $timeout;

    private ?string $currentUserUid    = null;
    private ?string $currentUserGroups = null;

    public function __construct(
        private readonly IClientService  $httpClientService,
        private readonly IConfig         $config,
        private readonly LoggerInterface $logger,
    ) {
        $this->agentUrl     = $config->getAppValue(Application::APP_ID, 'agent_url',      '');
        $this->bearerToken  = $config->getAppValue(Application::APP_ID, 'bearer_token',   '');
        $this->certPath     = $config->getAppValue(Application::APP_ID, 'client_cert',    '');
        $this->certPassword = $config->getAppValue(Application::APP_ID, 'cert_password',  '');
        $this->timeout      = (int)$config->getAppValue(Application::APP_ID, 'timeout',   '10');
    }

    public function setContext(\OCP\IUser $user, string $groupsHeader): void
    {
        $this->currentUserUid    = $user->getUID();
        $this->currentUserGroups = $groupsHeader;
    }

    // ── ACL ───────────────────────────────────────────────────────────

    public function getAcl(string $path): array
    {
        return $this->get('/api/acl', ['path' => $path]);
    }

    public function setAcl(string $path, string $groupIdentity, string $permission,
                           string $action, string $initiatedBy, ?string $comment = null): array
    {
        return $this->post('/api/acl', [
            'path'            => $path,
            'groupIdentity'   => $groupIdentity,
            'permission'      => $permission,
            'action'          => $action,
            'initiatedByUser' => $initiatedBy,
            'comment'         => $comment,
        ]);
    }

    public function removeAcl(string $path, string $groupIdentity,
                              string $initiatedBy, ?string $comment = null): array
    {
        return $this->delete('/api/acl', [
            'path'            => $path,
            'groupIdentity'   => $groupIdentity,
            'initiatedByUser' => $initiatedBy,
            'comment'         => $comment,
        ]);
    }

    // ── Группы ────────────────────────────────────────────────────────

    public function getFolderGroups(string $folderPath): array
    {
        return $this->get('/api/groups', ['path' => $folderPath]);
    }

    public function createFolderGroups(string $folderPath, string $initiatedBy,
                                       array $suffixes = ['RO', 'RX', 'RW']): array
    {
        return $this->post('/api/groups', [
            'folderPath'      => $folderPath,
            'initiatedByUser' => $initiatedBy,
            'suffixes'        => $suffixes,
        ]);
    }

    public function deleteFolderGroups(string $folderPath, string $initiatedBy): array
    {
        return $this->delete('/api/groups', [
            'folderPath'      => $folderPath,
            'initiatedByUser' => $initiatedBy,
        ]);
    }

    // ── Состав группы ─────────────────────────────────────────────────

    public function getGroupMembers(string $groupName): array
    {
        return $this->get("/api/groups/{$groupName}/members");
    }

    public function addGroupMember(string $groupName, string $userSam,
                                   string $initiatedBy, ?string $comment = null): array
    {
        return $this->post("/api/groups/{$groupName}/members", [
            'userSamName'     => $userSam,
            'comment'         => $comment,
        ], $initiatedBy);
    }

    public function removeGroupMember(string $groupName, string $userSam,
                                      string $initiatedBy, ?string $comment = null): array
    {
        return $this->delete("/api/groups/{$groupName}/members/{$userSam}", [
            'comment' => $comment,
        ], $initiatedBy);
    }

    // ── Пользователи ──────────────────────────────────────────────────

    public function searchUsers(string $query, int $max = 20): array
    {
        return $this->get('/api/users/search', ['q' => $query, 'max' => $max]);
    }

    public function getManagerChain(string $sam): array
    {
        return $this->get("/api/users/{$sam}/manager-chain");
    }

    // ── Health check с диагностикой ───────────────────────────────────

    public function healthCheck(): array
    {
        $diagnostics = $this->buildDiagnostics();

        // Логируем в NC (видно в Administration → Logging)
        $this->logger->info('NcAclManager healthCheck запущен', [
            'app'  => Application::APP_ID,
            'diag' => $diagnostics,
        ]);

        // Пробуем реальный запрос
        try {
            $result = $this->get('/api/acl/health');

            $this->logger->info('NcAclManager healthCheck успешен', [
                'app'    => Application::APP_ID,
                'result' => $result,
            ]);

            return [
                'success'     => true,
                'result'      => $result,
                'diagnostics' => $diagnostics,
                'curl'        => $this->buildCurlCommand('GET', '/api/acl/health'),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('NcAclManager healthCheck ошибка: ' . $e->getMessage(), [
                'app'  => Application::APP_ID,
                'diag' => $diagnostics,
            ]);

            return [
                'success'     => false,
                'error'       => $e->getMessage(),
                'diagnostics' => $diagnostics,
                'curl'        => $this->buildCurlCommand('GET', '/api/acl/health'),
            ];
        }
    }

    // ── Диагностика ───────────────────────────────────────────────────

    /**
     * Возвращает диагностические данные для отображения в UI.
     * Секреты маскируются: показываем первые 2 и последние 2 символа.
     */
    public function buildDiagnostics(): array
    {
        $certExists = !empty($this->certPath) && file_exists($this->certPath);

        return [
            'agent_url'      => $this->agentUrl ?: '(не задан)',
            'bearer_token'   => $this->maskSecret($this->bearerToken),
            'cert_path'      => $this->certPath ?: '(не задан)',
            'cert_exists'    => $certExists,
            'cert_readable'  => $certExists && is_readable($this->certPath),
            'cert_password'  => $this->maskSecret($this->certPassword),
            'timeout'        => $this->timeout,
            'current_user'   => $this->currentUserUid ?? '(не задан)',
            'user_groups'    => $this->currentUserGroups ?? '(не задан)',
        ];
    }

    /**
     * Генерирует curl команду для ручного тестирования вне NC.
     * Токен маскируется — пользователь подставит реальный.
     */
    public function buildCurlCommand(string $method, string $endpoint, array $body = []): string
    {
        $url = rtrim($this->agentUrl, '/') . $endpoint;

        $lines = [
            "curl -v \\",
            "  --cert '{$this->certPath}' \\",
            "  --cert-type P12 \\",
        ];

        if (!empty($this->certPassword)) {
            $lines[] = "  --pass '{$this->maskSecret($this->certPassword)}' \\";
        }

        $lines[] = "  -H 'Authorization: Bearer {$this->maskSecret($this->bearerToken)}' \\";
        $lines[] = "  -H 'Content-Type: application/json' \\";
        $lines[] = "  -H 'Accept: application/json' \\";
        $lines[] = "  -X {$method} \\";

        if (!empty($body)) {
            $json     = json_encode($body, JSON_UNESCAPED_UNICODE);
            $lines[]  = "  -d '{$json}' \\";
        }

        $lines[] = "  '{$url}'";

        return implode("\n", $lines);
    }

    // ── HTTP методы ───────────────────────────────────────────────────

    private function get(string $endpoint, array $params = []): array
    {
        $url = rtrim($this->agentUrl, '/') . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $response = $this->httpClientService->newClient()->get($url, $this->options());
        return $this->parse($response);
    }

    private function post(string $endpoint, array $body = [], string $initiatedBy = ''): array
    {
        if ($initiatedBy !== '') {
            $body['initiatedByUser'] = $initiatedBy;
        }

        $opts = array_merge($this->options(), [
            'body'    => json_encode($body),
            'headers' => $this->headers(),
        ]);

        $response = $this->httpClientService->newClient()
            ->post(rtrim($this->agentUrl, '/') . $endpoint, $opts);

        return $this->parse($response);
    }

    private function delete(string $endpoint, array $body = [], string $initiatedBy = ''): array
    {
        if ($initiatedBy !== '') {
            $body['initiatedByUser'] = $initiatedBy;
        }

        $opts = array_merge($this->options(), [
            'body'    => json_encode($body),
            'headers' => $this->headers(),
        ]);

        $response = $this->httpClientService->newClient()
            ->delete(rtrim($this->agentUrl, '/') . $endpoint, $opts);

        return $this->parse($response);
    }

    private function options(array $extra = []): array
    {
        $opts = [
            'timeout' => $this->timeout,
            'verify'  => true,
            'headers' => $this->headers(),
        ];

        // Клиентский сертификат (mTLS)
        if (!empty($this->certPath)) {
            $opts['cert'] = [$this->certPath, $this->certPassword];
        }

        return array_merge($opts, $extra);
    }

    private function headers(): array
    {
        $requestId = date('Ymd-His') . '-' . bin2hex(random_bytes(4));
        return [
            'Authorization'  => 'Bearer ' . $this->bearerToken,
            'Content-Type'   => 'application/json',
            'Accept'         => 'application/json',
            'X-Request-Id'   => $requestId,
            'X-Nc-User'      => $this->currentUserUid    ?? '',
            'X-Nc-User-Groups' => $this->currentUserGroups ?? '',
        ];
    }

    private function parse($response): array
    {
        $body = $response->getBody();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Невалидный JSON от агента: ' . substr($body, 0, 200));
        }

        return $data ?? [];
    }

    // ── Утилиты ───────────────────────────────────────────────────────

    /**
     * Маскирует секрет: показывает первые 2 и последние 2 символа.
     * Пустой → "(не задан)", короткий → "***".
     */
    private function maskSecret(string $secret): string
    {
        if (empty($secret)) return '(не задан)';
        $len = mb_strlen($secret);
        if ($len <= 6) return str_repeat('*', $len);
        return mb_substr($secret, 0, 2) . str_repeat('*', $len - 4) . mb_substr($secret, -2);
    }
}
