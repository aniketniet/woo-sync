@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1>Products</h1>
    <a href="{{ route('products.create') }}" class="btn btn-primary">Create Product</a>
</div>

@if($products->isEmpty())
    <div class="alert alert-info">No products found. Create your first product!</div>
@else
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>WooCommerce ID</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($products as $product)
                <tr>
                    <td>{{ $product->name }}</td>
                    <td>${{ number_format($product->price, 2) }}</td>
                    <td>
                        <span class="badge 
                            @if($product->status === 'synced') bg-success
                            @elseif(in_array($product->status, ['created', 'updating'])) bg-warning
                            @else bg-danger
                            @endif">
                            {{ $product->status }}
                        </span>
                    </td>
                    <td>{{ $product->wc_product_id ?? 'N/A' }}</td>
                    <td>
                        <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-primary">Edit</a>
                        <form action="{{ route('products.destroy', $product) }}" method="POST" class="d-inline">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">Delete</button>
                        </form>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection