<?php

use App\Enums\Role;
use App\Enums\StatutCarte;
use App\Models\Carte;
use App\Models\JournalAudit;
use App\Models\Titulaire;
use Illuminate\Database\Eloquent\Model;

/**
 * @param  array<string, string>  $surcharge
 * @return array<string, string>
 */
function formulaireActivation(array $surcharge = []): array
{
    return array_merge([
        'telephone' => '07 07 12 34 56',
        'nom' => '  kouassi ',
        'prenom' => 'aya   marie',
        'numero_carte' => '000 000 1',
        'numero_carte_confirmation' => '0000001',
    ], $surcharge);
}

describe('activation', function () {
    it('activates a card and shows it', function () {
        $agent = utilisateurAvecRole(Role::Agent);

        $reponse = connecter($agent)->post(route('agent.cartes.store'), formulaireActivation());

        $carte = Carte::where('numero_carte', '0000001')->sole();
        $reponse->assertRedirect(route('agent.cartes.show', $carte))
            ->assertSessionHas('succes', 'Carte 000 000 1 activée pour Aya Marie KOUASSI.');

        expect($carte->titulaire)
            ->nom->toBe('KOUASSI')
            ->prenom->toBe('Aya Marie')
            ->telephone->toBe('+2250707123456');
    });

    it('rejects invalid input', function (array $surcharge, string $champ) {
        connecter(utilisateurAvecRole(Role::Agent))
            ->post(route('agent.cartes.store'), formulaireActivation($surcharge))
            ->assertSessionHasErrors($champ);

        expect(Carte::count())->toBe(0);
    })->with([
        'numéro à 6 chiffres' => [['numero_carte' => '000001', 'numero_carte_confirmation' => '000001'], 'numero_carte'],
        'numéro à 8 chiffres' => [['numero_carte' => '00000001', 'numero_carte_confirmation' => '00000001'], 'numero_carte'],
        'numéro avec lettres' => [['numero_carte' => '00A0001', 'numero_carte_confirmation' => '00A0001'], 'numero_carte'],
        'confirmation différente' => [['numero_carte_confirmation' => '0000002'], 'numero_carte'],
        'téléphone à 9 chiffres' => [['telephone' => '070712345'], 'telephone'],
        'téléphone à 11 chiffres' => [['telephone' => '07071234567'], 'telephone'],
        'pays inconnu' => [['pays_telephone' => 'ZZ'], 'pays_telephone'],
        'pays injecté en tableau' => [['pays_telephone' => ['CI']], 'pays_telephone'],
        'longueur fausse pour le Sénégal' => [['pays_telephone' => 'SN', 'telephone' => '0707123456'], 'telephone'],
        'nom avec chiffres' => [['nom' => 'Kouassi2'], 'nom'],
        'nom avec balise' => [['nom' => '<script>alert(1)</script>'], 'nom'],
        'prénoms vides' => [['prenom' => '   '], 'prenom'],
    ]);

    it('accepts any ten-digit ivorian number and numbers from other countries', function (array $surcharge, string $attendu) {
        connecter(utilisateurAvecRole(Role::Agent))
            ->post(route('agent.cartes.store'), formulaireActivation($surcharge))
            ->assertSessionHasNoErrors();

        expect(Carte::sole()->titulaire->telephone)->toBe($attendu);
    })->with([
        'fixe ivoirien' => [['telephone' => '27 22 12 34 56'], '+2252722123456'],
        'Sénégal choisi' => [['pays_telephone' => 'SN', 'telephone' => '77 123 45 67'], '+221771234567'],
        'international collé' => [['telephone' => '+233 24 123 4567'], '+233241234567'],
    ]);

    it('explains that a card number is already used', function () {
        Carte::factory()->revoquee()->create(['numero_carte' => '0000001']);

        connecter(utilisateurAvecRole(Role::Agent))
            ->from(route('agent.cartes.create'))
            ->post(route('agent.cartes.store'), formulaireActivation())
            ->assertRedirect(route('agent.cartes.create'))
            ->assertSessionHasErrors(['activation' => "La carte 0000001 a déjà été activée. Un numéro de carte ne peut être utilisé qu'une seule fois."])
            ->assertSessionHasInput('nom');
    });

    it('forbids activation to partners', function (Role $role) {
        $utilisateur = utilisateurAvecRole($role);

        connecter($utilisateur)->get(route('agent.cartes.create'))->assertForbidden();
        connecter($utilisateur)->post(route('agent.cartes.store'), formulaireActivation())->assertForbidden();

        expect(Carte::count())->toBe(0);
    })->with([Role::Partenaire]);

    it('lets the superadmin and the admin activate a card', function (Role $role) {
        connecter(utilisateurAvecRole($role))
            ->post(route('agent.cartes.store'), formulaireActivation())
            ->assertSessionHasNoErrors();

        expect(Carte::count())->toBe(1);
    })->with([Role::Superadmin, Role::Admin]);

    it('shows the name and role of whoever activated the card', function () {
        $admin = utilisateurAvecRole(Role::Admin, ['nom' => 'Awa Koné']);
        connecter($admin)->post(route('agent.cartes.store'), formulaireActivation());

        connecter($admin)->get(route('agent.cartes.show', Carte::sole()))
            ->assertSee('Awa Koné · Administrateur');
    });
});

describe('liste', function () {
    it('lists the cards of every agent', function () {
        $agent = utilisateurAvecRole(Role::Agent);
        Carte::factory()->for(utilisateurAvecRole(Role::Agent), 'activePar')->create(['numero_carte' => '1111111']);
        Carte::factory()->for($agent, 'activePar')->create(['numero_carte' => '2222222']);

        connecter($agent)->get(route('agent.cartes.index'))
            ->assertOk()
            ->assertSee('111 111 1')
            ->assertSee('222 222 2');
    });

    it('filters on my activations', function () {
        $agent = utilisateurAvecRole(Role::Agent);
        Carte::factory()->for(utilisateurAvecRole(Role::Agent), 'activePar')->create(['numero_carte' => '1111111']);
        Carte::factory()->for($agent, 'activePar')->create(['numero_carte' => '2222222']);

        connecter($agent)->get(route('agent.cartes.index', ['mes_activations' => 1]))
            ->assertSee('222 222 2')
            ->assertDontSee('111 111 1');
    });

    it('searches by card number prefix, phone or holder name', function (string $recherche) {
        $cible = Titulaire::factory()->create(['nom' => 'KONAN', 'prenom' => 'Yao', 'telephone' => '+2250505123456']);
        Carte::factory()->for($cible)->create(['numero_carte' => '4567890']);
        Carte::factory()->create(['numero_carte' => '1111111']);

        connecter(utilisateurAvecRole(Role::Agent))->get(route('agent.cartes.index', ['recherche' => $recherche]))
            ->assertSee('456 789 0')
            ->assertDontSee('111 111 1');
    })->with(['4567', '05 05 12', 'konan', 'Yao']);

    it('does not treat like wildcards in the search as match-all', function () {
        Carte::factory()->create(['numero_carte' => '1111111']);

        connecter(utilisateurAvecRole(Role::Agent))->get(route('agent.cartes.index', ['recherche' => '%']))
            ->assertDontSee('111 111 1');
    });

    it('counts an outdated active card as expired in the status filter', function () {
        Carte::factory()->activeeIlYa(12, 3)->create(['numero_carte' => '1111111']);
        Carte::factory()->create(['numero_carte' => '2222222']);

        connecter(utilisateurAvecRole(Role::Agent))->get(route('agent.cartes.index', ['statut' => 'expiree']))
            ->assertSee('111 111 1')
            ->assertDontSee('222 222 2');
    });

    it('loads the list without lazy loading', function () {
        Carte::factory(3)->create();
        $agent = utilisateurAvecRole(Role::Agent);
        Model::preventLazyLoading();

        connecter($agent)->get(route('agent.cartes.index'))->assertOk();

        Model::preventLazyLoading(false);
    });

    it('rejects an unknown status filter', function () {
        connecter(utilisateurAvecRole(Role::Agent))
            ->get(route('agent.cartes.index', ['statut' => 'inexistant']))
            ->assertSessionHasErrors('statut');
    });
});

describe('détail', function () {
    it('shows who activated and who last modified the card', function () {
        $activateur = utilisateurAvecRole(Role::Agent, ['nom' => 'Agent Activateur']);
        $carte = Carte::factory()->for($activateur, 'activePar')->create();
        $modificateur = utilisateurAvecRole(Role::Agent, ['nom' => 'Agent Modificateur']);
        $this->actingAs($modificateur);
        $carte->update(['motif_statut' => 'Contrôle']);

        connecter($modificateur)->get(route('agent.cartes.show', $carte))
            ->assertOk()
            ->assertSee('Agent Activateur')
            ->assertSee('Agent Modificateur')
            ->assertSee($carte->titulaire->telephoneFormate());
    });
});

describe('déclaration de perte', function () {
    it('revokes the card, records the reason and the author', function () {
        $carte = Carte::factory()->for(utilisateurAvecRole(Role::Agent), 'activePar')->create();
        $autreAgent = utilisateurAvecRole(Role::Agent);

        connecter($autreAgent)->post(route('agent.cartes.perte', $carte), ['motif' => 'Perdue au marché'])
            ->assertRedirect(route('agent.cartes.show', $carte))
            ->assertSessionHas('succes');

        expect($carte->fresh())
            ->statut->toBe(StatutCarte::Revoquee)
            ->motif_statut->toBe('Perte déclarée : Perdue au marché')
            ->modifie_par_id->toBe($autreAgent->id)
            ->and(JournalAudit::where('action', 'carte.statut_modifie')->where('entite_id', $carte->id)->sole())
            ->acteur_id->toBe($autreAgent->id);
    });

    it('shows the missing reason error when javascript is unavailable', function () {
        $carte = Carte::factory()->create();

        connecter(utilisateurAvecRole(Role::Agent))
            ->from(route('agent.cartes.show', $carte))
            ->followingRedirects()
            ->post(route('agent.cartes.perte', $carte), ['motif' => ''])
            ->assertSee('Déclaration de perte :');
    });

    it('requires a reason', function () {
        $carte = Carte::factory()->create();

        connecter(utilisateurAvecRole(Role::Agent))->post(route('agent.cartes.perte', $carte), ['motif' => ''])
            ->assertSessionHasErrors('motif');

        expect($carte->fresh()->statut)->toBe(StatutCarte::Active);
    });

    it('refuses a card that is no longer in circulation', function (Closure $fabriquer) {
        $carte = $fabriquer();

        connecter(utilisateurAvecRole(Role::Agent))
            ->post(route('agent.cartes.perte', $carte), ['motif' => 'Perdue'])
            ->assertForbidden();
    })->with([
        'révoquée' => [fn () => Carte::factory()->revoquee()->create()],
        'expirée' => [fn () => Carte::factory()->expiree()->create()],
        'date échue' => [fn () => Carte::factory()->activeeIlYa(12, 1)->create()],
    ]);

    it('still refuses a revoked card for the superadmin, who bypasses policies', function () {
        $carte = Carte::factory()->revoquee()->create();

        connecter(utilisateurAvecRole(Role::Superadmin))
            ->post(route('agent.cartes.perte', $carte), ['motif' => 'Perdue'])
            ->assertSessionHas('erreur');

        expect($carte->fresh()->motif_statut)->toBe('Carte déclarée perdue');
    });

    it('forbids a partner from declaring a loss', function () {
        $carte = Carte::factory()->create();

        connecter(utilisateurAvecRole(Role::Partenaire))
            ->post(route('agent.cartes.perte', $carte), ['motif' => 'Perdue'])
            ->assertForbidden();
    });
});

describe('recherche du titulaire', function () {
    it('returns the known holder and their cards', function () {
        $titulaire = Titulaire::factory()->create(['nom' => 'KONAN', 'prenom' => 'Yao', 'telephone' => '+2250707123456']);
        Carte::factory()->for($titulaire)->create(['numero_carte' => '4567890']);

        connecter(utilisateurAvecRole(Role::Agent))
            ->postJson(route('agent.titulaires.recherche'), ['telephone' => '07 07 12 34 56'])
            ->assertOk()
            ->assertJson([
                'existe' => true,
                'titulaire' => ['nom' => 'KONAN', 'prenom' => 'Yao', 'telephone' => '+225 07 07 12 34 56'],
                'carte_en_circulation' => '456 789 0',
            ]);
    });

    it('reports an unknown holder', function () {
        connecter(utilisateurAvecRole(Role::Agent))
            ->postJson(route('agent.titulaires.recherche'), ['telephone' => '0707123456'])
            ->assertExactJson(['existe' => false]);
    });

    it('rejects an invalid phone number', function () {
        connecter(utilisateurAvecRole(Role::Agent))
            ->postJson(route('agent.titulaires.recherche'), ['telephone' => '1234'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('telephone');
    });

    it('is not reachable with a get request that would log the phone number', function () {
        connecter(utilisateurAvecRole(Role::Agent))
            ->get('/agent/titulaires/recherche?telephone=0707123456')
            ->assertMethodNotAllowed();
    });

    it('forbids partners from looking up holders', function () {
        connecter(utilisateurAvecRole(Role::Partenaire))
            ->postJson(route('agent.titulaires.recherche'), ['telephone' => '0707123456'])
            ->assertForbidden();
    });
});
