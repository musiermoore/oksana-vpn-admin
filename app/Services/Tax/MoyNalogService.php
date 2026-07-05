<?php

declare(strict_types=1);

namespace App\Services\Tax;

use App\Models\Invoice;
use App\Models\TaxSetting;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

class MoyNalogService
{
    private const BASE_URL = 'https://lknpd.nalog.ru/api/v1';

    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 11_2_2) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/88.0.4324.192 Safari/537.36';

    public function authenticate(TaxSetting $settings, string $scope): array
    {
        if (blank($settings->login) || blank($settings->password)) {
            throw new RuntimeException('Налоговые настройки не заполнены: укажите логин и пароль.');
        }

        $response = $this->baseRequest()
            ->post('/auth/lkfl', [
                'username' => $settings->login,
                'password' => $settings->password,
                'deviceInfo' => [
                    'sourceType' => 'WEB',
                    'sourceDeviceId' => $this->deviceId($scope),
                    'appVersion' => '1.0.0',
                    'metaDetails' => [
                        'userAgent' => self::USER_AGENT,
                    ],
                ],
            ]);

        $payload = $response->json() ?? [];
        $token = (string) (
            data_get($payload, 'token')
            ?? data_get($payload, 'accessToken')
            ?? data_get($payload, 'access_token')
            ?? data_get($payload, 'data.token')
            ?? data_get($payload, 'data.accessToken')
            ?? data_get($payload, 'data.access_token')
            ?? ''
        );

        if ($token === '') {
            throw new RuntimeException('Не удалось получить access_token от Moy Nalog.');
        }

        Redis::setex($this->tokenKey($scope), 900, $token);

        return [
            'response' => $response,
            'json' => $payload,
            'token' => $token,
        ];
    }

    public function getCurrentUser(string $scope): array
    {
        $response = $this->authorizedRequest($scope)->get('/user');

        return [
            'response' => $response,
            'json' => $response->json(),
        ];
    }

    public function createIncomeReceipt(Invoice $invoice, TaxSetting $settings, string $scope): array
    {
        $payload = $this->buildIncomePayload($invoice, $settings);
        $response = $this->authorizedRequest($scope)->post('/income', $payload);

        return [
            'response' => $response,
            'json' => $response->json(),
            'payload' => $payload,
            'receipt_uuid' => (string) (
                data_get($response->json(), 'approvedReceiptUuid')
                ?? data_get($response->json(), 'receiptUuid')
                ?? data_get($response->json(), 'uuid')
                ?? data_get($response->json(), 'id')
                ?? data_get($response->json(), 'data.approvedReceiptUuid')
                ?? data_get($response->json(), 'data.receiptUuid')
                ?? data_get($response->json(), 'data.uuid')
                ?? data_get($response->json(), 'data.id')
                ?? ''
            ),
        ];
    }

    public function clearToken(string $scope): void
    {
        Redis::del($this->tokenKey($scope));
    }

    /**
     * @return array<string, mixed>
     */
    public function buildIncomePayload(Invoice $invoice, TaxSetting $settings): array
    {
        $invoice->loadMissing(['transactions.type', 'user']);
        $operationTime = $this->resolveOperationTime($invoice);
        $serviceName = trim((string) $settings->service_name) !== ''
            ? trim((string) $settings->service_name)
            : 'Настройка сетевой конфигурации';

        $services = $invoice->transactions
            ->map(fn ($transaction) => [
                'name' => $serviceName,
                'amount' => $this->formatAmount(abs((float) $transaction->amount)),
                'quantity' => '1',
            ])
            ->values()
            ->all();

        if ($services === []) {
            $services = [[
                'name' => $serviceName,
                'amount' => $this->formatAmount((float) $invoice->amount),
                'quantity' => '1',
            ]];
        }

        return [
            'operationTime' => $operationTime,
            'requestTime' => $operationTime,
            'services' => $services,
            'totalAmount' => $this->formatAmount((float) $invoice->amount),
            'client' => [
                'contactPhone' => null,
                'displayName' => null,
                'incomeType' => 'FROM_INDIVIDUAL',
                'inn' => null,
            ],
            'paymentType' => 'CASH',
            'ignoreMaxTotalIncomeRestriction' => false,
        ];
    }

    private function authorizedRequest(string $scope): PendingRequest
    {
        $token = (string) Redis::get($this->tokenKey($scope));

        if ($token === '') {
            throw new RuntimeException('В Redis не найден access_token для налоговой операции.');
        }

        return $this->baseRequest()
            ->withToken($token);
    }

    private function baseRequest(): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->acceptJson()
            ->contentType('application/json')
            ->withHeaders([
                'Accept-Language' => 'ru-RU,ru;q=0.9,en-US;q=0.8,en;q=0.7',
                'User-Agent' => self::USER_AGENT,
            ])
            ->timeout(30);
    }

    private function tokenKey(string $scope): string
    {
        return 'tax:moy-nalog:access-token:'.$scope;
    }

    private function deviceId(string $scope): string
    {
        return 'wireguard-vpn-app-'.$scope;
    }

    private function resolveOperationTime(Invoice $invoice): string
    {
        $date = $invoice->paid_at instanceof CarbonInterface
            ? $invoice->paid_at
            : ($invoice->created_at instanceof CarbonInterface ? $invoice->created_at : now());

        return $date->setTimezone('Europe/Moscow')->format('Y-m-d\TH:i:s.vP');
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
