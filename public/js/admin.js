const $ = (id) => document.getElementById(id);
let historyPage = 1;
let historyLast = 1;

function showToast(message, isError = false) {
    const toast = $('toast');
    toast.hidden = false;
    toast.textContent = message;
    toast.classList.toggle('error', isError);
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

    if (response.status === 401) {
        window.location.href = '/login';
        throw new Error(body.message || 'Autenticação necessária.');
    }

    if (!response.ok) {
        const details = body.errors ? Object.values(body.errors).flat().join(' ') : '';
        throw new Error([body.message, details].filter(Boolean).join(' '));
    }

    return body;
}

function kg(value) {
    return Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
}

async function loadSettings() {
    const response = await api('/api/elevator');
    const elevator = response.data.elevator;
    $('setting-name').value = elevator.name;
    $('setting-min').value = elevator.min_floor;
    $('setting-max').value = elevator.max_floor;
    $('setting-weight').value = elevator.max_weight_kg;
    $('setting-passengers').value = elevator.max_passengers;
}

async function loadPeople() {
    const response = await api('/api/people');
    $('people-rows').innerHTML = response.data.map((person) => `
        <tr>
            <td>${person.name}</td>
            <td>${person.card_code}</td>
            <td>${person.destination_floor}</td>
            <td>${kg(person.weight_kg)}</td>
            <td><span class="tag ${person.card_status}">${person.card_status_label}</span></td>
            <td>
                <button type="button" data-edit="${person.id}">Editar</button>
                <button type="button" data-status="${person.id}" data-next="${person.card_status === 'active' ? 'blocked' : 'active'}">
                    ${person.card_status === 'active' ? 'Bloquear' : 'Ativar'}
                </button>
            </td>
        </tr>
    `).join('');
    $('people-rows').dataset.people = JSON.stringify(response.data);
}

function resetPersonForm() {
    $('person-form').reset();
    $('person-id').value = '';
    $('person-form-title').textContent = 'Novo cartão';
    $('person-submit').textContent = 'Cadastrar';
    $('person-cancel').hidden = true;
    $('person-error').hidden = true;
}

async function loadHistory() {
    const response = await api(`/api/elevator/history?page=${historyPage}&per_page=12`);
    historyLast = response.data.pagination.last_page;
    $('history-page').textContent = `Página ${response.data.pagination.current_page} de ${historyLast}`;
    $('history-rows').innerHTML = response.data.items.map((event) => `
        <tr>
            <td>${event.occurred_at ? new Date(event.occurred_at).toLocaleString('pt-BR') : ''}</td>
            <td>${event.event_type}</td>
            <td>${event.floor ?? ''}</td>
            <td>${event.description}</td>
        </tr>
    `).join('') || '<tr><td colspan="4">Nenhum evento registrado.</td></tr>';
}

$('settings-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    $('settings-error').hidden = true;
    const form = new FormData(event.currentTarget);

    try {
        const response = await api('/api/elevator/settings', {
            method: 'PATCH',
            body: JSON.stringify({
                name: form.get('name'),
                min_floor: Number(form.get('min_floor')),
                max_floor: Number(form.get('max_floor')),
                max_weight_kg: Number(form.get('max_weight_kg')),
                max_passengers: Number(form.get('max_passengers')),
            }),
        });
        showToast(response.message, false);
    } catch (error) {
        $('settings-error').hidden = false;
        $('settings-error').textContent = error.message;
    }
});

$('person-form').addEventListener('submit', async (event) => {
    event.preventDefault();
    $('person-error').hidden = true;
    const id = $('person-id').value;
    const payload = {
        name: $('person-name').value.trim(),
        card_code: $('person-code').value.trim(),
        destination_floor: Number($('person-floor').value),
        weight_kg: Number($('person-weight').value),
    };

    try {
        const response = await api(id ? `/api/people/${id}` : '/api/people', {
            method: id ? 'PATCH' : 'POST',
            body: JSON.stringify(payload),
        });
        showToast(response.message, false);
        resetPersonForm();
        await loadPeople();
    } catch (error) {
        $('person-error').hidden = false;
        $('person-error').textContent = error.message;
    }
});

$('person-cancel').addEventListener('click', resetPersonForm);

$('people-rows').addEventListener('click', async (event) => {
    const edit = event.target.closest('[data-edit]');
    const status = event.target.closest('[data-status]');
    const people = JSON.parse($('people-rows').dataset.people || '[]');

    if (edit) {
        const person = people.find((item) => item.id === Number(edit.dataset.edit));
        if (!person) {
            return;
        }
        $('person-id').value = person.id;
        $('person-name').value = person.name;
        $('person-code').value = person.card_code;
        $('person-floor').value = person.destination_floor;
        $('person-weight').value = person.weight_kg;
        $('person-form-title').textContent = 'Editar cartão';
        $('person-submit').textContent = 'Salvar alterações';
        $('person-cancel').hidden = false;
    }

    if (status) {
        try {
            const response = await api(`/api/people/${status.dataset.status}/status`, {
                method: 'PATCH',
                body: JSON.stringify({ card_status: status.dataset.next }),
            });
            showToast(response.message, false);
            await loadPeople();
        } catch (error) {
            showToast(error.message, true);
        }
    }
});

$('history-refresh').addEventListener('click', () => loadHistory());
$('history-prev').addEventListener('click', () => {
    if (historyPage > 1) {
        historyPage -= 1;
        loadHistory();
    }
});
$('history-next').addEventListener('click', () => {
    if (historyPage < historyLast) {
        historyPage += 1;
        loadHistory();
    }
});

Promise.all([loadSettings(), loadPeople(), loadHistory()]).catch((error) => showToast(error.message, true));
