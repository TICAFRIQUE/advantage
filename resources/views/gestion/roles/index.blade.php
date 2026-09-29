<x-layouts.app titre="Rôles et permissions" sous-titre="Back-office · Paramètres">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-1">
        <h1 class="h3 fw-bold mb-0">Rôles et permissions</h1>
        @if ($peutCreer)
            <a href="{{ route('gestion.roles.create') }}" class="btn btn-or">
                <i class="bi bi-plus-circle me-1" aria-hidden="true"></i>Nouveau rôle
            </a>
        @endif
    </div>
    <p class="text-secondary mb-4">
        Chaque utilisateur a un rôle ; un rôle regroupe des permissions. Le super administrateur a tous les droits.
        Vous ne pouvez attribuer que des permissions que vous détenez, et jamais modifier votre propre rôle.
    </p>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive matrice-droits">
                <table class="table table-sm align-middle mb-0">
                    <caption class="visually-hidden">Permissions accordées à chaque rôle</caption>
                    <thead>
                        <tr>
                            <th scope="col" class="matrice-droits__permission">Permission</th>
                            @foreach ($roles as $role)
                                <th scope="col" class="text-center matrice-droits__role">
                                    <span class="d-block fw-bold">{{ $role->libelle() }}</span>
                                    <span class="d-block small fw-normal text-secondary">
                                        {{ $role->espace() === 'partenaire' ? 'Espace partenaire' : 'Back-office' }}
                                        · {{ $role->nombre_comptes }} compte(s)
                                    </span>
                                    @if (! $role->estSysteme())
                                        <span class="badge text-bg-info mt-1">Personnalisé</span>
                                    @endif
                                    @unless ($role->estVerrouille())
                                        <a href="{{ route('gestion.roles.edit', $role) }}" class="btn btn-sm btn-outline-primary d-block mt-2"
                                           aria-label="Modifier le rôle {{ $role->libelle() }}">
                                            <i class="bi bi-pencil me-1" aria-hidden="true"></i>Modifier
                                        </a>
                                    @endunless
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($groupes as $groupe)
                            <tr class="table-light">
                                <th scope="rowgroup" colspan="{{ $roles->count() + 1 }}" class="text-uppercase small fw-bold text-secondary">
                                    {{ $groupe['libelle'] }}
                                </th>
                            </tr>
                            @foreach ($groupe['permissions'] as $permission)
                                <tr>
                                    <th scope="row" class="fw-normal">{{ $permission->libelle() }}</th>
                                    @foreach ($roles as $role)
                                        <td class="text-center">
                                            @if ($role->estVerrouille())
                                                <i class="bi bi-check-circle-fill texte-or-fonce" aria-hidden="true"></i>
                                                <span class="visually-hidden">Accordée (tous les droits)</span>
                                            @elseif ($permission->espace() !== $role->espace())
                                                <span class="text-secondary" aria-hidden="true">·</span>
                                                <span class="visually-hidden">Non applicable à cet espace</span>
                                            @elseif ($role->permissions->contains('name', $permission->value))
                                                <i class="bi bi-check-circle-fill text-success" aria-hidden="true"></i>
                                                <span class="visually-hidden">Accordée</span>
                                            @else
                                                <i class="bi bi-dash text-secondary" aria-hidden="true"></i>
                                                <span class="visually-hidden">Non accordée</span>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
