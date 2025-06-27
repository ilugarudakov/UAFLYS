# Поиск авиамаршрутов

Мини-демо поиска маршрутов между аэропортами с возможностью фильтрации по авиакомпаниям и пересадкам.

---
## Требования:

- **Линукс** (я разрабатывал и тестировал на Ubuntu и Pop-Os)  
- **Docker Engine**: https://docs.docker.com/get-docker/
- **Docker Compose**: https://docs.docker.com/compose/install/

## Архитектура

- **Backend**  
  - Чистый PHP 8.4-CLI, встроенный сервер PHP  
  - База данных — SQLite (`data/data.sqlite`), управление через PDO  
  - Импорт данных и миграции (CSV → таблицы через `SplFileObject`)  
  - Монолитный фронт-контроллер `public/index.php` с маршрутизацией по пути и выдачей JSON-API:  
    - `GET /airports` — аэропорты
    - `GET /airlines` — авиакомпании  
    - `GET /routes?from=…&to=…` — маршруты  
    
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

**Импорт** данных и создание схемы выполняются командой:

```bash 

 docker compose exec backend php import/import.php
 
```
> Контейнеры должны быть запущены  
> В гите лежит уже заполненная база, команда перезальет данные.
---

## Используемые библиотеки и обоснование

- **Чистый PHP 8.4-CLI** без тяжёлых фреймворков  
- **PDO (PHP Data Objects)** для лёгкой и надёжной работы с SQLite  
- **SplFileObject** для встроенного парсинга CSV без сторонних зависимостей  
- **Composer**
- **Bootstrap 5 (CDN)** для быстрого и адаптивного UI  
---

## Формат импорта данных

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