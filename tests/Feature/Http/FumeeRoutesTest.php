<?php

use App\Enums\FormatExport;
use App\Enums\Role;
use App\Enums\TypeOperationCarte;
use App\Http\Controllers\Gestion\ExportController;
use App\Models\AlerteExpiration;
use App\Models\Carte;
use App\Models\DemandeOtp;
use App\Models\JournalAudit;
use App\Models\OperationCarte;
use App\Models\Partenaire;
use App\Models\RoleUtilisateur;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PartenaireCourant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Testing\TestResponse;

/*
|--------------------------------------------------------------------------
| Test de fumée : chaque page et chaque flux de données s'affiche avec
| plusieurs enregistrements de chaque type, sans requête N+1 (le chargement
| paresseux d'une relation lève une exception).
|--------------------------------------------------------------------------
*/

beforeEach(function () {
    Model::preventLazyLoading();

    $this->superadmin = utilisateurAvecRole(Role::Superadmin);
    $agents = collect(range(1, 3))->map(fn () => utilisateurAvecRole(Role::Agent));
    $this->operateur = utilisateurAvecRole(Role::Partenaire);
    $this->partenaire = $this->operateur->partenaire;

    $this->actingAs($agents->first());
    $this->cartes = $agents->map(fn (User $agent) => Carte::factory()->for($agent, 'activePar')->create(['active_le' => now()->subYear()->addDays(20)]));
    $this->cartes->each(fn (Carte $carte) => OperationCarte::enregistrer($carte, TypeOperationCarte::Activation, auteur: $agents->first()));

    foreach ($this->cartes as $carte) {
        $demande = DemandeOtp::factory()->utilisee()->create(['carte_id' => $carte->id, 'partenaire_id' => $this->partenaire->id]);
        Transaction::factory()->create(['demande_otp_id' => $demande->id, 'valide_par_id' => $this->operateur->id]);
        AlerteExpiration::create(['carte_id' => $carte->id, 'palier' => '1_mois', 'canal' => 'sms', 'envoyee_le' => now()]);
    }

    Partenaire::factory()->count(2)->create()->each(fn (Partenaire $p) => $p->delete());
    User::factory()->count(2)->create()->each(fn (User $u) => $u->assignRole(Role::Agent))->each(fn (User $u) => $u->delete());
    JournalAudit::query()->count() > 3 ?: $this->fail('Le journal devrait contenir des entrées.');
    auth()->logout();
});

function fumeeTableau(TestResponse $reponse): TestResponse
{
    return $reponse->assertOk()->assertJsonStructure(['data', 'recordsTotal', 'recordsFiltered']);
}

it('renders every back-office page for the superadmin', function () {
    $carte = $this->cartes->first();
    $agent = $carte->activePar;
    $pages = [
        route('gestion.tableau-de-bord'),
        route('gestion.cartes.index'),
        route('gestion.cartes.index', ['mes_activations' => 1, 'recherche' => '0']),
        route('gestion.cartes.create'),
        route('gestion.cartes.rapport'),
        route('gestion.cartes.show', $carte),
        route('gestion.cartes.titulaire.edit', $carte),
        route('gestion.partenaires.index'),
        route('gestion.partenaires.create'),
        route('gestion.partenaires.show', $this->partenaire),
        route('gestion.partenaires.edit', $this->partenaire),
        route('gestion.transactions.rapport'),
        route('gestion.transaction.verifier'),
        route('gestion.transaction.resultat', Transaction::first()),
        route('gestion.utilisateurs.index'),
        route('gestion.utilisateurs.create'),
        route('gestion.utilisateurs.show', $agent),
        route('gestion.comptes.edit', $agent),
        route('gestion.comptes.edit', $this->operateur),
        route('gestion.roles.index'),
        route('gestion.roles.create'),
        route('gestion.roles.edit', RoleUtilisateur::findByName('agent')),
        route('gestion.journal.index'),
        route('gestion.corbeille.index'),
        route('gestion.mise-en-production'),
        route('gestion.sms-simules.index'),
    ];

    foreach ($pages as $page) {
        $reponse = connecter($this->superadmin)->withSession([PartenaireCourant::CLE_SESSION => $this->partenaire->id])->get($page);

        expect($reponse->status())->toBe(200, "Page {$page} : HTTP {$reponse->status()}");
    }
});

it('serves every data table for the superadmin', function () {
    $parametres = ['draw' => 1, 'start' => 0, 'length' => 50, 'search' => ['value' => '']];

    foreach (['gestion.cartes.rapport.donnees', 'gestion.partenaires.donnees', 'gestion.transactions.rapport.donnees', 'gestion.utilisateurs.donnees', 'gestion.journal.donnees'] as $route) {
        $reponse = fumeeTableau(connecter($this->superadmin)->getJson(route($route, $parametres)));

        expect($reponse->json('recordsTotal'))->toBeGreaterThan(0, "Tableau {$route} vide");
    }
});

it('exports every list in every format', function (string $format) {
    foreach (array_keys(ExportController::LISTES) as $liste) {
        $reponse = connecter($this->superadmin)->get(route('gestion.exports', [$liste, $format]));
        // Le flux est consommé : l'export est réellement produit (N+1 compris).
        $contenu = $format === 'pdf' ? $reponse->getContent() : $reponse->streamedContent();

        expect($reponse->getStatusCode())->toBe(200, "Export {$liste} en {$format}")
            ->and(strlen($contenu))->toBeGreaterThan(100);
    }
})->with(['csv', 'xlsx', 'pdf']);

it('renders every page of the partner space', function () {
    foreach ([route('partenaire.tableau-de-bord'), route('partenaire.historique.index'), route('partenaire.transaction.verifier'), route('partenaire.transaction.resultat', Transaction::first())] as $page) {
        expect(connecter($this->operateur)->get($page)->status())->toBe(200, "Page {$page}");
    }

    fumeeTableau(connecter($this->operateur)->getJson(route('partenaire.historique.donnees', ['draw' => 1, 'start' => 0, 'length' => 50])));

    foreach (FormatExport::cases() as $format) {
        $reponse = connecter($this->operateur)->get(route('partenaire.historique.export', $format));
        $contenu = $format === FormatExport::Pdf ? $reponse->getContent() : $reponse->streamedContent();

        expect($reponse->getStatusCode())->toBe(200, "Export de l'historique en {$format->value}")
            ->and(strlen($contenu))->toBeGreaterThan(100);
    }
});
