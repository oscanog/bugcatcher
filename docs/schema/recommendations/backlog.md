# Schema Recommendation Backlog

These recommendations are based on the current schema in `infra/database/schema.sql` and the current runtime behavior in the PHP application.

## P0

### Add missing foreign keys for user references

Problem:
The schema uses several columns as user references without database-level foreign keys.

Why it matters:
Rows can keep stale references after a user or organization is deleted, and the database cannot help enforce consistency.

Affected tables and queries:
- `users.last_active_org_id`
- `issues.assigned_dev_id`
- `issues.assigned_junior_id`
- `issues.assigned_qa_id`
- `issues.assigned_senior_qa_id`
- `issues.assigned_qa_lead_id`
- `issues.pm_id`
- login restoration in `rainier/login.php`
- assignment and workflow queries in `/zen/dashboard.php`

Suggested migration direction:
- Add `users.last_active_org_id -> organizations.id ON DELETE SET NULL`
- Add `issues.assigned_dev_id -> users.id ON DELETE SET NULL`
- Add `issues.assigned_junior_id -> users.id ON DELETE SET NULL`
- Add `issues.assigned_qa_id -> users.id ON DELETE SET NULL`
- Add `issues.assigned_senior_qa_id -> users.id ON DELETE SET NULL`
- Add `issues.assigned_qa_lead_id -> users.id ON DELETE SET NULL`
- Add `issues.pm_id -> users.id ON DELETE SET NULL`

### Add missing workflow indexes on `issues`

Problem:
The application filters by several assignee columns that are not indexed.

Why it matters:
Issue-list queries and stage-specific dashboards will become slower as the `issues` table grows.

Affected tables and queries:
- `issues.assigned_junior_id`
- `issues.assigned_senior_qa_id`
- `issues.assigned_qa_lead_id`
- `issues.pm_id`
- role-scoped issue queries in `/zen/dashboard.php`

Suggested migration direction:
- Add indexes for `assigned_junior_id`
- Add indexes for `assigned_senior_qa_id`
- Add indexes for `assigned_qa_lead_id`
- Add an index for `pm_id`

### Remove the redundant `uniq_org_user` key

Problem:
`org_members` defines both a composite primary key on `(org_id, user_id)` and a separate unique key on the same columns.

Why it matters:
The extra index adds maintenance overhead without adding protection the primary key does not already provide.

Affected tables and queries:
- `org_members`

Suggested migration direction:
- Drop `uniq_org_user`
- Keep the composite primary key as the single uniqueness rule for organization membership

## P1

### Constrain `issues.assign_status` to the actual workflow states

Problem:
The app treats `assign_status` as a finite state machine, but the column is an unconstrained `VARCHAR(20)`.

Why it matters:
Unexpected strings can be inserted manually or by future code changes, which would break workflow assumptions.

Affected tables and queries:
- `issues.assign_status`
- workflow transitions in `/zen/dashboard.php`

Suggested migration direction:
- Constrain the column to the current observed set:
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
- Prefer a lookup table or other explicit state model over a bare `VARCHAR` if more workflow changes are expected

### Resolve the overlap between `status='closed'` and `assign_status='closed'`

Problem:
Closure is represented in two different columns.

Why it matters:
The model duplicates state and increases the risk of inconsistent rows.

Affected tables and queries:
- `issues.status`
- `issues.assign_status`
- close flow in `/zen/dashboard.php`

Suggested migration direction:
- Keep open or closed in `status`
- Keep workflow progress in `assign_status`
- In a future migration, remove `closed` from `assign_status` and convert close checks to use `status`

### Add a unique constraint on `labels.name`

Problem:
Labels are global and seed-driven, but nothing prevents duplicate names.

Why it matters:
Duplicate label names would make filtering and maintenance ambiguous.

Affected tables and queries:
- `labels`
- label selection in `/zen/create_issue.php`
- label filtering in `/zen/dashboard.php`
- seed data in `infra/database/seed_reference_data.sql`

Suggested migration direction:
- Add a unique constraint on `labels.name`
- Clean up duplicates first if any exist

### Add a database-level uniqueness rule for organization names

Problem:
The PHP layer checks for case-insensitive uniqueness on organization names, but the database does not enforce it.

Why it matters:
Concurrent inserts or manual SQL changes can bypass the current application-level rule.

Affected tables and queries:
- `organizations.name`
- organization creation flow in `/zen/organization.php`

Suggested migration direction:
- Add a unique constraint on `organizations.name`
- Under the current case-insensitive collation, this matches the existing PHP behavior

## P2

### Normalize issue assignments and workflow history

Problem:
The current `issues` table stores one assignee column and one timestamp column per workflow role.

Why it matters:
Every new role or step makes the table wider, and the current shape cannot keep a real history of transitions.

Affected tables and queries:
- `issues`
- nearly all workflow logic in `/zen/dashboard.php`

Suggested migration direction:
- Introduce a normalized assignment or workflow-event model
- Move historical transitions out of the main `issues` row
- Keep only stable issue attributes on `issues`

### Revisit `org_members.role` if role definitions need to change often

Problem:
Organization roles are encoded as an enum.

Why it matters:
Changing role names or adding roles requires a schema migration and coordinated code updates.

Affected tables and queries:
- `org_members.role`
- permission checks in `/zen/dashboard.php` and `/zen/organization.php`

Suggested migration direction:
- Keep the enum if the role list is stable
- Move to a lookup table only if roles are expected to change frequently or become configurable

### Expand `contact` if the feature is kept

Problem:
The `contact` table has a short `message` field and no timestamp.

Why it matters:
It is not well-shaped for a real contact or support inbox workflow.

Affected tables and queries:
- `contact`

Suggested migration direction:
- Change `message` from `VARCHAR(255)` to `TEXT`
- Add `created_at`
- Consider whether the table should remain in the production schema if the feature is unused
