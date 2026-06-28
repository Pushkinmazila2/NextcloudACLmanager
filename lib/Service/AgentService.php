<?php

declare(strict_types=1);

namespace OCA\NcAclManager\Service;

use OCA\NcAclManager\AppInfo\Application;
use OCP\Http\Client\IClientService;
use OCP\IConfig;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;

class AgentService
{
    private ?string $currentUserUid    = null;
    private ?string $currentUserGroups = null;

    private string $agentUrl;
    private string $bearerToken;
    private string $certPath;
    private string $certPassword;
    private int    $timeout;

    public function __construct(
        private readonly IClientService $httpClientService,
        private readonly IConfig        $config,
        private readonly LoggerInterface $logger,
    ) {
        $this->agentUrl    = $config->getAppValue(Application::APP_ID, 'agent_url',      '');
        $this->bearerToken = $config->getAppValue(Application::APP_ID, 'bearer_token',   '');
        $this->certPath    = $config->getAppValue(Application::APP_ID, 'client_cert',    '');
        $this->certPassword = $config->getAppValue(Application::APP_ID, 'cert_password', '');
        $this->timeout     = (int)$config->getAppValue(Application::APP_ID, 'timeout',  '10');
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

    // ── Пользователи ─────────────────────────────────────────────────

    public function searchUsers(string $query, int $max = 20): array
    {
        return $this->get('/api/users/search', ['q' => $query, 'max' => $max]);
    }

    public function getManagerChain(string $sam): array
    {
        return $this->get("/api/users/{$sam}/manager-chain");
    }

    // ── Health check ──────────────────────────────────────────────────

    public function healthCheck(): array
    {
        try {
            $result = $this->get('/api/acl/health');
            return [
                'status' => 'ok',
                'result' => $result,
            ];
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function maskSecret(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $len = strlen($value);

        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return substr($value, 0, 2)
            . str_repeat('*', $len - 4)
            . substr($value, -2);
    }

    private function getCertFingerprint(): ?string
    {
        if (!$this->certPath || !file_exists($this->certPath)) {
            return null;
        }

        $pfx = file_get_contents($this->certPath);

        if (!openssl_pkcs12_read($pfx, $certs, $this->certPassword)) {
            return null;
        }

        $parsed = openssl_x509_parse($certs['cert']);

        return $parsed['serialNumberHex'] ?? null;
    }

    private function buildDebugSnapshot(string $url, array $opts): array
    {
        return [
            'url' => $url,

            'bearer' => $this->maskSecret($this->bearerToken),
            'cert_password' => $this->maskSecret($this->certPassword),

            'cert_path' => basename($this->certPath),
            'cert_fingerprint' => $this->getCertFingerprint(),

            'timeout' => $this->timeout,

            'headers' => [
                'Authorization' => 'Bearer ***',
                'Content-Type'  => $opts['headers']['Content-Type'] ?? null,
                'Accept'        => $opts['headers']['Accept'] ?? null,
                'X-Request-Id'  => $opts['headers']['X-Request-Id'] ?? null,
                'X-Nc-User'     => $opts['headers']['X-Nc-User'] ?? null,
                'X-Nc-Groups'   => $opts['headers']['X-Nc-User-Groups'] ?? null,
            ],
        ];
    }

    // ── HTTP методы ───────────────────────────────────────────────────

    private function get(string $endpoint, array $params = []): array
    {
        $url = $this->agentUrl . $endpoint;

        $opts = $this->options();

        $isDebug = $endpoint === '/api/acl/health';

        if ($isDebug) {
            $this->logger->info('AGENT DEBUG SNAPSHOT', [
                'snapshot' => $this->buildDebugSnapshot($url, $opts),
            ]);
        }

        $response = $this->client()->get($url, $opts);

        return $this->parse($response);
    }

    private function post(string $endpoint, array $body = [], string $initiatedBy = ''): array
    {
        // Добавляем initiatedByUser если передан
        if ($initiatedBy !== '') {
            $body['initiatedByUser'] = $initiatedBy;
        }

        $response = $this->client()->post(
            $this->agentUrl . $endpoint,
            $this->options(['body' => json_encode($body),
                            'headers' => $this->headers()])
        );
        return $this->parse($response);
    }

    private function delete(string $endpoint, array $body = [], string $initiatedBy = ''): array
    {
        if ($initiatedBy !== '') {
            $body['initiatedByUser'] = $initiatedBy;
        }

        $response = $this->client()->delete(
            $this->agentUrl . $endpoint,
            $this->options(['body'    => json_encode($body),
                            'headers' => $this->headers()])
        );
        return $this->parse($response);
    }

    private function client()
    {
        return $this->httpClientService->newClient();
    }

    private function options(array $extra = []): array
    {
        $opts = [
            'timeout'     => $this->timeout,
            'verify'      => true,
            'cert'        => [$this->certPath, $this->certPassword],
            'headers'     => $this->headers(),
        ];

        return array_merge_recursive($opts, $extra);
    }

    private function headers(): array
    {
        $requestId = $this->generateRequestId();
        return [
            'Authorization' => 'Bearer ' . $this->bearerToken,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
            'X-Request-Id'  => $requestId,
            // NC пользователь и его группы — агент использует для авторизации
            'X-Nc-User'        => $this->getCurrentUser(),
            'X-Nc-User-Groups' => $this->getCurrentUserGroups(),
        ];
    }

    private function parse($response): array
    {
        $body = $response->getBody();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Агент вернул невалидный JSON: ' . $body);
        }

        return $data ?? [];
    }

    /** YYYYMMDD-HHmmss-{8 hex} */
    private function generateRequestId(): string
    {
        return date('Ymd-His') . '-' . bin2hex(random_bytes(4));
    }

    /**
     * Устанавливает контекст текущего пользователя.
     * Вызывается из контроллеров перед каждым запросом к агенту.
     */
    public function setContext(\OCP\IUser $user, string $groupsHeader): void
    {
        $this->currentUserUid    = $user->getUID();
        $this->currentUserGroups = $groupsHeader;
    }

    private function getCurrentUser(): string
    {
        return $this->currentUserUid ?? '';
    }

    private function getCurrentUserGroups(): string
    {
        return $this->currentUserGroups ?? '';
    }
}
