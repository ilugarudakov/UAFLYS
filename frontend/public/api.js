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

async function fetchRoutes({from, to, airline, depth}) {
    const params = new URLSearchParams({from, to, depth});
    if (airline) params.append('airline', airline);
    const res = await fetch(`${API}/routes?${params}`);
    const text = await res.text();
    try {
        const data = JSON.parse(text);
        if (!res.ok) throw new Error(data.error || res.statusText);
        return data;
    } catch {
        console.error(`Bad response (${res.status}):`, text);
        throw new Error('Неверный формат ответа от сервера');
    }
}

function populateSelects(airports) {
    const from = document.getElementById('from');
    const to = document.getElementById('to');
    from.innerHTML = '<option value="">— Откуда —</option>';
    to.innerHTML = '<option value="">— Куда —</option>';

    airports.forEach(a => {
        const label = `${a.iata} — ${a.name} (${a.city}, ${a.country})`;
        from.add(new Option(label, a.iata));
        to.add(new Option(label, a.iata));
    });
}

function populateAirlines(airlines) {
    const sel = document.getElementById('airline');
    sel.innerHTML = '<option value="">— Все —</option>';
    airlines.forEach(a => {
        sel.add(new Option(`${a.code} — ${a.name}`, a.code));
    });
}

function updateSubmitState() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = !(
        document.getElementById('from').value &&
        document.getElementById('to').value
    );
}

function showError(msg) {
    document.getElementById('errorMsg').textContent = msg;
}

function renderRoutes(routes) {
    console.log('Данные маршрутов:', routes); // <— для отладки
    const container = document.getElementById('results');
    if (!routes.length) {
        container.innerHTML = '<p>Маршрутов не найдено.</p>';
        return;
    }

    let html = `
    <table class="table table-striped">
      <thead>
        <tr>
          <th>Путь (IATA)</th>
          <th>Города</th>
          <th>Пересадки</th>
          <th>Авиакомпании</th>
        </tr>
      </thead>
      <tbody>
  `;

    routes.forEach(r => {
        const codes = r.path.join(' → ');
        const cities = (r.cities || [])
            .map(cityCountry => cityCountry.split(',')[0].trim())
            .join(' → ');
        // Убираем дубли авиакомпаний
        const seen = {};
        (r.airlines || []).forEach((code, i) => {
            if (!seen[code]) {
                seen[code] = r.airlineNames?.[i] || '';
            }
        });
        const airlines = Object.entries(seen)
            .map(([code, name]) => name ? `${code} (${name})` : code)
            .join(', ');
        html += `
      <tr>
        <td>${codes}</td>
        <td>${cities}</td>
        <td>${r.stops}</td>
        <td>${airlines}</td>
      </tr>
    `;
    });

    html += `</tbody></table>`;
    container.innerHTML = html;
}

document.addEventListener('DOMContentLoaded', async () => {
    try {
        const [airports, airlines] = await Promise.all([
            fetchAirports(),
            fetchAirlines()
        ]);
        populateSelects(airports);
        populateAirlines(airlines);
        ['from', 'to'].forEach(id =>
            document.getElementById(id).addEventListener('change', updateSubmitState)
        );
    } catch (e) {
        console.error(e);
        showError('Не удалось загрузить справочники.');
    }

    document.getElementById('searchForm')
        .addEventListener('submit', async e => {
            e.preventDefault();
            showError('');
            const from = document.getElementById('from').value;
            const to = document.getElementById('to').value;
            const airline = document.getElementById('airline').value;
            const depth = document.querySelector('input[name=depth]:checked').value;

            // Предварительная проверка
            if (from === to) {
                showError('Пункт вылета и пункт назначения не могут совпадать');
                return;
            }

            try {
                const routes = await fetchRoutes({from, to, airline, depth});
                renderRoutes(routes);
            } catch (err) {
                console.error(err);
                showError(err.message);
                document.getElementById('results').innerHTML = '';
            }
        });
});
