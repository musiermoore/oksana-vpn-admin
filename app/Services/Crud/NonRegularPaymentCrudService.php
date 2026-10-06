<?php

declare(strict_types=1);

namespace App\Services\Crud;

use App\DTOs\NonRegularPayment\NonRegularPaymentData;
use App\Models\NonRegularPayment;
use App\Repositories\NonRegularPaymentRepository;

class NonRegularPaymentCrudService
{
    public function __construct(
        private readonly NonRegularPaymentRepository $payments,
    ) {}

    public function create(NonRegularPaymentData $data): NonRegularPayment
    {
        return $this->payments->create($data->toArray());
    }

    public function delete(string|int $id): void
    {
        $this->payments->deleteById($id);
    }
}
