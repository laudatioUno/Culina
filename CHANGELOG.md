# Changelog

Alle relevanten Änderungen an Culina. Die Releases auf GitHub übernehmen den Abschnitt der jeweiligen Version.

## [1.0.0] – 2026-11-01

Erste Version: Rezepte erfassen, Woche planen, Einkaufsliste als PDF.

### Rezepte
- Rezepte im Tool erfassen und bearbeiten, Zutaten mit Autovervollständigung aus dem Katalog
- Import von Rezepten als JSON (einzeln oder mehrere), mit Vorschau, Validierung, Duplikat-Hinweis und Zuordnung neuer Zutaten
- Prompt-Vorlage, um Rezepte in einem Chat mit Claude ins Import-Format zu bringen
- Suche nach Titel und Zutat, Filter nach Grundnahrungsmittel, Aufwand, Saison und Archiv
- Favoriten, Archivieren statt Löschen, JSON-Export einzeln oder als Backup
- Kochansicht mit Mengen für die gewählte Personenzahl und «Bildschirm wach halten»

### Wochenplan
- Woche Montag bis Sonntag, Abendessen, Standard 6 Personen, pro Tag anpassbar
- Generator mit Regeln: jedes Rezept einmal pro Woche, Rezepte der letzten Wochen meiden, gleiches Grundnahrungsmittel max. N-mal und nie an zwei Tagen hintereinander; Regeln werden gelockert, wenn nichts passt
- Tage fixieren, einzeln neu würfeln, per Suche wählen, auswärts markieren
- Manuelle Menüs als Freitext, optional mit Grundnahrungsmittel und Zutaten für die Einkaufsliste
- Hinweise bei Regelverletzungen, Verlauf aller geplanten Wochen

### Einkaufsliste
- Mengen nach Personen skaliert, gleiche Zutaten summiert und Einheiten umgerechnet (g/kg, ml/dl/l, EL/TL, eigene Umrechnungen wie 1 Stk = 150 g)
- Sortierung nach Ladenrubriken in frei einstellbarer Laufreihenfolge (Vorbelegung Migros)
- Vorratsartikel separat unter «Vorrat prüfen»
- Artikel manuell ergänzen, ändern, entfernen; Vorhandenes abhaken
- Einkaufsliste als A4-PDF mit Menüübersicht und Kästchen zum Abhaken

### Verwaltung
- Ein Account, ganze Seite passwortgeschützt, Sperre nach 5 Fehlversuchen
- Zutatenkatalog mit Basiseinheit, Rubrik, Vorrat-Flag, Umrechnungen und Zusammenführen
- Grundnahrungsmittel und Ladenrubriken umbenennen, sortieren, ergänzen
- PHP + SQLite, keine externen Dienste; Version im Footer, Release per GitHub Action
