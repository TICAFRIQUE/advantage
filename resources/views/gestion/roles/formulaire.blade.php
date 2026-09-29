@php
    $creation = $role === null;
    $modifiablesNoms = array_map(fn ($p) => $p->value, $modifiables);
    $cocheesAnciennes = old('permissions', $cochees);
    $titre = $creation ? 'Nouveau rôle' : 'Modifier le rôle '.$role->libelle();
@endphp

<x-layouts.app :titre="$titre" sous-titre="Back-office · Paramètres">
    <nav aria-label="Fil d'Ariane" class="mb-3">
        <ol class="breadcrumb mb-0">
            <li class="breadcrumb-item"><a href="{{ route('gestion.roles.index') }}">Rôles et permissions</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $creation ? 'Nouveau' : $role->libelle() }}</li>
        </ol>
    </nav>

    <div class="row justify-content-center">
        <div class="col-12 col-lg-10 col-xl-8">
            <h1 class="h3 fw-bold mb-1">{{ $titre }}</h1>
            <p class="text-secondary mb-4">
                @if ($creation)
                    Rôle du back-office : il s'attribue ensuite aux utilisateurs dans Paramètres › Utilisateurs.
                @else
                    {{ $role->espace() === 'partenaire' ? 'Espace partenaire' : 'Back-office' }} ·
                    {{ $nombreComptes }} compte(s) avec ce rôle.
                @endif
                Les cases grisées correspondent à des permissions que vous ne détenez pas ou que vous ne pouvez pas modifier.
            </p>

            <form method="POST" novalidate class="card border-0 shadow-sm mb-4"
                  action="{{ $creation ? route('gestion.roles.store') : route('gestion.roles.update', $role) }}">
                @csrf
                @unless ($creation) @method('PUT') @endunless

                <div class="card-body p-4">
                    @if ($creation || ! $role->estSysteme())
                        <div class="mb-4">
                            <label for="libelle" class="form-label fw-semibold">Nom du rôle</label>
                            <input type="text" id="libelle" name="libelle" value="{{ old('libelle', $role?->getAttributes()['libelle'] ?? null) }}" required maxlength="60"
                                   @disabled(! $creation && ! $peutRenommer) placeholder="ex. Superviseur d'agence"
                                   class="form-control form-control-lg @error('libelle') is-invalid @enderror">
                            @error('libelle')<div class="invalid-feedback">{{ $message }}</div>@enderror
                        </div>
                    @endif

                    @error('permissions')<div class="alert alert-danger">{{ $message }}</div>@enderror
                    @error('permissions.*')<div class="alert alert-danger">{{ $message }}</div>@enderror

                    @foreach ($groupes as $groupe)
                        <fieldset class="mb-4">
                            <legend class="h6 fw-bold text-uppercase text-secondary">{{ $groupe['libelle'] }}</legend>
                            <div class="row g-2">
                                @foreach ($groupe['permissions'] as $permission)
                                    @php($modifiable = in_array($permission->value, $modifiablesNoms, true))
                                    @php($cochee = in_array($permission->value, $cocheesAnciennes, true))
                                    <div class="col-12 col-md-6">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="permissions[]" value="{{ $permission->value }}"
                                                   id="permission-{{ $permission->value }}" @checked($cochee) @disabled(! $modifiable)>
                                            <label class="form-check-label {{ $modifiable ? '' : 'text-secondary' }}" for="permission-{{ $permission->value }}">
                                                {{ $permission->libelle() }}
                                                @unless ($modifiable)
                                                    <i class="bi bi-lock ms-1" aria-hidden="true"></i><span class="visually-hidden">(non modifiable)</span>
                                                @endunless
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">{{ $creation ? 'Créer le rôle' : 'Enregistrer' }}</button>
                        <a href="{{ route('gestion.roles.index') }}" class="btn btn-link">Annuler</a>
                    </div>
                </div>
            </form>

            @if (! $creation && $peutRenommer)
                <form method="POST" action="{{ route('gestion.roles.destroy', $role) }}" class="card border-danger-subtle shadow-sm"
                      data-titre="Supprimer le rôle {{ $role->libelle() }} ?"
                      data-confirmer="Le rôle doit d'abord être retiré de tous les comptes."
                      data-bouton-confirmer="Supprimer" data-danger="1">
                    @csrf
                    @method('DELETE')
                    <div class="card-body d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <span class="small text-secondary">Un rôle encore attribué à des comptes ne peut pas être supprimé.</span>
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="bi bi-trash me-1" aria-hidden="true"></i>Supprimer le rôle
                        </button>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-layouts.app>
