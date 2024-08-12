<!-- resources/views/configs/create.blade.php -->
@extends('layouts.app')

@section('title', 'Create Config')

@section('content')
    <h1>Create Config</h1>
    <form action="{{ route('configs.store') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="user_id">User</label>
            <select name="user_id" id="user_id" class="form-control" required>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <x-configs.config-items
            :configs="old('configs', [[]])"
        />

        <button type="submit" class="btn btn-primary">Save</button>
    </form>
@endsection
