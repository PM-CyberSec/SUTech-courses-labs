# Business Requirements Document

## Project Overview

AutoConfigLab is a Laravel + Ansible platform for guided, AI-assisted network provisioning. It targets education, demo labs, and production-inspired automation workflows for Cisco-style infrastructure.

## Business Goals

1. Remove repetitive manual CLI entry from standard provisioning tasks.
2. Standardize deployments through reusable templates, validation, and rollback.
3. Reduce misconfiguration risk through intelligent checks before execution.
4. Increase visibility with replayable logs, diffs, and deployment analytics.
5. Deliver a polished graduation and client-demo experience with a modern UX.

## Stakeholders

1. Network Engineers
2. DevOps and Automation Teams
3. NOC and Operations
4. Security and Compliance
5. Product Owners and Academic Evaluators

## Functional Requirements

### 1. Guided Wizard UX

- Replace rigid CRUD-first flows with a task wizard: Device -> Goal -> Inputs -> Preview -> Deploy.
- Support smart auto-fill using historical device, inventory, and deployment data.
- Show contextual help, validation messages, and previewed impact before execution.
- Support one-click automation from approved presets for common lab and enterprise patterns.

### 2. Dashboard

- Display KPIs for devices, templates, deployments, success rate, failure rate, and time saved.
- Show live logs, recent activity, queue state, and deployment status trends.
- Provide quick actions for common tasks such as new deployment, simulation, and rollback.

### 3. Device And Inventory Management

- Manage device profiles, credentials, platform information, status, and topology membership.
- Manage inventories and host variables for both direct provisioning and topology-wide automation.
- Generate Ansible inventory artifacts from stored records.

### 4. Template And Preset Management

- Maintain reusable templates for switching, routing, security, and management tasks.
- Support preset packages such as Small Office, Enterprise, and Lab.
- Provide a template marketplace concept for reusable community or internal packages.

### 5. AI Assistant

- Accept natural language intent such as "Create VLAN 10 with DHCP and ACL".
- Convert intent into a structured deployment plan with suggested fields and validation hints.
- Offer recommendations for routing protocols, VLAN ranges, and IP ranges.
- Accept follow-up commands such as expand VLAN 10 to trunk ports and keep them consistent with the current plan.
- Generate configuration drafts automatically from intent, with a human review step before execution.

### 6. Validation Engine

- Detect IP conflicts across the inventory and topology.
- Detect VLAN duplication and overlapping assignments.
- Flag routing risks such as ambiguous protocol selection or unsafe redistribution paths.
- Validate pre-deployment and summarize issues before execution.
- Detect invalid Cisco CLI patterns before they are deployed.
- Return clear remediation guidance for each blocking issue.

### 7. Deployment And Simulation

- Execute Ansible playbooks asynchronously through Laravel queues.
- Support simulation mode for dry runs and demonstration scenarios.
- Capture execution output, errors, timestamps, and idempotency indicators.

### 8. Topology Visualization

- Provide a drag-and-drop topology canvas for arranging devices and links.
- Show live device status and deployment impact on the map.
- Support simulation overlays for traffic flow and routing behavior.
- Show live status badges for nodes and links.
- Support drag-and-drop placement of devices in a guided topology editor.

### 8.1 AI Topology Builder

- Accept natural-language topology prompts and convert them into a complete Cisco lab plan.
- Auto-generate device lists, interface layouts, cable links, IP plans, VLAN plans, routing plans, validation results, and simulation steps.
- Support beginner mode with safe defaults and expert mode with editable JSON topology drafts.
- Export Cisco CLI per device and provide ZIP packaging for Packet Tracer-style labs.
- Validate topology consistency before deployment and surface missing IPs, duplicate IPs, VLAN conflicts, trunk/access mismatches, missing gateways, and routing gaps.

### 9. Rollback And Audit

- Provide one-click rollback for deployment recovery.
- Store config diffs, replay data, and deployment history for traceability.
- Preserve audit logs for operational and compliance review.
- Support deployment replay so operators can review the exact sequence of actions.

### 10. Presets And Marketplace

- Provide ready-made presets for Small Office, Enterprise, and Lab environments.
- Allow templates to be reused across devices and deployments.
- Support a marketplace-style catalog for approved internal templates.

### 10. RBAC And Security

- Enforce admin, engineer, and viewer permissions in the UI and API layers.
- Protect credentials with a vault-style storage strategy and avoid plaintext exposure.
- Restrict sensitive operations to authorized users only.

## Non-Functional Requirements

### Security

- Authorization must be enforced server-side and reflected in the UI.
- Secrets must not be exposed in plain API responses or logs.
- All deployment actions must be attributable to a user and timestamp.
- Secure credentials must be masked in UI and API responses.
- Audit trails must include deployment actor, role, timestamp, and affected objects.

### Reliability

- The core execution sequence must remain deterministic: validate -> queue -> execute -> verify -> archive.
- Rollback must be available for failed or unsafe deployments.
- Long-running tasks must not block the web request lifecycle.
- Deployment jobs must survive retries and queue restarts without corrupting state.

### Scalability

- The system should use service-oriented boundaries for execution, AI, and validation.
- Deployment jobs should be queue-backed and horizontally scalable.
- Template and validation logic should be extensible without changing core controllers.
- The architecture should support new AI and validation services without breaking current flows.

### Performance

- The wizard should remain responsive with predictable step transitions.
- Dashboard queries should remain fast enough for live usage and demo sessions.
- Queue-backed deployments should keep the UI non-blocking even for long playbooks.

### Observability

- Persist structured logs, output artifacts, diffs, and status transitions.
- Support auditing, replay, and historical analysis of deployments.
- Provide metrics suitable for dashboards and demo reporting.
- Track deployment success rate, failure rate, and estimated time saved from automation.

### Security And Compliance

- Enforce RBAC in controllers, middleware, and API responses.
- Persist audit logs for deployments, rollbacks, and template changes.
- Prevent exposure of plaintext secrets or credentials in logs.

### Usability

- The UI must be responsive, guided, and role-aware.
- Users should always understand the next step, current risk, and deployment outcome.
- Empty states, warnings, and confirmations must be explicit and helpful.
- The AI Topology Builder must be usable by beginners without Cisco knowledge and still expose expert controls for manual topology editing.

## Success Metrics

1. Users can complete a deployment through the wizard without visiting raw CRUD screens.
2. AI suggestions reduce manual field entry for repeated tasks.
3. Validation blocks invalid or conflicting changes before execution.
4. Deployment, rollback, and replay actions are fully auditable.
5. Demo mode can show success, failure, and rollback scenarios in a controlled way.

## Testing Requirements

### Feature Tests

- Dashboard loads and displays summary data.
- Wizard flow stores intent, preview, and deployment decisions.
- Devices, templates, deployments, and rollback paths remain functional.
- RBAC restrictions are enforced for UI and API actions.

### Validation Tests

- IP conflict detection blocks deployment.
- VLAN duplication detection blocks deployment.
- Routing risk warnings are surfaced before execution.
- Validation results are stored with the deployment record.

### Integration Tests

- `migrate:fresh --seed`
- `route:list`
- `test`

### Manual UAT

- Generate Cisco configuration from a natural-language request.
- Preview topology and simulation output before deployment.
- Execute a rollback and confirm the diff, audit trail, and replay record.
