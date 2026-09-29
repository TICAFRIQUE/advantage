<?php

use App\Enums\Role;
use App\Enums\TypePurge;
use App\Models\Carte;
use App\Models\JournalAudit;
use App\Models\PurgeJournalAudit;
use App\Models\User;
use App\Support\LibellesAudit;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

function donneesJournal(User $user, array $parametres = []): array
{
    return connecter($user)
        ->getJson(route('gestion.journal.donnees', array_merge(['draw' => 1, 'start' => 0, 'length' => 100], $parametres)))
        ->assertOk()
        ->json();
}

function entreeJournal(string $action, ?User $acteur = null, string $creeLe = 'now', array $attributs = []): void
{
    DB::table('journaux_audit')->insert($attributs + [
        'acteur_id' => $acteur?->id,
        'type_acteur' => $acteur ? 'utilisateur' : 'systeme',
        'action' => $action,
        'cree_le' => $creeLe === 'now' ? now() : $creeLe,
    ]);
}

function superadminAvecConfirmation(): TestCase
{
    return connecter(utilisateurAvecRole(Role::Superadmin))->withSession([
        'connecte_le' => now()->getTimestamp(),
        'auth.password_confirmed_at' => now()->getTimestamp(),
    ]);
}

describe('consultation', function () {
    it('is open to the admin, closed to agents and partners', function () {
        connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.journal.index'))->assertOk()->assertSee('Registre des purges');
        connecter(utilisateurAvecRole(Role::Agent))->get(route('gestion.journal.index'))->assertForbidden();
        connecter(utilisateurAvecRole(Role::Partenaire))->get(route('gestion.journal.index'))->assertForbidden();
    });

    it('shows the author, a readable action, the element and the details', function () {
        $agent = utilisateurAvecRole(Role::Agent, ['nom' => 'Yao Agent']);
        $carte = Carte::factory()->create();
        entreeJournal('carte.activee', $agent, attributs: [
            'type_entite' => 'Carte', 'entite_id' => $carte->id, 'adresse_ip' => '10.0.0.7',
            'donnees' => json_encode(['apres' => ['statut' => 'active']]),
        ]);

        // La factory journalise aussi une activation (sans auteur) : on isole la nôtre.
        $ligne = collect(donneesJournal(utilisateurAvecRole(Role::Admin), ['action' => 'carte.activee', 'acteur' => $agent->nom_utilisateur])['data'])->sole();

        expect($ligne)
            ->auteur->toBe('Yao Agent · Agent')
            ->action_libelle->toBe('Carte activée')
            ->element->toBe('<a href="'.route('gestion.cartes.show', $carte->id).'">Carte #'.$carte->id.'</a>')
            ->adresse_ip->toBe('10.0.0.7')
            ->details->toContain('<dt>Statut</dt><dd>Active</dd>');
    });

    it('escapes whatever was logged', function () {
        entreeJournal('titulaire.modifie', utilisateurAvecRole(Role::Agent), attributs: [
            'type_entite' => 'Titulaire', 'entite_id' => 1,
            'donnees' => json_encode(['apres' => ['nom' => '<script>alert(1)</script>']]),
        ]);

        $ligne = collect(donneesJournal(utilisateurAvecRole(Role::Admin), ['action' => 'titulaire.modifie'])['data'])->sole();

        expect($ligne['details'])->not->toContain('<script>')->toContain('&lt;script&gt;');
    });

    it('labels scheduled tasks as the system', function () {
        entreeJournal('carte.statut_modifie');

        expect(collect(donneesJournal(utilisateurAvecRole(Role::Admin))['data'])->firstWhere('action_libelle', 'Statut de carte modifié')['auteur'])
            ->toBe('Système (tâche planifiée)');
    });

    it('filters by period, action, element type and author', function () {
        $awa = utilisateurAvecRole(Role::Agent, ['nom' => 'Awa Koné', 'nom_utilisateur' => 'awa.kone']);
        $yao = utilisateurAvecRole(Role::Agent, ['nom' => 'Yao Kouamé', 'nom_utilisateur' => 'yao.k']);
        entreeJournal('carte.consultee', $awa, now()->subDays(3)->toDateTimeString(), ['type_entite' => 'Carte', 'entite_id' => 1]);
        entreeJournal('carte.consultee', $yao, attributs: ['type_entite' => 'Carte', 'entite_id' => 2]);
        entreeJournal('partenaire.modifie', $awa, attributs: ['type_entite' => 'Partenaire', 'entite_id' => 3]);
        $admin = utilisateurAvecRole(Role::Admin);

        expect(donneesJournal($admin, ['action' => 'carte.consultee'])['recordsFiltered'])->toBe(2)
            ->and(donneesJournal($admin, ['action' => 'carte.consultee', 'du' => now()->subDay()->toDateString()])['recordsFiltered'])->toBe(1)
            ->and(donneesJournal($admin, ['type_entite' => 'Partenaire'])['recordsFiltered'])->toBe(1)
            ->and(donneesJournal($admin, ['acteur' => 'awa', 'type_entite' => 'Carte'])['recordsFiltered'])->toBe(1)
            ->and(donneesJournal($admin, ['acteur' => 'Kouamé', 'type_entite' => 'Carte'])['recordsFiltered'])->toBe(1);
    });

    it('rejects unknown filters', function () {
        connecter(utilisateurAvecRole(Role::Admin))
            ->getJson(route('gestion.journal.donnees', ['action' => 'drop.table', 'type_entite' => 'Autre', 'du' => '2026-02-30']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action', 'type_entite', 'du']);
    });

    it('shows the menu entry to the admin', function () {
        connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.tableau-de-bord'))->assertSee(route('gestion.journal.index'), false);
    });
});

describe('purge manuelle', function () {
    it('deletes older entries, records the purge with its reason and author', function () {
        entreeJournal('carte.consultee', creeLe: now()->subDays(10)->toDateTimeString());
        entreeJournal('carte.consultee', creeLe: now()->subDays(8)->toDateTimeString());
        entreeJournal('carte.consultee');

        superadminAvecConfirmation()
            ->post(route('gestion.journal.purger'), ['avant' => now()->subDays(5)->toDateString(), 'motif' => 'Demande de la direction'])
            ->assertRedirect(route('gestion.journal.index'))
            ->assertSessionHas('succes');

        $purge = PurgeJournalAudit::sole();

        expect(JournalAudit::where('action', 'carte.consultee')->count())->toBe(1)
            ->and($purge->type)->toBe(TypePurge::Manuelle)
            ->and($purge->nombre_entrees)->toBe(2)
            ->and($purge->motif)->toBe('Demande de la direction')
            ->and($purge->purgePar->hasRole(Role::Superadmin))->toBeTrue();

        connecter($purge->purgePar)->get(route('gestion.journal.index'))
            ->assertSeeInOrder(['Registre des purges', 'Manuelle', 'Demande de la direction']);
    });

    it('asks for the password before purging', function () {
        entreeJournal('carte.consultee', creeLe: now()->subDays(10)->toDateTimeString());

        connecter(utilisateurAvecRole(Role::Superadmin))
            ->post(route('gestion.journal.purger'), ['avant' => now()->toDateString(), 'motif' => 'Motif valable'])
            ->assertRedirect(route('password.confirm'));

        expect(JournalAudit::where('action', 'carte.consultee')->count())->toBe(1);
    });

    it('requires a reason and a date that is not in the future', function () {
        superadminAvecConfirmation()
            ->post(route('gestion.journal.purger'), ['avant' => now()->addDay()->toDateString(), 'motif' => ''])
            ->assertSessionHasErrors(['avant' => 'La date limite ne peut pas être dans le futur.', 'motif' => 'Le motif de la purge est obligatoire.']);

        expect(PurgeJournalAudit::count())->toBe(0);
    });

    it('reopens the purge tab when the purge is refused', function () {
        $html = superadminAvecConfirmation()
            ->from(route('gestion.journal.index'))
            ->followingRedirects()
            ->post(route('gestion.journal.purger'), ['avant' => now()->toDateString(), 'motif' => ''])
            ->assertOk()
            ->getContent();

        expect($html)->toContain('Le motif de la purge est obligatoire.')
            ->toMatch('/class="tab-pane fade\s+show active\s*" id="panneau-purges"/')
            ->not->toMatch('/class="tab-pane fade\s+show active\s*" id="panneau-entrees"/');
    });

    it('is reserved to the purge permission, which the admin does not hold by default', function () {
        $admin = utilisateurAvecRole(Role::Admin);

        connecter($admin)->get(route('gestion.journal.index'))->assertDontSee(route('gestion.journal.purger'), false);
        connecter($admin)->withSession(['connecte_le' => now()->getTimestamp(), 'auth.password_confirmed_at' => now()->getTimestamp()])
            ->post(route('gestion.journal.purger'), ['avant' => now()->toDateString(), 'motif' => 'Tentative'])
            ->assertForbidden();
    });
});

it('shows changes as before → after with French field names, in the screen and the export', function () {
    entreeJournal('partenaire.modifie', utilisateurAvecRole(Role::Admin), attributs: [
        'type_entite' => 'Partenaire', 'entite_id' => 1,
        'donnees' => json_encode(['avant' => ['taux_reduction' => '10.00', 'nom' => 'Pharma'], 'apres' => ['taux_reduction' => '15.00', 'nom' => 'Pharmacie']]),
    ]);

    $ligne = collect(donneesJournal(utilisateurAvecRole(Role::Admin), ['action' => 'partenaire.modifie'])['data'])->sole();

    expect($ligne['details'])->toContain('<dt>Remise</dt>', 'journal-details__avant">10.00</span>', 'journal-details__apres">15.00</span>', '<dt>Nom</dt>')
        ->and(LibellesAudit::detailsTexte(['avant' => ['nom' => 'Pharma'], 'apres' => ['nom' => 'Pharmacie'], 'valide' => true]))
        ->toBe('Carte valide : Oui · Nom : Pharma → Pharmacie');
});
