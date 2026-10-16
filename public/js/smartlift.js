const FLOOR_H = 74;
const state = {
    elevator: null,
    people: [],
    animating: false,
    flow: 0,
};

const $ = (id) => document.getElementById(id);

function kg(value) {
    return Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + ' kg';
}

function initials(name) {
    return name.split(' ').filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase();
}

async function api(url, options = {}) {
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(options.headers || {}),
        },
        ...options,
    });

    const body = await response.json().catch(() => ({}));

    if (!response.ok) {
        const error = new Error(body.message || 'Falha na requisição.');
        error.status = response.status;
        error.body = body;
        throw error;
    }

    return body;
}

function showToast(message, isError = false) {
    const toast = $('toast');
    toast.hidden = false;
    toast.textContent = message;
    toast.classList.toggle('error', isError);
}

function beep() {
    if (!$('sound-toggle').checked) {
        return;
    }

    const AudioCtx = window.AudioContext || window.webkitAudioContext;
    if (!AudioCtx) {
        return;
    }

    const context = new AudioCtx();
    const oscillator = context.createOscillator();
    const gain = context.createGain();
    oscillator.type = 'square';
    oscillator.frequency.value = 420;
    gain.gain.value = 0.04;
    oscillator.connect(gain);
    gain.connect(context.destination);
    oscillator.start();
    oscillator.stop(context.currentTime + 0.28);
    oscillator.onended = () => context.close();
}

function alarm() {
    const building = $('building');
    building.classList.add('alarm');
    beep();
    setTimeout(() => building.classList.remove('alarm'), 1400);
}

function topFor(floor) {
    const { max_floor: max } = state.elevator.elevator;
    return (max - floor) * FLOOR_H + 5;
}

function placeCabin(floor, open) {
    const cabin = $('cabin');
    cabin.style.transition = 'none';
    cabin.style.top = `${topFor(floor)}px`;
    cabin.classList.toggle('open', open);
}

function wait(ms) {
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    return new Promise((resolve) => setTimeout(resolve, motion ? Math.min(ms, 80) : ms));
}

function moveCabin(from, to, duration) {
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const ms = motion ? 80 : duration;

    return new Promise((resolve) => {
        const cabin = $('cabin');
        let done = false;
        const finish = () => {
            if (done) {
                return;
            }
            done = true;
            resolve();
        };

        cabin.style.transition = 'none';
        cabin.style.top = `${topFor(from)}px`;
        requestAnimationFrame(() => {
            cabin.style.transition = `top ${ms}ms linear`;
            cabin.style.top = `${topFor(to)}px`;
            cabin.addEventListener('transitionend', finish, { once: true });
            setTimeout(finish, ms + 60);
        });
    });
}

async function playSteps(animation) {
    if (!animation?.steps?.length) {
        return;
    }

    const cabin = $('cabin');
    if (animation.steps[0]?.type === 'close_doors') {
        cabin.classList.add('open');
    }

    for (const step of animation.steps) {
        if (step.type === 'close_doors') {
            $('status-readout').textContent = 'Fechando portas';
            cabin.classList.remove('open');
            await wait(step.duration_ms);
        }

        if (step.type === 'move') {
            $('status-readout').textContent = step.direction === 'up' ? 'Subindo' : 'Descendo';
            $('direction-readout').textContent = step.direction === 'up' ? 'Subindo' : 'Descendo';
            await moveCabin(step.from, step.to, step.duration_ms);
            $('floor-readout').textContent = step.to;
            markCurrentFloor(step.to);
        }

        if (step.type === 'open_doors') {
            $('status-readout').textContent = 'Abrindo portas';
            cabin.classList.add('open');
            await wait(step.duration_ms);
            $('status-readout').textContent = 'Portas abertas';
        }
    }
}

function markCurrentFloor(floor) {
    document.querySelectorAll('.landing').forEach((row) => {
        row.classList.toggle('current', Number(row.dataset.floor) === Number(floor));
    });
}

function renderBuilding(data) {
    const elevator = data.elevator;
    const calls = new Set((data.pending_calls || []).map((call) => call.requested_floor));
    const floors = [...elevator.floors].reverse();
    const landings = $('landings');
    const well = $('well');

    landings.innerHTML = floors.map((floor) => `
        <div class="landing ${floor === elevator.current_floor ? 'current' : ''}" data-floor="${floor}">
            <span class="num">${floor}</span>
            <span>${floor === elevator.current_floor ? 'Andar atual' : 'Hall'}</span>
            ${calls.has(floor) ? '<span class="bell" title="Chamada pendente"></span>' : '<span></span>'}
        </div>
    `).join('');

    well.style.height = `${floors.length * FLOOR_H}px`;

    const callFloor = $('call-floor');
    const previous = callFloor.value;
    callFloor.innerHTML = elevator.floors.map((floor) => `<option value="${floor}">${floor}</option>`).join('');
    if (previous) {
        callFloor.value = previous;
    }
}

function renderPeople(data) {
    const onboardIds = new Set((data.passengers || []).map((person) => person.person_id));
    const cabin = $('cabin-people');
    cabin.innerHTML = (data.passengers || []).map((person) => `<span class="avatar" title="${person.name}">${initials(person.name)}</span>`).join('');

    $('onboard-list').innerHTML = (data.passengers || []).map((person) => `
        <li>
            <span><strong>${person.name}</strong><br><small>Destino ${person.destination_floor} · ${kg(person.weight_kg)}</small></span>
            <button type="button" data-alight="${person.person_id}" ${data.elevator.door_open && person.destination_floor === data.elevator.current_floor ? '' : 'disabled'}>Sair</button>
        </li>
    `).join('') || '<li>Nenhum passageiro a bordo.</li>';

    const waiting = state.people.filter((person) => !onboardIds.has(person.id));
    $('waiting-list').innerHTML = waiting.map((person) => `
        <li>
            <span>
                <strong>${person.name}</strong><br>
                <small>${person.card_code} · andar ${person.destination_floor} · ${kg(person.weight_kg)}</small>
            </span>
            <button type="button" data-card="${person.card_code}">Usar cartão</button>
        </li>
    `).join('') || '<li>Ninguém aguardando.</li>';

    $('people-list').innerHTML = state.people.map((person) => `<option value="${person.card_code}">${person.name}</option>`).join('');

    $('call-list').innerHTML = (data.pending_calls || []).map((call) => `<li><span>Andar ${call.requested_floor}</span><span>Pendente</span></li>`).join('') || '<li>Nenhuma chamada pendente.</li>';
}

function renderMeters(data) {
    const percent = Math.min(data.capacity.weight_percent, 100);
    const fill = $('weight-fill');
    fill.style.width = `${percent}%`;
    fill.classList.toggle('warn', percent >= 70 && percent < 100);
    fill.classList.toggle('full', percent >= 100);
    $('weight-label').textContent = `${kg(data.current_weight_kg)} de ${kg(data.elevator.max_weight_kg)}`;
    $('count-label').textContent = `${data.passenger_count} de ${data.elevator.max_passengers} pessoas`;
}

function renderReadouts(data) {
    $('elevator-name').textContent = data.elevator.name;
    $('floor-readout').textContent = data.elevator.current_floor;
    $('direction-readout').textContent = data.elevator.direction_label;
    $('status-readout').textContent = data.elevator.status_label;
}

function applyState(data, { snap = true } = {}) {
    state.elevator = data;
    renderBuilding(data);
    renderPeople(data);
    renderMeters(data);
    renderReadouts(data);
    if (snap) {
        placeCabin(data.elevator.current_floor, data.elevator.door_open);
    }
    setBusy(state.animating);
}

function setBusy(busy) {
    ['call-button', 'board-button', 'alight-button', 'depart-button'].forEach((id) => {
        $(id).disabled = busy;
    });
}

function cancelDepart() {
    state.flow += 1;
    $('dwell').hidden = true;
}

function scheduleDepart(ms) {
    cancelDepart();
    const token = state.flow;
    let left = Math.ceil(ms / 1000);
    const dwell = $('dwell');
    dwell.hidden = false;
    dwell.textContent = `Portas abertas. Partida automática em ${left}s.`;

    const ticker = setInterval(() => {
        if (token !== state.flow) {
            clearInterval(ticker);
            return;
        }
        left -= 1;
        if (left <= 0) {
            clearInterval(ticker);
            return;
        }
        dwell.textContent = `Portas abertas. Partida automática em ${left}s.`;
    }, 1000);

    setTimeout(async () => {
        if (token !== state.flow) {
            return;
        }
        dwell.hidden = true;
        await depart();
    }, ms);
}

async function continueFlow(data) {
    if (data.ready_to_alight?.length && data.elevator.door_open) {
        await wait(900);
        await alight(null);
        return;
    }

    if ((data.should_depart || data.queued || data.pending_work) && data.elevator.door_open && data.pending_work) {
        scheduleDepart(data.should_depart ? 4000 : 2200);
    }
}

async function run(action) {
    if (state.animating) {
        return null;
    }

    cancelDepart();
    state.animating = true;
    setBusy(true);

    try {
        const response = await action();
        showToast(response.message, false);
        applyState(response.data, { snap: !response.data.animation });
        state.animating = true;
        await playSteps(response.data.animation);
        applyState(response.data, { snap: !response.data.animation });
        state.animating = false;
        setBusy(false);
        await continueFlow(response.data);
        return response;
    } catch (error) {
        state.animating = false;
        setBusy(false);
        showToast(error.message, true);
        if (/não autorizado|capacidade|bloqueado|excedid/i.test(error.message || '')) {
            alarm();
        }
        await refresh();
        return null;
    }
}

async function refresh() {
    const [elevator, people] = await Promise.all([
        api('/api/elevator'),
        api('/api/people'),
    ]);
    state.people = people.data;
    applyState(elevator.data);
}

async function previewCard() {
    const code = $('card-code').value.trim();
    const box = $('card-preview');
    if (code.length < 3) {
        box.hidden = true;
        return;
    }

    try {
        const response = await api(`/api/cards/${encodeURIComponent(code)}`);
        const person = response.data;
        box.hidden = false;
        box.innerHTML = `<strong>${person.name}</strong><br>Destino: andar ${person.destination_floor} · ${kg(person.weight_kg)} · ${person.card_status_label}`;
    } catch (error) {
        box.hidden = false;
        box.textContent = error.message;
    }
}

async function alight(personId) {
    await run(() => api('/api/elevator/alight', {
        method: 'POST',
        body: JSON.stringify({ person_id: personId }),
    }));
}

async function depart() {
    await run(() => api('/api/elevator/depart', { method: 'POST', body: '{}' }));
}

async function loadStats() {
    const params = new URLSearchParams();
    if ($('stats-from').value) {
        params.set('from', $('stats-from').value);
    }
    if ($('stats-to').value) {
        params.set('to', $('stats-to').value);
    }
    const query = params.toString() ? `?${params.toString()}` : '';
    const [overview, passengers] = await Promise.all([
        api(`/api/statistics${query}`),
        api(`/api/statistics/passengers${query}`),
    ]);
    const data = overview.data;
    const cards = [
        ['Viagens', data.trips_total],
        ['Subidas', data.ascents_total],
        ['Descidas', data.descents_total],
        ['Pessoas transportadas', data.passengers_transported],
        ['Peso transportado', kg(data.weight_transported_kg)],
        ['Peso médio por viagem', kg(data.average_weight_per_trip_kg)],
        ['Embarques recusados', data.refused_boardings],
        ['Chamadas atendidas', data.calls_attended],
        ['Chamadas pendentes', data.calls_pending],
    ];
    $('stat-grid').innerHTML = cards.map(([label, value]) => `<article class="stat"><span>${label}</span><strong>${value}</strong></article>`).join('');
    $('weight-note').textContent = data.weight_definition;

    const rows = passengers.data.by_destination_floor;
    const max = Math.max(1, ...rows.map((row) => row.passengers));
    $('floor-bars').innerHTML = rows.map((row) => `
        <div class="bar-row">
            <span>Andar ${row.floor}</span>
            <div class="bar-track"><span style="width:${(row.passengers / max) * 100}%"></span></div>
            <strong>${row.passengers}</strong>
        </div>
    `).join('') || '<p>Nenhum embarque no período.</p>';

    const direction = { up: 'Subida', down: 'Descida' };
    $('trip-rows').innerHTML = data.trip_history.map((trip) => `
        <tr>
            <td>${trip.origin_floor}</td>
            <td>${trip.destination_floor}</td>
            <td>${direction[trip.direction] || trip.direction}</td>
            <td>${trip.finished_at ? new Date(trip.finished_at).toLocaleString('pt-BR') : ''}</td>
        </tr>
    `).join('') || '<tr><td colspan="4">Nenhuma viagem concluída.</td></tr>';
}

$('call-button').addEventListener('click', () => {
    run(() => api('/api/elevator/call', {
        method: 'POST',
        body: JSON.stringify({
            requested_floor: Number($('call-floor').value),
            requested_direction: $('call-direction').value || null,
        }),
    }));
});

$('board-button').addEventListener('click', () => {
    run(() => api('/api/elevator/board', {
        method: 'POST',
        body: JSON.stringify({ card_code: $('card-code').value.trim() }),
    }));
});

$('alight-button').addEventListener('click', () => alight(null));
$('depart-button').addEventListener('click', () => depart());
$('card-code').addEventListener('input', () => {
    cancelDepart();
    previewCard();
});
$('card-code').addEventListener('focus', cancelDepart);

document.body.addEventListener('click', (event) => {
    const card = event.target.closest('[data-card]');
    if (card) {
        $('card-code').value = card.dataset.card;
        previewCard();
        cancelDepart();
    }

    const leave = event.target.closest('[data-alight]');
    if (leave) {
        alight(Number(leave.dataset.alight));
    }
});

$('open-stats').addEventListener('click', async () => {
    $('stats-modal').hidden = false;
    await loadStats();
});
$('close-stats').addEventListener('click', () => {
    $('stats-modal').hidden = true;
});
$('reload-stats').addEventListener('click', loadStats);

refresh().catch((error) => showToast(error.message, true));
