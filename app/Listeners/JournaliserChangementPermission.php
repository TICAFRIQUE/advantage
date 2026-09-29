<?php

namespace App\Listeners;

use App\Services\JournaliserAudit;
use Spatie\Permission\Events\PermissionAttachedEvent;
use Spatie\Permission\Events\PermissionDetachedEvent;
use Spatie\Permission\Models\Permission;

/**
 * Trace toute permission ajoutée ou retirée, sur un rôle (Paramètres › Rôles
 * et permissions, synchronisation) comme directement sur un compte.
 */
class JournaliserChangementPermission
{
    public function handle(PermissionAttachedEvent|PermissionDetachedEvent $event): void
    {
        JournaliserAudit::enregistrer(
            $event instanceof PermissionAttachedEvent ? 'permission.attribuee' : 'permission.retiree',
            $event->model,
            ['permissions' => $this->noms($event->permissionsOrIds)],
        );
    }

    /**
     * @return list<string>
     */
    private function noms(mixed $permissionsOrIds): array
    {
        $ids = collect(is_iterable($permissionsOrIds) ? $permissionsOrIds : [$permissionsOrIds])
            ->map(fn ($permission) => $permission instanceof Permission ? $permission->getKey() : $permission);

        return Permission::query()->whereKey($ids->all())->pluck('name')->sort()->values()->all();
    }
}
