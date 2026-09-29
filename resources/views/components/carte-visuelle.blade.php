@props(['carte'])

@php
    $statut = $carte->statutEffectif();
    $chiffres = str_split($carte->numero_carte);
    // Échéance proche (moins de 3 mois) : bandeau posé sur la carte.
    $echeance = $carte->echeanceProche();
@endphp

<div {{ $attributes->class(['carte-adv', 'carte-adv--inactive' => $statut !== \App\Enums\StatutCarte::Active]) }}
     role="img" aria-label="Carte ADVANTAGE numéro {{ $carte->numeroFormate() }}, {{ $statut->libelle() }}{{ $echeance ? ', '.mb_strtolower($echeance['libelle']) : '' }}">
    <img class="carte-adv__papillon" src="{{ asset('images/papillon-or.png') }}" alt="">

    <div class="carte-adv__marque">
        <span class="carte-adv__groupe">fontaine <strong>GROUP</strong></span>
        <span class="carte-adv__nom">ADVANTAGE</span>
    </div>

    <span class="carte-adv__puce"></span>

    <div class="carte-adv__expiration">
        @if ($statut === \App\Enums\StatutCarte::Active)
            EXPIRE DANS<br>{{ $carte->moisRestants() }} MOIS
        @else
            {{ mb_strtoupper($statut->libelle()) }}
        @endif
    </div>

    <img class="carte-adv__logo" src="{{ asset('images/logo-fg.png') }}" alt="">

    <div class="carte-adv__type">CARTE DE<br>REMISE</div>

    <div class="carte-adv__numero">
        @foreach ($chiffres as $index => $chiffre)
            @if ($index === 6)
                <span class="carte-adv__lignes"><i></i><i></i><i></i></span>
            @endif
            <span class="carte-adv__chiffre">{{ $chiffre }}</span>
        @endforeach
    </div>

    @if ($echeance)
        <span class="carte-adv__echeance carte-adv__echeance--{{ $echeance['niveau'] }}" aria-hidden="true">
            <i class="bi bi-hourglass-split"></i> {{ $echeance['libelle'] }}
        </span>
    @endif

    @if ($statut !== \App\Enums\StatutCarte::Active)
        <span class="carte-adv__bandeau">{{ $statut->libelle() }}</span>
    @endif
</div>
