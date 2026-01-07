<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    /**
     * Display system settings.
     */
    public function index()
    {
        return Inertia::render('admin/settings/index');
    }
}
