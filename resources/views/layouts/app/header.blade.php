<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="{{ url('/') }}">Latvia VPN</a>
    @if (!empty($isAuthorized))
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" href="{{ route('users.index') }}">Участники</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('configs.index') }}">Конфиги</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('transactions.index') }}">Транзакции</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('current-payments.index') }}">Периоды оплаты</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('servers.index') }}">Серверы</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('wireguard.traffic') }}">Трафик</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('limits.index') }}">Ограничения</a></li>
            </ul>
        </div>
    @endif
</nav>
