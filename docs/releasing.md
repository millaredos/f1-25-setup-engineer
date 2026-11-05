[Versión en Español](es-es/publicar-versiones.md)

# Release workflow

Use one `release/X.Y.Z` branch and one PR to `main` for the entire release. Add every Alpha increment to that PR. Keep its description current: it becomes the release notes.

## Alpha

The release pipeline runs when a same-repository release PR is opened, reopened or updated.

1. Check out the PR head commit and run domain, update, publication and browser tests.
2. Build `X.Y.Z-alpha.N`, where N is the workflow run number.
3. Create a draft prerelease with the exact source commit and PR identity.
4. Upload the reproducible update ZIP and verify its GitHub digest.
5. Publish the prerelease only if the PR is still open at the same commit.

The optional `ALPHA_BUILD_OFFSET` repository variable adds a non-negative integer to the workflow run number. Its default is zero. Use it when moving to a new repository so Alpha versions continue above previously distributed builds.

A newer commit supersedes an older run. Failed or incomplete builds are not offered to installations. Fork PRs and non-release branches cannot publish through this workflow.

## Stable release

Merge the release PR when it is ready. The pipeline tests the merge commit and prepares a draft for `vX.Y.Z`, with:

- The stable update ZIP.
- The PR description as release notes.
- Package metadata linking the version, commit and PR.

Review the draft and publish it as a stable release, marking it as latest. A merge alone does not make it available to the Release channel. Keep CHANGELOG updated separately; its application-documentation translation belongs in `docs/es-es/`. Release notes and commit messages stay in English.

Do not replace published packages or reuse version tags for different commits. A correction needs a new version. GitHub release immutability can enforce this after all assets are attached.

## Repository access

The workflow uses its GitHub-provided token with write access to release contents and read access to PRs. Installations read the fixed project repository; private repositories require their own read-only server token.

## Package identity

`src/VERSION` declares the development version, for example `0.3.0-alpha.0`. The branch must be `release/0.3.0`. The builder sets the installed version inside the package to the exact Alpha or stable version.

Each `f125-update-v2` manifest records:

- Version, channel, full commit SHA, PR number and release branch.
- Minimum PHP version and required database schema.
- SHA-256 for every application file.

A machine-readable marker in the release body links the GitHub asset to that identity. Installations verify both GitHub's archive digest and the manifest.

For a local test package:

```sh
python bin/package.py --pr 1 --branch release/0.3.0
```

This creates a package in ignored `dist/`; it does not publish anything.
