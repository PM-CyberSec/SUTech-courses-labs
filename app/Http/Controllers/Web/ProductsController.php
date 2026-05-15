<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\Tag;
use Illuminate\Http\Request;


class ProductsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = Product::with(['category', 'tags']);

        $query->when($request->keywords, function ($q) use ($request) {
            $q->where(function($inner) use ($request) {
                $inner->where('name', 'like', '%' . $request->keywords . '%')
                      ->orWhere('code', 'like', '%' . $request->keywords . '%');
            });
        });

        $query->when($request->category_id, function ($q) use ($request) {
            $q->where('category_id', $request->category_id);
        });

        $query->when($request->tag_id, function ($q) use ($request) {
            $q->whereHas('tags', function ($q2) use ($request) {
                $q2->where('tags.id', $request->tag_id);
            });
        });

        $query->when($request->min_price, function ($q) use ($request) {
            $q->where('price', '>=', $request->min_price);
        });

        $query->when($request->max_price, function ($q) use ($request) {
            $q->where('price', '<=', $request->max_price);
        });

        $orderBy = $request->order_by ?? 'created_at';
        $direction = $request->order_direction ?? 'DESC';
        $query->orderBy($orderBy, $direction);

        $products = $query->paginate(12)->withQueryString();
        $categories = Category::all();
        $tags = Tag::all();

        return view('Products', compact('products', 'categories', 'tags'));
    }

    public function create()
    {
        $categories = Category::all();
        $tags = Tag::all();

       return view('products.create', compact('categories', 'tags'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'code' => ['required', 'string', 'max:255', 'unique:products,code'],
            'name' => ['required', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'photo' => ['required', 'image', 'max:4096'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'in:available,empty'],
            'description' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
        ]);

        $photoName = time() . '_' . $request->file('photo')->getClientOriginalName();
        $request->file('photo')->move(public_path('images'), $photoName);

        $product = Product::create([
            'category_id' => $validated['category_id'],
            'code' => $validated['code'],
            'name' => $validated['name'],
            'model' => $validated['model'] ?? null,
            'photo' => $photoName,
            'price' => $validated['price'],
            'stock' => $validated['stock'],
            'description' => $validated['description'] ?? null,
        ]);
        
        $tagIds = [];
        if ($request->has('tags')) {
            foreach ($request->tags as $tagName) {
                $tag = Tag::firstOrCreate(['name' => $tagName]);
                $tagIds[] = $tag->id;
            }
        }
        $product->tags()->sync($tagIds);

        return redirect()->route('products.index')->with('status', 'Product added successfully.');
    }

    public function show(Product $product)
    {
        return response()->json($product->load('tags', 'category'));
    }

    public function update(Request $request, Product $product)
    {
        $validated = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'code' => ['required', 'string', 'max:255', 'unique:products,code,' . $product->id],
            'name' => ['required', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'photo' => ['nullable', 'image', 'max:4096'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['required', 'in:available,empty'],
            'description' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
        ]);

        if ($request->hasFile('photo')) {
            $photoName = time() . '_' . $request->file('photo')->getClientOriginalName();
            $request->file('photo')->move(public_path('images'), $photoName);
            $validated['photo'] = $photoName;
        }

        $product->update($validated);

        $tagIds = [];
        if ($request->has('tags')) {
            foreach ($request->tags as $tagName) {
                $tag = Tag::firstOrCreate(['name' => $tagName]);
                $tagIds[] = $tag->id;
            }
        }
        $product->tags()->sync($tagIds);

        return redirect()->route('products.index')->with('status', 'Product updated successfully.');
    }
}
