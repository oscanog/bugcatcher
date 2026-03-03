# Issue Workflow

This file documents the issue lifecycle enforced by the PHP application. The database stores the state, but the allowed transitions are checked in code rather than by database constraints.

## Current status fields

`issues.status` values used by the app:

- `open`
- `closed`

`issues.assign_status` values observed in PHP:

- `unassigned`
- `rejected`
- `with_senior`
- `with_junior`
- `done_by_junior`
- `with_qa`
- `with_senior_qa`
- `with_qa_lead`
- `approved`
- `closed`

## Lifecycle steps

### 1. Issue creation

- Actor: any authenticated user in the active organization.
- Main columns written:
  - `title`
  - `description`
  - `author_id`
  - `org_id`
- Default state after insert:
  - `status='open'`
  - `assign_status='unassigned'`

### 2. Project Manager assigns a Senior Developer

- Actor: organization member with role `Project Manager`.
- Preconditions:
  - issue is in the same organization
  - `assign_status` is `unassigned` or `rejected`
  - selected assignee is an `org_members` row with role `Senior Developer`
- Columns changed:
  - `pm_id`
  - `assigned_dev_id`
  - `assigned_at`
  - `assign_status='with_senior'`
  - workflow assignee and timestamp columns for later stages are cleared on reassignment

### 3. Senior Developer assigns a Junior Developer

- Actor: organization member with role `Senior Developer`.
- Preconditions:
  - issue is assigned to that senior developer
  - `assigned_junior_id IS NULL`
  - selected assignee is an `org_members` row with role `Junior Developer`
- Columns changed:
  - `assigned_junior_id`
  - `junior_assigned_at`
  - `assign_status='with_junior'`

### 4. Junior Developer marks work done

- Actor: organization member with role `Junior Developer`.
- Preconditions:
  - issue is `open`
  - issue is assigned to that junior developer
  - `assign_status='with_junior'`
- Columns changed:
  - `junior_done_at`
  - `assign_status='done_by_junior'`

### 5. Senior Developer assigns a QA Tester

- Actor: organization member with role `Senior Developer`.
- Preconditions:
  - issue is `open`
  - issue is assigned to that senior developer
  - `assign_status='done_by_junior'`
  - `assigned_qa_id IS NULL`
  - selected assignee is an `org_members` row with role `QA Tester`
- Columns changed:
  - `assigned_qa_id`
  - `qa_assigned_at`
  - `assign_status='with_qa'`

### 6. QA Tester reports to Senior QA

- Actor: organization member with role `QA Tester`.
- Preconditions:
  - issue is `open`
  - issue is assigned to that QA tester
  - `assign_status='with_qa'`
  - `assigned_senior_qa_id IS NULL`
  - selected assignee is an `org_members` row with role `Senior QA`
- Columns changed:
  - `assigned_senior_qa_id`
  - `senior_qa_assigned_at`
  - `assign_status='with_senior_qa'`

### 7. Senior QA reports to QA Lead

- Actor: organization member with role `Senior QA`.
- Preconditions:
  - issue is `open`
  - issue is assigned to that senior QA
  - `assign_status='with_senior_qa'`
  - `assigned_qa_lead_id IS NULL`
  - selected assignee is an `org_members` row with role `QA Lead`
- Columns changed:
  - `assigned_qa_lead_id`
  - `qa_lead_assigned_at`
  - `assign_status='with_qa_lead'`

### 8. QA Lead approves or rejects

- Actor: organization member with role `QA Lead`.

Approve path:

- Preconditions:
  - issue is `open`
  - issue is assigned to that QA Lead
  - `assign_status='with_qa_lead'`
- Columns changed:
  - `assign_status='approved'`

Reject path:

- Preconditions:
  - issue is `open`
  - issue is assigned to that QA Lead
  - `assign_status='with_qa_lead'`
- Columns changed:
  - `assign_status='rejected'`
  - `assigned_dev_id`
  - `assigned_junior_id`
  - `assigned_qa_id`
  - `assigned_senior_qa_id`
  - `assigned_qa_lead_id`
  - `assigned_at`
  - `junior_assigned_at`
  - `junior_done_at`
  - `qa_assigned_at`
  - `senior_qa_assigned_at`
  - `qa_lead_assigned_at`
- Effect:
  - the Project Manager can start the cycle again

### 9. Project Manager closes an approved issue

- Actor: organization member with role `Project Manager`.
- Preconditions:
  - issue is `open`
  - `assign_status='approved'`
- Columns changed:
  - `status='closed'`
  - `assign_status='closed'`

## Enforcement note

The allowed workflow states above are not constrained by an `ENUM`, lookup table, or check constraint in `schema.sql`. They are enforced in PHP, mainly in `dashboard.php`.
