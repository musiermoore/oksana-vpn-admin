<?php

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class TelegramExceptionReporter
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    public function report(Throwable $throwable): void
    {
        if (! $this->shouldReport($throwable)) {
            return;
        }

        $botToken = (string) config('services.telegram.bot_token');
        $chatId = (string) config('services.telegram.dev_chat_id');

        if ($botToken === '' || $chatId === '') {
            return;
        }

        $this->http
            ->asForm()
            ->timeout(5)
            ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
                'text' => $this->buildMessage($throwable),
            ])
            ->throw();
    }

    private function shouldReport(Throwable $throwable): bool
    {
        if (app()->environment('local', 'testing')) {
            return false;
        }

        return ! $throwable instanceof ValidationException
            && ! $throwable instanceof AuthenticationException
            && ! $throwable instanceof AuthorizationException
            && ! $throwable instanceof NotFoundHttpException
            && ! $throwable instanceof TokenMismatchException;
    }

    private function buildMessage(Throwable $throwable): string
    {
        $request = request();
        $trace = collect($throwable->getTrace())
            ->take(5)
            ->map(fn (array $frame) => sprintf(
                '%s:%s %s%s%s()',
                Arr::get($frame, 'file', 'n/a'),
                Arr::get($frame, 'line', '?'),
                Arr::get($frame, 'class', ''),
                Arr::get($frame, 'type', ''),
                Arr::get($frame, 'function', 'closure'),
            ))
            ->implode("\n");

        $context = [
            '<b>App:</b> '.e((string) config('app.name')),
            '<b>Env:</b> '.e((string) config('app.env')),
            '<b>Exception:</b> '.e($throwable::class),
            '<b>Message:</b> '.e($throwable->getMessage() ?: '(empty)'),
            '<b>Location:</b> '.e($throwable->getFile().':'.$throwable->getLine()),
        ];

        if ($request) {
            $context[] = '<b>URL:</b> '.e($request->fullUrl());
            $context[] = '<b>Method:</b> '.e($request->method());
            $context[] = '<b>IP:</b> '.e((string) $request->ip());
            $context[] = '<b>User:</b> '.e((string) optional($request->user())->id ?: 'guest');
        } elseif (app()->runningInConsole()) {
            $context[] = '<b>Command:</b> '.e(implode(' ', $_SERVER['argv'] ?? []));
        }

        $message = implode("\n", $context)."\n<b>Trace:</b>\n<pre>".e($trace).'</pre>';

        return Str::limit($message, 3900, "\n...");
    }
}
