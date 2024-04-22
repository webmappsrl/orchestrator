<?php

namespace App\Http\Middleware;

use Closure;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfCustomer
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $customerRole = UserRole::Customer;
        $user = $request->user();
        // Controlla se l'utente è autenticato e ha il ruolo di 'customer'
        if (isset($user) && $user->hasRole($customerRole)) {
            // Reindirizza a 'customerStory' se l'utente è un 'customer'
            if ($request->is('resources/stories')) {
                return redirect('resources/story-showed-by-customers');
            } elseif ($request->is('resources/stories/*')) {
                // Estrai l'ID della storia dalla URL
                $path = $request->path(); // es. 'resources/stories/3084'
                $pathParts = explode('/', $path);
                $id = end($pathParts);
                // Reindirizza alla nuova URL includendo l'ID
                return redirect('resources/story-showed-by-customers/' . $id);
            }
        } else {
            if ($request->is('resources/stories')) {
                return redirect('resources/developer-stories');
            } elseif ($request->is('resources/stories/*')) {
                // Estrai l'ID della storia dalla URL
                $path = $request->path(); // es. 'resources/stories/3084'
                $pathParts = explode('/', $path);
                $id = end($pathParts);
                // Reindirizza alla nuova URL includendo l'ID
                return redirect('resources/developer-stories/' . $id);
            }
        }

        return $next($request);
    }
}
