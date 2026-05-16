@csrf
@if(isset($template))
    @method('PUT')
@endif
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Template Name</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $template->name ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Slug (optional)</label>
        <input type="text" name="slug" class="form-control" value="{{ old('slug', $template->slug ?? '') }}">
    </div>
    <div class="col-md-4">
        <label class="form-label">Category</label>
        <select name="category" class="form-select" required>
            @foreach($categories as $category)
                <option value="{{ $category }}" @selected(old('category', $template->category ?? 'custom') === $category)>{{ ucfirst($category) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Template Group</label>
        <select name="template_group" class="form-select" required>
            @foreach($groups as $group)
                <option value="{{ $group }}" @selected(old('template_group', $template->template_group ?? 'switching') === $group)>{{ ucfirst($group) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-4">
        <label class="form-label">Description</label>
        <input type="text" name="description" class="form-control" value="{{ old('description', $template->description ?? '') }}">
    </div>
    <div class="col-12">
        <label class="form-label">Template Body</label>
        <textarea name="template_body" class="form-control font-monospace" rows="12" required>{{ old('template_body', $template->template_body ?? '') }}</textarea>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" value="1" name="is_active" id="is_active" @checked(old('is_active', $template->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active template</label>
        </div>
    </div>
</div>
