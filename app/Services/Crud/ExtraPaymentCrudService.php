<?php

namespace App\Services\Crud;

use App\DTOs\ExtraPayment\ExtraPaymentData;
use App\Models\UserExtraPayment;
use App\Repositories\ExtraPaymentRepository;

class ExtraPaymentCrudService
{
    public function __construct(
        private readonly ExtraPaymentRepository $payments,
    ) {}

    public function create(ExtraPaymentData $data): UserExtraPayment
    {
        return $this->payments->create($data->toArray());
    }

    public function delete(string|int $id): void
    {
        $this->payments->deleteById($id);
    }
}
