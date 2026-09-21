-- OutForTender: one table holds every tender from every source in the world.
-- Kept deliberately flat: the site's queries are all "recent, open, filtered by
-- country or category", and a flat table with the right indexes serves those
-- far faster than a normalised schema on shared hosting.

PRAGMA journal_mode = WAL;

CREATE TABLE IF NOT EXISTS tenders (
    id               TEXT PRIMARY KEY,      -- "source:native-reference"
    source           TEXT NOT NULL,
    source_ref       TEXT,
    url              TEXT NOT NULL,         -- the official notice, always
    title            TEXT NOT NULL,
    title_lang       TEXT,
    titles_json      TEXT,                  -- every language the source gave us
    description      TEXT,
    description_lang TEXT,
    buyer_name       TEXT,
    country          TEXT,                  -- ISO 3166 alpha-2
    country_name     TEXT,
    cpv              TEXT,
    cpv_division     TEXT,                  -- our master category key
    category         TEXT,
    value_amount     REAL,
    value_currency   TEXT,
    procedure        TEXT,
    contract_nature  TEXT,
    published_at     TEXT,                  -- ISO date
    deadline_at      TEXT,                  -- ISO datetime with offset, as published
    deadline_utc     TEXT,                  -- the same moment in UTC: compare THIS, never deadline_at
    status           TEXT NOT NULL DEFAULT 'open',   -- open | closed
    content_hash     TEXT,
    first_seen_at    TEXT NOT NULL,
    last_seen_at     TEXT NOT NULL,
    updated_at       TEXT NOT NULL
);

CREATE INDEX IF NOT EXISTS ix_open_deadline  ON tenders (status, deadline_utc);
CREATE INDEX IF NOT EXISTS ix_country        ON tenders (country, status, deadline_utc);
CREATE INDEX IF NOT EXISTS ix_division       ON tenders (cpv_division, status, deadline_utc);
CREATE INDEX IF NOT EXISTS ix_published      ON tenders (published_at DESC);
CREATE INDEX IF NOT EXISTS ix_source         ON tenders (source, source_ref);

-- Full-text search. SQLite's FTS5 is compiled into DreamHost's PHP, so search
-- is a real index rather than a LIKE scan that would crawl at 100k rows.
-- 'external content' keeps one copy of the data: the index points back at
-- tenders rather than duplicating it.
CREATE VIRTUAL TABLE IF NOT EXISTS tenders_fts USING fts5(
    title, description, buyer_name, country_name, category,
    content = 'tenders',
    content_rowid = 'rowid',
    tokenize = 'unicode61 remove_diacritics 2'
);

-- Keep the index in step with the table.
CREATE TRIGGER IF NOT EXISTS tenders_ai AFTER INSERT ON tenders BEGIN
    INSERT INTO tenders_fts (rowid, title, description, buyer_name, country_name, category)
    VALUES (new.rowid, new.title, new.description, new.buyer_name, new.country_name, new.category);
END;
CREATE TRIGGER IF NOT EXISTS tenders_ad AFTER DELETE ON tenders BEGIN
    INSERT INTO tenders_fts (tenders_fts, rowid, title, description, buyer_name, country_name, category)
    VALUES ('delete', old.rowid, old.title, old.description, old.buyer_name, old.country_name, old.category);
END;
CREATE TRIGGER IF NOT EXISTS tenders_au AFTER UPDATE ON tenders BEGIN
    INSERT INTO tenders_fts (tenders_fts, rowid, title, description, buyer_name, country_name, category)
    VALUES ('delete', old.rowid, old.title, old.description, old.buyer_name, old.country_name, old.category);
    INSERT INTO tenders_fts (rowid, title, description, buyer_name, country_name, category)
    VALUES (new.rowid, new.title, new.description, new.buyer_name, new.country_name, new.category);
END;

-- Every import writes a row here. If the site ever looks stale, this table says
-- when the last delta landed and what was in it.
CREATE TABLE IF NOT EXISTS imports (
    id         INTEGER PRIMARY KEY AUTOINCREMENT,
    ran_at     TEXT NOT NULL,
    delta_file TEXT,
    inserted   INTEGER NOT NULL DEFAULT 0,
    updated    INTEGER NOT NULL DEFAULT 0,
    unchanged  INTEGER NOT NULL DEFAULT 0,
    closed     INTEGER NOT NULL DEFAULT 0,
    skipped    INTEGER NOT NULL DEFAULT 0,
    manifest   TEXT
);
