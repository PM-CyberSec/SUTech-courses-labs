# Agent Skills Catalog

Overview
--------
This document catalogs the Digital AI Assistant's core skills grouped by category. Each skill describes capability, typical inputs/outputs, and examples of tasks the agent can perform. Use this as the basis for mapping skills to tooling and automated connectors.

1) Reasoning & Analysis
-----------------------
- Capability: Structured problem decomposition and prioritized recommendations.
- Inputs: Incident logs, requirements, data excerpts.
- Outputs: Stepwise plans, root-cause hypotheses, prioritized action lists.
- Examples:
  - Produce a triage checklist for a security alert, prioritized by impact.
  - Compare two diagnostic hypotheses and list evidence supporting each.

2) Writing & Communication
--------------------------
- Capability: Summarization, rewriting, and creation of templates and reports.
- Inputs: Raw notes, meeting transcripts, BRD drafts.
- Outputs: Executive summaries, issue reports, email drafts, policy templates.
- Examples:
  - Convert a technical incident log into a one-page executive summary.
  - Draft a customer-facing status update using provided facts.

3) Coding & Dev Productivity
---------------------------
- Capability: Generate, review, and explain code in multiple languages; produce infra-as-code snippets.
- Inputs: Code snippets, bug descriptions, repository context.
- Outputs: Code patches, test cases, configuration templates.
- Examples:
  - Generate a unit test for a given function with edge cases.
  - Provide a safe shell command to extract logs with an explanation and safety checks.

4) Data & Evidence Extraction
-----------------------------
- Capability: Extract structured facts from semi-structured sources (logs, CSV, JSON, PDFs).
- Inputs: PCAP excerpts, log files, CSV exports.
- Outputs: Tables, CSV extracts, JSON records with schema.
- Examples:
  - Parse a Suricata alert stream and output top-10 alert types with counts.
  - Extract IPs and timestamps from a packet capture summary.

5) Orchestration & Tooling
--------------------------
- Capability: Compose multi-step workflows, call external connectors (with gating), and return traceable action plans.
- Inputs: Desired outcome, available connectors, RBAC policy.
- Outputs: Workflow definitions, step-by-step runbooks, invocation plans.
- Examples:
  - Create an automation playbook to gather logs, scan them, and create a ticket with findings.
  - Draft a checklist for human approval before performing an automated remediation.

6) Research & Knowledge Retrieval
---------------------------------
- Capability: Summarize domain literature, extract actionable insights, and cite reputable sources.
- Inputs: URLs, documents, knowledge-base queries.
- Outputs: Annotated summaries, citation lists, recommended readings.
- Examples:
  - Produce a concise summary of a vulnerability disclosure and recommended mitigations.

Usage Mapping
-------------
- Map each skill to a specific implementation: e.g., data extraction → parser microservice; code generation → CI pipeline with human review; orchestration → workflow engine.

Skill Governance
----------------
- Assign ownership for each skill to a role (Analyst, Dev, Manager).
- Define gating policies and test suites for high-risk skills (code execution, remediation).

Extensibility
-------------
Skills should be versioned and extendable. Each new capability must include: acceptance tests, a sample prompt template, and a mapping to required tool connectors.
