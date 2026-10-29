<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Proxy;
use App\Models\ProxyGroup;
use App\Models\ProxyHost;
use App\Models\Server;
use phpseclib4\Crypt\PublicKeyLoader;
use phpseclib4\Net\SSH2;
use RuntimeException;
use Throwable;

class ProxyGroupConfigurationService
{
    private const FRONTEND_TEMPLATE = '/etc/haproxy/frontends/frontend.cfg.template';
    private const BACKEND_TEMPLATE = '/etc/haproxy/backends/backend.cfg.template';
    private const FRONTEND_FILE = '/etc/haproxy/frontends/frontend.cfg';
    private const BACKEND_FILE = '/etc/haproxy/backends/backend.cfg';
    private const FRONTEND_BACKUP = '/etc/haproxy/frontends/frontend.cfg.backup';
    private const BACKEND_BACKUP = '/etc/haproxy/backends/backend.cfg.backup';

    /** @return array<string, mixed> */
    public function preview(ProxyGroup $group): array
    {
        $group->load(['proxies.server', 'proxies.xrayInbound', 'proxies.hosts.proxyServer']);
        $plans = $this->buildPlans($group);

        return [
            'group' => ['id' => (int) $group->id, 'name' => $group->name],
            'servers' => array_map(fn (array $plan): array => [
                'id' => (int) $plan['server']->id,
                'name' => $plan['server']->name,
                'host' => $plan['server']->ip,
                'frontend_config' => $plan['frontend'],
                'backend_config' => $plan['backend'],
            ], array_values($plans)),
            'flow' => $this->buildFlow($group),
            'instructions' => [
                'Configure the HAProxy systemd override with systemctl edit haproxy.',
                'Keep /etc/haproxy/frontends/frontend.cfg.template and /etc/haproxy/backends/backend.cfg.template with a {TEXT} marker. Generated files are frontend.cfg and backend.cfg in those directories.',
                <<<'SYSTEMD'
[Service]
ExecStart=
ExecStart=/usr/sbin/haproxy -Ws \
    -f /etc/haproxy/haproxy.cfg \
    -f /etc/haproxy/frontends/frontend.cfg \
    -f /etc/haproxy/backends/backend.cfg \
    -p /run/haproxy.pid
SYSTEMD,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function apply(ProxyGroup $group): array
    {
        $group->load(['proxies.server', 'proxies.xrayInbound', 'proxies.hosts.proxyServer']);
        $plans = array_values($this->buildPlans($group));
        $steps = [];
        $prepared = [];

        foreach ($plans as $plan) {
            $server = $plan['server'];
            $ssh = null;
            try {
                $ssh = $this->connect($server);
                $steps[] = $this->step($server, 'SSH connection', true, 'Connected.');
                $this->checkRemotePrerequisites($ssh, $server, $steps);
                $this->createBackups($ssh, $server, $steps);
                $prepared[] = ['server' => $server, 'plan' => $plan];
                $this->renderFiles($ssh, $server, $plan, $steps);
                $this->checkConfigurationAndRestart($ssh, $server, $steps);
            } catch (Throwable $exception) {
                $steps[] = $this->step($server, 'Configuration', false, $exception->getMessage());
                if ($ssh instanceof SSH2) {
                    $ssh->disconnect();
                }

                $rollback = $this->rollback($prepared);
                $steps = [...$steps, ...$rollback];

                return ['success' => false, 'steps' => $steps];
            }

            if ($ssh instanceof SSH2) {
                $ssh->disconnect();
            }
        }

        foreach ($prepared as $entry) {
            $server = $entry['server'];
            try {
                $ssh = $this->connect($server);
                $this->run($ssh, 'rm -f '.self::FRONTEND_BACKUP.' '.self::BACKEND_BACKUP);
                $steps[] = $this->step($server, 'Remove backups', true, 'HAProxy restarted successfully; backups removed.');
                $ssh->disconnect();
            } catch (Throwable $exception) {
                $steps[] = $this->step($server, 'Remove backups', false, $exception->getMessage());
            }
        }

        return ['success' => true, 'steps' => $steps];
    }

    /** @return array<int, array{server:Server,frontend:string,backend:string}> */
    private function buildPlans(ProxyGroup $group): array
    {
        $snippets = [];
        foreach ($group->proxies as $proxy) {
            $hosts = $proxy->hosts->where('is_active', true)->sortBy([['sort', 'asc'], ['id', 'asc']])->values();
            $lastHost = $hosts->last();
            if (! $lastHost instanceof ProxyHost || $hosts->count() < 2) {
                throw new RuntimeException("Proxy {$proxy->name} needs an active cascade host after its entrypoint.");
            }

            foreach ($hosts->slice(1)->values() as $offset => $host) {
                if ($host->proxy_server_id === null || ! $host->proxyServer instanceof Server) {
                    throw new RuntimeException("Select a HAProxy configuration server for active hop {$host->host} on proxy {$proxy->name}.");
                }

                $nextHost = $hosts->get($offset + 2);
                $target = $nextHost instanceof ProxyHost
                    ? $this->hostTarget($nextHost->host, (int) $nextHost->port, (bool) $nextHost->is_https)
                    : $this->finalTarget($proxy);
                $suffix = sprintf('p%d_h%d', $proxy->id, $host->sort);
                $frontendName = 'vless_'.$suffix;
                $backendName = 'vless_backend_'.$suffix;
                $port = $this->safePort((int) $host->port);
                $serverName = $this->safeName((string) $proxy->name.'_'.$host->sort);
                $server = $host->proxyServer;
                $snippets[$server->id]['server'] = $server;
                $snippets[$server->id]['frontend'][] = "frontend {$frontendName}\n    bind *:{$port}\n    mode tcp\n    default_backend {$backendName}";
                $ssl = $target['is_https'] ? ' ssl verify none' : '';
                $snippets[$server->id]['backend'][] = "backend {$backendName}\n    mode tcp\n    server {$serverName} {$target['host']}:{$target['port']} check{$ssl}";
            }
        }

        if ($snippets === []) {
            throw new RuntimeException('This group has no active cascade hosts with a proxy server selected.');
        }

        $plans = [];
        foreach ($snippets as $serverId => $snippet) {
            $plans[(int) $serverId] = [
                'server' => $snippet['server'],
                'frontend' => implode("\n\n", $snippet['frontend'])."\n",
                'backend' => implode("\n\n", $snippet['backend'])."\n",
            ];
        }

        return $plans;
    }

    /** @return array{host:string,port:int,is_https:bool} */
    private function finalTarget(Proxy $proxy): array
    {
        $server = $proxy->server;
        if (! $server instanceof Server) {
            throw new RuntimeException("Proxy {$proxy->name} has no final destination server.");
        }

        $port = data_get($proxy->xrayInbound?->params, 'port');
        if (! is_numeric($port) || (int) $port < 1 || (int) $port > 65535) {
            throw new RuntimeException("Proxy {$proxy->name} has no valid port on its selected inbound.");
        }

        return [
            'host' => $this->safeHost($server->getLinkAddressHost()),
            'port' => (int) $port,
            'is_https' => (bool) $proxy->is_https,
        ];
    }

    /** @return array{host:string,port:int,is_https:bool} */
    private function hostTarget(string $host, int $port, bool $isHttps): array
    {
        return ['host' => $this->safeHost($host), 'port' => $this->safePort($port), 'is_https' => $isHttps];
    }

    /** @return array<int, array<string, string>> */
    private function buildFlow(ProxyGroup $group): array
    {
        $flow = [];
        foreach ($group->proxies as $proxy) {
            $hosts = $proxy->hosts->where('is_active', true)->sortBy([['sort', 'asc'], ['id', 'asc']])->values();
            $entry = $hosts->first();
            if (! $entry instanceof ProxyHost) {
                continue;
            }

            $parts = [$this->formatTarget($entry->host, (int) $entry->port, (bool) $entry->is_https)];
            foreach ($hosts->slice(1) as $host) {
                $parts[] = $this->formatTarget($host->host, (int) $host->port, (bool) $host->is_https);
            }
            $final = $this->finalTarget($proxy);
            $parts[] = $this->formatTarget($final['host'], $final['port'], $final['is_https']);
            $flow[] = ['proxy' => $proxy->name, 'path' => implode(' → ', $parts)];
        }

        return $flow;
    }

    private function formatTarget(string $host, int $port, bool $isHttps): string
    {
        return sprintf('%s://%s:%d', $isHttps ? 'https' : 'http', $host, $port);
    }

    private function safeHost(string $host): string
    {
        $host = trim($host);
        if ($host === '' || preg_match('/^[a-zA-Z0-9.:[\]-]+$/', $host) !== 1) {
            throw new RuntimeException('A proxy host contains unsupported characters.');
        }

        return $host;
    }

    private function safePort(int $port): int
    {
        if ($port < 1 || $port > 65535) {
            throw new RuntimeException('A proxy host contains an invalid port.');
        }

        return $port;
    }

    private function safeName(string $name): string
    {
        $name = strtolower((string) str($name)->slug('_'));

        return $name !== '' ? $name : 'proxy';
    }

    private function connect(Server $server): SSH2
    {
        if (trim((string) $server->ssh_private_key) === '' && trim((string) $server->ssh_password) === '') {
            throw new RuntimeException("Server {$server->name} has no SSH credentials configured.");
        }

        $ssh = new SSH2((string) $server->ip, (int) ($server->ssh_port ?: 22), 20);
        $username = (string) ($server->ssh_username ?: 'root');
        $authenticated = false;
        $privateKey = trim((string) $server->ssh_private_key);
        if ($privateKey !== '') {
            try {
                $authenticated = $ssh->login($username, PublicKeyLoader::load($privateKey));
            } catch (Throwable $exception) {
                report($exception);
                $authenticated = false;
            }
        }
        if (! $authenticated && trim((string) $server->ssh_password) !== '') {
            $authenticated = $ssh->login($username, (string) $server->ssh_password);
        }
        if (! $authenticated) {
            throw new RuntimeException("SSH authentication failed for {$server->name}.");
        }

        return $ssh;
    }

    /** @param array<int, array<string, mixed>> $steps */
    private function checkRemotePrerequisites(SSH2 $ssh, Server $server, array &$steps): void
    {
        foreach ([self::FRONTEND_TEMPLATE, self::BACKEND_TEMPLATE] as $template) {
            $this->run($ssh, 'test -f '.escapeshellarg($template).' && grep -Fq '.escapeshellarg('{TEXT}').' '.escapeshellarg($template));
            $steps[] = $this->step($server, 'Check '.$template, true, 'Template exists and contains {TEXT}.');
        }

        $effectiveExecStart = 'systemctl show haproxy --property=ExecStart --value';
        $requiredArguments = [
            '/usr/sbin/haproxy',
            '-Ws',
            '/etc/haproxy/haproxy.cfg',
            self::FRONTEND_FILE,
            self::BACKEND_FILE,
            '/run/haproxy.pid',
        ];
        $overrideCheck = implode(' && ', array_map(
            fn (string $argument): string => $effectiveExecStart.' | grep -Fq '.escapeshellarg($argument),
            $requiredArguments,
        ));
        $this->run($ssh, $overrideCheck);
        $steps[] = $this->step($server, 'Check systemd override', true, 'Effective ExecStart includes both generated HAProxy files.');
    }

    /** @param array<string, mixed> $plan
     *  @param array<int, array<string, mixed>> $steps
     */
    private function createBackups(SSH2 $ssh, Server $server, array &$steps): void
    {
        $this->run($ssh, 'cp -a '.self::FRONTEND_FILE.' '.self::FRONTEND_BACKUP.' && cp -a '.self::BACKEND_FILE.' '.self::BACKEND_BACKUP);
        $steps[] = $this->step($server, 'Create backups', true, 'Current frontend.cfg and backend.cfg copied to .backup files.');
    }

    /** @param array<string, mixed> $plan
     *  @param array<int, array<string, mixed>> $steps
     */
    private function renderFiles(SSH2 $ssh, Server $server, array $plan, array &$steps): void
    {
        $frontendTemplate = $this->readRemote($ssh, self::FRONTEND_TEMPLATE);
        $backendTemplate = $this->readRemote($ssh, self::BACKEND_TEMPLATE);
        if (! str_contains($frontendTemplate, '{TEXT}') || ! str_contains($backendTemplate, '{TEXT}')) {
            throw new RuntimeException('A HAProxy template no longer contains its {TEXT} marker.');
        }
        $this->writeRemote($ssh, self::FRONTEND_FILE, str_replace('{TEXT}', rtrim((string) $plan['frontend']), $frontendTemplate));
        $this->writeRemote($ssh, self::BACKEND_FILE, str_replace('{TEXT}', rtrim((string) $plan['backend']), $backendTemplate));
        $steps[] = $this->step($server, 'Render HAProxy files', true, 'Template markers replaced with the previewed group configuration.');
    }

    /** @param array<int, array<string, mixed>> $steps */
    private function checkConfigurationAndRestart(SSH2 $ssh, Server $server, array &$steps): void
    {
        $this->run($ssh, '/usr/sbin/haproxy -c -f /etc/haproxy/haproxy.cfg -f '.self::FRONTEND_FILE.' -f '.self::BACKEND_FILE);
        $steps[] = $this->step($server, 'Validate HAProxy config', true, 'haproxy -c passed.');
        $this->run($ssh, 'systemctl restart haproxy');
        $steps[] = $this->step($server, 'Restart HAProxy', true, 'systemctl restart haproxy completed.');
        $this->run($ssh, "systemctl status haproxy --no-pager | grep -F 'Active: active (running)'");
        $steps[] = $this->step($server, 'Check HAProxy status', true, 'Active: active (running).');
    }

    /** @param array<int, array{server:Server,plan:array<string,mixed>}> $prepared
     *  @return array<int, array<string, mixed>>
     */
    private function rollback(array $prepared): array
    {
        $steps = [];
        foreach (array_reverse($prepared) as $entry) {
            $server = $entry['server'];
            try {
                $ssh = $this->connect($server);
                $this->run($ssh, 'test -f '.self::FRONTEND_BACKUP.' && test -f '.self::BACKEND_BACKUP.' && cp -a '.self::FRONTEND_BACKUP.' '.self::FRONTEND_FILE.' && cp -a '.self::BACKEND_BACKUP.' '.self::BACKEND_FILE.' && systemctl restart haproxy');
                $this->run($ssh, "systemctl status haproxy --no-pager | grep -F 'Active: active (running)'");
                $steps[] = $this->step($server, 'Rollback', true, 'Backups restored and HAProxy restarted.');
                $ssh->disconnect();
            } catch (Throwable $exception) {
                $steps[] = $this->step($server, 'Rollback', false, $exception->getMessage());
            }
        }

        return $steps;
    }

    private function readRemote(SSH2 $ssh, string $path): string
    {
        $output = $ssh->exec('cat '.escapeshellarg($path));
        if (! is_string($output) || $ssh->getExitStatus() !== 0) {
            throw new RuntimeException('Could not read '.$path.'.');
        }

        return $output;
    }

    private function writeRemote(SSH2 $ssh, string $path, string $content): void
    {
        $encoded = base64_encode($content);
        $this->run($ssh, "printf %s ".escapeshellarg($encoded).' | base64 -d > '.escapeshellarg($path));
    }

    private function run(SSH2 $ssh, string $command): string
    {
        $output = $ssh->exec($command);
        if (! is_string($output) || $ssh->getExitStatus() !== 0) {
            throw new RuntimeException(trim((string) $output) ?: 'Remote command failed: '.$command);
        }

        return $output;
    }

    /** @return array{server:string,step:string,success:bool,message:string} */
    private function step(Server $server, string $step, bool $success, string $message): array
    {
        return ['server' => $server->name, 'step' => $step, 'success' => $success, 'message' => $message];
    }
}
