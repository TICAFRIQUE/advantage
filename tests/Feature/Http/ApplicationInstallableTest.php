<?php

use App\Enums\Role;

it('declares the web app manifest and the install button on every page', function () {
    $this->get(route('login'))->assertOk()
        ->assertSee('rel="manifest"', false)
        ->assertSee('apple-touch-icon', false)
        ->assertSee('x-data="installationApp"', false);

    connecter(utilisateurAvecRole(Role::Partenaire))->get(route('profil'))->assertOk()
        ->assertSee('rel="manifest"', false)
        ->assertSee('Installer l\'application', false);
});

it('ships an installable manifest whose icons exist', function () {
    $manifeste = json_decode((string) file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifeste)
        ->toHaveKeys(['name', 'short_name', 'start_url', 'display', 'icons'])
        ->and($manifeste['display'])->toBe('standalone')
        ->and(collect($manifeste['icons'])->pluck('sizes')->unique()->sort()->values()->all())->toBe(['192x192', '512x512'])
        ->and(collect($manifeste['icons'])->pluck('purpose')->unique()->sort()->values()->all())->toBe(['any', 'maskable']);

    foreach ($manifeste['icons'] as $icone) {
        [$largeur, $hauteur] = getimagesize(public_path(ltrim($icone['src'], '/')));
        expect("{$largeur}x{$hauteur}")->toBe($icone['sizes']);
    }
});

it('never caches authenticated pages in the service worker', function () {
    $sw = (string) file_get_contents(public_path('sw.js'));

    expect($sw)->toContain("mode !== 'navigate'")
        ->and($sw)->not->toContain('cache.put')
        ->and(is_file(public_path('hors-ligne.html')))->toBeTrue();
});
