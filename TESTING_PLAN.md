# Testing Improvement Plan

This package currently provides a single command and a minimal set of Pest tests focusing on command options. To improve confidence in the package we will extend the test suite to cover the internal helper methods and file-copy behaviour.

## Goals

1. Verify that helper methods such as `installPackages`, `getDistFiles` and `copyFile` work as expected.
2. Ensure that running the command copies files correctly and respects the `--force` option.
3. Provide utilities for invoking protected methods inside tests.

## Steps

1. Add a helper in `tests/Pest.php` allowing reflection access to protected methods.
2. Create new feature tests covering:
   - Retrieval of files from the `dist/` directory via `getDistFiles`.
   - Correct command construction within `installPackages`.
   - File copy behaviour through `copyFile`.
   - End‑to‑end copying of files when executing `artisan laravel:omakase --files` including skip and force behaviour.
3. Run the test suite to ensure all tests pass.

