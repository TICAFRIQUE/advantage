<?php

use Illuminate\Support\Facades\File;

/*
| Configuration de production conforme, dans un dossier public temporaire
| (fichiers construits présents, aucun fichier « hot »).
*/
function configurationDeProductionConforme(): string
{
    $public = sys_get_temp_dir().'/advantage-public-'.uniqid();
    File::ensureDirectoryExists($public.'/build');
    File::put($public.'/build/manifest.json', '{}');
    app()->usePublicPath($public);
    app()->loadEnvironmentFrom('.env.absent');
    app()->detectEnvironment(fn () => 'production');

    config([
        'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'app.url' => 'https://advantage.example.ci',
        'plateforme.cle_hmac' => base64_encode(random_bytes(32)),
        'session.secure' => true,
        'session.http_only' => true,
        'session.encrypt' => true,
        'logging.channels.single.level' => 'warning',
        'logging.channels.daily.level' => 'warning',
        'logging.channels.stack.channels' => ['daily'],
        'plateforme.sms.driver' => 'ticafrique',
        'services.ticafrique.cle' => 'cle-de-test',
        'services.ticafrique.url' => 'https://sms.example.ci/api',
        'plateforme.sauvegardes.dossier' => str_replace('\\', '/', sys_get_temp_dir()).'/advantage-sauvegardes-'.uniqid(),
        'filesystems.disks.local.serve' => false,
    ]);

    return $public;
}

afterEach(function () {
    File::deleteDirectory((string) ($this->dossierPublic ?? ''));
});

it('passes when the production configuration is sound', function () {
    $this->dossierPublic = configurationDeProductionConforme();

    $this->artisan('securite:verifier')
        ->expectsOutputToContain('Configuration de production conforme.')
        ->assertSuccessful();
});

it('fails and tells what to fix for each unsafe setting', function (array $reglage, string $correction) {
    $this->dossierPublic = configurationDeProductionConforme();
    config($reglage);

    $this->artisan('securite:verifier')
        ->expectsOutputToContain($correction)
        ->assertFailed();
})->with([
    'debug neutralisé mais resté dans .env' => [['plateforme.securite.debug_neutralise' => true], 'APP_DEBUG=false'],
    'adresse en http' => [['app.url' => 'http://advantage.example.ci'], 'APP_URL=https://'],
    'cookie non sécurisé' => [['session.secure' => null], 'SESSION_SECURE_COOKIE=true'],
    'clé HMAC absente' => [['plateforme.cle_hmac' => null], 'PLATEFORME_CLE_HMAC'],
    'journal en debug' => [['logging.channels.daily.level' => 'debug'], 'LOG_LEVEL=warning'],
    'journal sans rotation' => [['logging.channels.stack.channels' => ['single']], 'LOG_STACK=daily'],
    'SMS simulés' => [['plateforme.sms.driver' => 'simulation'], 'SMS_DRIVER=ticafrique'],
    'fichiers privés servis' => [['filesystems.disks.local.serve' => true], "'serve' => false"],
]);

it('refuses a backup folder inside the public folder', function () {
    $this->dossierPublic = configurationDeProductionConforme();
    config(['plateforme.sauvegardes.dossier' => str_replace('\\', '/', public_path('sauvegardes'))]);

    $this->artisan('securite:verifier')->expectsOutputToContain('hors de public/')->assertFailed();
});

it('refuses a backup folder that the deployment would wipe, but accepts storage/', function () {
    $this->dossierPublic = configurationDeProductionConforme();
    $dansLeProjet = str_replace('\\', '/', base_path('sauvegardes-test-'.uniqid()));
    config(['plateforme.sauvegardes.dossier' => $dansLeProjet]);

    $this->artisan('securite:verifier')->expectsOutputToContain('rsync --delete')->assertFailed();
    File::deleteDirectory($dansLeProjet);

    config(['plateforme.sauvegardes.dossier' => str_replace('\\', '/', storage_path('app/sauvegardes'))]);
    $this->artisan('securite:verifier')->assertSuccessful();
});

it('detects a leftover development server file', function () {
    $this->dossierPublic = configurationDeProductionConforme();
    File::put(public_path('hot'), 'http://localhost:5173');

    $this->artisan('securite:verifier')->expectsOutputToContain('public/hot')->assertFailed();
});

it('reports the development environment as not ready for production', function () {
    $this->artisan('securite:verifier')->expectsOutputToContain('APP_ENV=production')->assertFailed();
});
