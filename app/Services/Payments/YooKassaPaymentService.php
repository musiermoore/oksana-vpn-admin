<?php

namespace App\Services\Payments;

use DomainException;
use Illuminate\Support\Str;
use YooKassa\Client;
use YooKassa\Model\Payment\PaymentInterface;

class YooKassaPaymentService
{
    public function createPayment(float $amount, string $description, array $metadata = [], ?string $returnUrl = null): array
    {
        $shopId = (string) config('services.yookassa.shop_id', '');
        $secretKey = (string) config('services.yookassa.secret_key', '');

        if ($shopId === '' || $secretKey === '') {
            throw new DomainException('YooKassa не настроена: заполните YOO_KASSA_SHOP_ID и YOO_KASSA_SECRET_KEY.');
        }

        $resolvedReturnUrl = $returnUrl ?: (string) config('services.yookassa.return_url', '');

        if ($resolvedReturnUrl === '') {
            throw new DomainException('YooKassa не настроена: отсутствует return URL.');
        }

        $client = new Client;
        $client->setAuth($shopId, $secretKey);

        $payment = $client->createPayment([
            'amount' => [
                'value' => number_format($amount, 2, '.', ''),
                'currency' => 'RUB',
            ],
            'capture' => true,
            'confirmation' => [
                'type' => 'redirect',
                'return_url' => $resolvedReturnUrl,
            ],
            'description' => Str::limit($description, 128, ''),
            'metadata' => $metadata,
        ], Str::uuid()->toString());

        if (! $payment instanceof PaymentInterface) {
            throw new DomainException('YooKassa вернула пустой ответ при создании платежа.');
        }

        return $this->normalizePayment($payment);
    }

    public function normalizePayment(PaymentInterface $payment): array
    {
        $amount = $payment->getAmount();
        $confirmation = $payment->getConfirmation();
        $metadata = $payment->getMetadata();

        return [
            'id' => $payment->getId(),
            'status' => $payment->getStatus(),
            'paid' => $payment->getPaid(),
            'amount' => [
                'value' => $amount?->getValue(),
                'currency' => $amount?->getCurrency(),
            ],
            'confirmation' => [
                'type' => $confirmation?->getType(),
                'confirmation_url' => method_exists($confirmation, 'getConfirmationUrl')
                    ? $confirmation->getConfirmationUrl()
                    : null,
            ],
            'created_at' => $payment->getCreatedAt()?->format(DATE_ATOM),
            'description' => $payment->getDescription(),
            'metadata' => $metadata ? iterator_to_array($metadata) : [],
            'raw' => json_decode(json_encode($payment, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR),
        ];
    }
}
