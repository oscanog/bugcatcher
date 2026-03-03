# org_members

## Purpose

Bridge table that records which users belong to which organizations and what organization-specific role each member has.

## Columns

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| `org_id` | `INT(11)` | No | none | Organization id. |
| `user_id` | `INT(11)` | No | none | User id. |
| `role` | `ENUM('owner','member','Project Manager','QA Lead','Senior Developer','Senior QA','Junior Developer','QA Tester')` | No | `member` | Organization workflow role. |
| `joined_at` | `TIMESTAMP` | No | `CURRENT_TIMESTAMP` | Membership creation time. |

## Keys and indexes

- Primary key: `(org_id, user_id)`
- Unique key: `uniq_org_user (org_id, user_id)`
- Index: `idx_org_members_user (user_id)`

## Relationships

- Foreign key: `org_id -> organizations.id ON DELETE CASCADE`
- Foreign key: `user_id -> users.id ON DELETE CASCADE`

## How the application uses it

- Membership is checked before issue creation, issue assignment, organization administration, and organization switching.
- The `role` column drives most workflow permissions in `dashboard.php`.
- The owner is also stored as a membership row with `role='owner'`.
- Users can belong to many organizations, but only once per organization.

## Known limitations

- `uniq_org_user` duplicates the same uniqueness already guaranteed by the composite primary key.
- The role list is embedded as an enum, which makes role changes a schema change rather than a data change.
