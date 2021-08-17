<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function browse(Request $request)
    {
        if ($request->has('tag')) {
            $products = self::getTagProducts(($request->get('tag')));
        } else {
            $products = self::getProducts();
        }

        $tags = Redis::sMembers('tags');

        return view('product.browse', compact('products', 'tags'));
    }

    public function create()
    {
        return view('product.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_name' => 'required|string',
            'product_image' => 'required|url',
            'tags' => 'required'
        ]);

        $tags = explode(',', $request->get('tags'));

        foreach ($tags as $key => $tag) {
            $tags[$key] = ltrim($tag);
        }

        $productId = self::getProductId();

        if (self::newProduct($productId, [
            'name' => $request->get('product_name'),
            'image' => $request->get('product_image'),
            'product_id' => $productId
        ])) {
            self::addToTags($tags);
            self::addToProductTags($productId, $tags);
            self::addProductToTags($productId, $tags);
        }

        return redirect()->route('products.browse');
    }

    static function getProducts($start = 0, $end = -1): array
    {
        $productIds = Redis::zRange('products', $start, $end, true);
        $products = [];

        foreach ($productIds as $productId => $score) {
            $products[$score] = Redis::hGetAll("product:$productId");
        }

        return $products;
    }

    static function getProductId()
    {
        if (!Redis::exists('product_count')) {
            Redis::set('product_count', 0);
        }

        return Redis::incr('product_count');
    }

    static function newProduct($productId, $data): bool
    {
        self::addToProducts($productId);

        return Redis::hMset("product:$productId", $data);
    }

    static function addToProducts($productId): void
    {
        Redis::zAdd('products', time(), $productId);
    }

    static function addToTags(array $tags)
    {
        Redis::sAddArray('tags', $tags);
    }

    static function addToProductTags($productId, $tags)
    {
        Redis::sAddArray("product:$productId:tags", $tags);
    }

    static function addProductToTags($productId, $tags)
    {
        foreach ($tags as $tag) {
            Redis::rPush($tag, $productId);
        }
    }

    static function getTagProducts($tag, $start = 0, $end = -1): array
    {
        $productIds = Redis::lRange($tag, $start, $end);
        $products = [];

        foreach ($productIds as $productId) {
            $products[] = Redis::hGetAll("product:$productId");
        }
        return $products;
    }
}
