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
            <section class="card border-0 shadow-sm" aria-labelledby="titre-activite">
                <div class="card-body p-4">
                    <h2 class="h6 mb-1" id="titre-activite"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>Mon activité récente</h2>
                    <p class="small text-secondary mb-3">
                        Vos {{ App\Http\Controllers\ProfilController::ACTIVITES }} dernières actions
                        (journal conservé {{ config('plateforme.journal_audit.retention_jours') }} jours).
                    </p>
                    <ul class="list-group list-group-flush small">
                        @forelse ($activites as $activite)
                            <li class="list-group-item px-0 d-flex justify-content-between align-items-start gap-3">
                                <div class="min-w-0">
                                    <span class="fw-semibold">{{ App\Support\LibellesAudit::action($activite->action) }}</span>
                                    @if ($details = App\Support\LibellesAudit::detailsTexte($activite->donnees))
                                        <span class="d-block text-secondary text-break">{{ Illuminate\Support\Str::limit($details, 140) }}</span>
                                    @endif
                                </div>
                                <time class="text-secondary text-nowrap" datetime="{{ $activite->cree_le->toIso8601String() }}"
                                      title="{{ $activite->cree_le->format('d/m/Y à H:i:s') }}">{{ $activite->cree_le->format('d/m/Y H:i') }}</time>
                            </li>
                        @empty
                            <li class="list-group-item px-0 text-secondary">Aucune activité enregistrée récemment.</li>
                        @endforelse
                    </ul>
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
