<!-- resources/views/transactions/create.blade.php -->
@extends('layouts.app')

@section('title', 'Создание транзакции')

@section('content')
    <h1>Создание транзакции</h1>
    <form action="{{ route('transactions.store') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="user_id">Участник</label>
            <select name="user_id" id="user_id" class="form-control" required>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->full_name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="amount">Сумма</label>
            <input type="number" step="0.01" name="amount" id="amount" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
@endsection
