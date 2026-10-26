<?php

declare(strict_types=1);

namespace App\Services\ServerConsole;

use App\Jobs\RunServerConsoleSessionJob;
use App\Models\Server;
use App\Models\ServerCommandTemplate;
use App\Models\ServerConsoleSession;
use App\Models\User;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use RuntimeException;

class ServerConsoleService
{
    public function start(Server $server, User $user): ServerConsoleSession
    {
        if (blank($server->ssh_private_key) && blank($server->ssh_password)) {
            throw new RuntimeException('Add an SSH private key or password in the server settings first.');
        }

        $session = ServerConsoleSession::query()->create([
            'uuid' => (string) Str::uuid(),
            'server_id' => $server->id,
            'user_id' => $user->id,
            'status' => 'queued',
            'expires_at' => now()->addMinutes(15),
        ]);

        RunServerConsoleSessionJob::dispatch($session->id);

        return $session;
    }

    public function command(ServerConsoleSession $session, string $command, int $timeout, array $answers = [], ?string $script = null): void
    {
        if ($session->status !== 'active' || $session->busy) {
            throw new RuntimeException('Wait for the current command to finish.');
        }

        if (blank($command)) {
            throw new RuntimeException('Enter a command to run.');
        }

        $key = $this->key($session, 'pending');
        if (! Redis::set($key, '1', 'EX', 3600, 'NX')) {
            throw new RuntimeException('A command is already waiting to run.');
        }

        Redis::rpush($this->key($session, 'commands'), json_encode([
            'command' => $command,
            'timeout' => min(max($timeout, 1), 3600),
            'answers' => $answers,
            'script' => $script,
        ], JSON_THROW_ON_ERROR));
    }

    public function runTemplate(ServerConsoleSession $session, ServerCommandTemplate $template): void
    {
        $content = (string) $template->content;
        $answers = [];

        if ($template->format === ServerCommandTemplate::FORMAT_BLOCKS) {
            $decoded = json_decode($content, true);
            $blocks = is_array($decoded) ? ($decoded['blocks'] ?? []) : [];
            $commands = [];
            foreach ($blocks as $block) {
                if (! is_array($block)) {
                    continue;
                }
                $commands[] = (string) ($block['command'] ?? '');
                $answers = [...$answers, ...array_values(array_filter($block['answers'] ?? [], 'is_array'))];
            }
            $content = implode("\n", array_filter($commands, fn (string $command): bool => trim($command) !== ''));
        } else {
            $content = $this->extractScriptAnswers($content, $answers);
        }

        $this->command($session, '__RUN_TEMPLATE_SCRIPT__', (int) $template->timeout_seconds, $answers, $content);
    }

    public function input(ServerConsoleSession $session, string $input): void
    {
        if ($session->status !== 'active') {
            throw new RuntimeException('The SSH session is not active.');
        }

        $key = $this->key($session, 'input');
        if ((int) Redis::llen($key) >= 100) {
            throw new RuntimeException('The terminal input queue is full. Wait for the server to read it.');
        }

        Redis::rpush($key, $input);
    }

    public function resize(ServerConsoleSession $session, int $columns, int $rows): void
    {
        if ($session->status !== 'active') {
            throw new RuntimeException('The SSH session is not active.');
        }

        Redis::set($this->key($session, 'resize'), json_encode([
            'columns' => $columns,
            'rows' => $rows,
        ], JSON_THROW_ON_ERROR), 'EX', 900);
    }

    public function close(ServerConsoleSession $session): void
    {
        $session->update(['status' => 'closing']);
        Redis::rpush($this->key($session, 'input'), "\x03");
        Redis::del($this->key($session, 'pending'));
    }

    /** @param array<int, array{scan:string,answer:string,send:string}> $answers */
    private function extractScriptAnswers(string $script, array &$answers): string
    {
        $lines = preg_split('/\R/', $script) ?: [];
        $scriptLines = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*#\s*\[\{\s*ANSWER:\s*"([^"]*)",\s*SCAN:\s*"([^"]*)"\s*\}\]\s*$/', $line, $matches) === 1) {
                $answers[] = ['answer' => $matches[1], 'scan' => $matches[2], 'send' => 'enter'];
                continue;
            }
            $scriptLines[] = $line;
        }

        return implode("\n", $scriptLines);
    }

    private function key(ServerConsoleSession $session, string $suffix): string
    {
        return 'server-console:'.$session->uuid.':'.$suffix;
    }
}
