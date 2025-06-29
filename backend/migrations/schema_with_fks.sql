PRAGMA foreign_keys = ON;

DROP TABLE IF EXISTS routes;
DROP TABLE IF EXISTS airlines;
DROP TABLE IF EXISTS airports;

CREATE TABLE airports (
                          id INTEGER PRIMARY KEY,
                          name TEXT,
                          city TEXT,
                          country TEXT,
                          iata TEXT NOT NULL UNIQUE,
                          icao TEXT
);

CREATE TABLE airlines (
                          id INTEGER PRIMARY KEY,
                          name TEXT,
                          alias TEXT,
                          iata TEXT,
                          icao TEXT,
                          active TEXT
);

CREATE TABLE routes (
                        id INTEGER PRIMARY KEY AUTOINCREMENT,
                        airline TEXT,
                        airline_id INTEGER,
                        source_airport TEXT NOT NULL,
                        source_airport_id INTEGER,
                        destination_airport TEXT NOT NULL,
                        destination_airport_id INTEGER,
                        codeshare TEXT,
                        stops INTEGER,
                        equipment TEXT,
                        FOREIGN KEY (airline_id) REFERENCES airlines(id),
                        FOREIGN KEY (source_airport) REFERENCES airports(iata),
                        FOREIGN KEY (destination_airport) REFERENCES airports(iata),
                        UNIQUE (source_airport, destination_airport, airline_id)
);
