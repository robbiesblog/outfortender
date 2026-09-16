<?php
/**
 * Language handling.
 *
 * English lives at the root; every other language sits under its own prefix
 * (/de/, /fr/ ...). Each page declares the whole set through hreflang, so a
 * search engine can offer a German reader the German page.
 *
 * What a reader actually gets translated:
 *   - the interface, always
 *   - category names, always: the EU publishes official CPV wording in every
 *     one of these languages
 *   - country names, always: from CLDR
 *   - the tender's own title, where the source published one in that language.
 *     TED gives all 24 EU languages; CanadaBuys gives English and French.
 *   - the description stays in the language the buyer wrote it in, and we say so.
 *
 * Nothing here machine-translates anything. What we show in another language is
 * what an official body actually published in that language.
 */

declare(strict_types=1);

require_once __DIR__ . '/i18n-data.php';

const OFT_LANGS = [
    'en' => 'English',
    'de' => 'Deutsch',
    'fr' => 'Français',
    'es' => 'Español',
    'it' => 'Italiano',
    'pl' => 'Polski',
    'pt' => 'Português',
    'nl' => 'Nederlands',
];

const OFT_DEFAULT_LANG = 'en';

/**
 * What a language calls itself. Tender text arrives in far more languages than
 * we publish in, and a language's own name is the one label that reads correctly
 * to everyone, whatever page it appears on.
 */
const OFT_LANG_NAMES = [
    'bg' => 'български', 'cs' => 'čeština', 'da' => 'dansk', 'de' => 'Deutsch',
    'el' => 'ελληνικά', 'en' => 'English', 'es' => 'español', 'et' => 'eesti',
    'fi' => 'suomi', 'fr' => 'français', 'ga' => 'Gaeilge', 'hr' => 'hrvatski',
    'hu' => 'magyar', 'it' => 'italiano', 'lt' => 'lietuvių', 'lv' => 'latviešu',
    'mt' => 'Malti', 'nl' => 'Nederlands', 'pl' => 'polski', 'pt' => 'português',
    'ro' => 'română', 'sk' => 'slovenčina', 'sl' => 'slovenščina', 'sv' => 'svenska',
    'ar' => 'العربية', 'ru' => 'русский', 'uk' => 'українська', 'zh' => '中文',
    'no' => 'norsk', 'is' => 'íslenska', 'tr' => 'Türkçe',
];

function oft_lang_name(?string $code): ?string
{
    if (!$code) {
        return null;
    }
    return OFT_LANG_NAMES[strtolower($code)] ?? strtoupper($code);
}

/** Interface strings. English is the key, every other language a full set. */
const OFT_STRINGS = [
    'de' => [
        'Language of this notice: %s. The title and description are shown exactly as the buyer published them.' => 'Sprache dieser Bekanntmachung: %s. Titel und Beschreibung erscheinen genau so, wie der Auftraggeber sie veröffentlicht hat.',
        'How public tendering works in %s' => 'Wie die öffentliche Auftragsvergabe in %s funktioniert',
        'Open now' => 'Derzeit offen',
        'Closing this week' => 'Endet diese Woche',
        'Typical time to bid' => 'Übliche Angebotsfrist',
        'With a published value' => 'Mit angegebenem Wert',
        '%d days' => '%d Tage',
        'Most common right now' => 'Derzeit am häufigsten',
        'Buyers publishing most often' => 'Auftraggeber mit den meisten Ausschreibungen',
        'Tenders for %s reach us through %s.' => 'Ausschreibungen für %s erreichen uns über %s.',
        'Each listing links to the official notice, which is where bidding happens and which is always the authoritative version.' => 'Jeder Eintrag verweist auf die offizielle Bekanntmachung, über die die Angebotsabgabe läuft und die stets maßgeblich ist.',
        'Out For Tender' => 'Out For Tender',
        'Search tenders' => 'Ausschreibungen suchen',
        'Search' => 'Suchen',
        'Home' => 'Startseite',
        'Countries' => 'Länder',
        'Categories' => 'Kategorien',
        'Closing soon' => 'Endet bald',
        'Just published' => 'Neu veröffentlicht',
        'By country' => 'Nach Land',
        'By category' => 'Nach Kategorie',
        'All countries' => 'Alle Länder',
        'All categories' => 'Alle Kategorien',
        'Buyer' => 'Auftraggeber',
        'Country' => 'Land',
        'Category' => 'Kategorie',
        'Value' => 'Auftragswert',
        'Deadline' => 'Frist',
        'Published' => 'Veröffentlicht',
        'Procedure' => 'Verfahren',
        'CPV code' => 'CPV-Code',
        'Closed' => 'Beendet',
        'Closes today' => 'Endet heute',
        'Closes tomorrow' => 'Endet morgen',
        'Closes in %d days' => 'Endet in %d Tagen',
        'Closes %s' => 'Endet am %s',
        'No deadline given' => 'Keine Frist angegeben',
        'open tenders' => 'offene Ausschreibungen',
        'open tender' => 'offene Ausschreibung',
        'Read the official notice and bid' => 'Offizielle Bekanntmachung lesen und bieten',
        'Bidding always happens on the buyer\'s own portal, never here.' => 'Die Angebotsabgabe erfolgt immer auf dem Portal des Auftraggebers, niemals hier.',
        'What the buyer is asking for' => 'Was der Auftraggeber sucht',
        'This tender in other languages' => 'Diese Ausschreibung in anderen Sprachen',
        'Public tenders in %s' => 'Öffentliche Ausschreibungen in %s',
        'Public tenders from around the world, free to read' => 'Öffentliche Ausschreibungen aus aller Welt, kostenlos lesbar',
        'Language' => 'Sprache',
        'This description is in the language the buyer published it in.' => 'Diese Beschreibung ist in der Sprache verfasst, in der der Auftraggeber sie veröffentlicht hat.',
    ],
    'fr' => [
        'Language of this notice: %s. The title and description are shown exactly as the buyer published them.' => 'Langue de cet avis : %s. Le titre et la description sont affichés tels que publiés par l\'acheteur.',
        'How public tendering works in %s' => 'Comment fonctionne la commande publique en %s',
        'Open now' => 'Ouverts actuellement',
        'Closing this week' => 'Clôturent cette semaine',
        'Typical time to bid' => 'Délai habituel pour répondre',
        'With a published value' => 'Avec montant publié',
        '%d days' => '%d jours',
        'Most common right now' => 'Les plus fréquents actuellement',
        'Buyers publishing most often' => 'Acheteurs les plus actifs',
        'Tenders for %s reach us through %s.' => 'Les appels d\'offres pour %s nous parviennent via %s.',
        'Each listing links to the official notice, which is where bidding happens and which is always the authoritative version.' => 'Chaque annonce renvoie à l\'avis officiel, où se fait la soumission et qui fait toujours foi.',
        'Search tenders' => 'Rechercher des appels d\'offres',
        'Search' => 'Rechercher',
        'Home' => 'Accueil',
        'Countries' => 'Pays',
        'Categories' => 'Catégories',
        'Closing soon' => 'Bientôt clôturés',
        'Just published' => 'Publiés récemment',
        'By country' => 'Par pays',
        'By category' => 'Par catégorie',
        'All countries' => 'Tous les pays',
        'All categories' => 'Toutes les catégories',
        'Buyer' => 'Acheteur',
        'Country' => 'Pays',
        'Category' => 'Catégorie',
        'Value' => 'Montant',
        'Deadline' => 'Date limite',
        'Published' => 'Publié le',
        'Procedure' => 'Procédure',
        'CPV code' => 'Code CPV',
        'Closed' => 'Clôturé',
        'Closes today' => 'Clôture aujourd\'hui',
        'Closes tomorrow' => 'Clôture demain',
        'Closes in %d days' => 'Clôture dans %d jours',
        'Closes %s' => 'Clôture le %s',
        'No deadline given' => 'Aucune date limite indiquée',
        'open tenders' => 'appels d\'offres ouverts',
        'open tender' => 'appel d\'offres ouvert',
        'Read the official notice and bid' => 'Lire l\'avis officiel et soumissionner',
        'Bidding always happens on the buyer\'s own portal, never here.' => 'La soumission se fait toujours sur le portail de l\'acheteur, jamais ici.',
        'What the buyer is asking for' => 'Ce que l\'acheteur demande',
        'This tender in other languages' => 'Cet appel d\'offres dans d\'autres langues',
        'Public tenders in %s' => 'Appels d\'offres publics en %s',
        'Public tenders from around the world, free to read' => 'Appels d\'offres publics du monde entier, en accès libre',
        'Language' => 'Langue',
        'This description is in the language the buyer published it in.' => 'Cette description est rédigée dans la langue utilisée par l\'acheteur.',
    ],
    'es' => [
        'Language of this notice: %s. The title and description are shown exactly as the buyer published them.' => 'Idioma de este anuncio: %s. El título y la descripción se muestran tal como los publicó el órgano de contratación.',
        'How public tendering works in %s' => 'Cómo funciona la contratación pública en %s',
        'Open now' => 'Abiertas ahora',
        'Closing this week' => 'Cierran esta semana',
        'Typical time to bid' => 'Plazo habitual para licitar',
        'With a published value' => 'Con importe publicado',
        '%d days' => '%d días',
        'Most common right now' => 'Lo más frecuente ahora',
        'Buyers publishing most often' => 'Órganos que más publican',
        'Tenders for %s reach us through %s.' => 'Las licitaciones de %s nos llegan a través de %s.',
        'Each listing links to the official notice, which is where bidding happens and which is always the authoritative version.' => 'Cada anuncio enlaza al anuncio oficial, donde se presentan las ofertas y que siempre prevalece.',
        'Search tenders' => 'Buscar licitaciones',
        'Search' => 'Buscar',
        'Home' => 'Inicio',
        'Countries' => 'Países',
        'Categories' => 'Categorías',
        'Closing soon' => 'Cierran pronto',
        'Just published' => 'Recién publicadas',
        'By country' => 'Por país',
        'By category' => 'Por categoría',
        'All countries' => 'Todos los países',
        'All categories' => 'Todas las categorías',
        'Buyer' => 'Órgano de contratación',
        'Country' => 'País',
        'Category' => 'Categoría',
        'Value' => 'Importe',
        'Deadline' => 'Fecha límite',
        'Published' => 'Publicada',
        'Procedure' => 'Procedimiento',
        'CPV code' => 'Código CPV',
        'Closed' => 'Cerrada',
        'Closes today' => 'Cierra hoy',
        'Closes tomorrow' => 'Cierra mañana',
        'Closes in %d days' => 'Cierra en %d días',
        'Closes %s' => 'Cierra el %s',
        'No deadline given' => 'Sin fecha límite indicada',
        'open tenders' => 'licitaciones abiertas',
        'open tender' => 'licitación abierta',
        'Read the official notice and bid' => 'Leer el anuncio oficial y licitar',
        'Bidding always happens on the buyer\'s own portal, never here.' => 'La presentación de ofertas se realiza siempre en el portal del órgano de contratación, nunca aquí.',
        'What the buyer is asking for' => 'Qué solicita el órgano de contratación',
        'This tender in other languages' => 'Esta licitación en otros idiomas',
        'Public tenders in %s' => 'Licitaciones públicas en %s',
        'Public tenders from around the world, free to read' => 'Licitaciones públicas de todo el mundo, de acceso libre',
        'Language' => 'Idioma',
        'This description is in the language the buyer published it in.' => 'Esta descripción está en el idioma en que la publicó el órgano de contratación.',
    ],
    'it' => [
        'Language of this notice: %s. The title and description are shown exactly as the buyer published them.' => 'Lingua di questo bando: %s. Titolo e descrizione sono mostrati esattamente come pubblicati dalla stazione appaltante.',
        'How public tendering works in %s' => 'Come funzionano gli appalti pubblici in %s',
        'Open now' => 'Aperte ora',
        'Closing this week' => 'In scadenza questa settimana',
        'Typical time to bid' => 'Tempo tipico per partecipare',
        'With a published value' => 'Con importo pubblicato',
        '%d days' => '%d giorni',
        'Most common right now' => 'Più frequenti in questo momento',
        'Buyers publishing most often' => 'Stazioni appaltanti più attive',
        'Tenders for %s reach us through %s.' => 'Le gare per %s ci arrivano tramite %s.',
        'Each listing links to the official notice, which is where bidding happens and which is always the authoritative version.' => 'Ogni annuncio rimanda al bando ufficiale, dove si presentano le offerte e che fa sempre fede.',
        'Search tenders' => 'Cerca gare d\'appalto',
        'Search' => 'Cerca',
        'Home' => 'Home',
        'Countries' => 'Paesi',
        'Categories' => 'Categorie',
        'Closing soon' => 'In scadenza',
        'Just published' => 'Appena pubblicate',
        'By country' => 'Per paese',
        'By category' => 'Per categoria',
        'All countries' => 'Tutti i paesi',
        'All categories' => 'Tutte le categorie',
        'Buyer' => 'Stazione appaltante',
        'Country' => 'Paese',
        'Category' => 'Categoria',
        'Value' => 'Importo',
        'Deadline' => 'Scadenza',
        'Published' => 'Pubblicata',
        'Procedure' => 'Procedura',
        'CPV code' => 'Codice CPV',
        'Closed' => 'Chiusa',
        'Closes today' => 'Scade oggi',
        'Closes tomorrow' => 'Scade domani',
        'Closes in %d days' => 'Scade tra %d giorni',
        'Closes %s' => 'Scade il %s',
        'No deadline given' => 'Nessuna scadenza indicata',
        'open tenders' => 'gare aperte',
        'open tender' => 'gara aperta',
        'Read the official notice and bid' => 'Leggi il bando ufficiale e partecipa',
        'Bidding always happens on the buyer\'s own portal, never here.' => 'La presentazione delle offerte avviene sempre sul portale della stazione appaltante, mai qui.',
        'What the buyer is asking for' => 'Che cosa richiede la stazione appaltante',
        'This tender in other languages' => 'Questa gara in altre lingue',
        'Public tenders in %s' => 'Gare d\'appalto pubbliche in %s',
        'Public tenders from around the world, free to read' => 'Gare d\'appalto pubbliche da tutto il mondo, consultabili gratuitamente',
        'Language' => 'Lingua',
        'This description is in the language the buyer published it in.' => 'Questa descrizione è nella lingua in cui è stata pubblicata dalla stazione appaltante.',
    ],
    'pl' => [
        'Language of this notice: %s. The title and description are shown exactly as the buyer published them.' => 'Język tego ogłoszenia: %s. Tytuł i opis pokazujemy dokładnie tak, jak opublikował je zamawiający.',
        'How public tendering works in %s' => 'Jak działają zamówienia publiczne w kraju: %s',
        'Open now' => 'Obecnie otwarte',
        'Closing this week' => 'Kończą się w tym tygodniu',
        'Typical time to bid' => 'Typowy czas na złożenie oferty',
        'With a published value' => 'Z podaną wartością',
        '%d days' => '%d dni',
        'Most common right now' => 'Najczęstsze obecnie',
        'Buyers publishing most often' => 'Najczęściej publikujący zamawiający',
        'Tenders for %s reach us through %s.' => 'Przetargi dla kraju %s docierają do nas przez %s.',
        'Each listing links to the official notice, which is where bidding happens and which is always the authoritative version.' => 'Każde ogłoszenie odsyła do oficjalnego ogłoszenia, gdzie składa się oferty i które jest zawsze wiążące.',
        'Search tenders' => 'Szukaj przetargów',
        'Search' => 'Szukaj',
        'Home' => 'Strona główna',
        'Countries' => 'Kraje',
        'Categories' => 'Kategorie',
        'Closing soon' => 'Wkrótce zamknięcie',
        'Just published' => 'Nowo opublikowane',
        'By country' => 'Według kraju',
        'By category' => 'Według kategorii',
        'All countries' => 'Wszystkie kraje',
        'All categories' => 'Wszystkie kategorie',
        'Buyer' => 'Zamawiający',
        'Country' => 'Kraj',
        'Category' => 'Kategoria',
        'Value' => 'Wartość',
        'Deadline' => 'Termin składania ofert',
        'Published' => 'Opublikowano',
        'Procedure' => 'Procedura',
        'CPV code' => 'Kod CPV',
        'Closed' => 'Zamknięty',
        'Closes today' => 'Kończy się dzisiaj',
        'Closes tomorrow' => 'Kończy się jutro',
        'Closes in %d days' => 'Kończy się za %d dni',
        'Closes %s' => 'Kończy się %s',
        'No deadline given' => 'Nie podano terminu',
        'open tenders' => 'otwartych przetargów',
        'open tender' => 'otwarty przetarg',
        'Read the official notice and bid' => 'Przeczytaj oficjalne ogłoszenie i złóż ofertę',
        'Bidding always happens on the buyer\'s own portal, never here.' => 'Oferty składa się zawsze na portalu zamawiającego, nigdy tutaj.',
        'What the buyer is asking for' => 'Czego szuka zamawiający',
        'This tender in other languages' => 'Ten przetarg w innych językach',
        'Public tenders in %s' => 'Przetargi publiczne w kraju: %s',
        'Public tenders from around the world, free to read' => 'Przetargi publiczne z całego świata, bezpłatnie',
        'Language' => 'Język',
        'This description is in the language the buyer published it in.' => 'Ten opis jest w języku, w którym opublikował go zamawiający.',
    ],
    'pt' => [
        'Language of this notice: %s. The title and description are shown exactly as the buyer published them.' => 'Idioma deste anúncio: %s. O título e a descrição são apresentados tal como a entidade adjudicante os publicou.',
        'How public tendering works in %s' => 'Como funciona a contratação pública em %s',
        'Open now' => 'Abertos agora',
        'Closing this week' => 'Terminam esta semana',
        'Typical time to bid' => 'Prazo habitual para concorrer',
        'With a published value' => 'Com valor publicado',
        '%d days' => '%d dias',
        'Most common right now' => 'Mais frequentes neste momento',
        'Buyers publishing most often' => 'Entidades que mais publicam',
        'Tenders for %s reach us through %s.' => 'Os concursos de %s chegam-nos através de %s.',
        'Each listing links to the official notice, which is where bidding happens and which is always the authoritative version.' => 'Cada anúncio remete para o anúncio oficial, onde se apresentam as propostas e que prevalece sempre.',
        'Search tenders' => 'Pesquisar concursos',
        'Search' => 'Pesquisar',
        'Home' => 'Início',
        'Countries' => 'Países',
        'Categories' => 'Categorias',
        'Closing soon' => 'A terminar em breve',
        'Just published' => 'Publicados recentemente',
        'By country' => 'Por país',
        'By category' => 'Por categoria',
        'All countries' => 'Todos os países',
        'All categories' => 'Todas as categorias',
        'Buyer' => 'Entidade adjudicante',
        'Country' => 'País',
        'Category' => 'Categoria',
        'Value' => 'Valor',
        'Deadline' => 'Prazo',
        'Published' => 'Publicado',
        'Procedure' => 'Procedimento',
        'CPV code' => 'Código CPV',
        'Closed' => 'Encerrado',
        'Closes today' => 'Termina hoje',
        'Closes tomorrow' => 'Termina amanhã',
        'Closes in %d days' => 'Termina em %d dias',
        'Closes %s' => 'Termina a %s',
        'No deadline given' => 'Sem prazo indicado',
        'open tenders' => 'concursos abertos',
        'open tender' => 'concurso aberto',
        'Read the official notice and bid' => 'Ler o anúncio oficial e concorrer',
        'Bidding always happens on the buyer\'s own portal, never here.' => 'As propostas são sempre apresentadas no portal da entidade adjudicante, nunca aqui.',
        'What the buyer is asking for' => 'O que a entidade adjudicante procura',
        'This tender in other languages' => 'Este concurso noutras línguas',
        'Public tenders in %s' => 'Concursos públicos em %s',
        'Public tenders from around the world, free to read' => 'Concursos públicos de todo o mundo, de acesso livre',
        'Language' => 'Idioma',
        'This description is in the language the buyer published it in.' => 'Esta descrição está na língua em que foi publicada pela entidade adjudicante.',
    ],
    'nl' => [
        'Language of this notice: %s. The title and description are shown exactly as the buyer published them.' => 'Taal van deze aankondiging: %s. Titel en beschrijving tonen we precies zoals de aanbestedende dienst ze heeft gepubliceerd.',
        'How public tendering works in %s' => 'Hoe openbare aanbesteding werkt in %s',
        'Open now' => 'Nu open',
        'Closing this week' => 'Sluiten deze week',
        'Typical time to bid' => 'Gebruikelijke inschrijftermijn',
        'With a published value' => 'Met gepubliceerde waarde',
        '%d days' => '%d dagen',
        'Most common right now' => 'Nu het meest voorkomend',
        'Buyers publishing most often' => 'Meest publicerende diensten',
        'Tenders for %s reach us through %s.' => 'Aanbestedingen voor %s bereiken ons via %s.',
        'Each listing links to the official notice, which is where bidding happens and which is always the authoritative version.' => 'Elke vermelding verwijst naar de officiële aankondiging, waar wordt ingeschreven en die altijd leidend is.',
        'Search tenders' => 'Aanbestedingen zoeken',
        'Search' => 'Zoeken',
        'Home' => 'Home',
        'Countries' => 'Landen',
        'Categories' => 'Categorieën',
        'Closing soon' => 'Sluit binnenkort',
        'Just published' => 'Net gepubliceerd',
        'By country' => 'Per land',
        'By category' => 'Per categorie',
        'All countries' => 'Alle landen',
        'All categories' => 'Alle categorieën',
        'Buyer' => 'Aanbestedende dienst',
        'Country' => 'Land',
        'Category' => 'Categorie',
        'Value' => 'Waarde',
        'Deadline' => 'Sluitingsdatum',
        'Published' => 'Gepubliceerd',
        'Procedure' => 'Procedure',
        'CPV code' => 'CPV-code',
        'Closed' => 'Gesloten',
        'Closes today' => 'Sluit vandaag',
        'Closes tomorrow' => 'Sluit morgen',
        'Closes in %d days' => 'Sluit over %d dagen',
        'Closes %s' => 'Sluit op %s',
        'No deadline given' => 'Geen sluitingsdatum vermeld',
        'open tenders' => 'openstaande aanbestedingen',
        'open tender' => 'openstaande aanbesteding',
        'Read the official notice and bid' => 'Lees de officiële aankondiging en schrijf in',
        'Bidding always happens on the buyer\'s own portal, never here.' => 'Inschrijven gebeurt altijd op het portaal van de aanbestedende dienst, nooit hier.',
        'What the buyer is asking for' => 'Wat de aanbestedende dienst vraagt',
        'This tender in other languages' => 'Deze aanbesteding in andere talen',
        'Public tenders in %s' => 'Openbare aanbestedingen in %s',
        'Public tenders from around the world, free to read' => 'Openbare aanbestedingen van over de hele wereld, gratis te lezen',
        'Language' => 'Taal',
        'This description is in the language the buyer published it in.' => 'Deze beschrijving staat in de taal waarin de aanbestedende dienst haar heeft gepubliceerd.',
    ],
];

function oft_lang(): string
{
    return $GLOBALS['oft_lang'] ?? OFT_DEFAULT_LANG;
}

function oft_set_lang(?string $code): string
{
    $code = strtolower((string) $code);
    $GLOBALS['oft_lang'] = isset(OFT_LANGS[$code]) ? $code : OFT_DEFAULT_LANG;
    return $GLOBALS['oft_lang'];
}

/** Translate an interface string. Falls back to the English it was written in. */
function t(string $text, ...$args): string
{
    $lang = oft_lang();
    $out = ($lang === OFT_DEFAULT_LANG) ? $text : (OFT_STRINGS[$lang][$text] ?? $text);
    return $args ? vsprintf($out, $args) : $out;
}

/** Prefix a path with the current language: "/country/de" -> "/fr/country/de". */
function oft_path(string $path, ?string $lang = null): string
{
    $lang = $lang ?? oft_lang();
    $path = '/' . ltrim($path, '/');
    return $lang === OFT_DEFAULT_LANG ? $path : '/' . $lang . $path;
}

/** The path with no language prefix, for building hreflang alternates. */
function oft_bare_path(): string
{
    $path = strtok($_SERVER['REQUEST_URI'] ?? '/', '?');
    foreach (array_keys(OFT_LANGS) as $code) {
        if ($code !== OFT_DEFAULT_LANG && ($path === "/$code" || str_starts_with($path, "/$code/"))) {
            return substr($path, strlen($code) + 1) ?: '/';
        }
    }
    return $path ?: '/';
}

function oft_country_name(?string $code, ?string $fallback = null): ?string
{
    if (!$code) {
        return $fallback;
    }
    $names = OFT_COUNTRY_NAMES[strtoupper($code)] ?? null;
    if (!$names) {
        return $fallback;
    }
    return $names[oft_lang()] ?? $names['en'] ?? $fallback;
}

function oft_category_name(?string $division, ?string $fallback = null): ?string
{
    if (!$division) {
        return $fallback;
    }
    $labels = OFT_CPV_DIVISIONS[$division] ?? null;
    if (!$labels) {
        return $fallback;
    }
    return $labels[oft_lang()] ?? $labels['en'] ?? $fallback;
}

/**
 * The best title we can show this reader, and whether it is really their language.
 * Returns [title, language-actually-used].
 */
function oft_title_for(array $tender): array
{
    $lang = oft_lang();
    $titles = $tender['titles_json'] ? json_decode($tender['titles_json'], true) : [];
    if (is_array($titles) && !empty($titles[$lang])) {
        return [$titles[$lang], $lang];
    }
    if (is_array($titles) && !empty($titles['en'])) {
        return [$titles['en'], 'en'];
    }
    return [$tender['title'], $tender['title_lang'] ?: 'en'];
}
