<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

// M1 placeholders only. Each placeholder names the milestone that will
// replace it, so no one mistakes a placeholder for a finished feature:
// katalog -> M2, admin dashboard -> M5, developer panel -> M4.
class PageController extends Controller
{
    public function katalog(): View
    {
        return view('placeholder', [
            'title' => 'Katalog',
            'note' => 'Placeholder M1. Katalog pembeli sesungguhnya dibangun di M2 (SHP-01..04).',
        ]);
    }

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
