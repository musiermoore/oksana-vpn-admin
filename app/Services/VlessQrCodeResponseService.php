<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\BotApiMessages;
use Exception;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Symfony\Component\HttpFoundation\Response;

class VlessQrCodeResponseService
{
    public function download(string $link, string $filename): Response
    {
        try {
            $png = QrCode::format('png')
                ->margin(5)
                ->size(512)
                ->generate($link);

            return response($png)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', sprintf('attachment; filename="%s"', $filename));
        } catch (Exception $exception) {
            report($exception);

            return response()->json([
                'message' => BotApiMessages::unexpectedError(),
            ], 500);
        }
    }
}
