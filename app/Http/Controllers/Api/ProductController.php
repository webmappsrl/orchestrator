<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorizeRole($request);

        $products = Product::all();

        return response()->json($products->map(fn(Product $p) => [
            'id'          => $p->id,
            'name'        => $p->name,
            'description' => $p->description,
            'sku'         => $p->sku,
            'price'       => $p->price,
        ]));
    }

    private function authorizeRole(Request $request): void
    {
        $user = $request->user();
        abort_unless(
            $user->hasRole(UserRole::Admin) || $user->hasRole(UserRole::Manager) || $user->hasRole(UserRole::Developer),
            403
        );
    }
}
