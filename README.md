# Поиск авиамаршрутов

Мини-демо поиска маршрутов между аэропортами с возможностью фильтрации по авиакомпаниям и пересадкам.

## Требования:

- **Линукс** (я разрабатывал и тестировал на Ubuntu и Pop-Os, по идее должно работать и под виндовс, но не проверял)  
- **Docker Engine**: https://docs.docker.com/get-docker/
- **Docker Compose**: https://docs.docker.com/compose/install/

## Архитектура

- **Backend**  
  - Чистый PHP 8.4-CLI, встроенный сервер PHP  
  - База данных — SQLite (`data/data.sqlite`), управление через PDO  
  - Импорт данных и миграции (CSV → таблицы через `SplFileObject`)  
  - **Front controller** (`public/index.php`) принимает все запросы и делегирует их PSR-4 контроллерам:
  - `GET /airports`  
    — возвращает список аэропортов (IATA, название, город, страна)
  - `GET /airlines`  
    — возвращает список авиакомпаний (IATA, полное название)
  - `GET /routes?from={IATA}&to={IATA}[&airline={IATA}][&depth={0|1|2}]`  
    — ищет маршруты между указанными аэропортами с учётом фильтра по авиакомпании и глубины пересадок (0–2), возвращая JSON с путём, городами и названиями авиакомпаний

  - **PSR-4 репозитории** (`src/Repository/`) инкапсулируют всю работу с БД через PDO:
  - `AirportRepository` — загружает только пять аэропортов по ТЗ (FCO, AYT, OTP, DUS, IST).
  - `AirlineRepository` — возвращает все авиакомпании с валидным двухбуквенным IATA-кодом.
  - `RouteRepository` — реализует поиск маршрутов с 0–2 пересадками, собирая из БД путь, города и названия авиакомпаний и формируя структуру для API.

- **Frontend**  
  - Статическая HTML+JS-страница с Bootstrap 5 по CDN
  - Простые `fetch`-запросы к API, заполнение `<select>` и рендер таблицы результатов
---

## Запуск

1. Склонируйте репозиторий и перейдите в корень:
   ```bash
   git clone https://github.com/ilugarudakov/UAFLYS.git uaflys-demo
   cd uaflys-demo
   ```

2. Запустите проект одной командой:
   ```bash
   docker compose up --build -d
   ```
   - **Backend API**: http://localhost:8000  
   - **Frontend UI**: http://localhost:3000  

---

## Используемые библиотеки и обоснование

- **Чистый PHP 8.4-CLI** без тяжёлых фреймворков  
- **PDO (PHP Data Objects)** для лёгкой и надёжной работы с SQLite  
- **SplFileObject** для встроенного парсинга CSV без сторонних зависимостей  
- **Composer** для зависимостей и автолоад
- **Bootstrap 5 (CDN)** для быстрого и адаптивного UI  
---

## Формат импорта данных и источники
**Импорт** данных и создание схемы выполняются командой:

```bash 
 docker compose exec backend php import/import.php
```
> Контейнеры должны быть запущены  
> В гите лежит уже заполненная база, команда перезальет данные.

В папке `backend/import/raw/` лежат три CSV-файла с полями (OpenFlights):

1. **airports.dat**  
   Колонки (используются первые 6):  
   ```
   Airport ID,Name,City,Country,IATA,ICAO,...
   ```
  
   - **Фильтр**: только IATA в {FCO, AYT, OTP, DUS, IST}.

2. **airlines.dat**  
   Колонки (используются первые 8):  
   ```
   Airline ID,Name,Alias,IATA,ICAO,Callsign,Country,Active
   ```
   Импортируются соответствующие поля в таблицу `airlines`.

3. **routes.dat**  
   Колонки (используются первые 9):  
   ```
   Airline,Airline ID,Source airport,Source airport ID,Destination airport,Destination airport ID,Codeshare,Stops,Equipment
   ```
   Импортирует маршруты **только** между допустимыми аэропортами (см. выше). Все прочие записи отбрасываются.

---