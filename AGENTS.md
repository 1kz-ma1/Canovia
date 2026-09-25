# Canovia Agent Instructions

## Repository workflow

- Treat the latest `main` of `1kz-ma1/Canovia` as the implementation source of truth.
- Before implementation, inspect the latest `main` and relevant specifications.
- **Do not push implementation or fixes directly to `main`.**
- Create a dedicated `feature/*`, `fix/*`, or equivalent branch.
- Complete implementation and verification on that branch.
- Create a Pull Request targeting `main`.
- Stop at PR creation. The user performs the normal merge manually.
- Direct `main` changes are allowed only when the user explicitly authorizes that specific exception.

See `docs/CANOVIA_DEVELOPMENT_WORKFLOW.md` for the full workflow.
