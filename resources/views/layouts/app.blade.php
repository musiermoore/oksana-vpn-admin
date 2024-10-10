<!-- resources/views/layouts/app.blade.php -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.6.0/css/all.min.css">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    @vite('resources/css/app.css')
    @vite('resources/js/app.js')
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <a class="navbar-brand" href="{{ url('/') }}">Latvia VPN</a>
    @if (!empty($isAuthorized))
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item"><a class="nav-link" href="{{ route('users.index') }}">Участники</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('configs.index') }}">Конфиги</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('transactions.index') }}">Транзакции</a></li>
                <li class="nav-item"><a class="nav-link" href="{{ route('current-payments.index') }}">Оплата</a></li>
            </ul>
        </div>
    @endif
</nav>
<div class="container mt-4">
    @yield('content')
</div>
</body>
</html>
