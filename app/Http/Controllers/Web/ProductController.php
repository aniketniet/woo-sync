<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Automattic\WooCommerce\Client;
use Automattic\WooCommerce\HttpClient\HttpClientException;
use Illuminate\Support\Facades\Auth;

class ProductController extends Controller
{
    protected $woocommerce;

    public function __construct()
    {
        $this->middleware('auth');

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
        $products = Auth::user()->products;
        return view('products.index', compact('products'));
    }

    public function create()
    {
        return view('products.create');
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
            'user_id' => Auth::id(),
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

            return redirect()->route('home')->with('success', 'Product created and synced!');
        } catch (HttpClientException $e) {
            $product->update(['status' => 'failed']);
            return back()->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    public function edit(Product $product)
    {
        if ($product->user_id !== Auth::id()) {
            abort(403);
        }

        return view('products.edit', compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        if ($product->user_id !== Auth::id()) {
            abort(403);
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'price' => 'required|numeric|min:0',
            'description' => 'nullable|string',
            'image_url' => 'nullable|url',
        ]);

        $product->update([
            'name' => $request->name,
            'description' => $request->description,
            'price' => $request->price,
            'image_url' => $request->image_url,
            'status' => 'updating',
        ]);

        try {
            $this->woocommerce->put("products/{$product->wc_product_id}", [
                'name' => $product->name,
                'regular_price' => (string) $product->price,
                'description' => $product->description,
                'images' => $product->image_url ? [['src' => $product->image_url]] : [],
            ]);

            $product->update(['status' => 'synced']);
            return redirect()->route('home')->with('success', 'Product updated and synced!');
        } catch (HttpClientException $e) {
            $product->update(['status' => 'failed']);
            return back()->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    public function destroy(Product $product)
    {
        if ($product->user_id !== Auth::id()) {
            abort(403);
        }

        try {
            if ($product->wc_product_id) {
                $this->woocommerce->delete("products/{$product->wc_product_id}", ['force' => true]);
            }
            $product->delete();
            return redirect()->route('home')->with('success', 'Product deleted!');
        } catch (HttpClientException $e) {
            return back()->with('error', 'Delete failed: ' . $e->getMessage());
        }
    }
}