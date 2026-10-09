<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\SubscriptionDebug\StoreSubscriptionDebugData;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SubscriptionDebugService
{
    private const DIRECTORY = 'subscription-debug';

    private const BODY_PATH = self::DIRECTORY.'/body';

    private const MARKER_KEY = 'subscription-debug:uuid';

    private const TTL_SECONDS = 3600;

    public function store(StoreSubscriptionDebugData $data): string
    {
        $uuid = (string) Str::uuid();
        $disk = $this->disk();

        if (! $disk->put(self::BODY_PATH, $data->body)) {
            throw new \RuntimeException('Unable to store subscription debug body.');
        }

        Redis::setex(self::MARKER_KEY, self::TTL_SECONDS, $uuid.'|'.$data->type);

        return $uuid;
    }

    /**
     * @return array{type: string, body: string}
     */
    public function body(string $uuid): array
    {
        $marker = explode('|', (string) Redis::get(self::MARKER_KEY), 2);

        if (! Str::isUuid($uuid)
            || ($marker[0] ?? null) !== $uuid
            || ! in_array($marker[1] ?? null, ['json', 'url'], true)
        ) {
            throw new NotFoundHttpException;
        }

        $disk = $this->disk();

        if (! $disk->exists(self::BODY_PATH)) {
            throw new NotFoundHttpException;
        }

        return [
            'type' => $marker[1],
            'body' => $disk->get(self::BODY_PATH),
        ];
    }

    public function ttlSeconds(): int
    {
        return self::TTL_SECONDS;
    }

    private function disk(): Filesystem
    {
        return Storage::disk('local');
    }
}
