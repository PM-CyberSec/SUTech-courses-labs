@php
    $title = 'AI Topology Builder';
    $subtitle = 'Drag, prompt, and generate Cisco labs with beginner-friendly defaults';
    $canvasData = json_decode($canvasJson ?? '{"devices":[],"links":[],"configs":[],"validation":[]}', true) ?: ['devices' => [], 'links' => [], 'configs' => [], 'validation' => []];
    $selectedDeviceData = $selectedDevice ? [
        'name' => $selectedDevice->name ?? $selectedDevice->hostname,
        'type' => $selectedDevice->type ?? $selectedDevice->device_type,
        'role' => $selectedDevice->role,
        'model' => $selectedDevice->model,
        'interfaces' => $selectedDevice->topologyInterfaces->map(fn ($interface) => [
            'name' => $interface->name,
            'type' => $interface->type,
            'ip_address' => $interface->ip_address,
            'subnet_mask' => $interface->subnet_mask,
            'vlan_id' => $interface->vlan_id,
            'mode' => $interface->mode,
        ])->values(),
    ] : null;
@endphp
@extends('layouts.app')

@section('content')
    <style>
        .builder-stage {
            background:
                radial-gradient(circle at top left, rgba(37, 99, 235, 0.16), transparent 32%),
                radial-gradient(circle at top right, rgba(15, 23, 42, 0.10), transparent 28%),
                linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #dbe4f0;
            border-radius: 18px;
            overflow: hidden;
        }
        .canvas-card {
            position: relative;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #fff;
            overflow: hidden;
        }
        .canvas-toolbar-section {
            position: sticky;
            top: 0;
            z-index: 50;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 16px;
        }
        .canvas-toolbar-section .row {
            margin: 0;
            align-items: center;
        }
        .canvas-toolbar-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .canvas-toolbar-left .title-block h5 {
            margin: 0;
            font-size: 1rem;
            font-weight: 600;
        }
        .canvas-toolbar-left .title-block small {
            color: #64748b;
            font-size: 0.75rem;
        }
        .canvas-toolbar-right {
            display: flex;
            flex-wrap: nowrap;
            gap: 6px;
            justify-content: flex-end;
            align-items: center;
        }
        .canvas-toolbar-buttons {
            display: flex;
            flex-wrap: nowrap;
            gap: 6px;
            align-items: center;
        }
        .canvas-toolbar-buttons .btn {
            padding: 5px 10px;
            font-size: 0.78rem;
            white-space: nowrap;
            border-radius: 6px;
        }
        .canvas-toolbar-buttons .btn i {
            margin-right: 3px;
        }
        .canvas-toolbar-buttons .btn span {
            display: inline;
        }
        .zoom-display {
            min-width: 48px;
            text-align: center;
            font-size: 0.78rem;
            padding: 5px 8px;
            border-radius: 6px;
        }
        .device-badge {
            font-size: 0.72rem;
            padding: 3px 8px;
        }
        .canvas-viewport {
            position: relative;
            height: 650px;
            overflow: auto;
            border: 1px solid #dbeafe;
            border-top: none;
            border-radius: 0 0 16px 16px;
            background: #fafbfc;
        }
        .canvas-scene {
            position: relative;
            min-width: 1200px;
            min-height: 850px;
            width: 1200px;
            height: 850px;
            transform-origin: 0 0;
            background:
                radial-gradient(circle, #e2e8f0 1px, transparent 1px);
            background-size: 20px 20px;
        }
        .canvas-scene svg {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
        }
        .topology-node {
            position: absolute;
            width: 150px;
            min-height: 78px;
            border-radius: 16px;
            border: 1px solid #cbd5e1;
            background: rgba(255, 255, 255, 0.98);
            box-shadow: 0 14px 30px rgba(15, 23, 42, 0.10);
            padding: 10px 12px;
            cursor: grab;
            z-index: 10;
            transition: box-shadow 0.15s ease;
            user-select: none;
        }
        .topology-node:hover {
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.15);
        }
        .topology-node.selected {
            border-color: #2563eb;
            box-shadow: 0 16px 34px rgba(37, 99, 235, 0.25);
            z-index: 20;
        }
        .topology-node.dragging {
            cursor: grabbing;
            z-index: 100;
        }
        .topology-node .device-icon {
            width: 28px;
            height: 28px;
            border-radius: 9px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            color: #fff;
            font-size: 12px;
            font-weight: 700;
        }
        .topology-node[data-type="router"] .device-icon { background: #1d4ed8; }
        .topology-node[data-type="switch"] .device-icon { background: #059669; }
        .topology-node[data-type="pc"] .device-icon { background: #7c3aed; }
.topology-node[data-type="server"] .device-icon { background: #b45309; }
        .topology-node[data-type="firewall"] .device-icon { background: #dc2626; }
        .topology-node[data-type="cloud"] .device-icon { background: #0f766e; }
        .topology-node .device-name { font-weight: 700; font-size: 0.92rem; }
        .topology-node .device-meta { font-size: 0.76rem; color: #64748b; }
        .result-tabs .nav-link { font-size: 0.9rem; }
        .builder-panel {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #fff;
        }
        .muted-code {
            background: #0f172a;
            color: #e2e8f0;
            border-radius: 14px;
            padding: 14px;
            max-height: 240px;
            overflow: auto;
            font-size: 0.8rem;
        }
        @media (max-width: 1200px) {
            .canvas-toolbar-buttons {
                flex-wrap: wrap;
            }
        }
        @media (max-width: 991px) {
            .canvas-toolbar-section {
                position: relative;
            }
            .canvas-toolbar-right {
                margin-top: 8px;
                justify-content: flex-start;
            }
        }
        @media (max-width: 576px) {
            .canvas-toolbar-buttons .btn span {
                display: none;
            }
            .canvas-toolbar-buttons .btn i {
                margin-right: 0;
            }
        }
    </style>

    <div class="row g-3">
        <div class="col-12">
            <div class="content-card builder-stage">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h4 class="mb-1">AI Topology Builder</h4>
                        <div class="text-muted">Beginner mode generates a full lab from one sentence. Expert mode lets you edit the JSON blueprint and manual connections.</div>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        @if($topology)
                            <a href="{{ route('ai-topology.export.json', $topology) }}" class="btn btn-outline-secondary">Export JSON</a>
                            <a href="{{ route('ai-topology.export.zip', $topology) }}" class="btn btn-outline-primary">Export ZIP</a>
                            <a href="{{ route('deployments.wizard') }}" class="btn btn-outline-success">Deploy</a>
                        @endif
                    </div>
                </div>

                <form method="POST" action="{{ route('ai-topology.generate') }}" id="aiTopologyForm">
                    @csrf
                    <input type="hidden" name="preset_key" id="preset_key" value="{{ old('preset_key') }}">
                    <input type="hidden" name="expert_blueprint_json" id="expert_blueprint_json" value="{{ old('expert_blueprint_json') }}">
                    <div class="row g-3">
                        <div class="col-lg-4">
                            <div class="builder-panel p-3 h-100">
                                <h5 class="mb-3">Prompt Input</h5>
                                <label class="form-label">Natural language request</label>
                                <textarea name="prompt" id="promptInput" class="form-control mb-3" rows="8" placeholder="Create a small office topology with 1 router, 2 switches, 4 PCs, VLAN 10 for HR, VLAN 20 for IT, DHCP, DNS, and internet access.">{{ old('prompt', $topology?->metadata['prompt'] ?? '') }}</textarea>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <button type="submit" class="btn btn-primary">Generate Topology</button>
                                    <button type="submit" name="auto_fix" value="1" class="btn btn-outline-warning">Auto Fix Issues</button>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Preset examples</label>
                                    <div class="d-grid gap-2">
                                        @foreach($presets as $key => $preset)
                                            <button type="button"
                                                    class="btn btn-outline-dark text-start preset-button"
                                                    data-preset="{{ $key }}"
                                                    data-prompt="{{ $preset['prompt'] }}">
                                                <div class="fw-semibold">{{ $preset['label'] }}</div>
                                                <div class="small text-muted">{{ $preset['description'] }}</div>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Expert JSON</label>
                                    <textarea name="expert_blueprint_json" id="expertBlueprintInput" class="form-control font-monospace" rows="10" placeholder='{"devices":[],"links":[]}'>{{ old('expert_blueprint_json', $jsonExport ?? '') }}</textarea>
                                </div>
                                <div class="small text-muted">
                                    Expert mode accepts a full blueprint JSON export. Move devices in the canvas or edit links, then generate again to persist the changes.
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-8">
                            <divclass="canvas-card mb-3">
                                <div class="canvas-toolbar-section">
                                    <div class="row align-items-center">
                                        <div class="col-lg-4">
                                            <div class="d-flex align-items-center gap-2">
                                                <div>
                                                    <h5 class="mb-0">Visual Canvas</h5>
                                                    <div class="small text-muted mb-0">Drag, zoom, connect, and inspect devices</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-4 text-center d-none d-lg-block">
                                            <div class="d-flex justify-content-center gap-2">
                                                <span class="badge bg-light text-dark border">
                                                    <i class="bi bi-diagram-3"></i> <span id="deviceCount">{{ count($canvasData['devices']) }}</span> Devices
                                                </span>
                                                <span class="badge bg-light text-dark border">
                                                    <i class="bi bi-link-45deg"></i> <span id="linkCount">{{ count($canvasData['links']) }}</span> Links
                                                </span>
                                                <span class="badge @if(count($validationResults) > 0) bg-danger @else bg-success @endif" id="validationBadge">
                                                    @if(count($validationResults) > 0) {{ count($validationResults) }} Issues @else Clean @endif
                                                </span>
                                            </div>
                                        </div>
                                        <div class="col-lg-4">
                                            <div class="canvas-toolbar-buttons">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" id="layoutBtn" title="Auto-arrange devices">
                                                    <i class="bi bi-grid-3x3-gap"></i><span>Auto-layout</span>
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" id="connectBtn" title="Click two devices to connect">
                                                    <i class="bi bi-plug"></i><span>Connect</span>
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" id="fitViewBtn" title="Fit all nodes in view">
                                                    <i class="bi bi-arrows-fullscreen"></i><span>Fit</span>
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" id="resetViewBtn" title="Reset zoom and position">
                                                    <i class="bi bi-arrow-counterclockwise"></i><span>Reset</span>
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" id="zoomOutBtn" title="Zoom out">
                                                    <i class="bi bi-zoom-out"></i>
                                                </button>
                                                <span class="btn btn-outline-secondary btn-sm zoom-display" id="zoomLevel" style="cursor: default;">100%</span>
                                                <button type="button" class="btn btn-outline-secondary btn-sm" id="zoomInBtn" title="Zoom in">
                                                    <i class="bi bi-zoom-in"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-primary btn-sm" id="exportPngBtn" title="Export as PNG">
                                                    <i class="bi bi-image"></i><span>PNG</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="canvas-viewport" id="canvasViewport">
                                    <div class="canvas-scene" id="canvasScene">
                                        <svg id="linksLayer"></svg>
                                        <div id="nodesContainer">
                                            @foreach($canvasData['devices'] as $device)
                                                <div class="topology-node"
                                                     data-device="{{ $device['name'] }}"
                                                     data-type="{{ $device['type'] }}"
                                                     data-role="{{ $device['role'] }}"
                                                     data-x="{{ $device['x'] }}"
                                                     data-y="{{ $device['y'] }}"
                                                     style="left: {{ (int) $device['x'] }}px; top: {{ (int) $device['y'] }}px;">
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        <div class="device-icon">{{ strtoupper(substr($device['type'], 0, 2)) }}</div>
                                                        <div>
                                                            <div class="device-name">{{ $device['name'] }}</div>
                                                            <div class="device-meta">{{ $device['type'] }} · {{ $device['role'] }}</div>
                                                        </div>
                                                    </div>
                                                    <div class="small text-muted">
                                                        {{ $device['interfaces'][0]['name'] ?? 'No interface' }}
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-lg-5">
                                    <div class="builder-panel p-3 h-100">
                                        <h5 class="mb-3">Device Inspector</h5>
                                        <div id="inspectorEmpty" class="text-muted small d-none">Click a device on the canvas to inspect interfaces and generated CLI.</div>
                                        <div id="inspectorContent">
                                            <div class="fw-semibold" id="inspectorName">{{ $selectedDeviceData['name'] ?? 'No device selected' }}</div>
                                            <div class="small text-muted mb-2" id="inspectorMeta">
                                                {{ isset($selectedDeviceData) ? $selectedDeviceData['type'].' · '.$selectedDeviceData['role'].' · '.$selectedDeviceData['model'] : '' }}
                                            </div>
                                            <div class="small mb-2"><span class="badge text-bg-light border" id="inspectorType">{{ $selectedDeviceData['type'] ?? '' }}</span></div>
                                            <div class="mb-2">
                                                <div class="small fw-semibold mb-1">Interfaces</div>
                                                <ul class="small mb-0" id="inspectorInterfaces">
                                                    @if($selectedDeviceData)
                                                        @foreach($selectedDeviceData['interfaces'] as $interface)
                                                            <li>{{ $interface['name'] }} @if($interface['ip_address']) · {{ $interface['ip_address'] }} {{ $interface['subnet_mask'] }}@endif</li>
                                                        @endforeach
                                                    @endif
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-7">
                                    <div class="builder-panel p-3 h-100">
                                        <h5 class="mb-3">Validation & Simulation</h5>
                                        <ul class="list-group mb-3">
                                            @forelse($validationResults as $result)
                                                <li class="list-group-item d-flex justify-content-between align-items-start">
                                                    <div class="me-3">
                                                        <div class="fw-semibold">{{ $result->category }}</div>
                                                        <div class="small text-muted">{{ $result->message }}</div>
                                                    </div>
                                                    <span class="badge {{ $result->severity === 'error' ? 'text-bg-danger' : 'text-bg-warning' }}">{{ $result->severity }}</span>
                                                </li>
                                            @empty
                                                <li class="list-group-item text-muted">No validation issues detected.</li>
                                            @endforelse
                                        </ul>
                                        <div class="small fw-semibold mb-2">Simulation Steps</div>
                                        <ol class="small mb-0">
                                            @forelse($simulationSteps as $step)
                                                <li>{{ $step }}</li>
                                            @empty
                                                <li>Generate a topology to see simulation steps.</li>
                                            @endforelse
                                        </ol>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="col-12">
            <div class="content-card">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
                    <h5 class="mb-0">Config Output</h5>
                    @if($topology)
                        <div class="d-flex gap-2">
                            <a href="{{ route('ai-topology.export.json', $topology) }}" class="btn btn-outline-secondary btn-sm">Copy JSON Export</a>
                            <a href="{{ route('ai-topology.export.zip', $topology) }}" class="btn btn-outline-primary btn-sm">Download ZIP</a>
                        </div>
                    @endif
                </div>
                @if($topology && $topology->configs->isNotEmpty())
                    <ul class="nav nav-tabs result-tabs mb-3">
                        @foreach($topology->configs as $config)
                            <li class="nav-item">
                                <button class="nav-link @if($loop->first) active @endif" data-bs-toggle="tab" data-bs-target="#cfg-{{ $config->id }}" type="button">
                                    {{ $config->topologyDevice?->name ?? $config->topologyDevice?->hostname }}
                                </button>
                            </li>
                        @endforeach
                    </ul>
                    <div class="tab-content">
                        @foreach($topology->configs as $config)
                            <div class="tab-pane fade @if($loop->first) show active @endif" id="cfg-{{ $config->id }}">
                                <div class="d-flex gap-2 mb-2 flex-wrap">
                                    <button type="button" class="btn btn-sm btn-outline-secondary js-copy-config" data-target="config-{{ $config->id }}">Copy CLI</button>
                                    <a href="{{ route('ai-topology.export.zip', $topology) }}" class="btn btn-sm btn-outline-primary d-none">Download .txt</a>
                                </div>
                                <pre id="config-{{ $config->id }}" class="muted-code mb-0">{{ $config->generated_cli }}</pre>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-muted">Generate a topology to see per-device CLI output.</div>
                @endif
            </div>
        </div>

        <div class="col-12">
            <div class="content-card">
                <div class="row g-3">
                    <div class="col-lg-6">
                        <h5 class="mb-3">Topology JSON</h5>
                        <pre class="muted-code mb-0" id="jsonExportBlock">{{ $jsonExport ?: 'Generate a topology to see the export JSON.' }}</pre>
                    </div>
                    <div class="col-lg-6">
                        <h5 class="mb-3">Device Snapshot</h5>
                        <div class="small text-muted mb-2">The canvas is backed by the saved topology model and can be re-generated from the JSON blueprint in Expert Mode.</div>
                        <div class="small text-muted">Selected device: {{ $selectedDeviceData['name'] ?? 'none' }}</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const canvasViewport = document.getElementById('canvasViewport');
            const canvasScene = document.getElementById('canvasScene');
            const linksLayer = document.getElementById('linksLayer');
            const nodesContainer = document.getElementById('nodesContainer');
            const nodes = nodesContainer ? Array.from(nodesContainer.querySelectorAll('.topology-node')) : [];
            const presetButtons = document.querySelectorAll('.preset-button');
            const promptInput = document.getElementById('promptInput');
            const presetInput = document.getElementById('preset_key');
            const expertTextarea = document.getElementById('expertBlueprintInput');
            const expertHidden = document.getElementById('expert_blueprint_json');
            const layoutBtn = document.getElementById('layoutBtn');
            const connectBtn = document.getElementById('connectBtn');
            const fitViewBtn = document.getElementById('fitViewBtn');
            const resetViewBtn = document.getElementById('resetViewBtn');
            const zoomInBtn = document.getElementById('zoomInBtn');
            const zoomOutBtn = document.getElementById('zoomOutBtn');
            const zoomLevelDisplay = document.getElementById('zoomLevel');
            const exportPngBtn = document.getElementById('exportPngBtn');
            const inspectorName = document.getElementById('inspectorName');
            const inspectorMeta = document.getElementById('inspectorMeta');
            const inspectorType = document.getElementById('inspectorType');
            const inspectorInterfaces = document.getElementById('inspectorInterfaces');
            const inspectorEmpty = document.getElementById('inspectorEmpty');
            const inspectorContent = document.getElementById('inspectorContent');
            const canvasData = @json($canvasData);
            let scale = 1;
            let panX = 0;
            let panY = 0;
            let connectMode = false;
            let connectSource = null;
            let dragState = null;

            // Scene dimensions for boundary clamping
            const SCENE_WIDTH = 1200;
            const SCENE_HEIGHT = 850;
            const NODE_WIDTH = 150;
            const NODE_HEIGHT = 78;
            const BOUNDARY_MARGIN = 20;

            function updateStatusBadges() {
                const deviceCountEl = document.getElementById('deviceCount');
                const linkCountEl = document.getElementById('linkCount');
                const validationBadge = document.getElementById('validationBadge');
                if (deviceCountEl) deviceCountEl.textContent = canvasData.devices ? canvasData.devices.length : 0;
                if (linkCountEl) linkCountEl.textContent = canvasData.links ? canvasData.links.length : 0;
                if (validationBadge) {
                    const issues = {{ count($validationResults) }};
                    if (issues > 0) {
                        validationBadge.className = 'badge bg-danger';
                        validationBadge.textContent = issues + ' Issues';
                    } else {
                        validationBadge.className = 'badge bg-success';
                        validationBadge.textContent = 'Clean';
                    }
                }
            }

            function syncExpertJson() {
                if (expertHidden) {
                    expertHidden.value = JSON.stringify(canvasData, null, 2);
                }
                if (expertTextarea) {
                    expertTextarea.value = JSON.stringify(canvasData, null, 2);
                }
            }

            function clampNodeToScene(node, x, y) {
                const nodeW = node.offsetWidth || NODE_WIDTH;
                const nodeH = node.offsetHeight || NODE_HEIGHT;
                
                // Clamp x between BOUNDARY_MARGIN and scene width - node width - BOUNDARY_MARGIN
                const minX = BOUNDARY_MARGIN;
                const maxX = SCENE_WIDTH - nodeW - BOUNDARY_MARGIN;
                x = Math.max(minX, Math.min(x, maxX));
                
                // Clamp y between BOUNDARY_MARGIN and scene height - node height - BOUNDARY_MARGIN
                // Must stay below toolbar (y >= 20)
                const minY = BOUNDARY_MARGIN;
                const maxY = SCENE_HEIGHT - nodeH - BOUNDARY_MARGIN;
                y = Math.max(minY, Math.min(y, maxY));
                
                return { x, y };
            }

            function updateNodePosition(node, x, y) {
                const clamped = clampNodeToScene(node, x, y);
                node.dataset.x = String(clamped.x);
                node.dataset.y = String(clamped.y);
                node.style.left = clamped.x + 'px';
                node.style.top = clamped.y + 'px';
                return clamped;
            }

            function getNodeCenter(node) {
                return {
                    x: parseFloat(node.dataset.x || '0') + (node.offsetWidth / 2),
                    y: parseFloat(node.dataset.y || '0') + (node.offsetHeight / 2),
                };
            }

            function renderLinks() {
                if (!linksLayer) return;
                const existing = Array.from(linksLayer.querySelectorAll('line'));
                existing.forEach((line) => line.remove());
                const deviceMap = {};
                nodes.forEach((node) => {
                    deviceMap[node.dataset.device] = node;
                });
                (canvasData.links || []).forEach((link) => {
                    const source = deviceMap[link.source_device];
                    const target = deviceMap[link.target_device];
                    if (!source || !target) {
                        return;
                    }
                    const sourceCenter = getNodeCenter(source);
                    const targetCenter = getNodeCenter(target);
                    const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
                    line.setAttribute('x1', sourceCenter.x);
                    line.setAttribute('y1', sourceCenter.y);
                    line.setAttribute('x2', targetCenter.x);
                    line.setAttribute('y2', targetCenter.y);
                    line.setAttribute('stroke', link.status === 'planned' ? '#64748b' : '#2563eb');
                    line.setAttribute('stroke-width', '3');
                    line.setAttribute('stroke-dasharray', link.cable_type === 'serial' ? '6 4' : '0');
                    linksLayer.appendChild(line);
                });
            }

            function updateInspector(node) {
                const name = node.dataset.device || '';
                const type = node.dataset.type || '';
                const role = node.dataset.role || '';
                const device = (canvasData.devices || []).find((item) => item.name === name);
                if (!device) {
                    inspectorEmpty.classList.remove('d-none');
                    inspectorContent.classList.add('d-none');
                    return;
                }
                inspectorEmpty.classList.add('d-none');
                inspectorContent.classList.remove('d-none');
                inspectorName.innerText = device.name;
                inspectorMeta.innerText = [device.type, device.role, device.interfaces?.length ? device.interfaces.length + ' interface(s)' : ''].filter(Boolean).join(' · ');
                inspectorType.innerText = type.toUpperCase();
                inspectorInterfaces.innerHTML = '';
                (device.interfaces || []).forEach((iface) => {
                    const li = document.createElement('li');
                    li.innerText = iface.name + (iface.ip_address ? ' · ' + iface.ip_address + ' ' + iface.subnet_mask : '');
                    inspectorInterfaces.appendChild(li);
                });
            }

            function autoLayout() {
                // Clear layer positions
                const layers = {
                    router: [], switch: [], firewall: [], cloud: [],
                    server: [], pc: []
                };
                
                // Categorize nodes by type
                nodes.forEach((node) => {
                    const type = node.dataset.type || 'pc';
                    if (layers[type]) {
                        layers[type].push(node);
                    } else {
                        layers.pc.push(node);
                    }
                });

                // Base positions for each layer (y coordinates)
                const yPositions = {
                    router: 60,
                    firewall: 60,
                    cloud: 60,
                    switch: 280,
                    server: 500,
                    pc: 500
                };
                
                // X spacing for each layer
                const xSpacing = 180;
                const ySpacing = 140;
                
                // Layout each layer
                let layerIndex = 0;
                const layerOrder = ['router', 'firewall', 'cloud', 'switch', 'server', 'pc'];
                
                layerOrder.forEach((type) => {
                    const layerNodes = layers[type];
                    layerNodes.forEach((node, idx) => {
                        const x = 80 + (idx * xSpacing);
                        const y = yPositions[type] + (idx > 0 ? idx * 60 : 0);
                        updateNodePosition(node, x, y);
                        
                        // Update canvasData
                        const deviceName = node.dataset.device;
                        const device = canvasData.devices.find(d => d.name === deviceName);
                        if (device) {
                            device.x = parseFloat(node.dataset.x);
                            device.y = parseFloat(node.dataset.y);
                        }
                    });
                });

                renderLinks();
                syncExpertJson();
                updateStatusBadges();
                
                // Use resetView instead of fitView for auto-layout
                setTimeout(() => resetView(), 50);
            }

            function fitView() {
                if (nodes.length === 0) return;
                
                // Get viewport dimensions
                const viewportRect = canvasViewport.getBoundingClientRect();
                const viewportWidth = canvasViewport.clientWidth || 800;
                const viewportHeight = canvasViewport.clientHeight || 650;
                
                // Calculate bounding box of all nodes
                let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
                nodes.forEach((node) => {
                    const x = parseFloat(node.dataset.x || '0');
                    const y = parseFloat(node.dataset.y || '0');
                    const w = node.offsetWidth || NODE_WIDTH;
                    const h = node.offsetHeight || NODE_HEIGHT;
                    minX = Math.min(minX, x);
                    minY = Math.min(minY, y);
                    maxX = Math.max(maxX, x + w);
                    maxY = Math.max(maxY, y + h);
                });
                
                const padding = 80;
                const contentWidth = maxX - minX + padding * 2;
                const contentHeight = maxY - minY + padding * 2;
                
                // Calculate scale to fit content in viewport
                const scaleX = viewportWidth / contentWidth;
                const scaleY = viewportHeight / contentHeight;
                scale = Math.min(scaleX, scaleY);
                
                // Clamp scale between 0.5 and 1.2
                scale = Math.max(0.5, Math.min(scale, 1.2));
                
                // Calculate pan to center content
                const scaledWidth = contentWidth * scale;
                const scaledHeight = contentHeight * scale;
                panX = (viewportWidth - scaledWidth) / 2 - minX * scale + padding * scale;
                panY = (viewportHeight - scaledHeight) / 2 - minY * scale + padding * scale;
                
                applyTransform();
                updateZoomDisplay();
            }

            function resetView() {
                scale = 1;
                panX = 0;
                panY = 0;
                applyTransform();
                updateZoomDisplay();
                
                // Ensure nodes are within bounds after reset
                nodes.forEach((node) => {
                    const x = parseFloat(node.dataset.x || '0');
                    const y = parseFloat(node.dataset.y || '0');
                    const clamped = clampNodeToScene(node, x, y);
                    if (clamped.x !== x || clamped.y !== y) {
                        updateNodePosition(node, clamped.x, clamped.y);
                    }
                });
                renderLinks();
            }

            function updateZoomDisplay() {
                if (zoomLevelDisplay) {
                    zoomLevelDisplay.textContent = Math.round(scale * 100) + '%';
                }
            }

            function applyTransform() {
                if (nodesContainer) {
                    nodesContainer.style.transform = 'translate(' + panX + 'px, ' + panY + 'px) scale(' + scale + ')';
                    nodesContainer.style.transformOrigin = '0 0';
                }
                if (linksLayer) {
                    linksLayer.style.transform = 'translate(' + panX + 'px, ' + panY + 'px) scale(' + scale + ')';
                    linksLayer.style.transformOrigin = '0 0';
                }
                updateZoomDisplay();
            }

            function handleNodeMouseDown(node, event) {
                event.preventDefault();
                node.classList.add('selected');
                node.classList.add('dragging');
                updateInspector(node);

                if (connectMode) {
                    if (!connectSource) {
                        connectSource = node;
                        node.style.outline = '3px solid #2563eb';
                        return;
                    }
                    if (connectSource === node) {
                        connectSource.style.outline = '';
                        connectSource = null;
                        return;
                    }
                    const sourceData = canvasData.devices.find((item) => item.name === connectSource.dataset.device);
                    const targetData = canvasData.devices.find((item) => item.name === node.dataset.device);
                    if (sourceData && targetData) {
                        canvasData.links = canvasData.links || [];
                        canvasData.links.push({
                            source_device: sourceData.name,
                            target_device: targetData.name,
                            source_interface: (sourceData.interfaces[0] || {}).name || 'GigabitEthernet0/0',
                            target_interface: (targetData.interfaces[0] || {}).name || 'FastEthernet0/0',
                            cable_type: 'copper-straight-through',
                            status: 'planned',
                        });
                        renderLinks();
                        syncExpertJson();
                        updateStatusBadges();
                    }
                    connectSource.style.outline = '';
                    connectSource = null;
                    return;
                }

                const rect = node.getBoundingClientRect();
                dragState = {
                    node,
                    offsetX: event.clientX - rect.left,
                    offsetY: event.clientY - rect.top,
                };
            }

            nodes.forEach((node) => {
                node.addEventListener('mousedown', (event) => handleNodeMouseDown(node, event));
                node.addEventListener('click', () => updateInspector(node));
            });

            document.addEventListener('mousemove', (event) => {
                if (!dragState) {
                    return;
                }
                
                // Get scene position relative to viewport
                const sceneRect = canvasScene.getBoundingClientRect();
                
                // Calculate new position in scene coordinates
                // Account for any scroll offset
                const scrollLeft = canvasViewport.scrollLeft;
                const scrollTop = canvasViewport.scrollTop;
                
                const x = event.clientX - sceneRect.left - dragState.offsetX + scrollLeft;
                const y = event.clientY - sceneRect.top - dragState.offsetY + scrollTop;
                
                // Update position with clamping
                updateNodePosition(dragState.node, x, y);
                renderLinks();
            });

            document.addEventListener('mouseup', () => {
                if (dragState) {
                    dragState.node.classList.remove('dragging');
                    syncExpertJson();
                    
                    // Update canvasData with new position
                    const deviceName = dragState.node.dataset.device;
                    const device = canvasData.devices.find(d => d.name === deviceName);
                    if (device) {
                        device.x = parseFloat(dragState.node.dataset.x);
                        device.y = parseFloat(dragState.node.dataset.y);
                    }
                }
                dragState = null;
            });

            // Handle window resize
            window.addEventListener('resize', () => {
                renderLinks();
            });

            presetButtons.forEach((button) => {
                button.addEventListener('click', () => {
                    if (promptInput) {
                        promptInput.value = button.dataset.prompt || '';
                    }
                    if (presetInput) {
                        presetInput.value = button.dataset.preset || '';
                    }
                });
            });

            if (layoutBtn) {
                layoutBtn.addEventListener('click', autoLayout);
            }
            if (fitViewBtn) {
                fitViewBtn.addEventListener('click', fitView);
            }
            if (resetViewBtn) {
                resetViewBtn.addEventListener('click', resetView);
            }
            if (connectBtn) {
                connectBtn.addEventListener('click', () => {
                    connectMode = !connectMode;
                    connectBtn.classList.toggle('btn-primary', connectMode);
                    connectBtn.classList.toggle('btn-outline-secondary', !connectMode);
                    connectBtn.innerHTML = connectMode ? '<i class="bi bi-plug-fill"></i> Connecting' : '<i class="bi bi-plug"></i> Connect';
                    if (!connectMode && connectSource) {
                        connectSource.style.outline = '';
                        connectSource = null;
                    }
                });
            }
            if (zoomInBtn) {
                zoomInBtn.addEventListener('click', () => {
                    scale = Math.min(scale + 0.15, 1.5);
                    applyTransform();
                    renderLinks();
                });
            }
            if (zoomOutBtn) {
                zoomOutBtn.addEventListener('click', () => {
                    scale = Math.max(scale - 0.15, 0.4);
                    applyTransform();
                    renderLinks();
                });
            }
            if (exportPngBtn) {
                exportPngBtn.addEventListener('click', () => {
                    alert('PNG export would capture the current canvas state. This feature requires html2canvas library.');
                });
            }

            document.querySelectorAll('.js-copy-config').forEach((button) => {
                button.addEventListener('click', () => {
                    const target = document.getElementById(button.dataset.target || '');
                    if (!target) {
                        return;
                    }
                    navigator.clipboard.writeText(target.innerText || '').then(() => {
                        const original = button.innerText;
                        button.innerText = 'Copied';
                        setTimeout(() => button.innerText = original, 1200);
                    });
                });
            });

            // Initialize
            renderLinks();
            syncExpertJson();
            updateStatusBadges();
            
            if (!{{ $topology ? 'true' : 'false' }}) {
                autoLayout();
            } else if (nodes.length > 0) {
                setTimeout(resetView, 100);
            }
        })();
    </script>
@endsection