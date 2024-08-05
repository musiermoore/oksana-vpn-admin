<?php

namespace App\Http\Controllers;

use App\Models\CurrentPayment;
use Illuminate\Http\Request;

class CurrentPaymentController extends Controller
{
    public function index()
    {
        $currentPayments = CurrentPayment::all();
        return view('current-payments.index', compact('currentPayments'));
    }

    public function create()
    {
        return view('current-payments.create');
    }

    public function store(Request $request)
    {
        CurrentPayment::create($request->all());
        return redirect()->route('current-payments.index');
    }

    public function edit(CurrentPayment $currentPayment)
    {
        return view('current-payments.edit', compact('currentPayment'));
    }

    public function update(Request $request, CurrentPayment $currentPayment)
    {
        $currentPayment->update($request->all());
        return redirect()->route('current-payments.index');
    }

    public function destroy(CurrentPayment $currentPayment)
    {
        $currentPayment->delete();
        return redirect()->route('current-payments.index');
    }
}
