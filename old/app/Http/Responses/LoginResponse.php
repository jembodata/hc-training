<?php

namespace App\Http\Responses;

use App\Support\Auth\AuthorizedRoute;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        return redirect()->to(
            AuthorizedRoute::urlFor($request->user())
        );
    }
}