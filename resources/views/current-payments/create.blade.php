<!-- resources/views/current-payments/create.blade.php -->
@extends('layouts.app')

@section('title', 'Create Current Payment')

@section('content')
    <h1>Create Current Payment</h1>
    <form action="{{ route('current-payments.store') }}" method="POST">
        @csrf
        @php
            $subMonth = now()->day < 21 ? 1 : 0;
        @endphp
        <div class="form-group">
            <label for="start_date">Start Date</label>
            <input type="date" class="form-control" id="start_date" name="start_date" value="{{ now()->subMonths($subMonth)->format('Y-m-21') }}" />
        </div>
        <div class="form-group">
            <label for="end_date">End Date</label>
            <input type="date" class="form-control" id="end_date" name="end_date" value="{{ now()->addMonth()->subMonths($subMonth)->format('Y-m-21') }}" />
        </div>
        <div class="form-group">
            <label for="amount">Amount</label>
            <input type="number" step="0.01" name="amount" id="amount" class="form-control" value="{{ \App\Models\CurrentPayment::getHostingPrice() }}" required>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
@endsection
