<!-- resources/views/current_payments/edit.blade.php -->
@extends('layouts.app')

@section('title', 'Edit Payment')

@section('content')
    <h1>Edit Payment</h1>
    <form action="{{ route('current-payments.update', $currentPayment->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label for="start_date">Start Date</label>
            <input type="date" id="start_date" name="start_date" class="form-control" value="{{ $currentPayment->start_date }}" />
        </div>
        <div class="form-group">
            <label for="end_date">End Date</label>
            <input type="date" id="end_date" name="end_date" class="form-control" value="{{ $currentPayment->end_date }}" />
        </div>
        <div class="form-group">
            <label for="amount">Amount</label>
            <input type="number" step="0.01" name="amount" id="amount" class="form-control" value="{{ $currentPayment->amount }}" required>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
@endsection
