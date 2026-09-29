<?php

namespace App\Enums;

/**
 * Formats d'export des listes.
 */
enum FormatExport: string
{
    case Csv = 'csv';
    case Excel = 'xlsx';
    case Pdf = 'pdf';

    public function libelle(): string
    {
        return match ($this) {
            self::Csv => 'CSV',
            self::Excel => 'Excel',
            self::Pdf => 'PDF',
        };
    }

    public function typeMime(): string
    {
        return match ($this) {
            self::Csv => 'text/csv; charset=UTF-8',
            self::Excel => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            self::Pdf => 'application/pdf',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Csv => 'bi-filetype-csv',
            self::Excel => 'bi-file-earmark-excel',
            self::Pdf => 'bi-file-earmark-pdf',
        };
    }

    /**
     * URL d'export d'une liste dans chaque format (boutons des tableaux).
     *
     * @return array<string, string>
     */
    public static function urls(string $liste): array
    {
        return self::urlsRoute('gestion.exports', [$liste]);
    }

    /**
     * URL d'une route d'export dans chaque format (format en dernier paramètre).
     *
     * @param  list<mixed>  $parametres
     * @return array<string, string>
     */
    public static function urlsRoute(string $route, array $parametres = []): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $format) => [$format->value => route($route, [...$parametres, $format])])->all();
    }
}
