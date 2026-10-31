<?php
declare(strict_types=1);
defined('CULINA_ROOT') || exit;

const DB_SCHEMA_V1 = <<<'SQL'
CREATE TABLE settings (
    key TEXT PRIMARY KEY,
    value TEXT NOT NULL
);
CREATE TABLE categories (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL UNIQUE COLLATE NOCASE,
    sort INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE aisles (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL UNIQUE COLLATE NOCASE,
    sort INTEGER NOT NULL DEFAULT 0
);
CREATE TABLE ingredients (
    id INTEGER PRIMARY KEY,
    name TEXT NOT NULL UNIQUE COLLATE NOCASE,
    unit TEXT NOT NULL DEFAULT '',
    aisle_id INTEGER REFERENCES aisles(id) ON DELETE SET NULL,
    pantry INTEGER NOT NULL DEFAULT 0,
    conversions TEXT NOT NULL DEFAULT ''
);
CREATE TABLE recipes (
    id INTEGER PRIMARY KEY,
    title TEXT NOT NULL,
    servings INTEGER NOT NULL DEFAULT 4,
    category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    effort TEXT NOT NULL DEFAULT 'mittel',
    seasons TEXT NOT NULL DEFAULT '',
    meal TEXT NOT NULL DEFAULT 'abend',
    protein TEXT NOT NULL DEFAULT '',
    steps TEXT NOT NULL DEFAULT '',
    notes TEXT NOT NULL DEFAULT '',
    source TEXT NOT NULL DEFAULT '',
    favorite INTEGER NOT NULL DEFAULT 0,
    archived INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE recipe_ingredients (
    id INTEGER PRIMARY KEY,
    recipe_id INTEGER NOT NULL REFERENCES recipes(id) ON DELETE CASCADE,
    ingredient_id INTEGER NOT NULL REFERENCES ingredients(id),
    quantity REAL,
    unit TEXT NOT NULL DEFAULT '',
    note TEXT NOT NULL DEFAULT '',
    optional INTEGER NOT NULL DEFAULT 0,
    sort INTEGER NOT NULL DEFAULT 0
);
CREATE INDEX idx_ri_recipe ON recipe_ingredients(recipe_id);
CREATE INDEX idx_ri_ingredient ON recipe_ingredients(ingredient_id);
CREATE TABLE plans (
    id INTEGER PRIMARY KEY,
    week_start TEXT NOT NULL UNIQUE,
    persons INTEGER NOT NULL DEFAULT 6,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
);
CREATE TABLE plan_days (
    id INTEGER PRIMARY KEY,
    plan_id INTEGER NOT NULL REFERENCES plans(id) ON DELETE CASCADE,
    day TEXT NOT NULL,
    meal TEXT NOT NULL DEFAULT 'abend',
    recipe_id INTEGER REFERENCES recipes(id) ON DELETE SET NULL,
    free_title TEXT NOT NULL DEFAULT '',
    free_category_id INTEGER REFERENCES categories(id) ON DELETE SET NULL,
    free_items TEXT NOT NULL DEFAULT '',
    persons INTEGER,
    locked INTEGER NOT NULL DEFAULT 0,
    skip INTEGER NOT NULL DEFAULT 0,
    UNIQUE (plan_id, day, meal)
);
CREATE INDEX idx_pd_recipe ON plan_days(recipe_id);
CREATE TABLE shop_extras (
    id INTEGER PRIMARY KEY,
    plan_id INTEGER NOT NULL REFERENCES plans(id) ON DELETE CASCADE,
    name TEXT NOT NULL,
    quantity REAL,
    unit TEXT NOT NULL DEFAULT '',
    aisle_id INTEGER REFERENCES aisles(id) ON DELETE SET NULL
);
CREATE TABLE shop_hidden (
    plan_id INTEGER NOT NULL REFERENCES plans(id) ON DELETE CASCADE,
    item_key TEXT NOT NULL,
    PRIMARY KEY (plan_id, item_key)
);
CREATE TABLE login_attempts (
    ip TEXT NOT NULL,
    at INTEGER NOT NULL
);
SQL;

const SEED_CATEGORIES = [
    'Teigwaren', 'Reis', 'Kartoffeln', 'Brot und Teig', 'Mais', 'Hülsenfrüchte',
    'Getreide', 'Eier und Mehlspeisen', 'Ohne Sättigungsbeilage',
];

const SEED_AISLES = [
    'Früchte und Gemüse', 'Brot und Backwaren', 'Fleisch und Fisch', 'Käse', 'Milchprodukte und Eier',
    'Kühlregal', 'Teigwaren, Reis, Konserven', 'Öl, Gewürze, Saucen', 'Backen und Frühstück',
    'Tiefkühl', 'Getränke', 'Non-Food',
];

/** [name, base unit, aisle, pantry, conversions] */
const SEED_INGREDIENTS = [
    ['Zwiebel', 'Stk', 'Früchte und Gemüse', 0, ''],
    ['Knoblauch', 'Zehe', 'Früchte und Gemüse', 0, ''],
    ['Kartoffeln', 'g', 'Früchte und Gemüse', 0, 'Stk=150'],
    ['Rüebli', 'g', 'Früchte und Gemüse', 0, 'Stk=100'],
    ['Tomaten', 'g', 'Früchte und Gemüse', 0, 'Stk=120'],
    ['Peperoni', 'Stk', 'Früchte und Gemüse', 0, ''],
    ['Zucchetti', 'g', 'Früchte und Gemüse', 0, 'Stk=250'],
    ['Lauch', 'g', 'Früchte und Gemüse', 0, 'Stk=300'],
    ['Champignons', 'g', 'Früchte und Gemüse', 0, ''],
    ['Zitrone', 'Stk', 'Früchte und Gemüse', 0, ''],
    ['Petersilie', 'Bund', 'Früchte und Gemüse', 0, ''],
    ['Kopfsalat', 'Stk', 'Früchte und Gemüse', 0, ''],
    ['Äpfel', 'Stk', 'Früchte und Gemüse', 0, ''],
    ['Brot', 'g', 'Brot und Backwaren', 0, ''],
    ['Hackfleisch', 'g', 'Fleisch und Fisch', 0, ''],
    ['Pouletbrust', 'g', 'Fleisch und Fisch', 0, ''],
    ['Speckwürfeli', 'g', 'Fleisch und Fisch', 0, ''],
    ['Lachsfilet', 'g', 'Fleisch und Fisch', 0, ''],
    ['Reibkäse', 'g', 'Käse', 0, ''],
    ['Parmesan', 'g', 'Käse', 0, ''],
    ['Mozzarella', 'Stk', 'Käse', 0, ''],
    ['Milch', 'ml', 'Milchprodukte und Eier', 0, ''],
    ['Rahm', 'ml', 'Milchprodukte und Eier', 0, ''],
    ['Butter', 'g', 'Milchprodukte und Eier', 0, 'EL=15'],
    ['Eier', 'Stk', 'Milchprodukte und Eier', 0, ''],
    ['Joghurt nature', 'g', 'Milchprodukte und Eier', 0, ''],
    ['Crème fraîche', 'g', 'Milchprodukte und Eier', 0, 'EL=15'],
    ['Kuchenteig', 'Stk', 'Kühlregal', 0, ''],
    ['Tofu', 'g', 'Kühlregal', 0, ''],
    ['Spaghetti', 'g', 'Teigwaren, Reis, Konserven', 0, ''],
    ['Hörnli', 'g', 'Teigwaren, Reis, Konserven', 0, ''],
    ['Risottoreis', 'g', 'Teigwaren, Reis, Konserven', 0, ''],
    ['Basmatireis', 'g', 'Teigwaren, Reis, Konserven', 0, ''],
    ['Polenta', 'g', 'Teigwaren, Reis, Konserven', 0, ''],
    ['Couscous', 'g', 'Teigwaren, Reis, Konserven', 0, ''],
    ['Rote Linsen', 'g', 'Teigwaren, Reis, Konserven', 0, ''],
    ['Pelati', 'Dose', 'Teigwaren, Reis, Konserven', 0, ''],
    ['Kichererbsen', 'Dose', 'Teigwaren, Reis, Konserven', 0, ''],
    ['Tomatenpüree', 'g', 'Teigwaren, Reis, Konserven', 0, 'EL=15'],
    ['Salz', 'TL', 'Öl, Gewürze, Saucen', 1, ''],
    ['Pfeffer', 'Prise', 'Öl, Gewürze, Saucen', 1, ''],
    ['Olivenöl', 'ml', 'Öl, Gewürze, Saucen', 1, ''],
    ['Rapsöl', 'ml', 'Öl, Gewürze, Saucen', 1, ''],
    ['Bouillon', 'TL', 'Öl, Gewürze, Saucen', 1, ''],
    ['Paprika edelsüss', 'TL', 'Öl, Gewürze, Saucen', 1, ''],
    ['Currypulver', 'TL', 'Öl, Gewürze, Saucen', 1, ''],
    ['Mehl', 'g', 'Backen und Frühstück', 1, 'EL=10'],
    ['Zucker', 'g', 'Backen und Frühstück', 1, 'EL=15, TL=5'],
    ['Erbsen tiefgekühlt', 'g', 'Tiefkühl', 0, ''],
    ['Blattspinat tiefgekühlt', 'g', 'Tiefkühl', 0, ''],
];

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $dir = (string) config('data_dir');
    if (!is_dir($dir) && !mkdir($dir, 0770, true) && !is_dir($dir)) {
        throw new RuntimeException('Datenverzeichnis kann nicht erstellt werden: ' . $dir);
    }
    $pdo = new PDO('sqlite:' . $dir . '/culina.sqlite');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');
    db_migrate($pdo);
    return $pdo;
}

function q(string $sql, array $params = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st;
}

function db_transaction(callable $fn): mixed
{
    $pdo = db();
    if ($pdo->inTransaction()) {
        return $fn();
    }
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function db_migrate(PDO $pdo): void
{
    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($version >= 1) {
        return;
    }
    $pdo->beginTransaction();
    try {
        $pdo->exec(DB_SCHEMA_V1);
        db_seed($pdo);
        $pdo->exec('PRAGMA user_version = 1');
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function db_seed(PDO $pdo): void
{
    $st = $pdo->prepare('INSERT INTO settings (key, value) VALUES (?, ?)');
    foreach (['persons' => '6', 'max_per_category' => '2', 'avoid_weeks' => '3'] as $k => $v) {
        $st->execute([$k, $v]);
    }
    $st = $pdo->prepare('INSERT INTO categories (name, sort) VALUES (?, ?)');
    foreach (SEED_CATEGORIES as $i => $name) {
        $st->execute([$name, $i + 1]);
    }
    $st = $pdo->prepare('INSERT INTO aisles (name, sort) VALUES (?, ?)');
    $aisleIds = [];
    foreach (SEED_AISLES as $i => $name) {
        $st->execute([$name, $i + 1]);
        $aisleIds[$name] = (int) $pdo->lastInsertId();
    }
    $st = $pdo->prepare('INSERT INTO ingredients (name, unit, aisle_id, pantry, conversions) VALUES (?, ?, ?, ?, ?)');
    foreach (SEED_INGREDIENTS as [$name, $unit, $aisle, $pantry, $conv]) {
        $st->execute([$name, $unit, $aisleIds[$aisle] ?? null, $pantry, $conv]);
    }
}
