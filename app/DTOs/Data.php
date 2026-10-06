<?php

declare(strict_types=1);

namespace App\DTOs;

use Spatie\LaravelData\Data as SpatieData;

/**
 * Application DTO base with snake_case input/output mapping configured globally.
 */
abstract class Data extends SpatieData {}
