<?php

use App\Enums\Role;
use App\Models\JournalAudit;
use App\Services\JournaliserAudit;

it('strips sensitive fields at any depth before writing', function () {
    $entree = JournaliserAudit::enregistrer('test.action', donnees: [
        'password' => '12345',
        'code' => '654321',
        'avant' => ['code_hash' => '$2y$04$empreinte', 'statut' => 'actif'],
    ]);

    expect($entree->fresh()->donnees)->toBe(['avant' => ['statut' => 'actif']]);
});

it('records the authenticated user as the actor', function () {
    $admin = utilisateurAvecRole(Role::Admin);
    $this->actingAs($admin);

    $entree = JournaliserAudit::enregistrer('test.action', $admin);

    expect($entree)
        ->acteur_id->toBe($admin->id)
        ->type_acteur->toBe('utilisateur')
        ->type_entite->toBe('User')
        ->entite_id->toBe($admin->id);
});

it('records a pin reset without the pin', function () {
    $agent = utilisateurAvecRole(Role::Agent);

    $agent->update(['password' => '48291']);

    $entree = JournalAudit::where('action', 'utilisateur.pin_reinitialise')->sole();

    expect(json_encode($entree->toArray()))->not->toContain('48291');
});

it('records role assignments', function () {
    utilisateurAvecRole(Role::Agent);

    expect(JournalAudit::where('action', 'role.attribue')->sole()->donnees)->toBe(['roles' => ['agent']]);
});

it('records business changes with before and after values', function () {
    $agent = utilisateurAvecRole(Role::Agent, ['nom' => 'Ancien Nom']);

    $agent->update(['nom' => 'Nouveau Nom']);

    expect(JournalAudit::where('action', 'utilisateur.modifie')->sole()->donnees)
        ->toEqual(['avant' => ['nom' => 'Ancien Nom'], 'apres' => ['nom' => 'Nouveau Nom']]);
});
