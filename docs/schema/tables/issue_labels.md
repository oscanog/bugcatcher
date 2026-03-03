# issue_labels

## Purpose

Bridge table that links issues to labels.

## Columns

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| `issue_id` | `INT(11)` | No | none | Linked issue id. |
| `label_id` | `INT(11)` | No | none | Linked label id. |

## Keys and indexes

- Primary key: `(issue_id, label_id)`
- Index: `idx_issue_labels_label (label_id)`

## Relationships

- Foreign key: `issue_id -> issues.id ON DELETE CASCADE`
- Foreign key: `label_id -> labels.id ON DELETE CASCADE`

## How the application uses it

- `create_issue.php` inserts one row per selected label when an issue is created.
- `dashboard.php` filters issues by label through a subquery on this table.
- Deleting an issue cascades and removes its label links automatically.

## Known limitations

- The table only stores the current issue-label link. It does not track who applied the label or when.
