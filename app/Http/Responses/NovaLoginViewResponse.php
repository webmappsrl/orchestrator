<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\ResponseFactory;
use Laravel\Fortify\Contracts\LoginViewResponse as Responsable;
use Laravel\Nova\Http\Middleware\HandleInertiaRequests;
use Laravel\Nova\Nova;

class NovaLoginViewResponse implements Responsable
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request)
    {
        // Applica il middleware HandleInertiaRequests di Nova per configurare correttamente Inertia
        $middleware = new HandleInertiaRequests();
        
        // Esegui il middleware per configurare Inertia prima di renderizzare
        $response = $middleware->handle($request, function ($req) {
            return Inertia::render('Nova.Login', [
                'username' => Nova::fortify()->username,
                'email' => Nova::fortify()->email,
            ])->toResponse($req);
        });

        return $response;
    }
}
