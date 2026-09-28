<?php

namespace App\Http\Controllers\Admin;

use App\Enums\TypeSms;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EnvoyerSmsTestRequest;
use App\Models\MessageSms;
use App\Services\Sms\EnvoiSms;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Boîte des SMS simulés : remplace le téléphone du titulaire tant que l'API
 * du fournisseur n'est pas branchée. Inaccessible en production ou avec un
 * pilote réel (routes non déclarées + garde ci-dessous).
 */
class SmsSimulesController extends Controller
{
    public function index(): View
    {
        abort_unless(self::disponible(), 404);

        return view('admin.sms-simules.index', [
            'messages' => MessageSms::query()->latest('id')->limit(50)->get(),
        ]);
    }

    public function store(EnvoyerSmsTestRequest $request, EnvoiSms $envoi): RedirectResponse
    {
        abort_unless(self::disponible(), 404);

        $envoi->envoyer($request->telephone(), $request->validated('message'), TypeSms::Information);

        return redirect()->route('admin.sms-simules.index')
            ->with('succes', 'SMS de test mis en file d\'attente.');
    }

    public static function disponible(): bool
    {
        return config('plateforme.sms.driver') === 'simulation' && ! app()->isProduction();
    }
}
