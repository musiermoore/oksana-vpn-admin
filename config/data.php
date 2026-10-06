<?php

declare(strict_types=1);

use Spatie\LaravelData\Mappers\SnakeCaseMapper;

return [
    // Request payloads and serialized DTOs use snake_case names while PHP properties remain camelCase.
    'name_mapping_strategy' => [
        'input' => SnakeCaseMapper::class,
        'output' => SnakeCaseMapper::class,
    ],
];
