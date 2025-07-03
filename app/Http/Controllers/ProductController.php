<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;

class ProductController extends Controller
{
    protected $woocommerce;

    public function __construct()
    {
        $this->middleware('auth:api');

        $this->woocommerce = new Client(
            env('WOOCOMMERCE_STORE_URL'),
            env('WOOCOMMERCE_CONSUMER_KEY'),
            env('WOOCOMMERCE_CONSUMER_SECRET'),
            [
                'version' => 'wc/v3',
                'timeout' => 120,
            ]
        );
    }

    public function index()
    {
        return response()->json(auth()->user()->products);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image_url' => 'nullable|url',
        ]);

        $product = Product::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'image_url' => $request->image_url,
            'status' => 'created',
        ]);

        try {
            $wcProduct = $this->woocommerce->post('products', [
                'name' => $product->name,
                'type' => 'simple',
                'regular_price' => (string) $product->price,
                'description' => $product->description,
                'images' => $product->image_url ? [['src' => $product->image_url]] : [],
            ]);

            $product->update([
                'wc_product_id' => $wcProduct->id,
                'status' => 'synced',
            ]);

            return response()->json($product, 201);

        } catch (HttpClientException $e) {
            $product->update(['status' => 'failed']);
            return response()->json([
                'error' => 'WooCommerce sync failed',
                'message' => $e->getMessage(),
                'product' => $product
            ], 500);
        }
    }
}