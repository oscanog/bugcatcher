# issue_attachments

## Purpose

Stores uploaded file metadata for issue attachments.

## Columns

| Column | Type | Nullable | Default | Notes |
| --- | --- | --- | --- | --- |
| `id` | `INT(11)` | No | auto increment | Primary key. |
| `issue_id` | `INT(11)` | No | none | Linked issue id. |
| `file_path` | `VARCHAR(255)` | No | none | Relative storage path such as `uploads/issues/...`. |
| `original_name` | `VARCHAR(255)` | No | none | Sanitized original filename. |
| `mime_type` | `VARCHAR(100)` | No | none | Detected MIME type. |
| `file_size` | `INT(11)` | No | none | Stored file size in bytes. |
| `uploaded_at` | `DATETIME` | No | `CURRENT_TIMESTAMP` | Upload timestamp. |

## Keys and indexes

- Primary key: `id`
- Index: `idx_issue_attachments_issue (issue_id)`

## Relationships

- Foreign key: `issue_id -> issues.id ON DELETE CASCADE`

## How the application uses it

- `/zen/create_issue.php` uploads files into the configured issues upload directory and stores metadata here.
- Stored paths are generated through the upload helpers in `app/bootstrap.php`.
- `/zen/dashboard.php` reads attachment metadata for display and removes file records before deleting an issue.
- Deleting the parent issue cascades and removes attachment rows.

## Known limitations

- The database stores metadata only. File existence and path safety are enforced by PHP rather than by the database.
- There is no hash or deduplication field for detecting repeated uploads.
