# Architecture

How **laravel-config-webhook** is structured and how a payload travels from a domain event to
a signed, logged HTTP delivery.

## Overview

The package is deliberately **app-agnostic**: it knows nothing about your domain. Hosts register
an event catalogue (via config or at runtime) and either dispatch payloads manually or map their
own domain events to webhook types. Every delivery is HMAC-signed, retried with exponential
backoff, and recorded in a delivery log.

## Table of Contents

### [1. Overview](01-overview.md)

The core idea, the two ways to dispatch, and the decoupling rules that keep the package
host-independent.

### [2. Components](02-components.md)

The classes that make up the package — manager, models, job, signing, events, command, and the
optional Livewire UI — and what each is responsible for.

### [3. Delivery Lifecycle](03-delivery-lifecycle.md)

What happens on each attempt: signing, the HTTP request, success/failure handling, the manual
retry loop, and exponential backoff.

## Related Documentation

- [Development](../02-development/README.md) — use the pieces described here
- [API Reference](../04-api/README.md) — method-level reference for the manager and signing
- [Configuration](../03-configuration/README.md) — tune the behaviour described here
