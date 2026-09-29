<x-layouts.app titre="Éléments supprimés" sous-titre="Back-office · Système">
    <h1 class="h3 fw-bold mb-1">Éléments supprimés</h1>
    <p class="text-secondary mb-4">
        Partenaires et comptes supprimés : ils ne peuvent plus se connecter ni effectuer de transaction, mais leur historique est conservé.
        Réservé au superadmin ; chaque restauration est inscrite au journal d'audit.
    </p>

    <section class="card border-0 shadow-sm mb-4" aria-labelledby="titre-partenaires-supprimes">
        <div class="card-body">
            <h2 class="h5 fw-bold" id="titre-partenaires-supprimes">Partenaires supprimés</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Partenaire</th>
                            <th scope="col">Supprimé le</th>
                            <th scope="col">Par</th>
                            <th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($partenaires as $partenaire)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $partenaire->nom }}</span>
                                    <span class="d-block small text-secondary">{{ $partenaire->secteur ?? '—' }} · {{ $partenaire->tauxFormate() }}</span>
                                </td>
                                <td class="text-nowrap">{{ $partenaire->deleted_at->format('d/m/Y H:i') }}</td>
                                <td class="small">{{ $partenaire->supprimePar?->libelleActeur() ?? '—' }}</td>
                                <td class="text-end">
                                    <form method="POST" action="{{ route('gestion.corbeille.partenaires.restaurer', $partenaire) }}"
                                          class="d-inline-flex flex-wrap align-items-center justify-content-end gap-2"
                                          data-titre="Restaurer {{ $partenaire->nom }} ?"
                                          data-confirmer="Le partenaire réapparaîtra dans la liste avec son statut d'avant la suppression."
                                          data-bouton-confirmer="Restaurer">
                                        @csrf
                                        @if ($partenaire->utilisateurs_supprimes_count > 0)
                                            <div class="form-check mb-0">
                                                <input class="form-check-input" type="checkbox" name="avec_utilisateurs" value="1" checked
                                                       id="avec-utilisateurs-{{ $partenaire->id }}">
                                                <label class="form-check-label small" for="avec-utilisateurs-{{ $partenaire->id }}">
                                                    avec ses {{ $partenaire->utilisateurs_supprimes_count }} utilisateur(s)
                                                </label>
                                            </div>
                                        @endif
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Restaurer
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-secondary">Aucun partenaire supprimé.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $partenaires->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
        </div>
    </section>

    <section class="card border-0 shadow-sm" aria-labelledby="titre-comptes-supprimes">
        <div class="card-body">
            <h2 class="h5 fw-bold" id="titre-comptes-supprimes">Comptes supprimés</h2>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Compte</th>
                            <th scope="col">Rôle</th>
                            <th scope="col">Supprimé le</th>
                            <th scope="col">Par</th>
                            <th scope="col" class="text-end"><span class="visually-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($comptes as $compte)
                            <tr>
                                <td>
                                    <span class="fw-semibold">{{ $compte->nom }}</span>
                                    <span class="d-block small font-monospace text-secondary">{{ '@'.$compte->nom_utilisateur }}</span>
                                </td>
                                <td class="small">
                                    {{ $compte->rolePrincipal()?->libelle() ?? '—' }}
                                    @if ($compte->partenaire)
                                        <span class="d-block text-secondary">
                                            {{ $compte->partenaire->nom }}
                                            @if ($compte->partenaire->trashed())
                                                <span class="badge text-bg-secondary">supprimé</span>
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td class="text-nowrap">{{ $compte->deleted_at->format('d/m/Y H:i') }}</td>
                                <td class="small">{{ $compte->supprimePar?->libelleActeur() ?? '—' }}</td>
                                <td class="text-end">
                                    @if ($compte->partenaire?->trashed())
                                        <span class="small text-secondary">Restaurez d'abord le partenaire</span>
                                    @else
                                        <form method="POST" action="{{ route('gestion.corbeille.comptes.restaurer', $compte) }}"
                                              data-titre="Restaurer le compte {{ $compte->nom_utilisateur }} ?"
                                              data-confirmer="L'utilisateur pourra de nouveau se connecter avec son PIN actuel."
                                              data-bouton-confirmer="Restaurer">
                                            @csrf
                                            <button type="submit" class="btn btn-sm btn-outline-success">
                                                <i class="bi bi-arrow-counterclockwise me-1" aria-hidden="true"></i>Restaurer
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-secondary">Aucun compte supprimé.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">{{ $comptes->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
        </div>
    </section>
</x-layouts.app>
