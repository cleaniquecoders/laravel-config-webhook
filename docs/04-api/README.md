# API Reference

Method-level reference for the public surface of the package.

## Overview

Almost everything goes through the `ConfigWebhook` manager (exposed via the facade). Signing is
also available directly through the stateless `WebhookSignature` helper. This section documents
the manager methods, the signing helper, and the exact bytes a subscriber receives.

## Table of Contents

### [1. Manager (`ConfigWebhook`)](01-manager.md)

Register events, map domain events, dispatch payloads, and inspect the catalogue.

### [2. Signatures (`WebhookSignature`)](02-signatures.md)

Generate and verify HMAC signatures, including how to verify on the receiving side.

### [3. Payload & Headers](03-payload-and-headers.md)

The JSON envelope and the HTTP headers sent on every delivery.

## Related Documentation

- [Architecture: Components](../01-architecture/02-components.md) — where these classes live
- [Usage](../02-development/02-usage.md) — task-oriented examples
- [Configuration](../03-configuration/01-reference.md) — keys that affect these APIs
