<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\StatutPartenaire;
use App\Models\Carte;
use App\Models\JournalAudit;
use App\Models\Partenaire;
use App\Models\Titulaire;
use App\Models\User;
use App\Services\Exports\Exporteur;
use App\Services\Listes\ListePartenaires;
use Illuminate\Testing\TestResponse;
use OpenSpout\Reader\XLSX\Reader;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * @return list<list<string>>
 */
function lignesCsv(string $contenu): array
{
    $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu);

    return array_map(fn (string $ligne) => str_getcsv($ligne, ';', '"', ''), array_values(array_filter(explode("\n", trim($contenu)))));
}

/**
 * @return list<list<mixed>>
 */
function lignesXlsx(string $contenu): array
{
    $fichier = tempnam(sys_get_temp_dir(), 'xlsx');
    file_put_contents($fichier, $contenu);
    $reader = new Reader;
    $reader->open($fichier);
    $lignes = [];

    foreach ($reader->getSheetIterator() as $feuille) {
        foreach ($feuille->getRowIterator() as $ligne) {
            $lignes[] = $ligne->toArray();
        }
    }

    $reader->close();
    unlink($fichier);

    return $lignes;
}

function exporter(User $user, string $liste, string $format, array $parametres = []): TestResponse
{
    return connecter($user)->get(route('gestion.exports', [$liste, $format] + $parametres));
}

describe('droits', function () {
    it('lets the admin export and shows the menu', function () {
        $admin = utilisateurAvecRole(Role::Admin);

        exporter($admin, 'partenaires', 'csv')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        connecter($admin)->get(route('gestion.partenaires.index'))->assertSee('Exporter')->assertSee(route('gestion.exports', ['partenaires', 'xlsx']), false);
    });

    it('refuses exports to accounts without the export permission, and hides the menu', function () {
        $agent = utilisateurAvecRole(Role::Agent);

        exporter($agent, 'cartes', 'csv')->assertForbidden();
        connecter($agent)->get(route('gestion.cartes.index'))->assertOk()->assertDontSee(route('gestion.exports', ['cartes', 'csv']), false);
    });

    it('also requires the right to see the list itself', function () {
        $agent = utilisateurAvecRole(Role::Agent);
        $agent->givePermissionTo(Permission::ExporterDonnees->value);

        exporter($agent, 'cartes', 'csv')->assertOk();
        exporter($agent, 'transactions', 'csv')->assertForbidden();
        exporter($agent, 'journal-audit', 'csv')->assertForbidden();
    });

    it('rejects unknown lists, formats and invalid filters', function () {
        $admin = utilisateurAvecRole(Role::Admin);

        exporter($admin, 'titulaires', 'csv')->assertNotFound();
        exporter($admin, 'cartes', 'docx')->assertNotFound();
        exporter($admin, 'partenaires', 'csv', ['statut' => 'supprime'])->assertSessionHasErrors('statut');
    });
});

describe('contenu', function () {
    it('exports exactly the filtered list, search included, in CSV with French separators', function () {
        Partenaire::factory()->create(['nom' => 'Pharmacie Lagune', 'secteur' => 'Santé', 'statut' => StatutPartenaire::Actif]);
        Partenaire::factory()->create(['nom' => 'Pharmacie Plateau', 'secteur' => 'Santé', 'statut' => StatutPartenaire::Inactif]);
        Partenaire::factory()->create(['nom' => 'Maquis Chez Tanti', 'secteur' => 'Restauration', 'statut' => StatutPartenaire::Actif]);

        $reponse = exporter(utilisateurAvecRole(Role::Admin), 'partenaires', 'csv', ['statut' => 'actif', 'recherche_tableau' => 'pharma']);
        $lignes = lignesCsv($reponse->streamedContent());

        expect($reponse->baseResponse)->toBeInstanceOf(StreamedResponse::class)
            ->and($reponse->headers->get('Content-Disposition'))->toMatch('/attachment; filename=advantage-partenaires-\d{4}-\d{2}-\d{2}-\d{6}\.csv/')
            ->and($lignes[0])->toBe(['Nom', 'Secteur', 'Localisation', 'Contact', 'Responsable', 'Email', 'Remise', 'Statut', 'Utilisateurs', 'Passages'])
            ->and(array_column(array_slice($lignes, 1), 0))->toBe(['Pharmacie Lagune']);
    });

    it('exports the cards of the current user only when asked', function () {
        $agent = utilisateurAvecRole(Role::Agent);
        $agent->givePermissionTo(Permission::ExporterDonnees->value);
        Carte::factory()->for($agent, 'activePar')->create(['numero_carte' => '1111111']);
        Carte::factory()->create(['numero_carte' => '2222222']);

        $numeros = fn (array $filtres) => array_column(array_slice(lignesCsv(exporter($agent, 'cartes', 'csv', $filtres)->streamedContent()), 1), 0);

        expect($numeros(['mes_activations' => 1]))->toBe(['111 111 1'])
            ->and($numeros([]))->toHaveCount(2);
    });

    it('neutralises spreadsheet formulas in CSV but keeps phone numbers readable', function () {
        Partenaire::factory()->create(['nom' => '=cmd|\' /C calc\'!A0', 'contact' => '+2250707123456']);

        $ligne = lignesCsv(exporter(utilisateurAvecRole(Role::Admin), 'partenaires', 'csv')->streamedContent())[1];

        expect($ligne[0])->toBe('\'=cmd|\' /C calc\'!A0')
            ->and($ligne[3])->toBe('+225 07 07 12 34 56');
    });

    it('never turns a value into an Excel formula', function () {
        Partenaire::factory()->create(['nom' => '=HYPERLINK("http://pirate")', 'secteur' => 'Santé']);

        $reponse = exporter(utilisateurAvecRole(Role::Admin), 'partenaires', 'xlsx');
        $contenu = $reponse->streamedContent();
        $lignes = lignesXlsx($contenu);

        // Relue, une formule ressortirait avec le même texte : on vérifie le XML
        // de la feuille, qui ne doit contenir aucune balise de formule <f>.
        $fichier = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($fichier, $contenu);
        $zip = new ZipArchive;
        $zip->open($fichier);
        $feuille = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        unlink($fichier);

        expect($reponse->headers->get('Content-Type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->and($lignes[1][0])->toBe('=HYPERLINK("http://pirate")')
            ->and($lignes[1][8])->toBe(0)
            ->and($feuille)->toContain('HYPERLINK')->not->toContain('<f>');
    });

    it('produces a PDF with its title, filters and author', function () {
        Partenaire::factory()->create(['nom' => 'Pharmacie Lagune']);

        $reponse = exporter(utilisateurAvecRole(Role::Admin, ['nom' => 'Awa Koné']), 'partenaires', 'pdf', ['statut' => 'actif']);

        expect($reponse->getContent())->toStartWith('%PDF')
            ->and($reponse->headers->get('Content-Type'))->toBe('application/pdf')
            ->and($reponse->headers->get('Content-Disposition'))->toContain('.pdf');
    });

    it('renders the PDF view with the rows, filters and author', function () {
        Partenaire::factory()->create(['nom' => 'Pharmacie Lagune', 'statut' => StatutPartenaire::Actif]);
        $admin = utilisateurAvecRole(Role::Admin, ['nom' => 'Awa Koné']);
        $liste = new ListePartenaires(['statut' => 'actif'], $admin, 'lagune');

        $html = view('exports.pdf', [
            'liste' => $liste,
            'lignes' => $liste->requeteExport()->get()->map(fn ($p) => $liste->ligne($p)),
            'nombre' => 1,
            'auteur' => $admin,
            'logo' => null,
        ])->render();

        expect($html)->toContain('Liste des partenaires', 'Pharmacie Lagune', 'Awa Koné · Administrateur', 'Statut</strong> Actif', 'Recherche</strong> lagune');
    });

    it('refuses a PDF that would be too long and suggests Excel', function () {
        config(['plateforme.exports.lignes_max.pdf' => 1]);
        Partenaire::factory()->count(2)->create();

        connecter(utilisateurAvecRole(Role::Admin))
            ->from(route('gestion.partenaires.index'))
            ->get(route('gestion.exports', ['partenaires', 'pdf']))
            ->assertRedirect(route('gestion.partenaires.index'))
            ->assertSessionHas('erreur', 'Export PDF limité à 1 lignes (2 demandées) : affinez les filtres ou choisissez Excel.');
    });

    it('exports every list', function (string $liste) {
        exporter(utilisateurAvecRole(Role::Superadmin), $liste, 'xlsx')->assertOk();
    })->with(['cartes', 'operations-cartes', 'partenaires', 'transactions', 'utilisateurs', 'journal-audit']);
});

it('logs every export with its list, format, filters and size', function () {
    Titulaire::factory()->create();
    $admin = utilisateurAvecRole(Role::Admin);
    Partenaire::factory()->count(2)->create(['statut' => StatutPartenaire::Actif]);

    exporter($admin, 'partenaires', 'csv', ['statut' => 'actif', 'recherche_tableau' => 'a'])->streamedContent();

    $entree = JournalAudit::where('action', 'export.genere')->sole();

    expect($entree->acteur_id)->toBe($admin->id)
        ->and($entree->donnees)->toMatchArray(['liste' => 'partenaires', 'format' => 'csv'])
        ->and($entree->donnees['filtres'])->toBe(['Statut' => 'Actif', 'Recherche' => 'a']);
});

it('neutralises dangerous CSV values only', function (string $valeur, string $attendu) {
    expect(Exporteur::neutraliserPourCsv($valeur))->toBe($attendu);
})->with([
    'formule' => ['=1+1', "'=1+1"],
    'arobase' => ['@SUM(A1)', "'@SUM(A1)"],
    'plus dangereux' => ['+cmd|x', "'+cmd|x"],
    'moins dangereux' => ['-2+3-cmd', "'-2+3-cmd"],
    'tabulation' => ["\t=1", "'\t=1"],
    'téléphone' => ['+225 07 07 12 34 56', '+225 07 07 12 34 56'],
    'nombre négatif' => ['-12,5', '-12,5'],
    'texte' => ['Pharmacie', 'Pharmacie'],
]);
