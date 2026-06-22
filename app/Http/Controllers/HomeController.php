<?php

namespace App\Http\Controllers;

use App\Models\GymSetting;
use App\Models\Member;
use App\Models\MembershipPlan;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        $setting = GymSetting::current();
        $plans   = MembershipPlan::active()->get();
        $activeMembers = Member::active()->count();

        return view('home', compact('setting', 'plans', 'activeMembers'));
    }
}
