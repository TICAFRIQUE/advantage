<?php

namespace App\Http\Controllers\Partenaire;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class TableauDeBordController extends Controller
{
    public function __invoke(): View
    {
        return view('partenaire.tableau-de-bord');
    }
}
