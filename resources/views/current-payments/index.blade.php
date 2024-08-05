<!-- resources/views/current-payments/index.blade.php -->
@extends('layouts.app')

@section('title', 'Скока платить щас')

@section('content')
    <div class="d-flex justify-content-between mb-3">
        <h1>Скока платить щас</h1>
        <a href="{{ route('current-payments.create') }}" class="btn btn-primary">Create Скока платить щас</a>
    </div>
    <table class="table">
        <thead>
        <tr>
            <th>Start Date</th>
            <th>End Date</th>
            <th>Amount</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($currentPayments as $currentPayment)
            <tr>
                <td>{{ $currentPayment->start_date }}</td>
                <td>{{ $currentPayment->end_date }}</td>
                <td>{{ $currentPayment->amount }}</td>
                <td>
                    <a href="{{ route('current-payments.edit', $currentPayment->id) }}" class="btn btn-warning btn-sm">Edit</a>
                    <form action="{{ route('current-payments.destroy', $currentPayment->id) }}" method="POST" style="display:inline-block;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
