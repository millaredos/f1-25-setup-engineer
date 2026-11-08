"""Offline tests for reproducible packages and the PR publication gate."""
import contextlib
import hashlib
import importlib.util
import io
import json
from pathlib import Path
import sys
import tempfile
import unittest
from unittest.mock import patch
import zipfile

sys.path.insert(0, str(Path(__file__).resolve().parent.parent / "bin"))
import package
spec = importlib.util.spec_from_file_location("publish_release", package.ROOT / "bin/publish-release.py")
publisher = importlib.util.module_from_spec(spec)
spec.loader.exec_module(publisher)

SHA = "a" * 40
MERGED = "b" * 40
BASE = (package.ROOT / "src/VERSION").read_text().strip().split("-alpha.")[0]


def event(merged=False):
    return {"action": "closed" if merged else "synchronize", "pull_request": {
        "number": 1, "title": "Release " + BASE, "body": "Complete release summary.", "state": "closed" if merged else "open",
        "merged": merged, "merge_commit_sha": MERGED, "head": {
            "ref": "release/" + BASE, "sha": SHA, "repo": {"full_name": publisher.REPOSITORY}},
        "base": {"ref": "main"}}}


class PackagingTests(unittest.TestCase):
    def test_reproducible_complete_package(self):
        path = package.build("0.3.0-alpha.991", "alpha", SHA, 1, "release/0.3.0")
        try:
            first = path.read_bytes()
            self.assertEqual(first, package.build("0.3.0-alpha.991", "alpha", SHA, 1, "release/0.3.0").read_bytes())
            with zipfile.ZipFile(path) as archive:
                manifest = json.loads(archive.read("manifest.json"))
                self.assertEqual(manifest["build"]["commit"], SHA)
                self.assertEqual(manifest["build"]["pr"], 1)
                self.assertEqual(archive.read("src/VERSION"), b"0.3.0-alpha.991\n")
                self.assertEqual(manifest["requires"]["schema"], 1)
                for required in ['src/Migrations.php', 'src/migration_catalog.php', 'public_html/database.php']:
                    self.assertIn(required, manifest['files'])
                for name, digest in manifest["files"].items():
                    self.assertTrue(name.startswith(("src/", "data/", "public_html/")))
                    self.assertEqual(hashlib.sha256(archive.read(name)).hexdigest(), digest)
                self.assertEqual(set(archive.namelist()), {"manifest.json", *manifest["files"]})
        finally:
            path.unlink()

    def test_version_and_channel_must_agree(self):
        for version, channel in [("main", "release"), ("0.3.0-beta.1", "alpha"), ("0.3.0", "alpha"), ("0.3.0-alpha.1", "release")]:
            with self.subTest(version=version, channel=channel), self.assertRaises(ValueError):
                package.build(version, channel, SHA, 1, "release/0.3.0")

    def test_release_uses_merge_commit(self):
        self.assertEqual(publisher.plan(event(True), 12), {"version": BASE, "channel": "release", "commit": MERGED, "pr": 1, "branch": "release/" + BASE})

    def test_alpha_uses_pr_head(self):
        self.assertEqual(publisher.plan(event(), 12)["version"], BASE + "-alpha.12")
        self.assertEqual(publisher.plan(event(), 12)["commit"], SHA)

    def test_fork_closed_unmerged_and_wrong_branch_rejected(self):
        for case in ["fork", "closed", "branch", "base"]:
            current = event()
            if case == "fork":
                current["pull_request"]["head"]["repo"]["full_name"] = "someone/fork"
            elif case == "closed":
                current["action"] = "closed"
            elif case == "branch":
                current["pull_request"]["head"]["ref"] = "feature/test"
            else:
                current["pull_request"]["base"]["ref"] = "develop"
            with self.subTest(case=case), self.assertRaises(ValueError):
                publisher.plan(current, 1)

    def publication(self, merged=False, stale=False):
        current = event(merged)
        calls = []
        with tempfile.TemporaryDirectory(prefix="f125-publish-") as directory:
            source = Path(directory) / "event.json"
            source.write_text(json.dumps(current))
            archive = Path(directory) / "package.zip"
            archive.write_bytes(b"test package bytes")
            digest = "sha256:" + hashlib.sha256(archive.read_bytes()).hexdigest()

            def fake_api(method, path, data=None, binary=False):
                calls.append((method, path, data))
                pr = dict(current["pull_request"])
                if stale:
                    pr["head"] = {**pr["head"], "sha": "c" * 40}
                if method == "GET" and path == "pulls/1":
                    return pr
                if method == "GET" and path.startswith("pulls?"):
                    return [pr]
                if method == "GET" and path.startswith("releases/tags/"):
                    return None
                if method == "POST" and path == "releases":
                    return {"id": 123, "assets": [], **data}
                if method == "POST" and path.startswith("releases/123/assets"):
                    return {"digest": digest, "state": "uploaded"}
                if method == "PATCH":
                    return data
                raise AssertionError((method, path))

            with patch.dict("os.environ", {"GITHUB_EVENT_PATH": str(source), "GITHUB_RUN_NUMBER": "12"}), \
                    patch.object(publisher, "api", fake_api), patch.object(publisher, "build", return_value=archive), \
                    patch.object(publisher.subprocess, "check_output", return_value=MERGED + "\n" if merged else SHA + "\n"), \
                    contextlib.redirect_stdout(io.StringIO()):
                publisher.publish()
        return calls

    def test_alpha_published_only_after_upload(self):
        calls = self.publication()
        self.assertEqual(calls[-1][0], "PATCH")
        self.assertEqual(calls[-1][2], {"draft": False, "prerelease": True, "make_latest": "false"})
        body = next(data for method, path, data in calls if method == "POST" and path == "releases")
        self.assertIn("Complete release summary.", body["body"])
        self.assertIn('"commit":"' + SHA + '"', body["body"])
        self.assertTrue(body["draft"])

    def test_merge_prepares_stable_draft_without_publishing(self):
        calls = self.publication(merged=True)
        self.assertFalse(any(method == "PATCH" for method, _, _ in calls))
        body = next(data for method, path, data in calls if method == "POST" and path == "releases")
        self.assertTrue(body["draft"])
        self.assertFalse(body["prerelease"])
        self.assertEqual(body["target_commitish"], MERGED)

    def test_superseded_alpha_does_not_publish(self):
        calls = self.publication(stale=True)
        self.assertFalse(any(method in {"POST", "PATCH"} for method, _, _ in calls))


if __name__ == "__main__":
    unittest.main()
