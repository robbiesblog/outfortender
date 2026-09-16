-- Email alerts: "tell me when something matching this appears".
--
-- Free and unlimited, because it is the exact feature the paid competition
-- charges for, and it costs almost nothing to run.
--
-- Two deliberate choices about people's data:
--   - double opt-in. A row is worthless until the person clicks the link in the
--     confirmation email, so an address typed in by somebody else never
--     receives anything.
--   - we store a hash of the IP, never the address itself. It exists only to
--     rate-limit sign-ups, and a hash does that just as well.

CREATE TABLE IF NOT EXISTS alerts (
    id              INTEGER PRIMARY KEY AUTOINCREMENT,
    email           TEXT NOT NULL,
    token           TEXT NOT NULL UNIQUE,     -- confirm and unsubscribe both use this
    filter_country  TEXT,                     -- ISO alpha-2, or null for everywhere
    filter_division TEXT,                     -- CPV division, or null for everything
    filter_q        TEXT,                     -- free text, or null
    lang            TEXT NOT NULL DEFAULT 'en',
    created_at      TEXT NOT NULL,
    confirmed_at    TEXT,                     -- null until they click the link
    unsubscribed_at TEXT,
    last_sent_at    TEXT,
    send_count      INTEGER NOT NULL DEFAULT 0,
    ip_hash         TEXT
);

CREATE UNIQUE INDEX IF NOT EXISTS ux_alert_unique
    ON alerts (email, IFNULL(filter_country,''), IFNULL(filter_division,''), IFNULL(filter_q,''));
CREATE INDEX IF NOT EXISTS ix_alert_due ON alerts (confirmed_at, unsubscribed_at, last_sent_at);
CREATE INDEX IF NOT EXISTS ix_alert_rate ON alerts (ip_hash, created_at);
