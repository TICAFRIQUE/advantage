<?php

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\JournalAudit;
use App\Services\Parametres;
use App\Services\Parametres\EnregistrerLogo;
use App\Services\Sauvegardes\GestionSauvegardes;
use App\Services\Sauvegardes\MoteurSauvegarde;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Moteur simulé : aucune commande MySQL, la vraie base n'est jamais touchée.
 */
class MoteurSauvegardeSimule implements MoteurSauvegarde
{
    /** @var list<string> */
    public array $importes = [];

    public function exporter(string $fichierSql): void
    {
        file_put_contents($fichierSql, "-- sauvegarde simulée\nSELECT 1;\n");
    }

    public function importer(string $fichierSql): void
    {
        $this->importes[] = (string) file_get_contents($fichierSql);
    }
}

beforeEach(function () {
    $this->dossier = str_replace('\\', '/', sys_get_temp_dir()).'/advantage-sauvegardes-'.uniqid();
    config(['plateforme.sauvegardes.dossier' => $this->dossier]);
    $this->moteur = new MoteurSauvegardeSimule;
    app()->instance(MoteurSauvegarde::class, $this->moteur);
});

afterEach(function () {
    File::deleteDirectory($this->dossier);
    File::deleteDirectory(public_path(EnregistrerLogo::DOSSIER));
});

function superadminConfirmePourParametres(): TestCase
{
    return connecter(utilisateurAvecRole(Role::Superadmin))->withSession([
        'connecte_le' => now()->getTimestamp(),
        'auth.password_confirmed_at' => now()->getTimestamp(),
    ]);
}

describe('accès', function () {
    it('opens settings to the admin (identity only) and to the superadmin (identity and backups)', function () {
        connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.parametres.index'))
            ->assertOk()->assertSee('Identité')->assertDontSee('Sauvegarder maintenant');

        connecter(utilisateurAvecRole(Role::Superadmin))->get(route('gestion.parametres.index'))
            ->assertOk()->assertSee('Identité')->assertSee('Sauvegarder maintenant');

        connecter(utilisateurAvecRole(Role::Agent))->get(route('gestion.parametres.index'))->assertForbidden();
    });

    it('keeps backups to the superadmin, even if the permission were granted', function () {
        $admin = utilisateurAvecRole(Role::Admin);
        $admin->givePermissionTo(Permission::GererSauvegardes->value);

        connecter($admin)->post(route('gestion.parametres.sauvegardes.store'))->assertForbidden();
    });

    it('shows the Administration menu with the settings entry', function () {
        connecter(utilisateurAvecRole(Role::Admin))->get(route('gestion.tableau-de-bord'))
            ->assertSee('Administration')
            ->assertSee(route('gestion.parametres.index'), false);
    });
});

describe('identité', function () {
    it('renames the application and the organisation everywhere, and audits it', function () {
        connecter(utilisateurAvecRole(Role::Admin))
            ->put(route('gestion.parametres.identite'), ['nom_application' => 'FIDELIS', 'nom_organisation' => 'Groupe Test'])
            ->assertRedirect(route('gestion.parametres.index'));

        expect(Parametres::nomApplication())->toBe('FIDELIS')
            ->and(JournalAudit::where('action', 'parametres.modifies')->sole()->donnees['apres'])->toMatchArray(['nom_application' => 'FIDELIS']);

        connecter(utilisateurAvecRole(Role::Agent))->get(route('gestion.tableau-de-bord'))
            ->assertSee('<title>Tableau de bord — FIDELIS</title>', false)
            ->assertSee('Groupe Test');
        auth()->logout();
        $this->get(route('login'))->assertOk()->assertSee('FIDELIS');
    });

    it('re-encodes the uploaded logo as PNG, replaces the previous one and can go back to the original', function () {
        $admin = utilisateurAvecRole(Role::Admin);
        $envoyer = fn (UploadedFile $logo) => connecter($admin)->put(route('gestion.parametres.identite'), [
            'nom_application' => 'ADVANTAGE', 'nom_organisation' => 'fontaine GROUP', 'logo' => $logo,
        ]);

        $envoyer(UploadedFile::fake()->image('logo.jpg', 800, 800))->assertSessionHasNoErrors();
        $premier = Parametres::logo();
        expect(getimagesize(public_path($premier)))->toMatchArray([0 => 512, 1 => 512, 'mime' => 'image/png']);
        $this->travel(2)->seconds();
        $envoyer(UploadedFile::fake()->image('logo2.webp', 300, 300))->assertSessionHasNoErrors();
        $second = Parametres::logo();

        expect($second)->toStartWith('uploads/identite/logo-')->toEndWith('.png')
            ->and(getimagesize(public_path($second)))->toMatchArray([0 => 300, 1 => 300, 'mime' => 'image/png'])
            ->and(is_file(public_path($premier)))->toBeFalse();

        connecter($admin)->get(route('gestion.tableau-de-bord'))->assertSee(asset($second), false);

        connecter($admin)->put(route('gestion.parametres.identite'), ['nom_application' => 'ADVANTAGE', 'nom_organisation' => 'fontaine GROUP', 'logo_defaut' => 1]);
        expect(Parametres::logo())->toBe(Parametres::LOGO_DEFAUT)->and(is_file(public_path($second)))->toBeFalse();
    });

    it('refuses SVG, oversized or tiny logos', function (UploadedFile $logo) {
        connecter(utilisateurAvecRole(Role::Admin))
            ->put(route('gestion.parametres.identite'), ['nom_application' => 'ADVANTAGE', 'nom_organisation' => 'fontaine GROUP', 'logo' => $logo])
            ->assertSessionHasErrors('logo');

        expect(Parametres::logoPersonnalise())->toBeFalse();
    })->with([
        'svg' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
        'trop lourd' => fn () => UploadedFile::fake()->image('logo.png', 600, 600)->size(2048),
        'trop petit' => fn () => UploadedFile::fake()->image('logo.png', 32, 32),
    ]);
});

describe('sauvegardes', function () {
    it('creates a compressed backup, lists it and audits it', function () {
        superadminConfirmePourParametres()->post(route('gestion.parametres.sauvegardes.store'))->assertSessionHas('succes');

        $sauvegardes = app(GestionSauvegardes::class)->lister();

        expect($sauvegardes)->toHaveCount(1)
            ->and($sauvegardes[0]['nom'])->toMatch('/^advantage-\d{4}-\d{2}-\d{2}-\d{6}\.sql\.gz$/')
            ->and(gzdecode((string) file_get_contents($this->dossier.'/'.$sauvegardes[0]['nom'])))->toContain('sauvegarde simulée')
            ->and(JournalAudit::where('action', 'sauvegarde.creee')->exists())->toBeTrue();
    });

    it('keeps only the ten most recent backups', function () {
        $gestion = app(GestionSauvegardes::class);

        foreach (range(1, 12) as $i) {
            $gestion->creer(null);
            $this->travel(1)->seconds();
        }

        $noms = collect($gestion->lister())->pluck('nom');

        expect($noms)->toHaveCount(10)
            ->and($noms->first())->toBe($gestion->lister()[0]['nom'])
            ->and($noms->sort()->values()->all())->toBe($noms->sortDesc()->reverse()->values()->all());
    });

    it('restores a backup after typed confirmation, with a safety backup first', function () {
        $gestion = app(GestionSauvegardes::class);
        $nom = $gestion->creer(null);
        $this->travel(2)->seconds();

        superadminConfirmePourParametres()->post(route('gestion.parametres.sauvegardes.restaurer', $nom), ['confirmation' => 'restaurer'])
            ->assertSessionHasErrors('confirmation');
        expect($this->moteur->importes)->toBe([]);

        superadminConfirmePourParametres()->post(route('gestion.parametres.sauvegardes.restaurer', $nom), ['confirmation' => 'RESTAURER'])
            ->assertSessionHas('succes');

        expect($this->moteur->importes)->toHaveCount(1)
            ->and($this->moteur->importes[0])->toContain('sauvegarde simulée')
            ->and(collect($gestion->lister())->pluck('nom')->filter(fn ($n) => str_contains($n, 'avant-restauration')))->toHaveCount(1)
            ->and(JournalAudit::where('action', 'sauvegarde.restauree')->sole()->donnees['fichier'])->toBe($nom);
    });

    it('asks for the password before restoring or downloading', function () {
        $nom = app(GestionSauvegardes::class)->creer(null);
        $superadmin = utilisateurAvecRole(Role::Superadmin);

        connecter($superadmin)->post(route('gestion.parametres.sauvegardes.restaurer', $nom), ['confirmation' => 'RESTAURER'])
            ->assertRedirect(route('password.confirm'));
        connecter($superadmin)->get(route('gestion.parametres.sauvegardes.telecharger', $nom))
            ->assertRedirect(route('password.confirm'));

        superadminConfirmePourParametres()->get(route('gestion.parametres.sauvegardes.telecharger', $nom))
            ->assertOk()->assertDownload($nom);
    });

    it('never serves a file outside the backups', function (string $nom) {
        superadminConfirmePourParametres()->get(route('gestion.parametres.sauvegardes.telecharger', $nom))
            ->assertRedirect()->assertSessionHas('erreur', 'Sauvegarde introuvable.');
    })->with(['composer.json', 'advantage-inconnue.sql.gz', 'advantage-2026-01-01-000000.sql']);

    it('accepts an absolute writable folder, never the public folder nor a relative path', function () {
        $nouveau = str_replace('\\', '/', sys_get_temp_dir()).'/advantage-autre-'.uniqid();

        superadminConfirmePourParametres()->put(route('gestion.parametres.sauvegardes.dossier'), ['dossier' => public_path('sauvegardes')])
            ->assertSessionHas('erreur');
        superadminConfirmePourParametres()->put(route('gestion.parametres.sauvegardes.dossier'), ['dossier' => 'storage/sauvegardes'])
            ->assertSessionHas('erreur');
        superadminConfirmePourParametres()->put(route('gestion.parametres.sauvegardes.dossier'), ['dossier' => $nouveau])
            ->assertSessionHas('succes');

        expect(app(GestionSauvegardes::class)->dossier())->toBe($nouveau)->and(is_dir($nouveau))->toBeTrue();
        File::deleteDirectory($nouveau);
    });

    it('runs a nightly backup', function () {
        $taches = collect(app(Schedule::class)->events())->mapWithKeys(fn ($e) => [$e->command => $e->expression]);

        expect($taches->first(fn ($x, $commande) => str_contains($commande, 'sauvegarde:creer')))->toBe('30 1 * * *');
    });
});
