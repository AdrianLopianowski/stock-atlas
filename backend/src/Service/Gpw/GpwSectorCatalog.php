<?php

declare(strict_types=1);

namespace App\Service\Gpw;

/**
 * Oficjalna klasyfikacja sektorowa GPW: 8 makrosektorów / 40 sektorów.
 *
 * Struktura pojedynczego wpisu:
 *   symbol      -> Sector::tickerSymbol (unikalny slug, np. "GPW-BANKI")
 *   name        -> Sector::name
 *   macro       -> ląduje w Sector::description (makrosektor)
 *   gicsCode    -> Sector::gicsCode  (przybliżone mapowanie na GICS Sector)
 *   gicsName    -> Sector::gicsName
 *
 * UWAGA: mapowanie GPW -> GICS jest przybliżeniem (GPW nie publikuje oficjalnego
 * przełożenia 1:1). Jeśli chcesz inne przypisanie – zmień tutaj, komenda importu
 * jest idempotentna i nadpisze wartości przy kolejnym uruchomieniu.
 */
final class GpwSectorCatalog
{
    /**
     * @return list<array{symbol:string,name:string,macro:string,gicsCode:string,gicsName:string}>
     */
    public static function all(): array
    {
        $raw = [
            // makrosektor, sektor, GICS code, GICS name
            ['Chemia i surowce', 'Chemia', '15', 'Materials'],
            ['Chemia i surowce', 'Drewno i papier', '15', 'Materials'],
            ['Chemia i surowce', 'Górnictwo', '15', 'Materials'],
            ['Chemia i surowce', 'Guma i tworzywa sztuczne', '15', 'Materials'],
            ['Chemia i surowce', 'Hutnictwo', '15', 'Materials'],
            ['Chemia i surowce', 'Recykling', '20', 'Industrials'],

            ['Dobra konsumpcyjne', 'Artykuły spożywcze', '30', 'Consumer Staples'],
            ['Dobra konsumpcyjne', 'Motoryzacja', '25', 'Consumer Discretionary'],
            ['Dobra konsumpcyjne', 'Odzież i kosmetyki', '25', 'Consumer Discretionary'],
            ['Dobra konsumpcyjne', 'Pozostałe - Dobra konsumpcyjne', '25', 'Consumer Discretionary'],
            ['Dobra konsumpcyjne', 'Wyposażenie domu', '25', 'Consumer Discretionary'],

            ['Finanse', 'Banki', '40', 'Financials'],
            ['Finanse', 'Działalność inwestycyjna', '40', 'Financials'],
            ['Finanse', 'Leasing i faktoring', '40', 'Financials'],
            ['Finanse', 'Nieruchomości', '60', 'Real Estate'],
            ['Finanse', 'Pośrednictwo finansowe', '40', 'Financials'],
            ['Finanse', 'Rynek kapitałowy', '40', 'Financials'],
            ['Finanse', 'Ubezpieczenia', '40', 'Financials'],
            ['Finanse', 'Wierzytelności', '40', 'Financials'],

            ['Handel i usługi', 'Gry', '50', 'Communication Services'],
            ['Handel i usługi', 'Handel hurtowy', '20', 'Industrials'],
            ['Handel i usługi', 'Handel internetowy', '25', 'Consumer Discretionary'],
            ['Handel i usługi', 'Media', '50', 'Communication Services'],
            ['Handel i usługi', 'Rekreacja i wypoczynek', '25', 'Consumer Discretionary'],
            ['Handel i usługi', 'Sieci handlowe', '30', 'Consumer Staples'],

            ['Ochrona zdrowia', 'Biotechnologia', '35', 'Health Care'],
            ['Ochrona zdrowia', 'Dystrybucja leków', '35', 'Health Care'],
            ['Ochrona zdrowia', 'Pozostałe - Ochrona zdrowia', '35', 'Health Care'],
            ['Ochrona zdrowia', 'Produkcja leków', '35', 'Health Care'],
            ['Ochrona zdrowia', 'Sprzęt i materiały medyczne', '35', 'Health Care'],
            ['Ochrona zdrowia', 'Szpitale i przychodnie', '35', 'Health Care'],

            ['Paliwa i Energia', 'Energia', '55', 'Utilities'],
            ['Paliwa i Energia', 'Paliwa i gaz', '10', 'Energy'],

            ['Produkcja przemysłowa i budowlano-montażowa', 'Budownictwo', '20', 'Industrials'],
            ['Produkcja przemysłowa i budowlano-montażowa', 'Przemysł elektromaszynowy', '20', 'Industrials'],
            ['Produkcja przemysłowa i budowlano-montażowa', 'Transport i logistyka', '20', 'Industrials'],
            ['Produkcja przemysłowa i budowlano-montażowa', 'Usługi dla przedsiębiorstw', '20', 'Industrials'],
            ['Produkcja przemysłowa i budowlano-montażowa', 'Zaopatrzenie przedsiębiorstw', '20', 'Industrials'],

            ['Technologie', 'Informatyka', '45', 'Information Technology'],
            ['Technologie', 'Telekomunikacja', '50', 'Communication Services'],
        ];

        $out = [];
        foreach ($raw as [$macro, $name, $gicsCode, $gicsName]) {
            $out[] = [
                'symbol' => 'GPW-' . self::slug($name),
                'name' => $name,
                'macro' => $macro,
                'gicsCode' => $gicsCode,
                'gicsName' => $gicsName,
            ];
        }

        return $out;
    }

    /**
     * Klucz do dopasowywania nazwy sektora zwróconej przez GPW do katalogu.
     */
    public static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'ą' => 'a',
            'ć' => 'c',
            'ę' => 'e',
            'ł' => 'l',
            'ń' => 'n',
            'ó' => 'o',
            'ś' => 's',
            'ź' => 'z',
            'ż' => 'z',
        ]);
        $value = preg_replace('/[^a-z0-9]+/', ' ', $value) ?? '';

        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }

    public static function slug(string $value): string
    {
        $slug = self::normalize($value);

        return strtoupper(str_replace(' ', '-', $slug));
    }
}
