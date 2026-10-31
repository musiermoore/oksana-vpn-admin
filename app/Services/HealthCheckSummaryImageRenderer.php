<?php

declare(strict_types=1);

namespace App\Services;

use Imagick;
use ImagickDraw;
use ImagickPixel;

final class HealthCheckSummaryImageRenderer
{
    private const WIDTH = 1040;
    private const MARGIN = 32;
    private const NAME_COLUMN_WIDTH = 480;
    private const STATUS_COLUMN_WIDTH = 99;
    private const HEADER_HEIGHT = 56;
    private const ROW_HEIGHT = 50;
    private const FONT = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    private const FONT_BOLD = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';

    /**
     * @param array<string, array{direct:string,connect:string,connectV2:string,external:string,custom:string}> $rows
     */
    public function render(array $rows, bool $current, int $unhealthy, int $warnings): string
    {
        $rows = array_slice($rows, 0, 30, true);
        $displayedRows = count($rows) + ($rows === [] ? 1 : 0);
        $height = 210 + self::HEADER_HEIGHT + $displayedRows * self::ROW_HEIGHT;
        $image = new Imagick();
        $image->newImage(self::WIDTH, $height, new ImagickPixel('#f8fafc'));
        $image->setImageFormat('png');

        $draw = new ImagickDraw();
        $draw->setFont(self::FONT);
        $draw->setTextAntialias(true);
        $draw->setStrokeAntialias(true);

        $this->drawTitle($image, $draw, $current, $unhealthy, $warnings);
        $this->drawTableHeader($image, $draw);
        $this->drawRows($image, $draw, $rows);
        $this->drawLegend($image, $draw, 158 + self::HEADER_HEIGHT + $displayedRows * self::ROW_HEIGHT + 32);

        $contents = $image->getImageBlob();
        $image->clear();
        $image->destroy();

        return $contents;
    }

    private function drawTitle(Imagick $image, ImagickDraw $draw, bool $current, int $unhealthy, int $warnings): void
    {
        $draw->setFont(self::FONT_BOLD);
        $draw->setFontSize(31);
        $draw->setFillColor(new ImagickPixel('#0f172a'));
        $image->annotateImage($draw, self::MARGIN, 56, 0, 'Состояние VPN');

        $draw->setFont(self::FONT);
        $draw->setFontSize(18);
        $draw->setFillColor(new ImagickPixel('#475569'));
        $subtitle = $current ? 'Текущие сохранённые результаты' : 'Проверки за последние 30 минут';
        $image->annotateImage($draw, self::MARGIN, 88, 0, $subtitle);

        $draw->setFont(self::FONT_BOLD);
        $draw->setFontSize(18);
        $draw->setFillColor(new ImagickPixel('#b91c1c'));
        $image->annotateImage($draw, self::MARGIN, 132, 0, '× Ошибки: '.$unhealthy);
        $draw->setFillColor(new ImagickPixel('#b45309'));
        $image->annotateImage($draw, self::MARGIN + 210, 132, 0, '! Нестабильно: '.$warnings);
    }

    private function drawTableHeader(Imagick $image, ImagickDraw $draw): void
    {
        $top = 158;
        $left = self::MARGIN;
        $this->drawRectangle($image, $left, $top, self::WIDTH - self::MARGIN, $top + self::HEADER_HEIGHT, '#1e293b', '#1e293b');

        $draw->setFont(self::FONT_BOLD);
        $draw->setFontSize(18);
        $draw->setFillColor(new ImagickPixel('#ffffff'));
        $image->annotateImage($draw, $left + 18, $top + 36, 0, 'Имя');

        $headers = ['Dir', 'V1', 'V2', 'Ext', 'Cus'];
        foreach ($headers as $index => $header) {
            $centerX = $left + self::NAME_COLUMN_WIDTH + ($index * self::STATUS_COLUMN_WIDTH) + intdiv(self::STATUS_COLUMN_WIDTH, 2);
            $draw->setTextAlignment(Imagick::ALIGN_CENTER);
            $image->annotateImage($draw, $centerX, $top + 36, 0, $header);
        }
        $draw->setTextAlignment(Imagick::ALIGN_LEFT);
    }

    /** @param array<string, array{direct:string,connect:string,connectV2:string,external:string,custom:string}> $rows */
    private function drawRows(Imagick $image, ImagickDraw $draw, array $rows): void
    {
        $keys = ['direct', 'connect', 'connectV2', 'external', 'custom'];
        $items = $rows === [] ? ['Нет сохранённых результатов' => null] : $rows;

        foreach (array_values($items) as $rowIndex => $row) {
            $top = 158 + self::HEADER_HEIGHT + ($rowIndex * self::ROW_HEIGHT);
            $left = self::MARGIN;
            $background = $rowIndex % 2 === 0 ? '#ffffff' : '#f1f5f9';
            $this->drawRectangle($image, $left, $top, self::WIDTH - self::MARGIN, $top + self::ROW_HEIGHT, $background, '#cbd5e1');

            $draw->setFont(self::FONT);
            $draw->setFontSize(19);
            $draw->setFillColor(new ImagickPixel('#0f172a'));
            $label = (string) array_keys($items)[$rowIndex];
            $name = preg_replace('/[\x{1F1E6}-\x{1F1FF}]{2}\s*/u', '', $label) ?? $label;
            $name = mb_strimwidth($name, 0, 40, '…');
            $image->annotateImage($draw, $left + 18, $top + 32, 0, $name);

            if ($row === null) {
                continue;
            }

            foreach ($keys as $statusIndex => $key) {
                $centerX = $left + self::NAME_COLUMN_WIDTH + ($statusIndex * self::STATUS_COLUMN_WIDTH) + intdiv(self::STATUS_COLUMN_WIDTH, 2);
                $this->drawStatus($image, $draw, $centerX, $top + 25, $row[$key]);
            }
        }
    }

    private function drawStatus(Imagick $image, ImagickDraw $draw, int $centerX, int $centerY, string $status): void
    {
        [$glyph, $color, $background] = match ($status) {
            'healthy' => ['✓', '#15803d', '#dcfce7'],
            'warning' => ['!', '#b45309', '#fef3c7'],
            'unhealthy' => ['×', '#b91c1c', '#fee2e2'],
            'pending' => ['…', '#1d4ed8', '#dbeafe'],
            'unsupported' => ['N/A', '#64748b', '#e2e8f0'],
            default => ['—', '#64748b', '#e2e8f0'],
        };

        $shape = new ImagickDraw();
        $shape->setFillColor(new ImagickPixel($background));
        $shape->setStrokeColor(new ImagickPixel($background));
        $shape->ellipse($centerX, $centerY, 18, 18, 0, 360);
        $image->drawImage($shape);
        $shape->clear();
        $draw->setFont(self::FONT_BOLD);
        $draw->setFontSize($glyph === 'N/A' ? 14 : 22);
        $draw->setFillColor(new ImagickPixel($color));
        $draw->setTextAlignment(Imagick::ALIGN_CENTER);
        $image->annotateImage($draw, $centerX, $centerY + 8, 0, $glyph);
        $draw->setTextAlignment(Imagick::ALIGN_LEFT);
    }

    private function drawLegend(Imagick $image, ImagickDraw $draw, int $top): void
    {
        $draw->setFont(self::FONT);
        $draw->setFontSize(16);
        $draw->setFillColor(new ImagickPixel('#475569'));
        $image->annotateImage($draw, self::MARGIN, $top, 0, '✓ Доступен    ! Нестабильно    × Ошибка    N/A Не поддерживается    — Нет данных');
    }

    private function drawRectangle(
        Imagick $image,
        int $left,
        int $top,
        int $right,
        int $bottom,
        string $fill,
        string $stroke,
    ): void
    {
        $shape = new ImagickDraw();
        $shape->setFillColor(new ImagickPixel($fill));
        $shape->setStrokeColor(new ImagickPixel($stroke));
        $shape->setStrokeWidth(1);
        $shape->rectangle($left, $top, $right, $bottom);
        $image->drawImage($shape);
        $shape->clear();
    }
}
