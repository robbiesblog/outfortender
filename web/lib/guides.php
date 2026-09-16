<?php
/**
 * Country reference guides.
 *
 * These exist so a country page is a page worth reading rather than a list with
 * a heading. Two halves:
 *
 *  - written guidance, kept to what we can actually stand behind: which register
 *    publishes the notices, what language they arrive in, what we do NOT cover,
 *    and where bidding really happens. No invented thresholds, no legal advice.
 *  - live figures computed from our own database, which no competitor can copy
 *    because they come from the data we hold.
 *
 * Published in the site's own voice, not under a personal byline: it is
 * reference material, not commentary.
 */

declare(strict_types=1);

/** Written guidance per country. Anything absent falls back to the source note. */
function oft_guide(string $code): ?array
{
    static $guides = [
        'DE' => [
            'intro' => 'Germany runs the largest public procurement market in the European Union, and publishes more tenders to the EU register than any other member state.',
            'notes' => [
                'German contracting authorities publish above-threshold notices to TED, the EU register, which is where our German listings come from.',
                'Contracts below the EU thresholds are published on federal, state and municipal portals instead. We do not cover those yet, so treat this as the large-contract view of Germany rather than the whole market.',
                'Notices are usually written in German. Bidding documents and correspondence are normally expected in German too, even where the notice itself has an English summary.',
            ],
        ],
        'PL' => [
            'intro' => 'Poland publishes a high volume of EU-level tenders, second only to Germany in most weeks, spread across national, regional and municipal buyers.',
            'notes' => [
                'Polish above-threshold notices reach TED, which is our source. Below-threshold contracts go to the national platform and are not included here.',
                'Notices are published in Polish. Expect to submit in Polish unless the notice says otherwise.',
            ],
        ],
        'FR' => [
            'intro' => 'France combines a large central-government procurement programme with thousands of separately purchasing communes and departments.',
            'notes' => [
                'We hold both halves of the French market: above-threshold notices through TED, and national notices below the EU thresholds through BOAMP.',
                'A large French contract is advertised in both places. We keep only the TED copy of those, so a contract appears once rather than twice under two references.',
                'Notices are in French, and submissions are normally expected in French.',
            ],
        ],
        'GB' => [
            'intro' => 'The United Kingdom is the one country where we hold both the large and the small contracts, because it publishes both through open registers.',
            'notes' => [
                'Above-threshold notices come from Find a Tender; lower-value opportunities come from Contracts Finder. We take both, so the UK view here is unusually complete.',
                'A contract can legitimately appear on both registers. Where that happens you will see two entries, because they are genuinely two notices.',
                'Everything is published in English, and bidding happens on the buyer\'s own portal.',
            ],
        ],
        'CA' => [
            'intro' => 'Canada publishes federal and participating provincial tenders through a single open register, in both official languages.',
            'notes' => [
                'Our Canadian listings come from CanadaBuys, which carries English and French versions of every notice. Where a notice has both, we hold both.',
                'Canada classifies its tenders using its own categories and UNSPSC codes rather than the European CPV system we use for category pages, so most Canadian tenders here have no category yet.',
                'Some notices are hosted on MERX rather than CanadaBuys; the link on each page goes wherever the official notice actually lives.',
            ],
        ],
        'CO' => [
            'intro' => 'Colombia publishes every stage of every public procurement process to a single national platform, which makes it one of the more transparent markets we cover.',
            'notes' => [
                'Our Colombian listings come from SECOP II, through the national open data portal.',
                'We list only processes that are genuinely open for offers and carry a submission deadline. Most records on the platform are directly negotiated contracts, which are published for transparency rather than as opportunities to bid.',
                'Notices are in Spanish, and values are in Colombian pesos.',
            ],
        ],
        'ES' => [
            'intro' => 'Spain publishes through a national platform and a set of regional ones, with the larger contracts reaching the EU register.',
            'notes' => [
                'Spanish above-threshold notices are published to TED, our source here.',
                'Notices are in Spanish, and in some autonomous communities in a co-official language as well.',
            ],
        ],
        'IT' => [
            'intro' => 'Italy\'s public buying is spread across national bodies, regions and a large number of municipal authorities.',
            'notes' => [
                'Italian above-threshold notices reach TED, which is our source.',
                'Notices are in Italian, and submissions are normally expected in Italian.',
            ],
        ],
    ];
    return $guides[strtoupper($code)] ?? null;
}

/**
 * What we can say about any country from the data itself.
 * Accurate by construction, and it updates every hour without anyone writing a word.
 */
function oft_country_stats(string $code): array
{
    $code = strtoupper($code);
    $bind = [':c' => $code];

    $open = (int) oft_value(
        "SELECT COUNT(*) FROM tenders WHERE status = 'open' AND country = :c", $bind);

    $withValue = (int) oft_value(
        "SELECT COUNT(*) FROM tenders WHERE status = 'open' AND country = :c
          AND value_amount IS NOT NULL", $bind);

    // Median lead time: how long bidders typically get between publication and
    // the deadline. This is the number a supplier actually plans around.
    $lead = oft_value(
        "SELECT CAST(julianday(deadline_at) - julianday(published_at) AS INTEGER) AS days
           FROM tenders
          WHERE status = 'open' AND country = :c
            AND deadline_at IS NOT NULL AND published_at IS NOT NULL
            AND julianday(deadline_at) > julianday(published_at)
          ORDER BY days
          LIMIT 1 OFFSET (
            SELECT COUNT(*) / 2 FROM tenders
             WHERE status = 'open' AND country = :c
               AND deadline_at IS NOT NULL AND published_at IS NOT NULL
               AND julianday(deadline_at) > julianday(published_at))", $bind);

    $closingWeek = (int) oft_value(
        "SELECT COUNT(*) FROM tenders WHERE status = 'open' AND country = :c
           AND deadline_at IS NOT NULL AND deadline_at <= :until",
        $bind + [':until' => gmdate('c', time() + 7 * 86400)]);

    return [
        'open' => $open,
        'with_value' => $withValue,
        'median_lead_days' => $lead !== false && $lead !== null ? (int) $lead : null,
        'closing_this_week' => $closingWeek,
        'categories' => oft_query(
            "SELECT category, cpv_division, COUNT(*) AS n FROM tenders
              WHERE status = 'open' AND country = :c AND category IS NOT NULL
              GROUP BY cpv_division ORDER BY n DESC LIMIT 5", $bind),
        'buyers' => oft_query(
            "SELECT buyer_name, COUNT(*) AS n FROM tenders
              WHERE status = 'open' AND country = :c AND buyer_name IS NOT NULL
              GROUP BY buyer_name ORDER BY n DESC LIMIT 5", $bind),
        'sources' => oft_query(
            "SELECT source, COUNT(*) AS n FROM tenders
              WHERE status = 'open' AND country = :c GROUP BY source ORDER BY n DESC", $bind),
    ];
}

/** Human label for each source, for the "where this comes from" line. */
function oft_source_label(string $source): string
{
    return [
        'ted' => 'TED, the European Union\'s official register',
        'fts' => 'Find a Tender, the UK above-threshold register',
        'cf' => 'Contracts Finder, the UK below-threshold register',
        'worldbank' => 'World Bank procurement notices',
        'canadabuys' => 'CanadaBuys, the Canadian federal register',
        'boamp' => 'BOAMP, the French national bulletin',
        'secop' => 'SECOP II, Colombia\'s public procurement platform',
    ][$source] ?? $source;
}
