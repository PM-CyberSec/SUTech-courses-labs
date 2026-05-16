@php
    $title = 'Smart Auto Config';
    $subtitle = 'AI-like Cisco config generation for '.$device->hostname;
@endphp
@extends('layouts.app')

@section('content')
    <div class="row g-3">
        <div class="col-lg-5">
            <div class="content-card mb-3">
                <h5>Intelligent Suggestions</h5>
                <div class="small text-muted mb-2">Profile: {{ strtoupper($suggestions['device_profile']) }}</div>
                <ul class="mb-2">
                    <li>Routing protocol: <strong>{{ $suggestions['recommended_routing_protocol'] }}</strong></li>
                    <li>Suggested VLAN ID: <strong>{{ $suggestions['suggested_vlan_id'] }}</strong></li>
                    <li>Suggested IP range: <strong>{{ $suggestions['suggested_ip_range'] }}</strong></li>
                </ul>

                @if(!empty($suggestions['missing_hints']))
                    <div class="alert alert-warning py-2">
                        <div class="fw-semibold">Missing fields detected:</div>
                        <ul class="mb-0">
                            @foreach($suggestions['missing_hints'] as $hint)
                                <li>{{ $hint }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>

            @if(in_array($currentRole, ['admin', 'engineer']))
                <div class="content-card">
                    <form method="POST" action="{{ route('devices.auto-config.generate', $device) }}">
                        @csrf
                        <div class="mb-2">
                            <label class="form-label">Inventory Context</label>
                            <select name="inventory_id" class="form-select">
                                <option value="">Use device inventory</option>
                                @foreach($inventories as $inventory)
                                    <option value="{{ $inventory->id }}" @selected((string) old('inventory_id') === (string) $inventory->id)>{{ $inventory->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Natural-language intent</label>
                            <textarea name="intent_text" rows="3" class="form-control" placeholder="Create VLAN 10 with DHCP and ACL">{{ old('intent_text', $intentText) }}</textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Preset</label>
                            <select name="preset_key" class="form-select">
                                <option value="">Auto detect from intent</option>
                                @foreach($presets as $preset)
                                    <option value="{{ $preset['key'] }}" @selected(old('preset_key') === $preset['key'])>{{ $preset['label'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Cisco Variables JSON</label>
                            <textarea name="payload_json" rows="12" class="form-control font-monospace" placeholder='{"hostname":"SW1","vlans":[{"id":10,"name":"USERS"}]}'>{{ old('payload_json', $payloadJson) }}</textarea>
                        </div>
                        <div class="mb-2">
                            <label class="form-label">Playbook</label>
                            <input type="text" name="playbook_name" class="form-control" value="{{ old('playbook_name', 'interface_config.yml') }}">
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary" name="action" value="generate">Generate Cisco Config</button>
                            <button class="btn btn-success" name="action" value="simulate_deployment">Simulation Mode</button>
                        </div>
                    </form>
                </div>
            @endif
        </div>

        <div class="col-lg-7">
            <div class="content-card mb-3">
                <h5>Validation Engine</h5>
                @if(($validation['errors'] ?? []) === [] && ($validation['warnings'] ?? []) === [])
                    <div class="text-muted">Run generation to view validation feedback.</div>
                @else
                    @if(!empty($validation['errors']))
                        <div class="alert alert-danger py-2">
                            <div class="fw-semibold">Errors:</div>
                            <ul class="mb-0">
                                @foreach($validation['errors'] as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if(!empty($validation['warnings']))
                        <div class="alert alert-warning py-2">
                            <div class="fw-semibold">Warnings:</div>
                            <ul class="mb-0">
                                @foreach($validation['warnings'] as $warning)
                                    <li>{{ $warning }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                @endif
            </div>

            <div class="content-card">
                <h5>Cisco CLI Output Preview</h5>
                @if($generatedConfig)
                    <pre class="bg-dark text-light p-3 rounded small">{{ $generatedConfig }}</pre>
                @else
                    <div class="text-muted">No generated config yet. Click "Generate Cisco Config".</div>
                @endif
            </div>

            <div class="content-card mt-3">
                <h5>Assistant Summary</h5>
                <div class="small text-muted mb-2">Natural-language intent and preset selection feed the same wizard logic used by deployments.</div>
                <pre class="bg-light p-3 rounded small mb-0">{{ $intentText ?: 'No intent provided yet.' }}</pre>
            </div>
        </div>
    </div>
@endsection
