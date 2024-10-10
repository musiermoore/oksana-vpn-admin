<!-- resources/views/configs/create.blade.php -->
@extends('layouts.app')

@section('title', 'Создать конфиг')

@section('content')
    <h1>Создать конфиг</h1>
    <form action="{{ route('configs.store') }}" method="POST">
        @csrf
        <div x-data="{ existing: false }" class="form-group">
            <label for="user_id">Участник</label>
            <select name="user_id" id="user_id" class="form-control" required>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}">{{ $user->full_name }}</option>
                @endforeach
            </select>
        </div>
        <x-configs.config-items
            :files="$fileNames"
            :configs="old('configs', [[]])"
        />

        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
@endsection
