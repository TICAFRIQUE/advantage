<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Enums\StatutLivraison;
use App\Enums\TypeSms;
use App\Models\JournalAudit;
use App\Models\MessageSms;
use Illuminate\Support\Facades\Http;

it('is reserved to the superadmin, even if the permission were granted', function () {
    $admin = utilisateurAvecRole(Role::Admin);
    $admin->givePermissionTo(Permission::TesterSms->value);

    connecter($admin)->get(route('gestion.sms-test.index'))->assertForbidden();
    connecter($admin)->post(route('gestion.sms-test.store'), ['telephone' => '0707123456'])->assertForbidden();

    connecter(utilisateurAvecRole(Role::Superadmin))->get(route('gestion.sms-test.index'))
        ->assertOk()
        ->assertSee('Pilote de')
        ->assertSee('simulation');
});

it('sends a fixed test text through the queue, and audits it with a masked number', function () {
    $superadmin = utilisateurAvecRole(Role::Superadmin);

    connecter($superadmin)->post(route('gestion.sms-test.store'), ['telephone' => '07 79 61 35 93'])
        ->assertRedirect(route('gestion.sms-test.index'))
        ->assertSessionHas('succes');

    $message = MessageSms::sole();

    expect($message)
        ->type->toBe(TypeSms::Test)
        ->telephone->toBe('+2250779613593')
        ->statut->toBe(StatutLivraison::Envoyee)
        ->and($message->contenu)->toContain('SMS de test')
        ->and(JournalAudit::where('action', 'sms.test')->sole()->donnees)->toBe(['pilote' => 'simulation', 'telephone' => '+22507******93']);

    connecter($superadmin)->get(route('gestion.sms-test.index'))->assertSee('+22507******93')->assertSee('Envoyée');
});

it('really sends through TICAFRIQUE when configured, and shows the reference', function () {
    config(['plateforme.sms.driver' => 'ticafrique', 'services.ticafrique.url' => 'https://sms.ticafrique.ci/api/v1/sms/send', 'services.ticafrique.cle' => 'cle', 'services.ticafrique.expediteur' => 'FONTAINE G']);
    Http::fake(['*' => Http::response(['success' => true, 'message' => 'SMS sent successfully', 'data' => ['message_ids' => ['TIC-42']]])]);
    $superadmin = utilisateurAvecRole(Role::Superadmin);

    connecter($superadmin)->post(route('gestion.sms-test.store'), ['telephone' => '0779613593'])->assertSessionHas('succes');

    expect(MessageSms::sole())->reference_fournisseur->toBe('TIC-42')->fournisseur->toBe('ticafrique');
    connecter($superadmin)->get(route('gestion.sms-test.index'))->assertSee('chaque test consomme une unité')->assertSee('TIC-42');
});

it('limits real tests to protect SMS units', function () {
    $superadmin = utilisateurAvecRole(Role::Superadmin);

    foreach (range(1, 5) as $essai) {
        connecter($superadmin)->post(route('gestion.sms-test.store'), ['telephone' => '0707123456'])->assertSessionHas('succes');
    }

    connecter($superadmin)->post(route('gestion.sms-test.store'), ['telephone' => '0707123456'])->assertStatus(429);
    expect(MessageSms::count())->toBe(5);
});

it('rejects an invalid number', function () {
    connecter(utilisateurAvecRole(Role::Superadmin))
        ->post(route('gestion.sms-test.store'), ['telephone' => '1234'])
        ->assertSessionHasErrors('telephone');

    expect(MessageSms::count())->toBe(0);
});
