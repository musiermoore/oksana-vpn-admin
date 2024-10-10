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
            <th>Конфиги</th>
        </tr>
        </thead>
        <tbody>
        @foreach ($users as $user)
            <tr>
                <td>
                    @if ($user->is_active)
                        <a href="{{ route('users.edit', $user->id) }}">{{ $user->full_name }}</a>
                    @else
                        {{ $user->full_name }}
                    @endif
                </td>
                <td>
                    <div class="d-flex flex-column" style="gap: 5px">
                        @foreach ($user->configs as $config)
                            <div class="d-flex align-items-center justify-content-between" style="gap: 10px">
                                <a href="{{ route('configs.edit', $config->id) }}">
                                    {{ $config->name }}
                                </a>
                                <div>
                                    <form action="{{ route('configs.destroy', $config->id) }}" method="POST" style="display:inline-block;">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm js-remove_confirmation">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endsection
