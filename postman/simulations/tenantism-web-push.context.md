# Tenantism web push simulation

Created and run locally on 2026-10-27. The mock models the Firebase HTTP v1/OAuth/error subset linked in `docs/WEB-PUSH-NOTIFICATIONS.md`. No connected Postman account was available to retrieve an owning-workspace mock. Nothing was published.

## Purpose

Run the actual PHP FCM client and delivery service over HTTP against `../mocks/firebase-web-push/`, including valid, unregistered, quota and temporarily unavailable device tokens. Request/response shapes and scenario token prefixes live in its handler.

## Execution

Start `postman simulation run postman/simulations/tenantism-web-push.sim.yaml --port 4900 --output ndjson`. Check its listening event for the selected address. Run `TENANTISM_FCM_SIMULATION_BASE=http://127.0.0.1:4900/firebase php artisan test tests/Integration/FcmSimulationTest.php --filter=test_real_fcm_http`. Inspect request events, then stop the simulator with Ctrl-C. Keep logs outside the repository.

## Assumptions

- Generated test RSA credentials and an in-memory database are used; real Firebase credentials/tokens must never be used with this mock. Observed 2026-10-27 in the integration test.
- The mock checks JWT shape and request fields but does not validate Google IAM/project permissions or cryptographic signatures. Browser/OS delivery and permission behavior still need live verification. Reasoned 2026-10-27 from the handler.
- Run the simulator and the PHP test with local loopback access permitted. Restricted execution can silently reach only the retry path. Observed 2026-10-27.

## Observations

<!-- begin:observations -->
2026-10-27: The Firebase member served OAuth plus success, unregistered, temporary-failure and quota responses; no member stayed silent. The real integration test passed, including skipping accepted devices and bounding retries. No real Google service was contacted.
<!-- end:observations -->

## What didn't work

- 2026-10-27: Restricted execution returned `listen EPERM` for the simulator and prevented the PHP client reaching loopback. Running these local checks with the appropriate execution permission resolved it.
- 2026-10-27: Handwritten mock manifests need `version: 1` and `mockSrc`; an `entry` field produced `Manifest must specify mockSrc`. The committed manifest fixes this.
