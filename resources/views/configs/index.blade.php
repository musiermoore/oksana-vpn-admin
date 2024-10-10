<!-- resources/views/configs/index.blade.php -->
@extends('layouts.app')

@section('title', 'Конфиги')

@section('content')
    <div class="d-flex justify-content-between mb-3">
        <h1>Конфиги</h1>
        <div>
            <a href="{{ route('configs.create') }}" class="btn btn-primary">Создать</a>
        </div>
    </div>
    <table class="table">
        <thead>
        <tr>
            <th>Участник</th>
            <th>Название</th>
            <th>Описание</th>
            <th>Действия</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($configs as $config)
            <tr>
                <td><a href="{{ route('users.edit', $config->user_id) }}">{{ $config->user->full_name }}</a></td>
                <td>{{ $config->name }}</td>
                <td>{{ $config->description }}</td>
                <td>
                    <a href="{{ route('configs.edit', $config->id) }}" class="btn btn-warning btn-sm"><i class="fa-solid fa-pen-to-square"></i></a>
                    <form action="{{ route('configs.destroy', $config->id) }}" method="POST" style="display:inline-block;">
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
