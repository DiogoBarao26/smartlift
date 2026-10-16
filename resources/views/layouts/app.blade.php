<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'SmartLift')</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,560;9..144,680&family=Outfit:wght@380;500;650&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/smartlift.css') }}" rel="stylesheet">
</head>
<body>
    <header class="topbar">
        <a class="brand" href="{{ route('home') }}">
            <span class="brand-mark" aria-hidden="true"></span>
            <span>
                <strong>SmartLift</strong>
                <small>Controle inteligente de elevadores</small>
            </span>
        </a>
        <nav class="top-actions">
            @yield('nav')
        </nav>
    </header>

    <main class="shell">
        @yield('content')
    </main>

    @yield('scripts')
</body>
</html>
