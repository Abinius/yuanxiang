<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;

class DashboardController extends Controller
{
    public function index()
    {
        $tenants = Tenant::withCount(['farms', 'plans'])->orderBy('id')->get();

        return view('platform.dashboard', ['tenants' => $tenants]);
    }
}
