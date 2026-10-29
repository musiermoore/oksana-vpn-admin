<?php

declare(strict_types=1);

namespace App\Services\ServerConsole;

use App\Events\ServerConsoleOutput;
use App\Models\Server;
use App\Models\ServerConsoleSession;
use Illuminate\Support\Facades\Redis;
use phpseclib4\Crypt\PublicKeyLoader;
use phpseclib4\Net\SFTP;
use phpseclib4\Net\SSH2;
use RuntimeException;
use Throwable;

class ServerConsoleSessionRunner
{
    private const SESSION_SECONDS = 900;

    public function run(ServerConsoleSession $session): void
    {
        if ($session->status === 'closing') {
            $session->update(['status' => 'closed']);
            broadcast(new ServerConsoleOutput($session->uuid, status: 'closed'));

            return;
        }

        $ssh = $this->connect($session->server);
        $ssh->setTimeout(1);
        $ssh->setKeepAlive(15);
        $initialOutput = $ssh->read();
        $session->update(['status' => 'active']);
        broadcast(new ServerConsoleOutput($session->uuid, status: 'active'));
        if (is_string($initialOutput)) {
            $this->broadcastOutput($session, $initialOutput);
        }
        $promptMarker = '__CONSOLE_PROMPT_'.$session->uuid.'__';
        $ssh->write('export PS1='.escapeshellarg($promptMarker.' ')."\n");

        try {
            $this->serveCommands($ssh, $session, $promptMarker);
        } finally {
            $ssh->disconnect();
            $session->refresh();
            $session->update(['status' => $session->status === 'closing' ? 'closed' : 'expired', 'busy' => false]);
            broadcast(new ServerConsoleOutput($session->uuid, status: $session->status));
            Redis::del($this->queueKey($session, 'commands'), $this->queueKey($session, 'input'));
        }
    }

    private function connect(Server $server): SSH2
    {
        $ssh = new SSH2((string) $server->ip, (int) ($server->ssh_port ?: 22), 15);
        $this->authenticate($ssh, $server);

        return $ssh;
    }

    private function authenticate(SSH2 $ssh, Server $server): void
    {
        $username = (string) ($server->ssh_username ?: 'root');
        $privateKey = trim((string) $server->ssh_private_key);
        $password = (string) $server->ssh_password;
        $authenticated = false;

        if ($privateKey !== '') {
            try {
                $authenticated = $ssh->login($username, PublicKeyLoader::load($privateKey));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        if (! $authenticated && $password !== '') {
            $authenticated = $ssh->login($username, $password);
        }

        if (! $authenticated) {
            throw new RuntimeException('Не удалось войти по SSH. Проверьте учётные данные сервера.');
        }
    }

    private function serveCommands(SSH2 $ssh, ServerConsoleSession $session, string $promptMarker): void
    {
        $promptBuffer = '';
        while ($session->expires_at->isFuture()) {
            $session->refresh();
            if (in_array($session->status, ['closing', 'closed'], true)) {
                break;
            }

            $this->applyResize($ssh, $session);
            $wasBusy = $session->busy;
            $this->forwardInput($ssh, $session);
            if (! $wasBusy && $session->busy) {
                $promptBuffer = '';
            }
            $payload = Redis::lpop($this->queueKey($session, 'commands'));
            if (is_string($payload) && $payload !== '') {
                $this->runPayload($ssh, $session, json_decode($payload, true) ?: []);
            }

            $chunk = $this->readChunk($ssh);
            if ($chunk !== '') {
                $this->broadcastOutput($session, $chunk);
                $promptBuffer = substr($promptBuffer.$chunk, -512);
                if ($session->busy && str_contains($promptBuffer, $promptMarker)) {
                    $this->setBusy($session, false);
                }
            }

            usleep(100_000);
        }
    }

    private function runPayload(SSH2 $ssh, ServerConsoleSession $session, array $payload): void
    {
        $timeout = min(max((int) ($payload['timeout'] ?? 300), 1), self::SESSION_SECONDS);
        $answers = array_values(array_filter($payload['answers'] ?? [], 'is_array'));
        $marker = '__CONSOLE_DONE_'.$session->uuid.'__';
        $command = (string) ($payload['command'] ?? '');
        if ($command === '') {
            return;
        }

        $this->setBusy($session, true);
        $command = $this->prepareCommand($session, $command, $payload['script'] ?? null);
        $ssh->write($this->completionCommand($command, $marker));
        $startedAt = time();
        $deadline = min($startedAt + $timeout, $session->expires_at->timestamp);
        if ($this->waitForCompletion($ssh, $session, $deadline, $marker, $answers)) {
            return;
        }

        if ($this->sessionIsClosing($session)) {
            $this->setBusy($session, false);

            return;
        }

        $ssh->write("\x03");
        $this->setBusy($session, false, 'Command timed out and was interrupted.');
    }

    private function prepareCommand(ServerConsoleSession $session, string $command, mixed $script): string
    {
        if (! is_string($script)) {
            return 'bash -lc '.escapeshellarg($command);
        }

        $remotePath = '/tmp/console-'.$session->uuid.'.sh';
        $this->uploadScript($session->server, $remotePath, $script);

        return 'bash '.escapeshellarg($remotePath).'; __console_status=$?; rm -f '.escapeshellarg($remotePath);
    }

    private function completionCommand(string $command, string $marker): string
    {
        return $command."; __console_status=$?; printf '\\n{$marker}:%s\\n' \"\$__console_status\"\n";
    }

    /** @param array<int, array<string, mixed>> $answers */
    private function waitForCompletion(SSH2 $ssh, ServerConsoleSession $session, int $deadline, string $marker, array $answers): bool
    {
        $buffer = '';
        $scanBuffer = '';
        while (time() < $deadline) {
            if ($this->sessionIsClosing($session)) {
                $ssh->write("\x03");

                return false;
            }
            $this->forwardInput($ssh, $session);
            $chunk = $this->readChunk($ssh);
            if ($chunk === '') {
                usleep(100_000);
                continue;
            }
            if ($this->handleCommandOutput($ssh, $session, $chunk, $marker, $answers, $buffer, $scanBuffer)) {
                return true;
            }
        }

        return false;
    }

    private function sessionIsClosing(ServerConsoleSession $session): bool
    {
        $session->refresh();

        return in_array($session->status, ['closing', 'closed'], true);
    }

    /** @param array<int, array<string, mixed>> $answers */
    private function handleCommandOutput(SSH2 $ssh, ServerConsoleSession $session, string $chunk, string $marker, array &$answers, string &$buffer, string &$scanBuffer): bool
    {
        $buffer = substr($buffer.$chunk, -128);
        $this->sendPromptAnswers($ssh, $chunk, $answers, $scanBuffer);
        if (strpos($buffer, $marker.':') === false) {
            $this->broadcastOutput($session, $chunk);

            return false;
        }

        $markerPosition = strpos($chunk, $marker.':');
        if ($markerPosition !== false && $markerPosition > 0) {
            $this->broadcastOutput($session, substr($chunk, 0, $markerPosition));
        }
        $this->setBusy($session, false);

        return true;
    }

    private function setBusy(ServerConsoleSession $session, bool $busy, ?string $error = null): void
    {
        $session->update(['busy' => $busy]);
        if (! $busy) {
            Redis::del($this->queueKey($session, 'pending'));
        }
        broadcast(new ServerConsoleOutput($session->uuid, busy: $busy, error: $error));
    }

    private function forwardInput(SSH2 $ssh, ServerConsoleSession $session): void
    {
        for ($count = 0; $count < 20; $count++) {
            $input = Redis::lpop($this->queueKey($session, 'input'));
            if (! is_string($input)) {
                return;
            }

            if (! $session->busy && (str_contains($input, "\n") || str_contains($input, "\r"))) {
                $session->refresh();
                if (! $session->busy) {
                    $this->setBusy($session, true);
                }
            }
            $ssh->write($input);
        }
    }

    private function applyResize(SSH2 $ssh, ServerConsoleSession $session): void
    {
        $key = $this->queueKey($session, 'resize');
        $payload = Redis::get($key);
        if (! is_string($payload)) {
            return;
        }

        $size = json_decode($payload, true);
        if (is_array($size)) {
            $ssh->setWindowSize((int) ($size['columns'] ?? 80), (int) ($size['rows'] ?? 24));
        }
        Redis::del($key);
    }

    private function sendPromptAnswers(SSH2 $ssh, string $chunk, array &$answers, string &$scanBuffer): void
    {
        $scanBuffer = substr($scanBuffer.$chunk, -2048);
        foreach ($answers as $index => $answer) {
            $scan = (string) ($answer['scan'] ?? '');
            $position = $scan === '' ? false : strpos($scanBuffer, $scan);
            if ($position === false) {
                continue;
            }

            $text = (string) ($answer['answer'] ?? '');
            $ssh->write($text.((($answer['send'] ?? 'enter') === 'enter') ? "\n" : ''));
            $scanBuffer = substr($scanBuffer, $position + strlen($scan));
            unset($answers[$index]);
        }
    }

    private function readChunk(SSH2 $ssh): string
    {
        $chunk = $ssh->read('', SSH2::READ_NEXT);
        if (is_string($chunk)) {
            return $chunk;
        }

        if (! $ssh->isShellOpen() || ! $ssh->isConnected()) {
            throw new RuntimeException('Удалённый сеанс SSH был закрыт.');
        }

        return '';
    }

    private function uploadScript(Server $server, string $remotePath, string $script): void
    {
        $sftp = new SFTP((string) $server->ip, (int) ($server->ssh_port ?: 22), 15);
        $this->authenticate($sftp, $server);
        $sftp->put($remotePath, $script);
        $sftp->chmod($remotePath, 0700);
        $sftp->disconnect();
    }

    private function broadcastOutput(ServerConsoleSession $session, string $output): void
    {
        $output = mb_convert_encoding($output, 'UTF-8', 'UTF-8');
        while ($output !== '') {
            $chunk = mb_strcut($output, 0, 6000, 'UTF-8');
            if ($chunk === '') {
                return;
            }
            broadcast(new ServerConsoleOutput($session->uuid, output: $chunk));
            $output = substr($output, strlen($chunk));
        }
    }

    private function queueKey(ServerConsoleSession $session, string $queue): string
    {
        return 'server-console:'.$session->uuid.':'.$queue;
    }
}
