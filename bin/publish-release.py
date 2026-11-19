"""Publish Alpha builds or prepare stable drafts for a same-repository release PR."""
import hashlib
import json
import os
from pathlib import Path
import re
import subprocess
import urllib.error
import urllib.request
from package import ROOT, build

REPOSITORY = "millaredos/f1-25-setup-engineer"


def api(method, path, data=None, binary=False):
    host = "uploads.github.com" if binary else "api.github.com"
    body = data if binary else (json.dumps(data).encode() if data is not None else None)
    request = urllib.request.Request(f"https://{host}/repos/{REPOSITORY}/{path}", data=body, method=method, headers={
        "Authorization": "Bearer " + os.environ["GH_TOKEN"],
        "Accept": "application/vnd.github+json",
        "Content-Type": "application/zip" if binary else "application/json",
        "X-GitHub-Api-Version": "2022-11-28", "User-Agent": "F125-release-pipeline",
    })
    try:
        with urllib.request.urlopen(request, timeout=60) as response:
            return json.load(response)
    except urllib.error.HTTPError as error:
        if error.code == 404 and method == "GET":
            return None
        raise RuntimeError(f"GitHub request failed ({error.code})") from None


def plan(event, run_number):
    pr = event["pull_request"]
    if pr["head"]["repo"]["full_name"] != REPOSITORY or pr["base"]["ref"] != "main":
        raise ValueError("Only same-repository release PRs may publish")
    branch = pr["head"]["ref"]
    if not re.fullmatch(r"release/\d+\.\d+\.\d+", branch):
        raise ValueError("Invalid release branch")
    stable = branch.removeprefix("release/")
    if (ROOT / "src/VERSION").read_text().strip().split("-alpha.")[0] != stable:
        raise ValueError("Branch and src/VERSION disagree")
    merged = event.get("action") == "closed" and pr.get("merged") is True
    if event.get("action") == "closed" and not merged:
        raise ValueError("An unmerged PR cannot publish")
    return {"version": stable if merged else f"{stable}-alpha.{run_number}",
            "channel": "release" if merged else "alpha",
            "commit": pr["merge_commit_sha"] if merged else pr["head"]["sha"],
            "pr": pr["number"], "branch": branch}


def alpha_number(stable, commit):
    """Reserve the first unused number for this version, including unpublished drafts."""
    pattern = re.compile(r"^v" + re.escape(stable) + r"-alpha\.([1-9]\d*)$")
    used = set()
    resume = []
    page = 1
    while True:
        releases = api("GET", f"releases?per_page=100&page={page}")
        if not isinstance(releases, list):
            raise ValueError("Cannot determine existing Alpha releases")
        for release in releases:
            match = pattern.fullmatch(release.get("tag_name", ""))
            if match:
                number = int(match[1])
                used.add(number)
                marker = re.search(r"<!-- f125-update (\{[^\r\n]+\}) -->", release.get("body") or "")
                if marker:
                    try:
                        build = json.loads(marker[1])
                    except (TypeError, ValueError):
                        continue
                    if isinstance(build, dict) and build.get("commit") == commit and build.get("branch") == "release/" + stable:
                        resume.append((number, release))
        if len(releases) < 100:
            break
        page += 1
    refs = api("GET", f"git/matching-refs/tags/v{stable}-alpha.")
    if refs is None:
        refs = []
    if not isinstance(refs, list):
        raise ValueError("Cannot determine existing Alpha tags")
    for ref in refs:
        name = ref.get("ref", "")
        match = pattern.fullmatch(name.removeprefix("refs/tags/"))
        if match:
            used.add(int(match[1]))
    if len(resume) > 1:
        raise ValueError("Multiple Alpha releases identify the same commit")
    if resume:
        return resume[0]
    number = 1
    while number in used:
        number += 1
    return number, None


def publish():
    event = json.loads(Path(os.environ["GITHUB_EVENT_PATH"]).read_text())
    selected = plan(event, int(os.environ["GITHUB_RUN_NUMBER"]))
    actual = subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT, text=True).strip()
    if actual != selected["commit"]:
        raise ValueError("Checked-out commit is not the requested build")
    pr = api("GET", f"pulls/{selected['pr']}")
    if selected["channel"] == "alpha":
        active = api("GET", "pulls?state=open&base=main&per_page=100") or []
        release_prs = [item for item in active if item["head"]["repo"]["full_name"] == REPOSITORY
                       and re.fullmatch(r"release/\d+\.\d+\.\d+", item["head"]["ref"])]
        if len(release_prs) != 1 or release_prs[0]["number"] != selected["pr"]:
            raise ValueError("Keep exactly one open release PR")
        if pr["state"] != "open" or pr["head"]["sha"] != selected["commit"]:
            print("A newer commit superseded this Alpha build; no release published.")
            return
    elif not pr.get("merged"):
        raise ValueError("The release PR has not been merged")
    reserved_release = None
    if selected["channel"] == "alpha":
        stable = selected["branch"].removeprefix("release/")
        number, reserved_release = alpha_number(stable, selected['commit'])
        selected["version"] = f"{stable}-alpha.{number}"
    package = build(**selected)
    digest = hashlib.sha256(package.read_bytes()).hexdigest()
    notes = pr.get("body") or pr["title"]
    marker = "<!-- f125-update " + json.dumps({key: selected[key] for key in ("channel", "commit", "pr", "branch")}, separators=(",", ":")) + " -->"
    tag = "v" + selected["version"]
    existing = reserved_release or api("GET", f"releases/tags/{tag}")
    if existing is not None:
        if existing["target_commitish"] != selected["commit"]:
            raise ValueError("That version already identifies another commit")
        if not existing["draft"]:
            assets = [asset for asset in existing["assets"] if asset["name"] == package.name]
            if len(assets) == 1 and assets[0].get("digest") == "sha256:" + digest:
                print("This exact build is already published.")
                return
            raise ValueError("Published release content cannot be replaced")
    release = existing or api("POST", "releases", {
        "tag_name": tag, "target_commitish": selected["commit"],
        "name": ("Alpha " if selected["channel"] == "alpha" else "Release ") + selected["version"],
        "body": notes + "\n\n" + marker, "draft": True,
        "prerelease": selected["channel"] == "alpha", "make_latest": "false",
    })
    assets = [asset for asset in release["assets"] if asset["name"] == package.name]
    if assets:
        if len(assets) != 1 or assets[0].get("digest") != "sha256:" + digest:
            raise ValueError("Draft asset does not match the reproducible package")
        asset = assets[0]
    else:
        asset = api("POST", f"releases/{release['id']}/assets?name={package.name}", package.read_bytes(), binary=True)
    if asset.get("digest") != "sha256:" + digest or asset.get("state") != "uploaded":
        raise ValueError("GitHub did not confirm the complete package digest")
    if selected["channel"] == "alpha":
        latest = api("GET", f"pulls/{selected['pr']}")
        if latest["state"] == "open" and latest["head"]["sha"] == selected["commit"]:
            api("PATCH", f"releases/{release['id']}", {"draft": False, "prerelease": True, "make_latest": "false"})
            print("Published " + tag)
        else:
            print("Superseded Alpha left as an unpublished draft.")
    else:
        print("Stable draft prepared: review its assets and notes, then publish it on GitHub.")


if __name__ == "__main__":
    publish()
