@extends('layouts.master')
@section('title', 'Add product')
@section('content')

<div id="particles-js"></div>

<div class="container py-4">
    <div class="mx-auto" style="max-width: 640px;">
        <h1 class="h4 mb-3">Add new product</h1>

        <div class="card glass-card p-4">
            <form action="{{ route('products.store') }}" method="post" enctype="multipart/form-data">
                @csrf

                @foreach ($errors->all() as $error)
                    <div class="alert alert-danger py-2">{{ $error }}</div>
                @endforeach

                <div class="mb-3">
                    <label class="form-label">Code</label>
                    <input type="text" name="code" class="form-control" value="{{ old('code') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Model</label>
                    <input type="text" name="model" class="form-control" value="{{ old('model') }}">
                </div>
                <div class="mb-3">
                    <label class="form-label">Photo</label>
                    <input type="file" name="photo" class="form-control" accept="image/*" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Price</label>
                    <input type="number" name="price" class="form-control" step="0.01" min="0" value="{{ old('price') }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Stock</label>
                    <select name="stock" class="form-select" required>
                        <option value="available" {{ old('stock') === 'available' ? 'selected' : '' }}>available</option>
                        <option value="empty" {{ old('stock') === 'empty' ? 'selected' : '' }}>empty</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Select a category</option>
                            @foreach ($categories as $category)
                            <option value="{{ $category->id  }}">
                                {{ $category->name }}
                            </option>
                            @endforeach
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Tags</label>
                    <select name="tags[]" id="tags-select" class="form-select" multiple placeholder="Select tags...">
                        @foreach ($tags as $tag)
                            <option value="{{ $tag->id }}" {{ in_array($tag->id, old('tags', [])) ? 'selected' : '' }}>
                                {{ $tag->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-future">Save product</button>
                    <a href="{{ route('products.index') }}" class="btn btn-outline-light">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection
