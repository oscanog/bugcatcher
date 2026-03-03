# Future Design Direction

This file describes a likely direction for a larger schema redesign. It is intentionally conceptual and does not define a migration plan.

## Candidate future-state model

Possible additions:

- `issue_assignments` to store which user held which workflow role on an issue
- `issue_workflow_events` to record transitions, timestamps, and actors
- an optional workflow-state lookup table for valid issue workflow states
- an optional role lookup table if organization roles need to become data-driven

## Why the current `issues` table is hard to extend

- It stores one column per workflow role, so adding new roles widens the table.
- It stores many workflow timestamps directly on the issue row, which mixes stable issue data with workflow history.
- Most workflow integrity is enforced in PHP rather than by the database.
- It cannot represent multiple assignment cycles or a full audit trail cleanly.

## What a redesign would improve

- Full assignment history instead of only the current assignee per role
- Cleaner state transitions with a smaller, more stable `issues` table
- Better auditability for approval, rejection, and reassignment flows
- Easier support for future workflow changes without repeatedly altering `issues`

## Scope note

This redesign direction is larger than a documentation task or a small migration. It would require coordinated schema, query, and application changes.
