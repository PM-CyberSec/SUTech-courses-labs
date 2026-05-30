# AutoConfigLab Agent Skills

## AI And Product Skills

1. Transform natural-language intent into structured network automation plans.
2. Recommend safe defaults for routing, VLANs, addressing, and deployment scope.
3. Detect ambiguity in user requests and ask only the minimum clarifying questions needed.
4. Produce demo-ready explanations for success, failure, and rollback scenarios.
5. Make small decision-level recommendations when the user has not specified a safe default.
6. Convert network intent into a ready-to-review execution plan instead of only offering suggestions.

## Laravel Skills

1. Build role-aware Laravel modules with clean boundaries between controllers, services, and jobs.
2. Model devices, inventories, templates, deployments, logs, topologies, and rollbacks with explicit relations.
3. Implement RBAC for admin, engineer, and viewer users across UI and API layers.
4. Create dashboard views, wizard flows, and actionable status pages that support a guided experience.
5. Design async workflows that are safe for queue-backed deployment execution.
6. Keep controllers thin and use form requests, policies, and service classes for behavior control.

## Ansible Automation Skills

1. Generate dynamic inventories and host variables from persisted application state.
2. Render templates and execute playbooks with pre-check and post-check stages.
3. Capture job output, artifact paths, timestamps, and idempotency markers.
4. Implement rollback orchestration and replayable execution records.
5. Keep playbooks modular so they can be reused by presets, preset marketplaces, and multi-device deployments.
6. Orchestrate multiple device deployments in the correct order when dependencies exist.
7. Distinguish dry-run, simulation, and live deployment paths.

## Network Provisioning Skills

1. Generate Cisco CLI for switching, routing, security, and management domains.
2. Support interfaces, VLANs, trunk/access mode, DHCP, NAT, ACL, port-security, SSH, Telnet, hostname, banner, encryption, and gateway settings.
3. Produce topology-aware output that can be previewed, simulated, and deployed.
4. Help visualize device relationships, traffic intent, and routing impact in a lab or demo setting.
5. Generate one-click automation payloads for common deployment patterns.
6. Build AI topology drafts for beginner and expert users, including Packet Tracer-style device placement, link planning, VLAN assignment, and per-device Cisco CLI.

## Validation And Safety Skills

1. Detect IP conflicts, VLAN duplication, and unsafe routing choices before deployment.
2. Separate hard validation errors from warnings and simulation-only notices.
3. Verify deployment state transitions and record the reasons for acceptance or rejection.
4. Preserve auditability by linking each action to a user, role, device set, and timestamp.
5. Detect invalid Cisco CLI patterns and unsafe changes before deployment starts.
6. Escalate ambiguous or conflicting inputs instead of silently guessing.

## Testing Skills

1. Write or describe feature tests for wizard flows, dashboard analytics, deployment, rollback, and RBAC.
2. Validate queue behavior and deployment lifecycle integrity.
3. Confirm idempotent behavior where repeated deployments should converge.
4. Ensure seeded data supports a ready-made demonstration environment.

## Documentation Skills

1. Keep README content aligned with the actual setup, runtime, and demo flows.
2. Keep BRD content aligned with business goals, stakeholders, and non-functional requirements.
3. Keep system prompt rules consistent with the platform architecture and safety constraints.
4. Produce concise delivery notes that list the architecture, UX flow, differentiators, and validation steps.
