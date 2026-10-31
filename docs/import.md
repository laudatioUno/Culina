# Rezepte importieren

Culina importiert Rezepte als JSON. Am einfachsten lässt man sie von Claude aufbereiten:

1. In Culina unter *Rezepte → Importieren* den Prompt kopieren. Er enthält bereits die aktuellen Grundnahrungsmittel und Ladenrubriken.
2. In einem Chat mit Claude den Prompt einfügen und darunter das Rezept: als Text, Foto oder Link. Mehrere Rezepte auf einmal gehen auch.
3. Den JSON-Codeblock aus der Antwort in Culina einfügen und *Vorschau* wählen.
4. In der Vorschau neue Zutaten prüfen: leer lassen = neu anlegen, oder eine bestehende Zutat wählen (z. B. «Zwiebel» für «Zwiebeln»), damit die Einkaufsliste korrekt summiert.

## Format

```json
{
  "title": "Älplermagronen",
  "servings": 4,
  "category": "Teigwaren",
  "effort": "mittel",
  "seasons": ["Herbst", "Winter"],
  "meal": "abend",
  "protein": "Vegetarisch",
  "ingredients": [
    {"name": "Hörnli", "quantity": 300, "unit": "g", "aisle": "Teigwaren, Reis, Konserven"},
    {"name": "Kartoffeln", "quantity": 400, "unit": "g", "note": "geschält, gewürfelt"},
    {"name": "Salz", "quantity": null, "unit": "", "pantry": true}
  ],
  "steps": ["Kartoffeln vorkochen.", "Hörnli dazugeben."],
  "notes": "Mit Apfelmus servieren.",
  "source": "Familienrezept"
}
```

Mehrere Rezepte: `{"recipes": [ … ]}` oder eine Liste `[ … ]`.

| Feld | Pflicht | Werte |
| --- | --- | --- |
| `title` | ja | Text |
| `servings` | nein (4) | Personen, für die die Mengen gelten |
| `category` | ja | ein Grundnahrungsmittel aus den Einstellungen |
| `effort` | nein (mittel) | `schnell`, `mittel`, `aufwendig` |
| `seasons` | nein | `Frühling`, `Sommer`, `Herbst`, `Winter`; leer = ganzjährig |
| `meal` | nein (abend) | `abend`, `mittag`, `beides` |
| `protein` | nein | z. B. `Fleisch`, `Geflügel`, `Fisch`, `Vegetarisch`, `Vegan` |
| `ingredients[].name` | ja | Zutat ohne Menge und Zubereitung |
| `ingredients[].quantity` | nein | Zahl, auch `"1/2"` oder `"1,5"`; `null` = nach Belieben |
| `ingredients[].unit` | nein | `g`, `kg`, `ml`, `dl`, `l`, `EL`, `TL`, `Stk`, `Prise`, `Bund`, `Pkg`, `Dose`, `Zehe` … |
| `ingredients[].note` | nein | z. B. «gehackt» |
| `ingredients[].optional` | nein | `true` / `false` |
| `ingredients[].aisle` | nein | Ladenrubrik, nur für neue Zutaten |
| `ingredients[].pantry` | nein | `true` für Vorratsartikel, nur für neue Zutaten |
| `steps` | nein | Liste oder Text mit einem Schritt pro Zeile |
| `notes`, `source` | nein | Text |

Zutaten dürfen auch als Text kommen, z. B. `"200 g Hackfleisch"`.

Der Export unter *Rezepte* liefert genau dieses Format und eignet sich als Backup.
