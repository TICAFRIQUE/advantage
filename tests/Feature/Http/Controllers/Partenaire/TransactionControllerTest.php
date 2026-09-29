<?php

use App\Enums\FormatExport;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Carte;
use App\Models\DemandeOtp;
use App\Models\JournalAudit;
use App\Models\Titulaire;
use App\Models\Transaction;
use App\Models\User;

function transactionPour(User $operateur, array $titulaire = [], string $valideeLe = 'now'): Transaction
{
    $carte = Carte::factory()->for(Titulaire::factory()->create($titulaire))->create();
    $demande = DemandeOtp::factory()->utilisee()->create(['carte_id' => $carte->id, 'partenaire_id' => $operateur->partenaire_id]);

    return Transaction::factory()->create(['demande_otp_id' => $demande->id, 'validee_le' => $valideeLe === 'now' ? now() : $valideeLe]);
}

function donneesTableau(User $operateur, array $parametres = []): array
{
    return connecter($operateur)
        ->getJson(route('partenaire.historique.donnees', array_merge(['draw' => 1, 'start' => 0, 'length' => 25], $parametres)))
        ->assertOk()
        ->json();
}

it('lists only the transactions of the current partner', function () {
    $operateur = utilisateurAvecRole(Role::Partenaire);
    transactionPour($operateur, ['nom' => 'KONAN']);
    transactionPour(utilisateurAvecRole(Role::Partenaire), ['nom' => 'AUTRE']);

    $donnees = donneesTableau($operateur);

    expect($donnees['recordsTotal'])->toBe(1)
        ->and($donnees['data'][0]['titulaire'])->toContain('KONAN');
});

it('filters by period on the server', function () {
    $operateur = utilisateurAvecRole(Role::Partenaire);
    transactionPour($operateur, ['nom' => 'ANCIEN'], now()->subDays(40)->toDateTimeString());
    transactionPour($operateur, ['nom' => 'RECENT']);

    $donnees = donneesTableau($operateur, ['du' => now()->subDays(7)->toDateString(), 'au' => now()->toDateString()]);

    expect($donnees['recordsFiltered'])->toBe(1)
        ->and($donnees['data'][0]['titulaire'])->toContain('RECENT');
});

it('searches by holder name on the server', function () {
    $operateur = utilisateurAvecRole(Role::Partenaire);
    transactionPour($operateur, ['nom' => 'KONAN']);
    transactionPour($operateur, ['nom' => 'TRAORE']);

    $donnees = donneesTableau($operateur, ['search' => ['value' => 'kon']]);

    expect($donnees['recordsFiltered'])->toBe(1);
});

it('escapes values to prevent script injection', function () {
    $operateur = utilisateurAvecRole(Role::Partenaire);
    transactionPour($operateur, ['nom' => '<script>alert(1)</script>']);

    expect(donneesTableau($operateur)['data'][0]['titulaire'])->not->toContain('<script>');
});

it('rejects an inconsistent period', function () {
    connecter(utilisateurAvecRole(Role::Partenaire))
        ->getJson(route('partenaire.historique.donnees', ['du' => '2026-09-10', 'au' => '2026-09-01']))
        ->assertUnprocessable();
});

it('forbids agents from reading partner transactions', function () {
    connecter(utilisateurAvecRole(Role::Agent))->getJson(route('partenaire.historique.donnees'))->assertForbidden();
});

describe('export', function () {
    it('shows the export buttons to a partner operator', function () {
        $html = connecter(utilisateurAvecRole(Role::Partenaire))->get(route('partenaire.historique.index'))->assertOk()->getContent();
        preg_match("/data-exports='([^']+)'/", $html, $exports);

        expect(json_decode(html_entity_decode($exports[1] ?? 'null'), true))->toBe([
            'csv' => route('partenaire.historique.export', 'csv'),
            'xlsx' => route('partenaire.historique.export', 'xlsx'),
            'pdf' => route('partenaire.historique.export', 'pdf'),
        ]);
    });

    it('exports only the current partner transactions, with the screen filters', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire);
        transactionPour($operateur, ['nom' => 'KONAN']);
        transactionPour($operateur, ['nom' => 'ANCIEN'], now()->subDays(40)->toDateTimeString());
        transactionPour($operateur, ['nom' => 'TRAORE']);
        transactionPour(utilisateurAvecRole(Role::Partenaire), ['nom' => 'AUTRE']);

        $reponse = connecter($operateur)->get(route('partenaire.historique.export', [
            FormatExport::Csv, 'du' => now()->subDays(7)->toDateString(), 'recherche_tableau' => 'kon',
        ]));

        $contenu = $reponse->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8')->streamedContent();

        expect($contenu)->toContain('KONAN')
            ->not->toContain('TRAORE')
            ->not->toContain('ANCIEN')
            ->not->toContain('AUTRE')
            ->and(JournalAudit::where('action', 'export.genere')->where('acteur_id', $operateur->id)->first()->donnees)
            ->toMatchArray(['liste' => 'historique', 'format' => 'csv', 'lignes' => 1]);
    });

    it('hides and forbids the export without the permission', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire);
        Spatie\Permission\Models\Role::findByName(Role::Partenaire->value)->revokePermissionTo(Permission::ExporterHistorique->value);

        connecter($operateur)->get(route('partenaire.historique.index'))
            ->assertOk()
            ->assertDontSee('data-exports', false);

        connecter($operateur)->get(route('partenaire.historique.export', FormatExport::Csv))->assertForbidden();
    });

    it('forbids back-office accounts from the partner export', function () {
        connecter(utilisateurAvecRole(Role::Agent))->get(route('partenaire.historique.export', FormatExport::Pdf))->assertForbidden();
    });
});
