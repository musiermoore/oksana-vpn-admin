<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\NonRegularPayment\StoreNonRegularPaymentRequest;
use App\Http\Resources\NonRegularPaymentResource;
use App\Repositories\NonRegularPaymentRepository;
use App\Services\Crud\NonRegularPaymentCrudService;
use Illuminate\Http\Request;

class NonRegularPaymentController extends Controller
{
    public function __construct(
        private readonly NonRegularPaymentRepository $payments,
        private readonly NonRegularPaymentCrudService $paymentService,
    ) {}

    public function index(Request $request)
    {
        return $this->inertia('NonRegularPayments/Index', [
            'payments' => NonRegularPaymentResource::collection($this->payments->latest())->toArray($request),
        ]);
    }

    public function create()
    {
        return $this->inertia('NonRegularPayments/Create', [
            'submit_url' => route('non-regular-payments.store'),
        ]);
    }

    public function store(StoreNonRegularPaymentRequest $request)
    {
        $this->paymentService->create($request->toData());

        return redirect()->route('non-regular-payments.index')
            ->with('success', 'Нерегулярный расход успешно добавлен.');
    }

    public function destroy(string $id)
    {
        $this->paymentService->delete($id);

        return redirect()->route('non-regular-payments.index')
            ->with('success', 'Нерегулярный расход удален.');
    }
}
