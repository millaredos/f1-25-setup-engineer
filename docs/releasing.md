[Versión en Español](es-es/publicar-versiones.md)

# Release workflow

Use one `release/X.Y.Z` branch and one PR to `main` for the entire release. Add every Alpha increment to that PR. Keep its description current: it becomes the release notes.

## Version policy

Versions use `MAJOR.MINOR.PATCH`, with `-alpha.N` for test builds. The current release target is `0.3.1`; its Alpha builds use `0.3.1-alpha.N`. Their build number is an identifier, not a readiness score.

| Change after a stable release | Next target from `0.3.0` | Examples |
| --- | --- | --- |
| Compatible correction or small presentation adjustment | `0.3.1` | Fix a failed save, incorrect label or layout defect. |
| New functionality or substantial interface/workflow redesign | `0.4.0` | Add session comparison or redesign how users prepare and test setups. |
| Incompatible change | New minor while below `1.0.0`; new major from `1.0.0` onward | Change an import contract or require a migration. Document compatibility and recovery before publication. |
| Documentation or development housekeeping only | No application version | Update a guide or remove ignored local notes. |

A new minor resets PATCH to zero. Corrections made before the target's stable publication remain Alpha builds of that target. They do not turn `0.3.0-alpha.N` into `0.3.1`. Choose the next target before opening its release branch and PR. Work that belongs to a later target waits for that PR.

The first `1.0.0` is an explicit milestone: the core workflow and supported data contracts are considered stable. It is not produced automatically after a particular number of minor releases. The updater rejects a different base schema version. Compatible additive catalog migrations are distributed in the package and applied separately through the browser; see [database migrations](database-migrations.md).

## When an Alpha becomes stable

Stable publication requires all of the following:

1. The agreed scope is complete and there are no known blocking defects, including data loss, broken save/import or failed updates.
2. Relevant automated checks pass on the final commit, and changed user flows have been exercised in a real installation. Review any remaining verification limits.
3. The PR summary, changelog, English guides and Spanish counterparts describe the shipped behavior. State any upgrade or compatibility requirements.
4. The maintainer explicitly approves closing the release and publishing it as stable. Time elapsed, a successful Alpha update or the number of Alpha builds does not constitute approval.

After approval, merge the release PR. CI tests the merge commit and prepares a stable draft. Review the resulting package and publish the draft as the latest stable release. Only publication makes `0.3.1` available on the Estable channel. The Alpha channel can also advance to that stable version; the selected channel stays unchanged.

Keep the current PR open while testing `0.3.1-alpha.N`. After publishing `0.3.1`, choose a new target and PR according to the table. Do not merge a release PR just to publish another Alpha.

## Changes that do not publish an Alpha

Changes only to README, CHANGELOG, guides, repository housekeeping or ignored local files do not justify an application build. Batch documentation changes with the next application change. A mixture of application and documentation changes produces one Alpha after checks pass. User-visible application text is application code; changing the home screen's content can justify an Alpha.

The current workflow reacts to every release PR update and does not yet filter documentation-only pushes. Until that filter is implemented, keep documentation-only work local for the next application push. Removing an ignored file requires no Git commit or push.

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

Review the draft and publish it as a stable release, marking it as latest. A merge alone does not make it available to the Estable channel. Keep CHANGELOG updated separately; its application-documentation translation belongs in `docs/es-es/`. Release notes and commit messages stay in English.

Do not replace published packages or reuse version tags for different commits. A correction needs a new version. GitHub release immutability can enforce this after all assets are attached.

## Repository access

The workflow uses its GitHub-provided token with write access to release contents and read access to PRs. Installations read the fixed project repository; private repositories require their own read-only server token.

## Package identity

`src/VERSION` declares the development version, for example `0.3.1-alpha.0`. The branch must be `release/0.3.1`. The builder sets the installed version inside the package to the exact Alpha or stable version.

Each `f125-update-v2` manifest records:

- Version, channel, full commit SHA, PR number and release branch.
- Minimum PHP version and required database schema.
- SHA-256 for every application file.

A machine-readable marker in the release body links the GitHub asset to that identity. Installations verify both GitHub's archive digest and the manifest.

For a local test package:

```sh
python bin/package.py --pr 1 --branch release/0.3.1
```

This creates a package in ignored `dist/`; it does not publish anything.
