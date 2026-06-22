<?php

namespace App\Http\Responses;

use App\Enums\UserRole;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        $home = auth()->user()->role === UserRole::Member
            ? route('portal.dashboard')
            : route('dashboard');

        return redirect()->intended($home);
    }
}
