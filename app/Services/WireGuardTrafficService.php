<?php

namespace App\Services;

use App\Models\Config;
use Carbon\Carbon;
use Exception;

class WireGuardTrafficService
{
    public const SENT_TYPE = 'sent';
    public const RECEIVED_TYPE = 'received';
    public const ALLOWED_TYPES = [
        self::SENT_TYPE,
        self::RECEIVED_TYPE,
    ];

    private Config $config;
    private string $transfer;
    private array $traffic = [
        self::SENT_TYPE => 0,
        self::RECEIVED_TYPE => 0,
    ];

    public function __construct(Config $config, $transfer)
    {
        $this->config = $config;
        $this->transfer = $transfer;
    }

    public function calculate()
    {
        try {
            $this->parseData();
        } catch (Exception $exception) {
            return [];
        }

        return $this->traffic;
    }

    private function parseData()
    {
        $transferTypes = explode(', ', $this->transfer);

        foreach ($transferTypes as $type) {
            try {
                preg_match('/(\d{0,9}\.?\d{1,9}?) ([TGM]iB) (received|sent)/', $type, $matches);

                [$string, $amount, $unit, $type] = $matches;

                if (empty($type) || !in_array($type, self::ALLOWED_TYPES)) {
                    continue;
                }

                $this->traffic[$type] = $this->calculateAmount($amount, $unit);
            } catch (Exception $exception) {
                continue;
            }
        }

        $this->traffic['config_id'] = $this->config->id;
        $this->traffic['created_at'] = now();
        $this->traffic['updated_at'] = now();
    }


    /**
     * Convert to bytes
     *
     * @param $amount
     * @param $unit
     * @return int
     */
    private function calculateAmount($amount, $unit): int
    {
        return (int) round($amount * $this->convertUnit($unit));
    }

    /**
     * Convert to bytes
     *
     * @param $unit
     * @return float|int
     */
    private function convertUnit($unit): float|int
    {
        $units = [
            'TiB' => 1024 * 1024 * 1024 * 1024,
            'GiB' => 1024 * 1024 * 1024,
            'MiB' => 1024 * 1024,
            'KiB' => 1024,
        ];

        return $units[$unit] ?? 1;
    }

    public static function getTraffic(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $service = new WireGuardService();

        if ($startDate && $endDate) {
            $service
                ->setStartDate($startDate)
                ->setEndDate($endDate);
        }

        $peers = $service->getClientPeers();

        $traffics = [];

        foreach ($peers as $peer) {
            if (empty($peer['transfer']) || empty($peer['config'])) {
                continue;
            }

            $trafficService = new self($peer['config'], $peer['transfer']);

            $traffic = $trafficService->calculate();

            if (empty($traffic)) {
                continue;
            }

            $traffics[] = $traffic;
        }

        return $traffics;
    }
}
