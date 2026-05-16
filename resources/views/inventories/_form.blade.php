@csrf
@if(isset($inventory))
    @method('PUT')
@endif
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Name</label>
        <input type="text" name="name" class="form-control" value="{{ old('name', $inventory->name ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Group Name</label>
        <input type="text" name="group_name" class="form-control" value="{{ old('group_name', $inventory->group_name ?? '') }}">
    </div>
    <div class="col-12">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2">{{ old('description', $inventory->description ?? '') }}</textarea>
    </div>
    <div class="col-12">
        <label class="form-label">Variables (JSON)</label>
        <textarea name="variables" class="form-control font-monospace" rows="6" placeholder='{"ansible_become": true}'>{{ old('variables', $variablesJson ?? '') }}</textarea>
    </div>
    <div class="col-12">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" value="1" name="is_active" id="is_active" @checked(old('is_active', $inventory->is_active ?? true))>
            <label class="form-check-label" for="is_active">
                Active inventory
            </label>
        </div>
    </div>
</div>
