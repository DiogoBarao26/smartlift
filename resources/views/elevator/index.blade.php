@extends('layouts.app')

@section('title', 'SmartLift — Elevador')

@section('nav')
    <label class="sound-toggle">
        <input type="checkbox" id="sound-toggle" checked>
        Alerta sonoro
    </label>
    <button class="btn-ghost" type="button" id="open-stats">Estatísticas</button>
    <a class="btn-ghost" href="{{ route('admin') }}">Painel administrativo</a>
@endsection

@section('content')
    <div class="lobby">
        <section class="stage" aria-label="Prédio e elevador">
            <div class="stage-head">
                <div>
                    <p class="eyebrow" id="elevator-name">Elevador</p>
                    <h1>Poço do elevador</h1>
                </div>
                <div class="indicators">
                    <div class="indicator">
                        <span>Andar</span>
                        <strong id="floor-readout">—</strong>
                    </div>
                    <div class="indicator">
                        <span>Direção</span>
                        <strong id="direction-readout">—</strong>
                    </div>
                    <div class="indicator">
                        <span>Estado</span>
                        <strong id="status-readout">—</strong>
                    </div>
                </div>
            </div>

            <div class="building" id="building">
                <div class="landings" id="landings"></div>
                <div class="well" id="well">
                    <div class="cabin" id="cabin">
                        <div class="cabin-people" id="cabin-people"></div>
                        <div class="door door-left"></div>
                        <div class="door door-right"></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="controls">
            <div id="toast" class="toast" hidden></div>
            <p id="dwell" class="dwell" hidden></p>

            <article class="card-block">
                <header>
                    <h2>Chamar elevador</h2>
                    <p>Escolha o andar de origem. O carro segue na direção atual e para no próximo destino antes de inverter.</p>
                </header>
                <div class="inline-fields">
                    <label>
                        Andar
                        <select id="call-floor"></select>
                    </label>
                    <label>
                        Sentido
                        <select id="call-direction">
                            <option value="">Automático</option>
                            <option value="up">Subir</option>
                            <option value="down">Descer</option>
                        </select>
                    </label>
                </div>
                <button class="btn-brass" type="button" id="call-button">Chamar elevador</button>
                <ul class="call-list" id="call-list"></ul>
            </article>

            <article class="card-block">
                <header>
                    <h2>Inserir cartão</h2>
                    <p>As portas precisam estar abertas. O destino autorizado é o do cartão.</p>
                </header>
                <label>
                    Código ou passageiro
                    <input id="card-code" list="people-list" placeholder="Ex.: ANA-1001" autocomplete="off">
                </label>
                <datalist id="people-list"></datalist>
                <div id="card-preview" class="preview" hidden></div>
                <button class="btn-brass" type="button" id="board-button">Inserir cartão</button>
            </article>

            <article class="card-block">
                <header class="split">
                    <h2>Carga</h2>
                    <button class="btn-ghost" type="button" id="depart-button">Fechar portas e partir</button>
                </header>
                <div class="meter">
                    <div class="meter-fill" id="weight-fill"></div>
                </div>
                <p class="meter-label" id="weight-label">0 kg</p>
                <p class="meter-label" id="count-label">0 pessoas</p>
                <h3>A bordo</h3>
                <ul class="people-list" id="onboard-list"></ul>
                <button class="btn-ink" type="button" id="alight-button">Desembarcar</button>
            </article>

            <article class="card-block">
                <h2>Aguardando embarque</h2>
                <ul class="people-list" id="waiting-list"></ul>
            </article>
        </section>
    </div>

    <div class="modal" id="stats-modal" hidden>
        <div class="modal-card" role="dialog" aria-modal="true" aria-labelledby="stats-title">
            <header class="split">
                <div>
                    <p class="eyebrow">SQLite em memória</p>
                    <h2 id="stats-title">Estatísticas</h2>
                </div>
                <button class="btn-ghost" type="button" id="close-stats">Fechar</button>
            </header>
            <div class="inline-fields">
                <label>De <input type="date" id="stats-from"></label>
                <label>Até <input type="date" id="stats-to"></label>
                <button class="btn-ink" type="button" id="reload-stats">Atualizar</button>
            </div>
            <div class="stat-grid" id="stat-grid"></div>
            <p class="hint" id="weight-note"></p>
            <h3>Passageiros por andar de destino</h3>
            <div class="bars" id="floor-bars"></div>
            <h3>Viagens concluídas</h3>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Origem</th>
                            <th>Destino</th>
                            <th>Sentido</th>
                            <th>Conclusão</th>
                        </tr>
                    </thead>
                    <tbody id="trip-rows"></tbody>
                </table>
            </div>
        </div>
    </div>
@endsection

@section('scripts')
    <script src="{{ asset('js/smartlift.js') }}"></script>
@endsection
