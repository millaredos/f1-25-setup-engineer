[Versión en Español](es-es/publicar-versiones.md)

# Releases

Maintain one `release/X.Y.Z` branch and PR per target version. Compatible fixes use a patch; features and substantial redesigns use a minor release. Before 1.0, incompatible changes require a new minor and an explicit upgrade procedure. Stable publication requires express approval.

## Change and commit categories

| Type | Meaning |
| --- | --- |
| `fix` / `bugfix` | Correct a defect. |
| `hotfix` | Urgent critical production fix. |
| `feat` / `feature` | Add user functionality. |
| `refactor` | Change structure without changing external behavior. |
| `chore` | Routine maintenance. |
| `docs` | Documentation-only changes. |
| `test` | Test changes. |
| `style` | Formatting without logic changes. |
| `perf` | Performance improvements. |
| `build` | Build system changes. |
| `ci` | Continuous integration changes. |
| `revert` | Undo a previous change. |

Describe changed or removed behavior explicitly rather than hiding it under refactor. Keep Unreleased separate from published versions. A local development version is not a published release.

## PHP pipeline

The workflow checks out the exact PR or merge commit using Git, installs PHP extensions, runs PHP tests and invokes `dev/bin/publish.php`. It uses no Node-based Actions or Python tools. Alpha numbering examines releases and tags for the target version. Alpha is published only if the PR still names the checked commit. A merged PR creates or verifies a stable draft; it does not publish it.

Documentation-only work must not trigger an Alpha. Use the PR marker `<!-- stable-release-only -->` when preparing only the stable draft; otherwise accumulate documentation with the next functional delivery.

Packages are `f125-update-v3`, including version, channel, commit, PR, branch, minimum PHP/schema and per-file SHA-256. GitHub confirms the uploaded ZIP digest before publication. Already published content cannot be replaced. Only complete published packages may be announced through the established channel procedure.

For a local test package, provide an explicit destination and source identity:

```sh
php dev/bin/package.php --version=0.5.0-alpha.1 --channel=alpha --commit=FULL_COMMIT_SHA --pr=1 --branch=release/0.5.0 --output=/ABSOLUTE/PATH/f125-update-0.5.0-alpha.1.zip
```

This command does not publish anything. The first move from the old application to MVC is manual; the previous updater cannot install the nested MVC package. Preserve private configuration, installation state, backups and MariaDB. No database data is deleted by the new application.
