const API = 'http://localhost:8000';

async function fetchAirports() {
    const res = await fetch(`${API}/airports`);
    if (!res.ok) throw new Error(res.statusText);
    return res.json();
}

async function fetchAirlines() {
    const res = await fetch(`${API}/airlines`);
    if (!res.ok) throw new Error(res.statusText);
    return res.json();
}

async function fetchRoutes({ from, to, airline, depth }) {
    const params = new URLSearchParams({ from, to, depth });
    if (airline) params.append('airline', airline);
    const url = `${API}/routes?${params}`;
    const res = await fetch(url);
    const txt = await res.text();
    try {
        const data = JSON.parse(txt);
        if (!res.ok) throw new Error(data.error || res.statusText);
        return data;
    } catch {
        console.error(`Ошибка ${res.status} при запросе ${url}:\n`, txt);
        throw new Error('Неверный формат ответа от сервера');
    }
}

function populateSelects(airports) {
    const from = document.getElementById('from');
    const to   = document.getElementById('to');
    from.innerHTML = '<option value="">— Откуда —</option>';
    to  .innerHTML = '<option value="">— Куда —</option>';
    airports.forEach(a => {
        const label = `${a.iata} — ${a.name}`;
        from.add(new Option(label, a.iata));
        to  .add(new Option(label, a.iata));
    });
}

function populateAirlines(airlines) {
    const sel = document.getElementById('airline');
    sel.innerHTML = '<option value="">— Все авиакомпании —</option>';
    airlines.forEach(a => {
        sel.add(new Option(`${a.code} — ${a.name}`, a.code));
    });
}

function updateSubmitState() {
    const btn  = document.getElementById('submitBtn');
    const from = document.getElementById('from').value;
    const to   = document.getElementById('to').value;
    btn.disabled = !(from && to);
}

function showError(msg) {
    document.getElementById('errorMsg').textContent = msg;
}

function renderRoutes(routes) {
    const c = document.getElementById('results');
    if (!routes.length) {
        c.innerHTML = '<p>Маршрутов не найдено.</p>';
        return;
    }
    let html = '<table class="table table-striped"><thead><tr>'
        + '<th>Путь</th><th>Пересадки</th><th>Авиакомпании</th>'
        + '</tr></thead><tbody>';
    routes.forEach(r => {
        html += `<tr>
      <td>${r.path.join(' → ')}</td>
      <td>${r.stops}</td>
      <td>${r.airlines.join(', ')}</td>
    </tr>`;
    });
    html += '</tbody></table>';
    c.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', async () => {
    try {
        const [airports, airlines] = await Promise.all([
            fetchAirports(),
            fetchAirlines()
        ]);
        populateSelects(airports);
        populateAirlines(airlines);
        ['from','to'].forEach(id =>
            document.getElementById(id).addEventListener('change', updateSubmitState)
        );
    } catch (e) {
        console.error('Ошибка загрузки справочников:', e);
        showError('Не удалось загрузить данные. Попробуйте обновить страницу.');
    }

    document.getElementById('searchForm').addEventListener('submit', async e => {
        e.preventDefault();
        showError('');

        const from    = document.getElementById('from').value;
        const to      = document.getElementById('to').value;
        const airline = document.getElementById('airline').value;
        const depth   = document.querySelector('input[name=depth]:checked').value;

        try {
            const routes = await fetchRoutes({ from, to, airline, depth });
            renderRoutes(routes);
        } catch (err) {
            console.error('Ошибка поиска маршрутов:', err);
            showError(err.message);
            document.getElementById('results').innerHTML = '';
        }
    });
});
