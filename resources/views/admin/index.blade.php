@extends('layouts.app')

@section('title', 'Administração — SmartLift')

@section('nav')
    <a class="btn-ghost" href="{{ route('home') }}">Voltar ao elevador</a>
    <form method="POST" action="{{ route('logout') }}">
        @csrf
        <button class="btn-ghost" type="submit">Sair</button>
    </form>
@endsection

@section('content')
    <section class="admin-grid">
        <article class="card-block">
            <h1>Configuração do prédio</h1>
            <p class="lede">Andares, peso máximo e quantidade máxima de passageiros valem para o único elevador da simulação.</p>
            <form id="settings-form" class="stack-form">
                <label>Nome <input name="name" id="setting-name" required></label>
                <div class="inline-fields">
                    <label>Andar mínimo <input name="min_floor" id="setting-min" type="number" min="0" required></label>
                    <label>Andar máximo <input name="max_floor" id="setting-max" type="number" min="1" required></label>
                </div>
                <div class="inline-fields">
                    <label>Peso máximo (kg) <input name="max_weight_kg" id="setting-weight" type="number" min="30" step="0.1" required></label>
                    <label>Passageiros <input name="max_passengers" id="setting-passengers" type="number" min="1" required></label>
                </div>
                <button class="btn-brass" type="submit">Salvar configuração</button>
                <p class="form-error" id="settings-error" hidden></p>
            </form>
        </article>

        <article class="card-block">
            <h2 id="person-form-title">Novo cartão</h2>
            <form id="person-form" class="stack-form">
                <input type="hidden" id="person-id">
                <label>Nome completo <input name="name" id="person-name" required></label>
                <label>Código do cartão <input name="card_code" id="person-code" required placeholder="ANA-1008"></label>
                <div class="inline-fields">
                    <label>Andar autorizado <input name="destination_floor" id="person-floor" type="number" required></label>
                    <label>Peso (kg) <input name="weight_kg" id="person-weight" type="number" min="1" step="0.1" required></label>
                </div>
                <div class="inline-fields">
                    <button class="btn-brass" type="submit" id="person-submit">Cadastrar</button>
                    <button class="btn-ghost" type="button" id="person-cancel" hidden>Cancelar edição</button>
                </div>
                <p class="form-error" id="person-error" hidden></p>
            </form>
        </article>

        <article class="card-block span-2">
            <h2>Cartões emitidos</h2>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Código</th>
                            <th>Andar</th>
                            <th>Peso</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="people-rows"></tbody>
                </table>
            </div>
        </article>

        <article class="card-block span-2">
            <header class="split">
                <h2>Histórico de operações</h2>
                <button class="btn-ghost" type="button" id="history-refresh">Atualizar</button>
            </header>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Quando</th>
                            <th>Evento</th>
                            <th>Andar</th>
                            <th>Descrição</th>
                        </tr>
                    </thead>
                    <tbody id="history-rows"></tbody>
                </table>
            </div>
            <div class="pager">
                <button class="btn-ghost" type="button" id="history-prev">Anterior</button>
                <span id="history-page"></span>
                <button class="btn-ghost" type="button" id="history-next">Próxima</button>
            </div>
        </article>
    </section>
    <div id="toast" class="toast" hidden></div>
@endsection

@section('scripts')
    <script src="{{ asset('js/admin.js') }}"></script>
@endsection
