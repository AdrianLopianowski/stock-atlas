<?php

declare(strict_types=1);

namespace App\Service\Gpw;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Pobiera dane spółek z serwisu GPW.
 *
 * Źródła:
 *  - lista spółek Głównego Rynku: ajaxindex.php?action=GPWListaSp (zwraca <a href="spolka?isin=...">NAZWA (TICKER)</a>)
 *  - karta spółki:                /spolka?isin=XXX  (adres, www, sektor, opis)
 *
 * GPW nie ma oficjalnego API, więc karta spółki jest parsowana heurystycznie.
 * Jeśli GPW zmieni layout – użyj `--debug-isin`, żeby zobaczyć, co parser widzi,
 * i popraw metodę labelMap()/valueFor().
 */
final class GpwClient
{
    private const BASE = 'https://www.gpw.pl/';
    private const LIST_URL = self::BASE . 'ajaxindex.php?action=GPWListaSp&start=search&format=html&lang=PL&searchCompany=';
    private const COMPANY_URL = self::BASE . 'ajaxindex.php?start=infoTab&format=html&action=GPWListaSp&gls_isin=';

    public function __construct(
        private readonly HttpClientInterface $http,
    ) {}

    /**
     * Pełna lista spółek Głównego Rynku GPW.
     *
     * @return list<array{isin:string,name:string,ticker:string}>
     */
    public function fetchCompanyList(): array
    {
        $html = $this->get(self::LIST_URL);

        // Zmień wzorzec, bo GPW zwraca <span class="pointer" data-isin="PLBRE0000012">MBANK SPÓŁKA AKCYJNA (MBK)</span>
        $patternNew = '/data-isin="([A-Z0-9]{9,12})"[^>]*>\s*(.+?)\s*\(([^)]+)\)\s*<\/span>/us';
        $patternOld = '/href="[^"]*isin=([A-Z0-9]{9,12})"[^>]*>\s*(.+?)\s*\(([^)]+)\)\s*</us';

        $matches = [];
        if (!preg_match_all($patternNew, $html, $matches, PREG_SET_ORDER) && !preg_match_all($patternOld, $html, $matches, PREG_SET_ORDER)) {
            throw new \RuntimeException('Nie udało się sparsować listy spółek GPW – prawdopodobnie zmienił się format odpowiedzi.');
        }

        $companies = [];
        foreach ($matches as [$_, $isin, $name, $ticker]) {
            $ticker = strtoupper(trim($ticker));
            $name = $this->cleanText(html_entity_decode($name, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if ('' === $ticker || '' === $name) {
                continue;
            }

            // lista bywa zduplikowana (kilka serii akcji) – ticker jest kluczem
            $companies[$ticker] = [
                'isin' => $isin,
                'name' => $this->prettifyName($name),
                'ticker' => $ticker,
            ];
        }

        return array_values($companies);
    }


    /**
     * Szczegóły z karty spółki. Wszystkie pola opcjonalne – GPW nie zawsze je podaje.
     *
     * @return array{sector:?string,macroSector:?string,address:?string,city:?string,postalCode:?string,website:?string,description:?string}
     */
    public function fetchCompanyDetails(string $isin): array
    {
        $html = $this->get(self::COMPANY_URL . $isin);
        $crawler = new Crawler($html, self::BASE);
        $labels = $this->labelMap($crawler);

        $sectorRaw = $this->valueFor($labels, ['sektor', 'branza', 'branża']);
        [$macro, $sector] = $this->splitSector($sectorRaw);

        $addressRaw = $this->valueFor($labels, ['adres', 'siedziba']);
        [$street, $postalCode, $city] = $this->splitAddress($addressRaw);

        return [
            'sector' => $sector,
            'macroSector' => $macro,
            'address' => $street,
            'postalCode' => $postalCode,
            'city' => $city,
            'website' => $this->normalizeUrl($this->valueFor($labels, ['strona www', 'www', 'strona internetowa', 'witryna'])) ?? $this->findExternalLink($crawler),
            'description' => $this->findDescription($crawler),
        ];
    }

    /**
     * Zrzut wszystkich rozpoznanych par etykieta => wartość (do debugowania parsera).
     *
     * @return array<string,string>
     */
    public function debugLabels(string $isin): array
    {
        return $this->labelMap(new Crawler($this->get(self::COMPANY_URL . $isin), self::BASE));
    }

    private function get(string $url): string
    {
        $response = $this->http->request('GET', $url, [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (compatible; gpw-importer/1.0)',
                'Accept' => 'text/html,application/xhtml+xml',
                'Accept-Language' => 'pl-PL,pl;q=0.9',
            ],
            'timeout' => 20,
            'max_duration' => 30,
        ]);

        if (200 !== $response->getStatusCode()) {
            throw new \RuntimeException(sprintf('GPW zwróciło HTTP %d dla %s', $response->getStatusCode(), $url));
        }

        return $response->getContent();
    }

    /**
     * Buduje mapę etykieta => wartość z tabel i list definicyjnych na stronie.
     *
     * @return array<string,string>
     */
    private function labelMap(Crawler $crawler): array
    {
        $map = [];

        $crawler->filter('table tr')->each(function (Crawler $row) use (&$map): void {
            $cells = $row->filter('th, td');
            if ($cells->count() < 2) {
                return;
            }
            $key = GpwSectorCatalog::normalize($cells->eq(0)->text(''));
            $value = $this->cleanText($cells->eq(1)->text(''));
            if ('' !== $key && '' !== $value && !isset($map[$key])) {
                $map[$key] = $value;
            }
        });

        $crawler->filter('dl')->each(function (Crawler $dl) use (&$map): void {
            $terms = $dl->filter('dt');
            $definitions = $dl->filter('dd');
            for ($i = 0; $i < min($terms->count(), $definitions->count()); ++$i) {
                $key = GpwSectorCatalog::normalize($terms->eq($i)->text(''));
                $value = $this->cleanText($definitions->eq($i)->text(''));
                if ('' !== $key && '' !== $value && !isset($map[$key])) {
                    $map[$key] = $value;
                }
            }
        });

        // Fallback: "Etykieta: wartość" w zwykłym tekście
        $text = $crawler->filter('body')->count() ? $crawler->filter('body')->text('') : '';
        foreach (preg_split('/[\r\n]+/', $text) ?: [] as $line) {
            if (preg_match('/^\s*([\p{L} \.]{3,40}?)\s*:\s*(.{2,200})$/u', trim($line), $m)) {
                $key = GpwSectorCatalog::normalize($m[1]);
                if ('' !== $key && !isset($map[$key])) {
                    $map[$key] = $this->cleanText($m[2]);
                }
            }
        }

        return $map;
    }

    /**
     * @param array<string,string> $labels
     * @param list<string>         $candidates
     */
    private function valueFor(array $labels, array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $key = GpwSectorCatalog::normalize($candidate);
            if (isset($labels[$key]) && '' !== $labels[$key]) {
                return $labels[$key];
            }
        }

        // dopasowanie częściowe, np. "sektor wg klasyfikacji gpw"
        foreach ($candidates as $candidate) {
            $key = GpwSectorCatalog::normalize($candidate);
            foreach ($labels as $label => $value) {
                if (str_contains($label, $key) && '' !== $value) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * "Handel i usługi / Gry" albo "Handel i usługi - Gry" -> [makrosektor, sektor].
     *
     * @return array{0:?string,1:?string}
     */
    private function splitSector(?string $raw): array
    {
        if (null === $raw || '' === trim($raw)) {
            return [null, null];
        }

        $parts = preg_split('#\s*(?:/|>|\||–|—|\s-\s)\s*#u', trim($raw)) ?: [];
        $parts = array_values(array_filter(array_map('trim', $parts), static fn(string $p): bool => '' !== $p));

        if (0 === count($parts)) {
            return [null, null];
        }
        if (1 === count($parts)) {
            return [null, $parts[0]];
        }

        // ostatni segment to sektor (lub subsektor), pierwszy to makrosektor
        return [$parts[0], $parts[1]];
    }

    /**
     * "ul. Jagiellońska 74, 03-301 Warszawa" -> ["ul. Jagiellońska 74", "03-301", "Warszawa"].
     *
     * @return array{0:?string,1:?string,2:?string}
     */
    private function splitAddress(?string $raw): array
    {
        if (null === $raw || '' === trim($raw)) {
            return [null, null, null];
        }

        $raw = $this->cleanText($raw);

        if (preg_match('/^(.*?)[,\s]+(\d{2}-\d{3})\s+(.+)$/u', $raw, $m)) {
            return [
                mb_substr(trim(rtrim($m[1], ',')), 0, 255),
                $m[2],
                mb_substr(trim($m[3]), 0, 100),
            ];
        }

        // brak kodu pocztowego – ostatni człon po przecinku traktujemy jako miasto
        $parts = array_map('trim', explode(',', $raw));
        if (count($parts) >= 2) {
            $city = array_pop($parts);

            return [mb_substr(implode(', ', $parts), 0, 255), null, mb_substr($city, 0, 100)];
        }

        return [mb_substr($raw, 0, 255), null, null];
    }

    private function findExternalLink(Crawler $crawler): ?string
    {
        $found = null;

        $crawler->filter('a[href^="http"]')->each(function (Crawler $a) use (&$found): void {
            if (null !== $found) {
                return;
            }
            $href = $a->attr('href') ?? '';
            $host = parse_url($href, \PHP_URL_HOST) ?: '';
            $blocked = ['gpw.pl', 'gpwbenchmark.pl', 'facebook.com', 'twitter.com', 'x.com', 'linkedin.com', 'youtube.com', 'google.com', 'newconnect.pl', 'gpwcatalyst.pl'];

            foreach ($blocked as $domain) {
                if (str_contains($host, $domain)) {
                    return;
                }
            }

            $found = $href;
        });

        return $this->normalizeUrl($found);
    }

    private function findDescription(Crawler $crawler): ?string
    {
        $best = '';

        $crawler->filter('p')->each(function (Crawler $p) use (&$best): void {
            $text = $this->cleanText($p->text(''));
            if (mb_strlen($text) > mb_strlen($best)) {
                $best = $text;
            }
        });

        return mb_strlen($best) >= 80 ? $best : null;
    }

    private function normalizeUrl(?string $url): ?string
    {
        if (null === $url) {
            return null;
        }

        $url = trim($url);
        if ('' === $url || !preg_match('#[a-z0-9-]+\.[a-z]{2,}#i', $url)) {
            return null;
        }

        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . ltrim($url, '/');
        }

        return mb_substr($url, 0, 255);
    }

    /**
     * "CD PROJEKT SPÓŁKA AKCYJNA" -> "CD Projekt S.A."
     */
    private function prettifyName(string $name): string
    {
        $name = preg_replace('/\s+SPÓŁKA AKCYJNA\b/ui', ' S.A.', $name) ?? $name;
        $name = preg_replace('/\s+SPÓŁKA EUROPEJSKA\b/ui', ' SE', $name) ?? $name;

        // ALL CAPS -> Title Case, ale zostawiamy skróty typu S.A., SE, PLC, KGHM
        if ($name === mb_strtoupper($name, 'UTF-8')) {
            $words = preg_split('/\s+/', $name) ?: [];
            $name = implode(' ', array_map(static function (string $word): string {
                if (mb_strlen($word) <= 4 || preg_match('/[.&\d]/', $word)) {
                    return $word;
                }

                return mb_convert_case(mb_strtolower($word, 'UTF-8'), \MB_CASE_TITLE, 'UTF-8');
            }, $words));
        }

        return mb_substr($this->cleanText($name), 0, 255);
    }

    private function cleanText(string $text): string
    {
        $text = str_replace(["\xc2\xa0", "\t"], ' ', $text);

        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
