<?php

namespace App\Http\Controllers;

use App\Http\Requests\GetSimilarProducts;
use App\Models\Product;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductController extends Controller
{
    // http://localhost/api/product.getSimilar?id=37
    public function similar(GetSimilarProducts $request)
    {
        $product = Product::find($request->input('id'));

        if ($product === null) {
            throw new HttpException(404);
        }

        $cacheKey = 'similarProducts_' . $product->id;
        $similarProducts = \Cache::get($cacheKey);

        if ($similarProducts === null) {
            $similarProducts = [];

            // \Cache::put($cacheKey, $similarProducts, now()->addMinutes(10));
        }

        return $similarProducts;
    }
}
