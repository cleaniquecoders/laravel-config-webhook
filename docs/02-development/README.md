# Development

Everything you need to install the package, wire it into your app, and verify it works.

## Overview

This section walks from a fresh `composer require` through registering your event catalogue,
dispatching payloads (manually or via mapped domain events), verifying signatures on the
receiving side, running the test suite, and booting the bundled end-to-end workbench.

## Table of Contents

### [1. Getting Started](01-getting-started.md)

Install, publish migrations/config, and register your first event catalogue.

### [2. Usage](02-usage.md)

Dispatch manually, map a domain event, verify signatures, enable the admin UI, and prune logs.

### [3. Testing](03-testing.md)

Run the Pest suite and understand the canonical end-to-end test and the testing gotchas.

### [4. Workbench](04-workbench.md)

Boot a real Laravel app around the package with Testbench and exercise the full pipeline
end-to-end, including a local receiver that verifies the signature.

## Related Documentation

- [Architecture](../01-architecture/README.md) — how the pieces fit together
- [Configuration](../03-configuration/README.md) — every config key
- [API Reference](../04-api/README.md) — method-level reference
