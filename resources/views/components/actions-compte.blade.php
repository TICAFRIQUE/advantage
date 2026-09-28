@props(['compte'])

@can('gerer', $compte)
    <div class="d-flex flex-wrap gap-1 justify-content-end">
        <form method="POST" action="{{ route('gestion.comptes.pin', $compte) }}"
              data-titre="Réinitialiser le PIN de {{ $compte->nom }} ?"
              data-confirmer="Un nouveau PIN sera généré et affiché une seule fois. L'ancien ne fonctionnera plus."
              data-bouton-confirmer="Réinitialiser">
            @csrf
            <button type="submit" class="btn btn-sm btn-outline-primary" title="Réinitialiser le PIN" aria-label="Réinitialiser le PIN de {{ $compte->nom }}">
                <i class="bi bi-key" aria-hidden="true"></i>
            </button>
        </form>

        <form method="POST" action="{{ route('gestion.comptes.verrouillage', $compte) }}"
              data-titre="{{ $compte->estVerrouille() ? 'Déverrouiller' : 'Verrouiller' }} le compte {{ $compte->nom_utilisateur }} ?"
              data-confirmer="{{ $compte->estVerrouille() ? 'Le compte pourra de nouveau se connecter.' : 'La connexion sera bloquée immédiatement.' }}"
              data-bouton-confirmer="{{ $compte->estVerrouille() ? 'Déverrouiller' : 'Verrouiller' }}">
            @csrf
            <input type="hidden" name="verrouiller" value="{{ $compte->estVerrouille() ? 0 : 1 }}">
            <button type="submit" class="btn btn-sm btn-outline-secondary"
                    aria-label="{{ $compte->estVerrouille() ? 'Déverrouiller' : 'Verrouiller' }} {{ $compte->nom }}"
                    title="{{ $compte->estVerrouille() ? 'Déverrouiller' : 'Verrouiller' }}">
                <i class="bi {{ $compte->estVerrouille() ? 'bi-unlock' : 'bi-lock' }}" aria-hidden="true"></i>
            </button>
        </form>

        @php($actif = $compte->statut === App\Enums\StatutUtilisateur::Actif)
        <form method="POST" action="{{ route('gestion.comptes.statut', $compte) }}"
              data-titre="{{ $actif ? 'Désactiver' : 'Réactiver' }} le compte {{ $compte->nom_utilisateur }} ?"
              data-confirmer="{{ $actif ? 'L\'utilisateur sera déconnecté et ne pourra plus se connecter.' : 'L\'utilisateur pourra de nouveau se connecter.' }}"
              data-bouton-confirmer="{{ $actif ? 'Désactiver' : 'Réactiver' }}" @if ($actif) data-danger="1" @endif>
            @csrf
            <input type="hidden" name="statut" value="{{ $actif ? 'inactif' : 'actif' }}">
            <button type="submit" class="btn btn-sm {{ $actif ? 'btn-outline-danger' : 'btn-outline-success' }}"
                    aria-label="{{ $actif ? 'Désactiver' : 'Réactiver' }} {{ $compte->nom }}" title="{{ $actif ? 'Désactiver' : 'Réactiver' }}">
                <i class="bi {{ $actif ? 'bi-person-x' : 'bi-person-check' }}" aria-hidden="true"></i>
            </button>
        </form>
    </div>
@endcan
