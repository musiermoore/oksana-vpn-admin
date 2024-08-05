<!-- resources/views/transactions/index.blade.php -->
@extends('layouts.app')

@section('title', 'Transactions')

@section('content')
    <div class="d-flex justify-content-between mb-3">
        <h1>Transactions</h1>
        <a href="{{ route('transactions.create') }}" class="btn btn-primary">Create Transaction</a>
    </div>
    <table class="table">
        <thead>
        <tr>
            <th>User</th>
            <th>Amount</th>
            <th>Actions</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($transactions as $transaction)
            <tr>
                <td>{{ $transaction->user->name }}</td>
                <td>{{ $transaction->amount }}</td>
                <td>
                    <a href="{{ route('transactions.edit', $transaction->id) }}" class="btn btn-warning btn-sm">Edit</a>
                    <form action="{{ route('transactions.destroy', $transaction->id) }}" method="POST" style="display:inline-block;">
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
