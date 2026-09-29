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
}
