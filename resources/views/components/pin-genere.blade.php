{{--
    PIN généré (création de compte ou réinitialisation) : affiché UNE seule fois.
    Il transite par un message flash, consommé à cette requête ; seul son hash
    est conservé en base. Pages en « Cache-Control: no-store » (pas de retour arrière).
--}}
@if (session('pin_genere'))
    @php($pin = session('pin_genere'))
    <section class="alert alert-warning border-2 shadow-sm pin-genere" role="alert" aria-labelledby="titre-pin"
             x-data="pinGenere(@js($pin['pin']))">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <i class="bi bi-key-fill fs-2" aria-hidden="true"></i>
            <div class="flex-grow-1">
                @if ($pin['personnel'] ?? false)
                    <h2 class="h6 fw-bold mb-1" id="titre-pin">Votre nouveau mot de passe</h2>
                    <p class="small mb-0">Notez-le maintenant. <strong>Il ne sera plus jamais affiché.</strong></p>
                @else
                    <h2 class="h6 fw-bold mb-1" id="titre-pin">PIN de {{ $pin['nom'] }} ({{ '@'.$pin['nom_utilisateur'] }})</h2>
                    <p class="small mb-0">Transmettez-le à l'utilisateur en main propre. <strong>Il ne sera plus jamais affiché.</strong></p>
                @endif
            </div>
            <output class="pin-genere__code" aria-label="PIN">{{ $pin['pin'] }}</output>
            <button type="button" class="btn btn-dark" x-on:click="copier()">
                <i class="bi" x-bind:class="copie ? 'bi-check2' : 'bi-clipboard'" aria-hidden="true"></i>
                <span x-text="copie ? 'Copié' : 'Copier'">Copier</span>
            </button>
        </div>
    </section>
@endif
