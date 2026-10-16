@extends('layouts.app')

@section('title', 'Entrar — SmartLift')

@section('content')
    <section class="auth-card">
        <p class="eyebrow">Painel administrativo</p>
        <h1>Acesso do administrador</h1>
        <p class="lede">Cadastre cartões, ajuste andares e capacidade, e consulte o histórico gravado no SQLite em memória.</p>

        <form method="POST" action="{{ route('login') }}" class="stack-form">
            @csrf
            <label>
                E-mail
                <input type="email" name="email" value="{{ old('email', 'admin@smartlift.local') }}" required autofocus>
            </label>
            <label>
                Senha
                <input type="password" name="password" value="admin123" required>
            </label>
            @error('email')
                <p class="form-error">{{ $message }}</p>
            @enderror
            <button class="btn-brass" type="submit">Entrar</button>
        </form>

        <p class="hint">Demonstração: <code>admin@smartlift.local</code> / <code>admin123</code></p>
        <a class="text-link" href="{{ route('home') }}">Voltar ao elevador</a>
    </section>
@endsection
