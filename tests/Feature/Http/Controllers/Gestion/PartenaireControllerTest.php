<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\StatutDemandeOtp;
use App\Enums\StatutPartenaire;
use App\Models\DemandeOtp;
use App\Models\HistoriqueTauxPartenaire;
use App\Models\JournalAudit;
use App\Models\Partenaire;
use App\Models\User;
use App\Services\PartenaireCourant;

/**
 * @param  array<string, mixed>  $surcharge
 * @return array<string, mixed>
 */
function formulairePartenaire(array $surcharge = []): array
{
    return array_merge([
        'nom' => 'Pharmacie du Plateau',
        'secteur' => 'Santé',
        'localisation' => 'Plateau, Abidjan',
        'contact' => 'Dr Koné',
        'taux_reduction' => '10',
    ], $surcharge);
}

function donneesPartenaires(User $user, array $parametres = []): array
{
    return connecter($user)
        ->getJson(route('gestion.partenaires.donnees', array_merge(['draw' => 1, 'start' => 0, 'length' => 50], $parametres)))
        ->assertOk()
        ->json();
}

describe('création et modification', function () {
    it('creates a partner and records its initial rate', function () {
        $admin = utilisateurAvecRole(Role::Admin);

        connecter($admin)->post(route('gestion.partenaires.store'), formulairePartenaire(['taux_reduction' => '12,5']))
            ->assertRedirect();

        $partenaire = Partenaire::sole();

        expect($partenaire)
            ->taux_reduction->toBe('12.50')
            ->statut->toBe(StatutPartenaire::Actif)
            ->and(HistoriqueTauxPartenaire::sole())
            ->ancien_taux->toBeNull()
            ->nouveau_taux->toBe('12.50')
            ->modifie_par_id->toBe($admin->id)
            ->and(JournalAudit::where('action', 'partenaire.cree')->exists())->toBeTrue();
    });

    it('traces every rate change with its author, but not other edits', function () {
        $partenaire = Partenaire::factory()->create(['taux_reduction' => 10]);
        $admin = utilisateurAvecRole(Role::Admin);

        connecter($admin)->put(route('gestion.partenaires.update', $partenaire), formulairePartenaire(['nom' => 'Autre nom', 'taux_reduction' => '10']));
        expect(HistoriqueTauxPartenaire::count())->toBe(0);

        connecter($admin)->put(route('gestion.partenaires.update', $partenaire), formulairePartenaire(['taux_reduction' => '15']));

        expect(HistoriqueTauxPartenaire::sole())
            ->ancien_taux->toBe('10.00')
            ->nouveau_taux->toBe('15.00')
            ->modifie_par_id->toBe($admin->id);
    });

    it('validates the partner', function (array $surcharge, string $champ) {
        connecter(utilisateurAvecRole(Role::Admin))
            ->post(route('gestion.partenaires.store'), formulairePartenaire($surcharge))
            ->assertSessionHasErrors($champ);

        expect(Partenaire::count())->toBe(0);
    })->with([
        'nom absent' => [['nom' => ''], 'nom'],
        'taux nul' => [['taux_reduction' => '0'], 'taux_reduction'],
        'taux négatif' => [['taux_reduction' => '-5'], 'taux_reduction'],
        'taux au-delà de 100' => [['taux_reduction' => '101'], 'taux_reduction'],
        'trois décimales' => [['taux_reduction' => '10.555'], 'taux_reduction'],
        'taux non numérique' => [['taux_reduction' => 'dix'], 'taux_reduction'],
    ]);

    it('deactivates a partner so that its operators can no longer work', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire);

        connecter(utilisateurAvecRole(Role::Admin))
            ->post(route('gestion.partenaires.statut', $operateur->partenaire), ['statut' => 'inactif'])
            ->assertSessionHas('succes');

        connecter($operateur->fresh())->get(route('partenaire.tableau-de-bord'))->assertForbidden();
    });
});

describe('liste', function () {
    it('lists partners with their operators and transactions counts', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire);
        $operateur->partenaire->update(['nom' => 'Hôtel Ivoire']);

        $ligne = collect(donneesPartenaires(utilisateurAvecRole(Role::Admin))['data'])->firstWhere('nom', 'Hôtel Ivoire');

        expect($ligne)
            ->operateurs_count->toBe(1)
            ->transactions_count->toBe(0)
            ->lien->toBe(route('gestion.partenaires.show', $operateur->partenaire));
    });

    it('searches partners on the server', function () {
        Partenaire::factory()->create(['nom' => 'Pharmacie Lagune']);
        Partenaire::factory()->create(['nom' => 'Restaurant Maquis']);

        expect(donneesPartenaires(utilisateurAvecRole(Role::Agent), ['search' => ['value' => 'lagune']])['recordsFiltered'])->toBe(1);
    });

    it('filters partners by partner and by status on the server', function () {
        $lagune = Partenaire::factory()->create(['nom' => 'Pharmacie Lagune', 'statut' => StatutPartenaire::Actif]);
        Partenaire::factory()->create(['nom' => 'Restaurant Maquis', 'statut' => StatutPartenaire::Inactif]);
        Partenaire::factory()->create(['nom' => 'Boutique Cocody', 'statut' => StatutPartenaire::Inactif]);
        $admin = utilisateurAvecRole(Role::Admin);

        $parPartenaire = donneesPartenaires($admin, ['partenaire_id' => $lagune->id]);
        $inactifs = donneesPartenaires($admin, ['statut' => StatutPartenaire::Inactif->value]);

        expect(collect($parPartenaire['data'])->pluck('nom')->all())->toBe(['Pharmacie Lagune'])
            ->and(collect($inactifs['data'])->pluck('nom')->sort()->values()->all())->toBe(['Boutique Cocody', 'Restaurant Maquis'])
            ->and(donneesPartenaires($admin, ['partenaire_id' => $lagune->id, 'statut' => StatutPartenaire::Inactif->value])['recordsFiltered'])->toBe(0);
    });

    it('rejects invalid filters', function () {
        connecter(utilisateurAvecRole(Role::Admin))
            ->getJson(route('gestion.partenaires.donnees', ['statut' => 'supprime', 'partenaire_id' => 999999]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['statut', 'partenaire_id']);
    });

    it('shows the filter form with the current selection', function () {
        $lagune = Partenaire::factory()->create(['nom' => 'Pharmacie Lagune']);

        connecter(utilisateurAvecRole(Role::Agent))
            ->get(route('gestion.partenaires.index', ['partenaire_id' => $lagune->id, 'statut' => 'inactif']))
            ->assertOk()
            ->assertSee('data-filtres="#filtres-partenaires"', false)
            ->assertSee('<option value="'.$lagune->id.'" selected>', false)
            ->assertSee('<option value="inactif" selected>', false);
    });
});

describe('utilisateurs du partenaire', function () {
    it('explains what partner users are on the detail page', function () {
        $partenaire = Partenaire::factory()->create();

        connecter(utilisateurAvecRole(Role::Admin))
            ->get(route('gestion.partenaires.show', $partenaire))
            ->assertOk()
            ->assertSee('Utilisateurs du partenaire')
            ->assertSee("se connectent à l'espace partenaire", false)
            ->assertSee('Ajouter un utilisateur du partenaire');
    });
});

describe('autorisations', function () {
    it('lets an agent view partners but not manage them', function () {
        $agent = utilisateurAvecRole(Role::Agent);
        $partenaire = Partenaire::factory()->create();

        connecter($agent)->get(route('gestion.partenaires.show', $partenaire))->assertOk()->assertDontSee('Ajouter un utilisateur du partenaire');
        connecter($agent)->get(route('gestion.partenaires.create'))->assertForbidden();
        connecter($agent)->post(route('gestion.partenaires.store'), formulairePartenaire())->assertForbidden();
        connecter($agent)->put(route('gestion.partenaires.update', $partenaire), formulairePartenaire())->assertForbidden();
    });

    it('keeps partner operators out of partner management', function () {
        connecter(utilisateurAvecRole(Role::Partenaire))->get(route('gestion.partenaires.index'))->assertForbidden();
    });
});

describe('suppression', function () {
    it('asks for the password before deleting a partner', function () {
        $partenaire = Partenaire::factory()->create();

        connecter(utilisateurAvecRole(Role::Admin))
            ->delete(route('gestion.partenaires.destroy', $partenaire))
            ->assertRedirect(route('password.confirm'));

        expect($partenaire->fresh()->trashed())->toBeFalse();
    });

    it('archives the partner with its users and expires its pending codes', function () {
        $operateur = utilisateurAvecRole(Role::Partenaire);
        $partenaire = $operateur->partenaire;
        $autrePartenaire = Partenaire::factory()->create();
        $demande = DemandeOtp::factory()->create(['partenaire_id' => $partenaire->id]);
        $autreDemande = DemandeOtp::factory()->create(['partenaire_id' => $autrePartenaire->id]);
        $admin = utilisateurAvecRole(Role::Admin);

        avecPinRecent($admin)->delete(route('gestion.partenaires.destroy', $partenaire))
            ->assertRedirect(route('gestion.partenaires.index'))
            ->assertSessionHas('succes');

        expect(Partenaire::find($partenaire->id))->toBeNull()
            ->and(Partenaire::withTrashed()->find($partenaire->id)->trashed())->toBeTrue()
            ->and(User::find($operateur->id))->toBeNull()
            ->and($demande->fresh()->statut)->toBe(StatutDemandeOtp::Expiree)
            ->and($autreDemande->fresh()->statut)->toBe(StatutDemandeOtp::EnAttente)
            ->and(JournalAudit::where('action', 'partenaire.supprime')->where('entite_id', $partenaire->id)->sole()->acteur_id)->toBe($admin->id)
            ->and(JournalAudit::where('action', 'utilisateur.supprime')->where('entite_id', $operateur->id)->exists())->toBeTrue();

        connecter($admin)->get(route('gestion.partenaires.show', $partenaire))->assertNotFound();
        expect(collect(donneesPartenaires($admin)['data'])->pluck('id'))->not->toContain($partenaire->id);
    });

    it('can no longer be chosen for a back-office transaction', function () {
        $partenaire = Partenaire::factory()->create();
        $admin = utilisateurAvecRole(Role::Admin);
        avecPinRecent($admin)->delete(route('gestion.partenaires.destroy', $partenaire));

        connecter($admin)->post(route('gestion.transaction.partenaire-courant.store'), ['partenaire_id' => $partenaire->id])
            ->assertSessionHasErrors('partenaire_id');
        connecter($admin)->withSession([PartenaireCourant::CLE_SESSION => $partenaire->id])
            ->post(route('gestion.transaction.verifier.store'), ['numero_carte' => '1234567'])
            ->assertRedirect(route('gestion.transaction.verifier'))
            ->assertSessionHas('erreur');
    });

    it('reserves the deletion to the dedicated permission', function () {
        $partenaire = Partenaire::factory()->create();
        $agent = utilisateurAvecRole(Role::Agent);
        $agent->givePermissionTo(Permission::GererPartenaires->value);

        connecter($agent)->get(route('gestion.partenaires.show', $partenaire))
            ->assertOk()
            ->assertDontSee('action="'.route('gestion.partenaires.destroy', $partenaire).'"', false);
        avecPinRecent($agent)->delete(route('gestion.partenaires.destroy', $partenaire))->assertForbidden();

        connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.partenaires.show', $partenaire))
            ->assertSee('action="'.route('gestion.partenaires.destroy', $partenaire).'"', false)
            ->assertSee('Supprimer le partenaire');
        expect($partenaire->fresh()->trashed())->toBeFalse();
    });
});
