@csrf
@if(isset($device))
    @method('PUT')
@endif
<div class="d-flex justify-content-end mb-2">
    <button type="button" class="btn btn-sm btn-outline-dark" id="autoFillDeviceFields">Auto Fill Suggestions</button>
</div>
<div class="row g-3">
    <div class="col-md-6">
        <label class="form-label">Hostname</label>
        <input type="text" name="hostname" class="form-control" value="{{ old('hostname', $device->hostname ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">IP Address</label>
        <input type="text" name="ip_address" class="form-control" value="{{ old('ip_address', $device->mgmt_ip ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Platform</label>
        <input type="text" name="platform" class="form-control" value="{{ old('platform', $device->platform ?? 'cisco.ios.ios') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Connection Type</label>
        <input type="text" name="connection_type" class="form-control" value="{{ old('connection_type', $device->connection ?? 'network_cli') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Username</label>
        <input type="text" name="username" class="form-control" value="{{ old('username', $device->auth_username ?? '') }}" required>
    </div>
    <div class="col-md-6">
        <label class="form-label">Password</label>
        <input type="text" name="password" class="form-control" value="{{ old('password', $device->auth_password ?? '') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Secret</label>
        <input type="text" name="secret" class="form-control" value="{{ old('secret', $device->become_password ?? '') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Status</label>
        <select name="status" class="form-select" required>
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected(old('status', $device->status ?? 'active') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-6">
        <label class="form-label">Inventory</label>
        <select name="inventory_id" class="form-select">
            <option value="">No Inventory</option>
            @foreach($inventories as $inventory)
                <option value="{{ $inventory->id }}" @selected((string) old('inventory_id', $device->inventory_id ?? '') === (string) $inventory->id)>{{ $inventory->name }}</option>
            @endforeach
        </select>
    </div>
    <div class="col-md-3">
        <label class="form-label">SSH Port</label>
        <input type="number" name="ssh_port" class="form-control" value="{{ old('ssh_port', $device->ssh_port ?? 22) }}">
    </div>
    <div class="col-md-3">
        <label class="form-label">Vendor</label>
        <input type="text" name="vendor" class="form-control" value="{{ old('vendor', $device->vendor ?? '') }}">
    </div>
    <div class="col-md-6">
        <label class="form-label">Ansible Host (optional)</label>
        <input type="text" name="ansible_host" class="form-control" value="{{ old('ansible_host', $device->ansible_host ?? '') }}">
    </div>
</div>
<script>
    (function() {
        const btn = document.getElementById('autoFillDeviceFields');
        if (!btn) return;
        btn.addEventListener('click', function() {
            const platform = document.querySelector('input[name="platform"]');
            const connection = document.querySelector('input[name="connection_type"]');
            const vendor = document.querySelector('input[name="vendor"]');
            const status = document.querySelector('select[name="status"]');

            if (platform && !platform.value) platform.value = 'cisco.ios.ios';
            if (connection && !connection.value) connection.value = 'network_cli';
            if (vendor && !vendor.value) vendor.value = 'Cisco';
            if (status && !status.value) status.value = 'active';
        });
    })();
</script>
