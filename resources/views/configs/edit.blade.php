<!-- resources/views/configs/edit.blade.php -->
@extends('layouts.app')

@section('title', 'Редактирование конфига')

@section('content')
    <h1>Редактирование конфига</h1>
    <form action="{{ route('configs.update', $config->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="form-group">
            <label for="user_id">Участник</label>
            <select name="user_id" id="user_id" class="form-control" required>
                @foreach ($users as $user)
                    <option value="{{ $user->id }}" {{ $config->user_id == $user->id ? 'selected' : '' }}>{{ $user->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="form-group">
            <label for="name">Название</label>
            <input type="text" name="name" id="name" class="form-control" value="{{ $config->name }}" required>
        </div>
        <div class="form-group">
            <label for="description">Описание</label>
            <textarea name="description" id="description" class="form-control">{{ $config->description }}</textarea>
        </div>
        <button type="submit" class="btn btn-primary">Сохранить</button>
    </form>
@endsection
