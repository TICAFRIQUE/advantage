<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class TableauDeBordController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.tableau-de-bord');
    }
}
