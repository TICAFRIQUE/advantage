{{--
    Desktop (≥ lg) : barre fixe, réductible (icônes seules + infobulles).
    Mobile / tablette (< lg) : volet coulissant (offcanvas Bootstrap).
--}}
<aside class="offcanvas-lg offcanvas-start barre-laterale" tabindex="-1" id="barre-laterale" aria-labelledby="barre-laterale-titre">
    <div class="barre-laterale__marque">
        <a href="{{ route('accueil-espace') }}" class="d-flex align-items-center gap-2 text-decoration-none" aria-label="{{ App\Services\Parametres::nomApplication() }} — accueil">
            <img src="{{ App\Services\Parametres::logoUrl() }}" alt="" width="40" height="40" class="rounded flex-shrink-0 logo-marque">
            <span class="barre-laterale__texte lh-1">
                <span class="d-block texte-or small fw-semibold">{{ App\Services\Parametres::nomOrganisation() }}</span>
                <span class="d-block text-white fw-bolder fs-5" id="barre-laterale-titre">{{ App\Services\Parametres::nomApplication() }}</span>
            </span>
        </a>
        <button type="button" class="btn-close btn-close-white d-lg-none ms-auto" data-bs-dismiss="offcanvas"
                data-bs-target="#barre-laterale" aria-label="Fermer le menu"></button>
    </div>

    <nav class="barre-laterale__nav" aria-label="Navigation principale">
        @foreach ($sections as $section)
            @if ($section['titre'])
                <p class="barre-laterale__section">{{ $section['titre'] }}</p>
            @endif
            <ul class="list-unstyled mb-2">
                @foreach ($section['entrees'] as $entree)
                    <li>
                        <a href="{{ $entree['url'] }}"
                           @class(['barre-laterale__lien', 'actif' => $entree['actif']])
                           @if ($entree['actif']) aria-current="page" @endif
                           data-infobulle="{{ $entree['libelle'] }}">
                            <i class="bi {{ $entree['icone'] }}" aria-hidden="true"></i>
                            <span class="barre-laterale__texte">{{ $entree['libelle'] }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endforeach
    </nav>

    <div class="barre-laterale__pied d-none d-lg-block">
        <button type="button" class="barre-laterale__lien w-100 border-0 bg-transparent" data-basculer-barre
                aria-controls="barre-laterale" aria-expanded="{{ $reduite ? 'false' : 'true' }}"
                data-infobulle="Étendre le menu">
            <i class="bi bi-chevron-double-left barre-laterale__chevron" aria-hidden="true"></i>
            <span class="barre-laterale__texte">Réduire le menu</span>
        </button>
    </div>
</aside>
