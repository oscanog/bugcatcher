# labels

## Purpose

Stores the global label catalog used to classify issues.

## Columns

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | `INT(11)` | No | auto increment | Primary key. |
| `name` | `VARCHAR(100)` | No | none | Label name. |
| `description` | `VARCHAR(255)` | Yes | `NULL` | Short description. |
| `color` | `VARCHAR(20)` | Yes | `#cccccc` | UI color token. |

## Keys and indexes

- Primary key: `id`

## Relationships

- Referenced by `issue_labels.label_id`

## How the application uses it

- Labels are loaded globally for the create-issue form and dashboard filtering.
- Reference labels are seeded from `infra/database/seed_reference_data.sql`.
- Labels are attached to issues through `issue_labels`.

## Known limitations

- There is no unique constraint on `name`, so duplicate global labels are possible.
- Labels are not scoped to organizations, so every organization sees the same label set.
