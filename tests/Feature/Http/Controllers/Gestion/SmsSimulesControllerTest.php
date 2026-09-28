<?php

use App\Enums\Role;
use App\Enums\TypeSms;
use App\Models\MessageSms;
use App\Services\Sms\EnvoiSms;

it('shows simulated messages, codes included, to the admin', function () {
    app(EnvoiSms::class)->envoyer('+2250707123456', 'Votre code ADVANTAGE : 482915', TypeSms::Otp);

    connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.sms-simules.index'))
        ->assertOk()
        ->assertSee('Votre code ADVANTAGE : 482915')
        ->assertSee('+225 07 07 12 34 56')
        ->assertSee('Mode simulation');
});

it('sends a test message through the whole chain', function () {
    connecter(utilisateurAvecRole(Role::Admin))
        ->post(route('gestion.sms-simules.store'), ['telephone' => '07 07 12 34 56', 'message' => 'Test ADVANTAGE'])
        ->assertRedirect(route('gestion.sms-simules.index'));

    expect(MessageSms::sole())
        ->telephone->toBe('+2250707123456')
        ->contenu->toBe('Test ADVANTAGE');
});

it('validates the test message', function () {
    connecter(utilisateurAvecRole(Role::Admin))
        ->post(route('gestion.sms-simules.store'), ['telephone' => '123', 'message' => ''])
        ->assertSessionHasErrors(['telephone', 'message']);
});

it('forbids agents and partners from reading the simulated inbox', function (Role $role) {
    connecter(utilisateurAvecRole($role))->get(route('gestion.sms-simules.index'))->assertForbidden();
})->with([Role::Agent, Role::Partenaire]);

it('is unavailable as soon as a real provider is configured', function () {
    config(['plateforme.sms.driver' => 'fournisseur']);

    connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.sms-simules.index'))->assertNotFound();
});
