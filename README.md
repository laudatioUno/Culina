# Culina

Menüplaner für die Familie: Rezepte sammeln, die Woche abwechslungsreich planen und daraus die Einkaufsliste rechnen.

- **Rezepte** im Tool erfassen oder als JSON importieren (aufbereitet im Chat mit Claude)
- **Wochenplan** Montag bis Sonntag, automatisch gewürfelt mit Abwechslung über das Grundnahrungsmittel
- **Einkaufsliste** skaliert auf die Personenzahl, summiert, nach Migros-Laufweg sortiert, als PDF druckbar

PHP + SQLite, ohne Framework und ohne externe Dienste.

## Installation

Voraussetzungen: PHP 8.2 oder neuer mit `pdo_sqlite`, `mbstring`, `iconv` (und `zlib` für kleinere PDFs).

1. Neuestes Release (`culina-x.y.z.zip`) herunterladen und in ein Verzeichnis auf dem Webhoster entpacken, z. B. als Subdomain.
2. Sicherstellen, dass PHP in `data/` schreiben darf.
3. Seite aufrufen und beim ersten Besuch das Passwort festlegen.

Unter Apache schützen die mitgelieferten `.htaccess`-Dateien die Ordner `data/`, `src/`, `views/`, `tests/` und `docs/`. Bei nginx diese Ordner in der Serverkonfiguration sperren oder `data_dir` in `config.php` ausserhalb des Web-Verzeichnisses setzen (siehe `config.sample.php`).

Lokal testen:

```bash
php -S 127.0.0.1:8080
```

## Bedienung

| Bereich | Was es tut |
| --- | --- |
| Woche | Plan würfeln, Tage fixieren, neu würfeln, Rezept per Suche wählen, manuelles Menü eintragen, Personen pro Tag |
| Einkauf | Liste der Woche, Vorhandenes abhaken, Artikel ergänzen («2 l Milch»), PDF drucken |
| Rezepte | Suchen, filtern, erfassen, importieren, Kochansicht, Favoriten, Archiv |
| Zutaten | Basiseinheit, Ladenrubrik, Vorrat und Umrechnungen pflegen, Duplikate zusammenführen |
| Einstellungen | Planungsregeln, Reihenfolge der Ladenrubriken, Grundnahrungsmittel, Passwort |

Rezepte per Chat aufbereiten: siehe [docs/import.md](docs/import.md).

## Backup

Alles liegt in `data/culina.sqlite`. Diese Datei sichern. Zusätzlich exportiert *Rezepte → Alle Rezepte als JSON exportieren* die Rezeptsammlung im Import-Format.

## Entwicklung

```bash
php tests/run.php
```

Die Version steht in `src/version.php`. Wird sie auf `main` erhöht, erstellt die GitHub Action ein Release mit den Notes aus `CHANGELOG.md`.
