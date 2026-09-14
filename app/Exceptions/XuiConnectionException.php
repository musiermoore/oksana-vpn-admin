<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class XuiConnectionException extends RuntimeException implements ShouldntReport
{
}
