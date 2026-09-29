<?php

namespace App\Http\Controllers\Gestion;

use App\Enums\TypeSms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Gestion\TesterSmsRequest;
use App\Models\MessageSms;
use App\Services\JournaliserAudit;
use App\Services\Sms\EnvoiSms;
use App\Services\Telephone;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Système › Test d'envoi SMS (superadmin) : envoie un vrai SMS avec le pilote
 * configuré, par la chaîne réelle (file d'attente puis fournisseur), pour
 * vérifier la configuration et le worker. Texte fixe, sans donnée sensible ;
 * chaque envoi consomme une unité, d'où une limite stricte (test-sms).
 */
class TestSmsController extends Controller
{
    public function index(): View
    {
        return view('gestion.outils.test-sms', [
            'pilote' => (string) config('plateforme.sms.driver'),
            'expediteur' => (string) (config('services.ticafrique.expediteur') ?: config('plateforme.sms.expediteur')),
            'pays' => Telephone::tousLesPays(),
            'essais' => MessageSms::query()->where('type', TypeSms::Test)->latest('id')->limit(10)->get(),
        ]);
    }

    public function store(TesterSmsRequest $request, EnvoiSms $envoi): RedirectResponse
    {
        $message = $envoi->envoyer($request->telephone(), self::texte(), TypeSms::Test);

        JournaliserAudit::enregistrer('sms.test', $message, [
            'pilote' => $message->fournisseur,
            'telephone' => Telephone::masquer($request->telephone()),
        ]);

        return redirect()->route('gestion.sms-test.index')
            ->with('succes', 'SMS de test envoyé à la file d\'attente : son statut s\'affiche ci-dessous (actualisez la page).');
    }

    public static function texte(): string
    {
        return config('app.name').' : SMS de test du '.now()->format('d/m/Y à H:i').'. La configuration des SMS fonctionne.';
    }
}
