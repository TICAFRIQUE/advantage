<x-layouts.app titre="Mon profil" :sous-titre="$compte->partenaire ? 'Espace partenaire' : 'Back-office'">
    <div class="row g-4">
        <div class="col-12 col-lg-5">
            <section class="card border-0 shadow-sm" aria-labelledby="titre-profil">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <span class="avatar avatar--grand" aria-hidden="true">{{ $compte->initiales() }}</span>
                        <div class="min-w-0">
                            <h1 class="h4 mb-0 text-truncate" id="titre-profil">{{ $compte->nom }}</h1>
                            <span class="font-monospace small text-secondary">{{ '@'.$compte->nom_utilisateur }}</span>
                            <span class="d-block mt-1"><span class="badge rounded-pill text-bg-warning">{{ $compte->rolePrincipal()?->libelle() ?? '—' }}</span></span>
                        </div>
                    </div>

                    <dl class="row small mb-0 fiche-infos">
                        @if ($compte->partenaire)
                            <dt class="col-5">Partenaire</dt><dd class="col-7">{{ $compte->partenaire->nom }}</dd>
                        @endif
                        <dt class="col-5">Téléphone</dt>
                        <dd class="col-7">{{ $compte->telephone ? App\Services\Telephone::formater($compte->telephone) : '—' }}</dd>
                        <dt class="col-5">E-mail</dt><dd class="col-7 text-break">{{ $compte->email ?? '—' }}</dd>
                        <dt class="col-5">Dernière connexion</dt>
                        <dd class="col-7">{{ $compte->derniere_connexion_le?->format('d/m/Y à H:i') ?? '—' }}</dd>
                        <dt class="col-5">Compte créé le</dt>
                        <dd class="col-7">{{ $compte->created_at->format('d/m/Y') }} par {{ $compte->creePar?->libelleActeur() ?? 'Système' }}</dd>
                        @if ($compte->modifiePar)
                            <dt class="col-5">Modifié le</dt>
                            <dd class="col-7">{{ $compte->updated_at->format('d/m/Y') }} par {{ $compte->modifiePar->libelleActeur() }}</dd>
                        @endif
                    </dl>

                    <div class="alert alert-light border small mt-4 mb-0" role="note">
                        <i class="bi bi-info-circle me-1" aria-hidden="true"></i>
                        @if ($superadmin)
                            Votre mot de passe se change depuis la configuration du serveur (<code>SUPERADMIN_MOT_DE_PASSE</code>).
                        @else
                            Pour modifier vos informations ou obtenir un nouveau PIN, adressez-vous à un administrateur.
                        @endif
                    </div>
                </div>
            </section>
        </div>

        <div class="col-12 col-lg-7">
            <section class="card border-0 shadow-sm" aria-labelledby="titre-droits">
                <div class="card-body p-4">
                    <h2 class="h6 mb-1" id="titre-droits"><i class="bi bi-shield-check me-1" aria-hidden="true"></i>Mes droits</h2>
                    <p class="small text-secondary mb-3">
                        {{ $superadmin ? 'Super administrateur : vous disposez de tous les droits.' : 'Ce que votre rôle vous permet de faire.' }}
                    </p>
                    @foreach ($groupes as $groupe)
                        <h3 class="small text-uppercase text-secondary fw-semibold mt-3 mb-2">{{ $groupe['libelle'] }}</h3>
                        <ul class="list-unstyled small mb-0 liste-droits">
                            @foreach ($groupe['permissions'] as $permission)
                                <li><i class="bi bi-check2 text-success me-1" aria-hidden="true"></i>{{ $permission }}</li>
                            @endforeach
                        </ul>
                    @endforeach
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
