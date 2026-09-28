<?php

namespace App\Listeners;

use App\Services\JournaliserAudit;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Events\RoleDetachedEvent;
use Spatie\Permission\Models\Role;

/**
 * Trace toute attribution ou tout retrait de rôle.
 */
class JournaliserChangementRole
{
    public function handle(RoleAttachedEvent|RoleDetachedEvent $event): void
    {
        JournaliserAudit::enregistrer(
            $event instanceof RoleAttachedEvent ? 'role.attribue' : 'role.retire',
            $event->model,
            ['roles' => $this->nomsDesRoles($event->rolesOrIds)],
        );
    }

    /**
     * @return list<string>
     */
    private function nomsDesRoles(mixed $rolesOrIds): array
    {
        $ids = collect(is_iterable($rolesOrIds) ? $rolesOrIds : [$rolesOrIds])
            ->map(fn ($role) => $role instanceof Role ? $role->getKey() : $role);

        return Role::query()->whereKey($ids->all())->pluck('name')->sort()->values()->all();
    }
}
