<?php

namespace App\Services\Exports;

use App\Enums\FormatExport;
use App\Exceptions\ExportTropVolumineuxException;
use App\Models\User;
use App\Services\JournaliserAudit;
use App\Services\Listes\Liste;
use App\Services\Parametres;
use Dompdf\Dompdf;
use Dompdf\Options as OptionsDompdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\EmptyCell;
use OpenSpout\Common\Entity\Cell\NumericCell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\CSV\Options as OptionsCsv;
use OpenSpout\Writer\CSV\Writer as WriterCsv;
use OpenSpout\Writer\WriterInterface;
use OpenSpout\Writer\XLSX\Writer as WriterXlsx;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exporte une liste du back-office (mêmes filtres et même recherche que
 * l'écran) en CSV, Excel ou PDF, côté serveur.
 *
 * - CSV / Excel : écriture en flux, ligne par ligne (mémoire constante) ;
 * - PDF : borné en nombre de lignes (rendu en mémoire) ;
 * - aucune cellule n'est interprétée comme formule (injection CSV/Excel) ;
 * - chaque export est journalisé (liste, format, filtres, nombre de lignes).
 */
class Exporteur
{
    /**
     * @throws ExportTropVolumineuxException
     */
    public function telecharger(Liste $liste, FormatExport $format, User $auteur): Response
    {
        $requete = $liste->requeteExport();
        $nombre = (clone $requete)->count();
        $maximum = (int) config("plateforme.exports.lignes_max.{$format->value}");

        if ($nombre > $maximum) {
            throw new ExportTropVolumineuxException(sprintf(
                'Export %s limité à %s lignes (%s demandées) : affinez les filtres%s.',
                $format->libelle(),
                number_format($maximum, 0, ',', ' '),
                number_format($nombre, 0, ',', ' '),
                $format === FormatExport::Pdf ? ' ou choisissez Excel' : '',
            ));
        }

        JournaliserAudit::enregistrer('export.genere', donnees: [
            'liste' => $liste->cle(),
            'format' => $format->value,
            'lignes' => $nombre,
            'filtres' => $liste->descriptionFiltres(),
        ], acteur: $auteur);

        $fichier = sprintf('advantage-%s-%s.%s', $liste->cle(), now()->format('Y-m-d-His'), $format->value);

        return match ($format) {
            FormatExport::Pdf => $this->pdf($liste, $requete, $nombre, $auteur, $fichier),
            FormatExport::Csv => $this->tableur(new WriterCsv(new OptionsCsv(FIELD_DELIMITER: ';')), $liste, $requete, $format, $fichier, true),
            FormatExport::Excel => $this->tableur(new WriterXlsx, $liste, $requete, $format, $fichier, false),
        };
    }

    /**
     * @param  Builder<Model>  $requete
     */
    private function tableur(WriterInterface $writer, Liste $liste, $requete, FormatExport $format, string $fichier, bool $csv): Response
    {
        return response()->streamDownload(function () use ($writer, $liste, $requete, $csv): void {
            $writer->openToFile('php://output');
            $writer->addRow(new Row(array_map(
                fn (string $titre) => new StringCell($titre, (new Style)->withFontBold(true)),
                $liste->colonnes(),
            )));

            foreach ($requete->lazy(500) as $modele) {
                $writer->addRow(new Row(array_map(fn ($valeur) => $this->cellule($valeur, $csv), $liste->ligne($modele))));
            }

            $writer->close();
        }, $fichier, ['Content-Type' => $format->typeMime()]);
    }

    /**
     * Cellule typée explicitement : une chaîne reste une chaîne (jamais une
     * formule, même si elle commence par « = »).
     */
    private function cellule(string|int|float|null $valeur, bool $csv): Cell
    {
        return match (true) {
            $valeur === null || $valeur === '' => new EmptyCell(null),
            is_int($valeur) || is_float($valeur) => new NumericCell($valeur),
            default => new StringCell($csv ? self::neutraliserPourCsv($valeur) : $valeur),
        };
    }

    /**
     * Un tableur ouvrant un CSV évalue les cellules commençant par = + - @
     * (injection de formules). On les préfixe d'une apostrophe, sauf les
     * valeurs purement numériques (téléphone « +225 07 … », montants négatifs).
     */
    public static function neutraliserPourCsv(string $valeur): string
    {
        $premier = $valeur[0] ?? '';

        $dangereux = in_array($premier, ['=', '@', "\t", "\r"], true)
            || (in_array($premier, ['+', '-'], true) && preg_match('/^[+-][\d\s.,]*$/', $valeur) !== 1);

        return $dangereux ? "'".$valeur : $valeur;
    }

    /**
     * @param  Builder<Model>  $requete
     */
    private function pdf(Liste $liste, $requete, int $nombre, User $auteur, string $fichier): Response
    {
        $html = view('exports.pdf', [
            'liste' => $liste,
            'lignes' => $requete->get()->map(fn ($modele) => $liste->ligne($modele)),
            'nombre' => $nombre,
            'auteur' => $auteur,
            'logo' => $this->logo(),
        ])->render();

        // Aucune ressource distante ni fichier hors du dossier public.
        $options = new OptionsDompdf;
        $options->setIsRemoteEnabled(false);
        $options->setChroot(public_path());
        $options->setDefaultFont('DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        $dompdf->getCanvas()->page_text(760, 570, 'Page {PAGE_NUM} / {PAGE_COUNT}', null, 8, [0.4, 0.4, 0.4]);

        return response((string) $dompdf->output(), 200, [
            'Content-Type' => FormatExport::Pdf->typeMime(),
            'Content-Disposition' => 'attachment; filename="'.$fichier.'"',
        ]);
    }

    private function logo(): ?string
    {
        $chemin = public_path(Parametres::logo());

        return is_file($chemin) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($chemin)) : null;
    }
}
