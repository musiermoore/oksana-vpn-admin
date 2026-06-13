<?php

namespace App\Services\Contracts;

interface WireGuardConfigServiceContract
{
    public function create(): bool;

    public function createOrFail(): bool;

    public function delete(): bool;

    public function enable(): bool;

    public function disable(): bool;

    public function setLimit(int|string $limit): bool;

    public function removeLimit(int|string $limit): bool;
}
