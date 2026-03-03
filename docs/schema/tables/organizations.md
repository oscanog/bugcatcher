# organizations

## Purpose

Stores organizations that group members and issues. Each organization has a single owner recorded directly on the table.

## Columns

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | `INT(11)` | No | auto increment | Primary key. |
| `name` | `VARCHAR(120)` | No | none | Organization display name. |
| `owner_id` | `INT(11)` | No | none | Current owner user id. |
| `created_at` | `TIMESTAMP` | No | `CURRENT_TIMESTAMP` | Organization creation time. |

## Keys and indexes

- Primary key: `id`
- Index: `idx_organizations_owner (owner_id)`

## Relationships

- Foreign key: `owner_id -> users.id ON DELETE CASCADE`
- Referenced by `org_members.org_id`
- Referenced by `issues.org_id`
- Referenced logically by `users.last_active_org_id`, but no foreign key exists for that column

## How the application uses it

- Organization creation inserts a row here, then inserts an `owner` membership into `org_members`.
- Organization ownership transfer updates both `organizations.owner_id` and membership roles.
- Deleting an organization removes related memberships and issues through cascading foreign keys.
- The PHP layer checks for global organization-name uniqueness before insert by comparing `LOWER(name)`.

## Known limitations

- There is no database-level unique constraint on `name`, even though the PHP layer treats organization names as globally unique.
- Ownership is stored in both `organizations.owner_id` and `org_members.role='owner'`, so the app must keep those in sync.
