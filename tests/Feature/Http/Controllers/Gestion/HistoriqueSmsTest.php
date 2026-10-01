<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\StatutLivraison;
use App\Enums\TypeSms;
use App\Models\MessageSms;
use App\Models\User;
use Illuminate\Support\Carbon;

function smsHistorique(TypeSms $type, StatutLivraison $statut, string $telephone = '+2250707123456', array $attributs = []): MessageSms
{
    $message = MessageSms::create([
        'telephone' => $telephone,
        'type' => $type,
        'contenu' => 'Votre code : 654321',
        'statut' => $statut,
        'fournisseur' => 'ticafrique',
    ]);

    $message->forceFill($attributs)->save();

    return $message;
}

function donneesHistoriqueSms(User $user, array $parametres = []): array
{
    return connecter($user)
        ->getJson(route('gestion.sms-historique.donnees', array_merge(['draw' => 1, 'start' => 0, 'length' => 100], $parametres)))
        ->assertOk()
        ->json();
}

it('is reserved to the superadmin, even if the permission were granted', function () {
    $admin = utilisateurAvecRole(Role::Admin);
    $admin->givePermissionTo(Permission::VoirHistoriqueSms->value);

    connecter($admin)->get(route('gestion.sms-historique.index'))->assertForbidden();
    connecter($admin)->getJson(route('gestion.sms-historique.donnees'))->assertForbidden();

    connecter(utilisateurAvecRole(Role::Superadmin))->get(route('gestion.sms-historique.index'))
        ->assertOk()
        ->assertSee('Historique des SMS');
});

it('lists every sms with its status, error or reference, and never its content', function () {
    smsHistorique(TypeSms::Otp, StatutLivraison::Envoyee, attributs: ['reference_fournisseur' => 'TIC-42', 'tentatives' => 1, 'envoye_le' => now()]);
    smsHistorique(TypeSms::AlerteExpiration, StatutLivraison::Echec, '+2250505000000', ['erreur' => 'Solde <insuffisant>', 'tentatives' => 3]);
    smsHistorique(TypeSms::Test, StatutLivraison::EnAttente, '+2250101000000');

    $reponse = donneesHistoriqueSms(utilisateurAvecRole(Role::Superadmin));
    $lignes = collect($reponse['data'])->keyBy('type');

    expect($reponse['recordsTotal'])->toBe(3)
        ->and($lignes['Code de validation']['statut'])->toContain('Envoyée')->toContain('TIC-42')
        ->and($lignes[e("Alerte d'expiration")]['statut'])->toContain('Échec')->toContain('Solde &lt;insuffisant&gt;')
        ->and($lignes[e("Test d'envoi")]['statut'])->toContain('En attente')
        ->and($lignes[e("Test d'envoi")]['envoye_le'])->toBe('—')
        ->and(json_encode($reponse))->not->toContain('654321')->not->toContain('contenu');
});

it('filters by type, status and period, and searches by phone number', function () {
    $superadmin = utilisateurAvecRole(Role::Superadmin);

    smsHistorique(TypeSms::Otp, StatutLivraison::Envoyee);
    smsHistorique(TypeSms::Otp, StatutLivraison::Echec, '+2250505000000');
    smsHistorique(TypeSms::Test, StatutLivraison::Envoyee, '+2250101000000', ['created_at' => Carbon::parse('2026-01-10 12:00:00')]);

    expect(donneesHistoriqueSms($superadmin, ['type' => 'otp'])['recordsFiltered'])->toBe(2)
        ->and(donneesHistoriqueSms($superadmin, ['statut' => 'echec'])['recordsFiltered'])->toBe(1)
        ->and(donneesHistoriqueSms($superadmin, ['du' => '2026-01-10', 'au' => '2026-01-10'])['recordsFiltered'])->toBe(1)
        ->and(donneesHistoriqueSms($superadmin, ['search' => ['value' => '07 07 12 34 56']])['recordsFiltered'])->toBe(1)
        ->and(donneesHistoriqueSms($superadmin, ['search' => ['value' => 'abc']])['recordsFiltered'])->toBe(0);

    connecter($superadmin)->getJson(route('gestion.sms-historique.donnees', ['type' => 'inconnu']))->assertUnprocessable();
});

it('warns when messages have been waiting too long (worker stopped)', function () {
    $superadmin = utilisateurAvecRole(Role::Superadmin);
    smsHistorique(TypeSms::Otp, StatutLivraison::EnAttente);

    connecter($superadmin)->get(route('gestion.sms-historique.index'))->assertOk()->assertDontSee('le worker de la file d\'attente ne tourne pas. Vérifiez', false);

    smsHistorique(TypeSms::Otp, StatutLivraison::EnAttente, attributs: ['created_at' => now()->subMinutes(10)]);

    connecter($superadmin)->get(route('gestion.sms-historique.index'))->assertOk()
        ->assertSee('1 SMS en attente depuis plus de 5 minutes');
});

it('shows the entry in the system menu for the superadmin only', function () {
    connecter(utilisateurAvecRole(Role::Superadmin))->get(route('gestion.tableau-de-bord'))->assertSee(route('gestion.sms-historique.index'));
    connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.tableau-de-bord'))->assertDontSee(route('gestion.sms-historique.index'));
});
