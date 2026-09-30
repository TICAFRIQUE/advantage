<?php

use App\Enums\Role;
use App\Models\JournalAudit;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

function superadminMotDePasseConfirme(User $superadmin): TestCase
{
    return connecter($superadmin)->withSession([
        'connecte_le' => now()->getTimestamp(),
        'auth.password_confirmed_at' => now()->getTimestamp(),
    ]);
}

it('lets the superadmin choose a new password', function () {
    $superadmin = utilisateurAvecRole(Role::Superadmin);

    superadminMotDePasseConfirme($superadmin)
        ->put(route('profil.mot-de-passe.modifier'), ['mot_de_passe' => '60394', 'mot_de_passe_confirmation' => '60394'])
        ->assertRedirect(route('profil'))
        ->assertSessionHas('succes');

    expect(Hash::check('60394', $superadmin->fresh()->password))->toBeTrue()
        ->and(JournalAudit::where('action', 'utilisateur.pin_reinitialise')->where('entite_id', $superadmin->id)->exists())->toBeTrue();
});

it('refuses a weak, malformed, unconfirmed or unchanged password', function (array $saisie) {
    $superadmin = utilisateurAvecRole(Role::Superadmin);

    superadminMotDePasseConfirme($superadmin)
        ->put(route('profil.mot-de-passe.modifier'), $saisie)
        ->assertSessionHasErrors('mot_de_passe');

    expect(Hash::check(UserFactory::PIN, $superadmin->fresh()->password))->toBeTrue();
})->with([
    'suite' => [['mot_de_passe' => '12345', 'mot_de_passe_confirmation' => '12345']],
    'chiffre répété' => [['mot_de_passe' => '77777', 'mot_de_passe_confirmation' => '77777']],
    'quatre chiffres' => [['mot_de_passe' => '4815', 'mot_de_passe_confirmation' => '4815']],
    'lettres' => [['mot_de_passe' => 'abcde', 'mot_de_passe_confirmation' => 'abcde']],
    'confirmation différente' => [['mot_de_passe' => '60394', 'mot_de_passe_confirmation' => '60395']],
    'identique à l\'actuel' => [['mot_de_passe' => UserFactory::PIN, 'mot_de_passe_confirmation' => UserFactory::PIN]],
]);

it('generates a new password shown once', function () {
    $superadmin = utilisateurAvecRole(Role::Superadmin);

    superadminMotDePasseConfirme($superadmin)
        ->post(route('profil.mot-de-passe.generer'))
        ->assertRedirect(route('profil'))
        ->assertSessionHas('pin_genere', fn (array $genere) => $genere['personnel'] === true
            && preg_match('/^\d{5}$/', $genere['pin']) === 1
            && Hash::check($genere['pin'], $superadmin->fresh()->password));
});

it('asks for the current password first', function () {
    $superadmin = utilisateurAvecRole(Role::Superadmin);

    connecter($superadmin)->withSession(['connecte_le' => now()->getTimestamp()])
        ->post(route('profil.mot-de-passe.generer'))
        ->assertRedirect(route('password.confirm'));

    expect(Hash::check(UserFactory::PIN, $superadmin->fresh()->password))->toBeTrue();
});

it('is reserved to the superadmin', function (Role $role) {
    $compte = utilisateurAvecRole($role);

    superadminMotDePasseConfirme($compte)->post(route('profil.mot-de-passe.generer'))->assertForbidden();
    superadminMotDePasseConfirme($compte)
        ->put(route('profil.mot-de-passe.modifier'), ['mot_de_passe' => '60394', 'mot_de_passe_confirmation' => '60394'])
        ->assertForbidden();

    expect(Hash::check(UserFactory::PIN, $compte->fresh()->password))->toBeTrue();
})->with([Role::Admin, Role::Agent, Role::Partenaire]);
