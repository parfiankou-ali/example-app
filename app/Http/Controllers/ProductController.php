<?php

namespace App\Http\Controllers;

use App\Http\Requests\GetSimilarProducts;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
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
            $similarProducts = new Collection();

            $prepositions = ['in', 'of', 'from', 'with', 'to'];
            $nameWords = array_unique(explode(' ', $product->name));
            
            $validatedWords = array_filter($nameWords, function ($word) use ($prepositions) {
                return in_array($word, $prepositions) === false;
            });

            $recommendationCount = env('PRODUCT_RECOMMENDATION_COUNT');
            $randomProductPercentage = env('PRODUCT_RANDOM_RECOMMENDATION_PERCENTAGE');

            /** @var \Illuminate\Database\Eloquent\Collection $similarNameProducts */
            $similarNameProducts = Product::select(
                    DB::raw('MATCH (`name`) AGAINST (\'' . join(' ', $validatedWords) . '\' IN BOOLEAN MODE) as `score`'),
                    'id',
                    'name',
                    'frequency'
                )
                ->orderBy('score', 'desc')
                ->whereNot('id', $product->id)
                ->limit($recommendationCount)
                ->get();

            $biggestFrequency = 0;

            $similarNameProducts->map(function(Product $similarNameProduct) use (&$biggestFrequency) {
                if ($similarNameProduct->frequency > $biggestFrequency) {
                    $biggestFrequency = $similarNameProduct->frequency;
                }
            });

            $randomizer = new \Random\Randomizer();

            $onlySimilarProducts = new Collection();

            while ($onlySimilarProducts->count() < $recommendationCount && $similarNameProducts->count()) {
                $minFrequency = $randomizer->getInt(0, $biggestFrequency);

                $candidateProducts = $similarNameProducts->filter(function(Product $similarNameProduct) use ($minFrequency) {
                    return $similarNameProduct->frequency >= $minFrequency;
                });

                if ($candidateProducts->count()) {
                    $candidateProduct = $candidateProducts->random();
                    $similarNameProducts = $similarNameProducts->except(['id' => $candidateProduct->id]);
                    $onlySimilarProducts->add($candidateProduct);
                }
            }

            foreach ($onlySimilarProducts as $similarProduct) {
                $shouldBeReplacedByRandomProduct = $randomizer->getInt(0, 100) <= $randomProductPercentage;
                if ($shouldBeReplacedByRandomProduct) {
                    $randomProduct = Product::inRandomOrder()
                        ->whereNot(function (Builder $builder) use ($similarProducts) {
                            foreach($similarProducts as $similarProduct) {
                                $builder->where('id', $similarProduct->id);
                            }
                        })
                        ->whereNot('id', $product->id)
                        ->first();

                    if ($randomProduct === null) {
                        $similarProducts->add($similarProduct);
                        break;
                    }

                    $similarProducts->add($randomProduct);
                } else {
                    $similarProducts->add($similarProduct);
                }
            }
            
            //\Cache::put($cacheKey, $similarProducts, now()->addMinutes(10));
        }

        return $similarProducts;
    }
}
