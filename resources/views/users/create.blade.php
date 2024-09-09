<!-- resources/views/users/create.blade.php -->
@extends('layouts.app')

@section('title', 'Create User')

@section('content')
    <h1>Create User</h1>
    <form action="{{ route('users.store') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="name">Name</label>
            <input type="text" name="name" id="name" class="form-control" value="{{ old('name') }}" required>
        </div>
        <div class="form-group">
            <label for="telegram">Telegram</label>
            <input type="text" name="telegram" id="telegram" class="form-control" value="{{ old('telegram') }}" required>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" class="form-control">{{ old('description') }}</textarea>
        </div>
        <div class="form-group">
            <label for="join_at">Join At</label>
            <select name="join_at" id="join_at" class="form-control">
                @foreach ($payments as $payment)
                    <option
                        value="{{ $payment->start_date }}"
                        @selected(old('join_at') === $payment->start_date)
                    >
                        {{ $payment->formatted_start_date }} ({{ $payment->amount }}₽)
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Save</button>
    </form>
@endsection
