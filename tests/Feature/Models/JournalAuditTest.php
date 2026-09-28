<?php

use App\Exceptions\JournalAuditImmuableException;
use App\Models\JournalAudit;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function creerEntreeAudit(): JournalAudit
{
    return JournalAudit::create([
        'type_acteur' => 'systeme',
        'action' => 'carte.activee',
        'type_entite' => 'carte',
        'entite_id' => 1,
        'donnees' => ['apres' => ['statut' => 'active']],
    ]);
}

it('records an entry with its creation time and structured data', function () {
    $entree = creerEntreeAudit()->fresh();

    expect($entree->cree_le)->not->toBeNull()
        ->and($entree->donnees)->toBe(['apres' => ['statut' => 'active']]);
});

it('rejects updating an entry through the model', function () {
    creerEntreeAudit()->update(['action' => 'falsifiee']);
})->throws(JournalAuditImmuableException::class);

it('rejects deleting an entry through the model', function () {
    creerEntreeAudit()->delete();
})->throws(JournalAuditImmuableException::class);

it('rejects updating entries with raw sql', function () {
    creerEntreeAudit();

    DB::table('journaux_audit')->update(['action' => 'falsifiee']);
})->throws(QueryException::class, 'journaux_audit est en ajout seul');

it('rejects deleting entries with raw sql', function () {
    creerEntreeAudit();

    DB::table('journaux_audit')->delete();
})->throws(QueryException::class, 'journaux_audit est en ajout seul');
