@php
    $title = 'Deployment Wizard';
    $subtitle = 'Device -> Goal -> Inputs -> Preview -> Deploy';
@endphp
@extends('layouts.app')

@section('content')
    <div class="row g-3 mb-3">
        @foreach($wizardSteps as $index => $step)
            <div class="col-6 col-lg-2">
                <div class="content-card text-center py-3">
                    <div class="badge text-bg-dark mb-2">{{ $index + 1 }}</div>
                    <div class="fw-semibold">{{ $step }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="content-card mb-3">
        <div class="alert alert-info py-2 mb-0">
            Wizard mode lets you build a deployment with smart defaults, AI-assisted intent parsing, validation preview, and queue-backed execution.
        </div>
    </div>

    <form method="POST" action="{{ route('deployments.store') }}" id="wizardForm">
        @csrf
        <input type="hidden" name="preset_key" id="preset_key" value="{{ old('preset_key') }}">
        <input type="hidden" name="scenario_key" id="scenario_key" value="{{ old('scenario_key') }}">
        <div class="row g-3">
            <div class="col-lg-8">
                <div class="content-card mb-3">
                    <h5>Scenario Library</h5>
                    <div class="row g-2">
                        @foreach($scenarioPresets as $key => $scenario)
                            <div class="col-md-6">
                                <button type="button"
                                        class="btn btn-outline-dark w-100 text-start scenario-button"
                                        data-scenario="{{ $key }}"
                                        data-playbook="{{ $key === 'dhcp_dns_http_lab' ? 'interface_config.yml' : ($key === 'ospf_md5_lab' ? 'routing_config.yml' : 'interface_config.yml') }}"
                                        data-goal="{{ $key === 'router_static_lab' ? 'multi_site_routing' : ($key === 'ospf_md5_lab' ? 'multi_site_routing' : 'access_segmentation') }}">
                                    <div class="fw-semibold">{{ $scenario['label'] }}</div>
                                    <div class="small text-muted">{{ $scenario['description'] }}</div>
                                </button>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="content-card mb-3">
                    <h5>1. Device & Goal</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Device</label>
                            <select name="device_id" class="form-select" required>
                                <option value="">Select device</option>
                                @foreach($devices as $device)
                                    <option value="{{ $device->id }}" @selected(old('device_id') == $device->id)>{{ $device->hostname }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Inventory (optional)</label>
                            <select name="inventory_id" class="form-select">
                                <option value="">Auto from device</option>
                                @foreach($inventories as $inventory)
                                    <option value="{{ $inventory->id }}" @selected(old('inventory_id') == $inventory->id)>{{ $inventory->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Goal</label>
                            <select name="goal" class="form-select">
                                <option value="">Let AI infer goal</option>
                                <option value="access_segmentation" @selected(old('goal') === 'access_segmentation')>Access Segmentation</option>
                                <option value="multi_site_routing" @selected(old('goal') === 'multi_site_routing')>Multi-site Routing</option>
                                <option value="simulation" @selected(old('goal') === 'simulation')>Simulation Mode</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Template</label>
                            <select name="config_template_id" class="form-select">
                                <option value="">Auto select active template</option>
                                @foreach($templates as $template)
                                    <option value="{{ $template->id }}" @selected(old('config_template_id') == $template->id)>{{ $template->name }} ({{ $template->category }})</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="content-card mb-3">
                    <h5>2. AI Assistant</h5>
                    <div class="mb-3">
                        <label class="form-label">Natural-language request</label>
                        <textarea name="intent_text" class="form-control" rows="3" placeholder="Create VLAN 10 with DHCP and ACL">{{ old('intent_text') }}</textarea>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        @foreach($presets as $preset)
                            <button type="button"
                                    class="btn btn-outline-primary preset-button"
                                    data-preset="{{ $preset['key'] }}"
                                    data-goal="{{ $preset['goal'] }}"
                                    data-playbook="{{ $preset['playbook'] }}"
                                    data-intent="{{ $preset['label'] }} preset deployment">
                                {{ $preset['label'] }}
                            </button>
                        @endforeach
                    </div>
                    <div class="small text-muted">Selecting a preset pre-fills the plan but still lets you review the generated config before execution.</div>
                </div>

                <div class="content-card mb-3">
                    <h5>3. Inputs</h5>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Playbook</label>
                            <select name="playbook_name" class="form-select">
                                <option value="">Auto select from goal/preset</option>
                                @foreach($playbooks as $playbook)
                                    <option value="{{ $playbook }}" @selected(old('playbook_name') === $playbook)>{{ $playbook }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Variables (JSON)</label>
                            <textarea name="variables" class="form-control font-monospace" rows="8" placeholder='{"interfaces":[{"name":"GigabitEthernet0/2","mode":"access","access_vlan":10}]}'>{{ old('variables') }}</textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notes</label>
                            <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="content-card mb-3">
                    <h5>4. Preview</h5>
                    <div class="small text-muted mb-2">The generated configuration is reviewed before execution and stored with validation results.</div>
                    <ul class="small mb-0">
                        <li>Validation before execution</li>
                        <li>Assistant recommendations stored with the deployment</li>
                        <li>Rollback and replay history retained</li>
                    </ul>
                </div>

                <div class="content-card mb-3">
                    <h5>5. Deploy</h5>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" value="1" name="execute_now" id="execute_now" @checked(old('execute_now', true))>
                        <label class="form-check-label" for="execute_now">Queue and execute immediately</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" value="1" name="simulation_mode" id="simulation_mode" @checked(old('simulation_mode', false))>
                        <label class="form-check-label" for="simulation_mode">Simulation mode only</label>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary flex-fill">Create Deployment</button>
                        <a href="{{ route('deployments.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </div>

                <div class="content-card">
                    <h5>Smart Tips</h5>
                    <div class="small text-muted">Choose a preset to accelerate common deployments:</div>
                    <ul class="small mb-0">
                        <li>Small Office: static routing + DHCP + ACL</li>
                        <li>Enterprise: OSPF + VLAN segmentation</li>
                        <li>Lab: simulation-first, safe demo baseline</li>
                    </ul>
                </div>
            </div>
        </div>
    </form>

    <div class="content-card mt-3">
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-outline-dark" id="openSmartConfig">Open Device AI Config</button>
            <a href="{{ route('topologies.index') }}" class="btn btn-outline-info">Open Topology Lab</a>
        </div>
    </div>

    <script>
        (function() {
            const deviceSelect = document.querySelector('select[name="device_id"]');
            const openButton = document.getElementById('openSmartConfig');
            const presetButtons = document.querySelectorAll('.preset-button');
            const presetKeyInput = document.getElementById('preset_key');
            const scenarioKeyInput = document.getElementById('scenario_key');
            const goalSelect = document.querySelector('select[name="goal"]');
            const playbookSelect = document.querySelector('select[name="playbook_name"]');
            const intentTextarea = document.querySelector('textarea[name="intent_text"]');
            const scenarioButtons = document.querySelectorAll('.scenario-button');

            if (openButton && deviceSelect) {
                openButton.addEventListener('click', function() {
                    if (!deviceSelect.value) {
                        alert('Please choose a device first.');
                        return;
                    }
                    window.location.href = '/devices/' + deviceSelect.value + '/auto-config';
                });
            }

            presetButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const preset = button.dataset.preset || '';
                    const goal = button.dataset.goal || '';
                    const playbook = button.dataset.playbook || '';
                    const intent = button.dataset.intent || '';

                    if (presetKeyInput) presetKeyInput.value = preset;
                    if (goalSelect && goal) goalSelect.value = goal;
                    if (playbookSelect && playbook) playbookSelect.value = playbook;
                    if (intentTextarea && intent) intentTextarea.value = intent;
                });
            });

            scenarioButtons.forEach(function(button) {
                button.addEventListener('click', function() {
                    const scenario = button.dataset.scenario || '';
                    const playbook = button.dataset.playbook || '';
                    const goal = button.dataset.goal || '';

                    if (scenarioKeyInput) scenarioKeyInput.value = scenario;
                    if (playbookSelect && playbook) playbookSelect.value = playbook;
                    if (goalSelect && goal) goalSelect.value = goal;
                    if (presetKeyInput && !presetKeyInput.value) presetKeyInput.value = scenario;
                });
            });
        })();
    </script>
@endsection
