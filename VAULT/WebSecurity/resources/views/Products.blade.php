@extends('layouts.master')
@section('title', 'Products')
@section('content')

<div class="container products-page py-3 px-3">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h4 mb-0">Products</h1>
        @auth
            <a href="{{ route('products.create') }}" class="btn btn-sm btn-future">Add new product</a>
        @endauth
    </div>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <form method="get" action="{{ route('products.index') }}" class="mb-4 glass-card p-3">
        <div class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-4 col-lg">
                <label class="form-label small">Keywords</label>
                <input name="keywords" type="text" class="form-control" placeholder="Search keywords..." value="{{ request()->keywords }}" />
            </div>
            <div class="col-6 col-sm-6 col-md-2 col-lg">
                <label class="form-label small">Category</label>
                <select name="category_id" class="form-select">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" {{ request()->category_id == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-sm-6 col-md-2 col-lg">
                <label class="form-label small">Tag</label>
                <select name="tag_id" id="tag-filter" class="form-select">
                    <option value="">All Tags</option>
                    @foreach ($tags as $tag)
                        <option value="{{ $tag->id }}" {{ request()->tag_id == $tag->id ? 'selected' : '' }}>{{ $tag->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-sm-3 col-md-2 col-lg">
                <label class="form-label small">Min Price</label>
                <input name="min_price" type="number" step="any" class="form-control" placeholder="Min" value="{{ request()->min_price }}" />
            </div>
            <div class="col-6 col-sm-3 col-md-2 col-lg">
                <label class="form-label small">Max Price</label>
                <input name="max_price" type="number" step="any" class="form-control" placeholder="Max" value="{{ request()->max_price }}" />
            </div>
            <div class="col-6 col-sm-6 col-md-2 col-lg">
                <label class="form-label small">Sort By</label>
                <select name="order_by" class="form-select">
                    <option value="created_at" {{ request()->order_by == 'created_at' ? 'selected' : '' }}>Latest</option>
                    <option value="name" {{ request()->order_by == 'name' ? 'selected' : '' }}>Name</option>
                    <option value="price" {{ request()->order_by == 'price' ? 'selected' : '' }}>Price</option>
                </select>
            </div>
            <div class="col-6 col-sm-6 col-md-2 col-lg">
                <label class="form-label small">Direction</label>
                <select name="order_direction" class="form-select">
                    <option value="DESC" {{ request()->order_direction == 'DESC' ? 'selected' : '' }}>DESC</option>
                    <option value="ASC" {{ request()->order_direction == 'ASC' ? 'selected' : '' }}>ASC</option>
                </select>
            </div>
            <div class="col-6 col-sm-6 col-md-auto">
                <button type="submit" class="btn btn-future w-100">Filter</button>
            </div>
            <div class="col-6 col-sm-6 col-md-auto">
                <a href="{{ route('products.index') }}" class="btn btn-outline-danger w-100">Reset</a>
            </div>
        </div>
    </form>

    <div class="row">
        @foreach($products as $product)
            <div class="col-12 mb-4">
                <div class="card glass-card product-card">
                    <div class="card-body">
                        <div class="row g-4 align-items-center">
                            <div class="col-12 col-md-4 col-lg-3 text-center">
                                <img src="{{ asset('images/' . $product->photo) }}" class="img-thumbnail product-card-img rounded-4" alt="{{ $product->name }}">
                            </div>
                            <div class="col-12 col-md-8 col-lg-9">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h2 class="h4 mb-0 text-cyan text-glow-cyan">{{ $product->name }}</h2>
                                    <span class="badge bg-info text-dark">{{ $product->category->name ?? 'Uncategorized' }}</span>
                                </div>
                                <div class="p-3 rounded-4 glass-effect info-container">
                                    <div class="row mb-2 pb-2 border-bottom border-white border-opacity-10 align-items-center">
                                        <div class="col-4 text-info fw-bold small text-uppercase" style="letter-spacing: 0.05rem;">Code</div>
                                        <div class="col-8 text-light"><code>{{ $product->code }}</code></div>
                                    </div>
                                    <div class="row mb-2 pb-2 border-bottom border-white border-opacity-10 align-items-center">
                                        <div class="col-4 text-info fw-bold small text-uppercase" style="letter-spacing: 0.05rem;">Model</div>
                                        <div class="col-8 text-light">{{ $product->model }}</div>
                                    </div>
                                    <div class="row mb-2 pb-2 border-bottom border-white border-opacity-10 align-items-center">
                                        <div class="col-4 text-info fw-bold small text-uppercase" style="letter-spacing: 0.05rem;">Stock</div>
                                        <div class="col-8">
                                            <span class="badge {{ $product->stock === 'available' ? 'bg-success' : 'bg-danger' }} text-uppercase shadow-sm">
                                                {{ $product->stock }}
                                            </span>
                                        </div>
                                    </div>
                                    <div class="row mb-2 pb-2 border-bottom border-white border-opacity-10 align-items-center">
                                        <div class="col-4 text-info fw-bold small text-uppercase" style="letter-spacing: 0.05rem;">Price</div>
                                        <div class="col-8 text-warning fw-bold fs-5">${{ number_format($product->price, 2) }}</div>
                                    </div>
                                    <div class="row mb-2 pb-2 border-bottom border-white border-opacity-10 align-items-center">
                                        <div class="col-4 text-info fw-bold small text-uppercase" style="letter-spacing: 0.05rem;">Tags</div>
                                        <div class="col-8">
                                            @forelse($product->tags as $tag)
                                                <span class="badge rounded-pill bg-secondary text-light me-1" style="font-size: 0.7rem; border: 1px solid rgba(255,255,255,0.1);">{{ $tag->name }}</span>
                                            @empty
                                                <span class="text-muted fst-italic small">No tags</span>
                                            @endforelse
                                        </div>
                                    </div>
                                    <div class="row align-items-start">
                                        <div class="col-4 text-info fw-bold small text-uppercase" style="letter-spacing: 0.05rem;">Description</div>
                                        <div class="col-8 text-light opacity-75 small" style="line-height: 1.5;">{{ $product->description }}</div>
                                    </div>
                                    <div class="row mt-3 pt-3 border-top border-white border-opacity-10">
                                        <div class="col-12 text-end">
                                            @auth
                                                <button onclick="openEditModal({{ $product->id }})" class="btn btn-sm btn-outline-info">Edit Product</button>
                                            @endauth
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="d-flex justify-content-center pt-4">
        {{ $products->links() }}
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content glass-card">
            <div class="modal-header border-bottom border-white border-opacity-10">
                <h5 class="modal-title text-cyan" id="editProductModalLabel">Edit Product</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editProductForm" method="POST" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Code</label>
                            <input type="text" name="code" id="edit_code" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Model</label>
                            <input type="text" name="model" id="edit_model" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select name="category_id" id="edit_category_id" class="form-select no-ts" required>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Price</label>
                            <input type="number" step="0.01" name="price" id="edit_price" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Stock</label>
                            <select name="stock" id="edit_stock" class="form-select no-ts" required>
                                <option value="available">Available</option>
                                <option value="empty">Empty</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Tags</label>
                            <select name="tags[]" id="edit_tags" class="form-select no-ts" multiple>
                                @foreach($tags as $tag)
                                    <option value="{{ $tag->name }}">{{ $tag->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Photo (Leave empty to keep current)</label>
                            <input type="file" name="photo" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top border-white border-opacity-10">
                    <button type="button" class="btn btn-outline-light" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-future">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let editTagsControl;
let editCategoryControl;
let editStockControl;

function openEditModal(productId) {
    fetch(`/products/${productId}`)
        .then(response => response.json())
        .then(product => {
            const form = document.getElementById('editProductForm');
            form.action = `/products/${productId}`;
            
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_code').value = product.code;
            document.getElementById('edit_model').value = product.model;
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_stock').value = product.stock;
            document.getElementById('edit_description').value = product.description;
            document.getElementById('edit_category_id').value = product.category_id;

            // Destroy existing controls if they exist
            if (editTagsControl) editTagsControl.destroy();
            if (editCategoryControl) editCategoryControl.destroy();
            if (editStockControl) editStockControl.destroy();

            // Set up tags
            const tagsSelect = document.getElementById('edit_tags');
            Array.from(tagsSelect.options).forEach(opt => opt.selected = false);
            
            product.tags.forEach(tag => {
                let opt = Array.from(tagsSelect.options).find(o => o.value === tag.name);
                if (!opt) {
                    opt = document.createElement('option');
                    opt.value = tag.name;
                    opt.textContent = tag.name;
                    tagsSelect.appendChild(opt);
                }
                opt.selected = true;
            });

            // Initialize TomSelect controls
            editTagsControl = new TomSelect("#edit_tags", { plugins: ['remove_button'], create: true, persist: false, dropdownParent: 'body' });
            editCategoryControl = new TomSelect("#edit_category_id", { create: false, persist: false, dropdownParent: 'body' });
            editStockControl = new TomSelect("#edit_stock", { create: false, persist: false, dropdownParent: 'body' });

            const modal = new bootstrap.Modal(document.getElementById('editProductModal'));
            modal.show();
        });
}
</script>
@endsection
