@php
    $title = 'Config Templates';
    $subtitle = 'Interface, VLAN, routing, and custom templates';
@endphp
@extends('layouts.app')

@section('content')
    <div class="content-card">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <form method="GET" class="d-flex gap-2">
                <select name="category" class="form-select">
                    <option value="">All Categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" @selected(request('category') === $category)>{{ ucfirst($category) }}</option>
                    @endforeach
                </select>
                <select name="template_group" class="form-select">
                    <option value="">All Groups</option>
                    @foreach($groups as $group)
                        <option value="{{ $group }}" @selected(request('template_group') === $group)>{{ ucfirst($group) }}</option>
                    @endforeach
                </select>
                <button class="btn btn-outline-secondary">Filter</button>
            </form>
            @if(in_array($currentRole, ['admin', 'engineer']))
                <a href="{{ route('templates.create') }}" class="btn btn-primary">Add Template</a>
            @endif
        </div>

        @if($templates->isEmpty())
            <div class="text-center text-muted py-4">No templates available.</div>
        @else
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Group</th>
                            <th>Category</th>
                            <th>Version</th>
                            <th>Deployments</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($templates as $template)
                            <tr>
                                <td>{{ $template->name }}</td>
                                <td><span class="badge text-bg-info">{{ $template->template_group }}</span></td>
                                <td><span class="badge text-bg-light border">{{ $template->category }}</span></td>
                                <td>v{{ $template->version }}</td>
                                <td>{{ $template->deployments_count }}</td>
                                <td>
                                    <span class="badge {{ $template->is_active ? 'text-bg-success' : 'text-bg-secondary' }}">
                                        {{ $template->is_active ? 'active' : 'inactive' }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('templates.show', $template) }}" class="btn btn-sm btn-outline-secondary">Preview</a>
                                    @if(in_array($currentRole, ['admin', 'engineer']))
                                        <a href="{{ route('templates.edit', $template) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                    @endif
                                    @if($currentRole === 'admin')
                                        <form method="POST" action="{{ route('templates.destroy', $template) }}" class="d-inline js-confirm" data-confirm="Delete this template?">
                                            @csrf
                                            @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Delete</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $templates->links() }}
        @endif
    </div>
@endsection
