<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Product;
use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    protected $woocommerce;
    protected $maxRetries = 3;
    protected $retryDelay = 2; // seconds

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
            'image_url' => [
                'nullable',
                'url',
                function ($attr, $value, $fail) {
                    $ext = pathinfo(parse_url($value, PHP_URL_PATH), PATHINFO_EXTENSION);
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if ($value && !in_array(strtolower($ext), $allowed)) {
                        $fail("Image must be JPG, JPEG, PNG, GIF, or WEBP.");
                    }
                }
            ],
        ]);

        $product = Product::create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'image_url' => $request->image_url,
            'status' => 'created',
        ]);

        $this->syncToWooCommerce($product, 'create');

        return response()->json($product, 201);
    }

    public function update(Request $request, Product $product)
    {
        // Authorization
        if ($product->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image_url' => [
                'nullable',
                'url',
                function ($attr, $value, $fail) {
                    $ext = pathinfo(parse_url($value, PHP_URL_PATH), PATHINFO_EXTENSION);
                    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    if ($value && !in_array(strtolower($ext), $allowed)) {
                        $fail("Image must be JPG, JPEG, PNG, GIF, or WEBP.");
                    }
                }
            ],
        ]);

        $product->update([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'image_url' => $request->image_url,
            'status' => 'updating',
        ]);

        $this->syncToWooCommerce($product, 'update');

        return response()->json($product);
    }

    public function destroy(Product $product)
    {
        // Authorization
        if ($product->user_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Delete from WooCommerce if exists
        if ($product->wc_product_id) {
            $this->deleteFromWooCommerce($product);
        }

        $product->delete();
        return response()->json(['message' => 'Product deleted']);
    }

    private function syncToWooCommerce(Product $product, $action = 'create')
    {
        $data = [
            'name' => $product->name,
            'type' => 'simple',
            'regular_price' => (string) $product->price,
            'description' => $product->description,
            'images' => $product->image_url ? [['src' => $product->image_url]] : [],
        ];

        $attempt = 1;
        $success = false;
        $lastException = null;

        while ($attempt <= $this->maxRetries && !$success) {
            try {
                if ($action === 'create' || !$product->wc_product_id) {
                    $wcProduct = $this->woocommerce->post('products', $data);
                    $product->update([
                        'wc_product_id' => $wcProduct->id,
                        'status' => 'synced',
                    ]);
                } else {
                    $this->woocommerce->put("products/{$product->wc_product_id}", $data);
                    $product->update(['status' => 'synced']);
                }
                $success = true;
                Log::info("Product {$product->id} synced to WooCommerce");
            } catch (HttpClientException $e) {
                $lastException = $e;
                Log::error("Sync attempt $attempt failed for product {$product->id}: " . $e->getMessage());
                $attempt++;
                sleep($this->retryDelay);
            }
        }

        if (!$success) {
            $product->update(['status' => 'failed']);
            Log::critical("All sync attempts failed for product {$product->id}");
            throw $lastException;
        }
    }

    private function deleteFromWooCommerce(Product $product)
    {
        $attempt = 1;
        $success = false;

        while ($attempt <= $this->maxRetries && !$success) {
            try {
                $this->woocommerce->delete("products/{$product->wc_product_id}", ['force' => true]);
                $success = true;
                Log::info("Product {$product->id} deleted from WooCommerce");
            } catch (HttpClientException $e) {
                Log::error("Delete attempt $attempt failed for product {$product->id}: " . $e->getMessage());
                $attempt++;
                sleep($this->retryDelay);
            }
        }

        if (!$success) {
            Log::critical("All delete attempts failed for product {$product->id}");
            $product->update(['status' => 'delete_failed']);
            throw new \Exception("Failed to delete product from WooCommerce");
        }
    }
}