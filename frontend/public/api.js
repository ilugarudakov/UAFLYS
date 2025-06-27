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
    if (!res.ok) {
        const text = await res.text();
        console.error(`Ошибка ${res.status} при запросе ${url}:\n`, text);
        throw new Error(res.statusText);
    }
    return res.json();
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
        sel.add(new Option(a.name, a.code));
    });
}

function renderRoutes(routes) {
    const container = document.getElementById('results');
    if (!routes.length) {
        container.innerHTML = '<p>Маршрутов не найдено.</p>';
        return;
    }
    let html = '<table class="table table-striped">';
    html += `<thead>
    <tr>
      <th>Путь</th>
      <th>Пересадки</th>
      <th>Авиакомпании</th>
    </tr>
  </thead><tbody>`;
    routes.forEach(r => {
        html += `<tr>
      <td>${r.path.join(' → ')}</td>
      <td>${r.stops}</td>
      <td>${r.airlines.join(', ')}</td>
    </tr>`;
    });
    html += '</tbody></table>';
    container.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', async () => {
    try {
        // Подгружаем справочники
        const [airports, airlines] = await Promise.all([
            fetchAirports(),
            fetchAirlines()
        ]);
        populateSelects(airports);
        populateAirlines(airlines);
    } catch (e) {
        console.error('Ошибка загрузки справочников:', e);
    }

    // Вешаем сабмит
    document.getElementById('searchForm').addEventListener('submit', async e => {
        e.preventDefault();
        const from    = document.getElementById('from').value;
        const to      = document.getElementById('to').value;
        const airline = document.getElementById('airline').value;
        const depth   = document.querySelector('input[name=depth]:checked').value;
        try {
            const routes = await fetchRoutes({ from, to, airline, depth });
            renderRoutes(routes);
        } catch (err) {
            console.error('Ошибка поиска маршрутов:', err);
            document.getElementById('results').innerHTML =
                '<p class="text-danger">Не удалось выполнить поиск. Проверьте консоль.</p>';
        }
    });
});
