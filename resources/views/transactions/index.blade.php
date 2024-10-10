<!-- resources/views/transactions/index.blade.php -->
@extends('layouts.app')

@section('title', 'Транзакции')

@section('content')
    <div class="d-flex justify-content-between mb-3">
        <h1>Транзакции</h1>
        <div>
            <a href="{{ route('transactions.create') }}" class="btn btn-primary">Создать</a>
        </div>
    </div>
    <table class="table">
        <thead>
        <tr>
            <th>Участник</th>
            <th>Сумма</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($transactions as $transaction)
            <tr>
                <td>
                    @if ($transaction->user->is_active)
                        <a href="{{ route('users.edit', $transaction->user->id) }}">{{ $transaction->user->full_name }}</a>
                    @else
                        {{ $transaction->user->full_name }}
                    @endif
                </td>
                <td>{{ $transaction->amount }}</td>
                <td>
                    <a href="{{ route('transactions.edit', $transaction->id) }}" class="btn btn-warning btn-sm"><i class="fa-solid fa-pen-to-square"></i></a>
                    <form action="{{ route('transactions.destroy', $transaction->id) }}" method="POST" style="display:inline-block;">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger btn-sm js-remove_confirmation">
                            <i class="fa-solid fa-trash"></i>
                        </button>
                    </form>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
