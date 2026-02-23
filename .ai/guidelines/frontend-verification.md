# Frontend verification (resources/js)

## When this guideline applies

If a task edits, adds, removes, or renames any file under:

- `resources/js/**`

## Required checks before finishing the task

Run the following commands (via Sail if you’re working inside Docker):

- `vendor/bin/sail npm run lint`
- `vendor/bin/sail npm run format`

## Completion criteria

- `npm run lint` completes with no errors.
- `npm run format` completes successfully.
- If formatting changes files, those changes must be included in the final patch/commit.

## If a command fails

1. Fix the reported issues.
2. Re-run:
    - `vendor/bin/sail npm run format`
    - `vendor/bin/sail npm run lint`

## Notes

- If no files in `resources/js/**` were changed, this guideline is not required unless the task explicitly asks for lint/format.
