<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

// M1 placeholders only, minus the katalog placeholder which M2
// replaced with Shop\CatalogController. Each remaining placeholder
// names the milestone that will replace it: admin dashboard -> M5,
// developer panel -> M4.
class PageController extends Controller
{
    public function adminDashboard(): View
    {
        return view('placeholder', [
            'title' => 'Dashboard Analitik',
            'note' => 'Placeholder M1. Dashboard analitik sesungguhnya dibangun di M5 (FR-01).',
        ]);
    }

    public function developerDashboard(): View
    {
        return view('placeholder', [
            'title' => 'Panel Developer',
            'note' => 'Placeholder M1. Panel developer sesungguhnya dibangun di M4 (SIM-01..42).',
        ]);
    }
}
