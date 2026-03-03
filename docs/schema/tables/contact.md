# contact

## Purpose

Stores contact-form style messages as standalone rows.

## Columns

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | `INT(11)` | No | auto increment | Primary key. |
| `name` | `VARCHAR(255)` | No | none | Sender name. |
| `email` | `VARCHAR(255)` | No | none | Sender email address. |
| `subject` | `VARCHAR(255)` | No | none | Message subject. |
| `message` | `VARCHAR(255)` | No | none | Message body. |

## Keys and indexes

- Primary key: `id`

## Relationships

- None

## How the application uses it

- The table is defined in `infra/database/schema.sql`.
- The current PHP application in this repository does not appear to read from or write to this table.

## Known limitations

- `message` is limited to `VARCHAR(255)`.
- There is no `created_at` column.
- Because the app does not appear to use it, this table may be legacy or planned functionality rather than an active feature.
