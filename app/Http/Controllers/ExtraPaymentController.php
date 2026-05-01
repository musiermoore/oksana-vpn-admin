<?php

namespace App\Http\Controllers;

use App\Http\Requests\ExtraPayment\StoreExtraPaymentRequest;
use App\Models\CurrentPayment;
use App\Models\User;
use App\Models\UserExtraPayment;
use App\Services\Crud\ExtraPaymentCrudService;

class ExtraPaymentController extends Controller
{
    public function __construct(
        private readonly ExtraPaymentCrudService $extraPaymentService,
    ) {}

    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $payments = UserExtraPayment::query()
            ->with(['user', 'currentPayment'])
            ->latest()
            ->get();

        return $this->inertia('ExtraPayments/Index', [
            'payments' => $payments->map(fn (UserExtraPayment $payment) => $this->extraPaymentData($payment))->values(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $users = User::all();
        $currentPayments = CurrentPayment::latest()->get();
        $activePeriodId = CurrentPayment::getActivePaymentPeriodId();

        return $this->inertia('ExtraPayments/Create', [
            'submit_url' => route('extra-payments.store'),
            'users' => $users->map(fn (User $user) => $this->userData($user))->values(),
            'current_payments' => $currentPayments
                ->map(fn (CurrentPayment $currentPayment) => $this->currentPaymentData($currentPayment))
                ->values(),
            'active_period_id' => $activePeriodId,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreExtraPaymentRequest $request)
    {
        $this->extraPaymentService->create($request->toDto());

        return redirect()->route('extra-payments.index')
            ->with('success', 'Доп. оплата успешно добавлена.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $this->extraPaymentService->delete($id);

        return redirect()->route('extra-payments.index')
            ->with('success', 'Доп. оплата успешно удалена.');
    }
}
