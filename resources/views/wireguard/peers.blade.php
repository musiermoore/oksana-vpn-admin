@extends('layouts.app')

@section('title', 'Конфиги')

@section('content')

    @foreach($peers as $key => $peerType)
        <h1 class="mb-4">{{ $key === 'active' ? 'Активные' : 'Оффлайн' }} ({{ count($peerType) }})</h1>
        <div class="row">
            @foreach($peerType as $peer)
                <div class="col-12 col-sm-6 col-md-4 col-xl-3 p-2">
                    <div class="card h-100 w-100">
                        <div class="card-body">
                            <h5 class="card-title">{{ $peer['telegram'] }}</h5>
                            <p class="card-text">
                                <strong>Последняя активность:</strong> <br />
                                {{ $peer['latest_handshake'] }}
                            </p>
                            <p class="card-text">
                                <strong>Трафика использовано:</strong> <br />
                                {{ $peer['transfer'] }}
                            </p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
@endsection
