"""Build a reproducible updater ZIP without user configuration."""
from pathlib import Path
import argparse
import hashlib
import json
import re
import subprocess
import zipfile
from translations import build as build_translations
from migrations import build as build_migrations

ROOT = Path(__file__).resolve().parent.parent


def build(version=None, channel=None, commit=None, pr=0, branch=None):
    build_translations(check=True)
    build_migrations(check=True)
    version = version or (ROOT / "src/VERSION").read_text().strip()
    if not re.fullmatch(r"(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-alpha\.(0|[1-9]\d*))?", version):
        raise ValueError("Invalid package version")
    channel = channel or ("alpha" if "-alpha." in version else "release")
    if (channel == "alpha") != ("-alpha." in version):
        raise ValueError("Channel and version disagree")
    commit = commit or subprocess.check_output(["git", "rev-parse", "HEAD"], cwd=ROOT, text=True).strip()
    if not re.fullmatch(r"[a-f0-9]{40}", commit):
        raise ValueError("Invalid source commit")
    branch = branch or subprocess.check_output(["git", "branch", "--show-current"], cwd=ROOT, text=True).strip()
    files = sorted([*ROOT.glob("public_html/*.php"), *ROOT.glob("public_html/assets/*.css"),
                    *ROOT.glob("public_html/assets/*.js"), *ROOT.glob("src/*.php"),
                    ROOT / "src/VERSION", ROOT / "data/parameters.json", ROOT / "data/symptoms.json"])
    contents = {path.relative_to(ROOT).as_posix(): path.read_bytes() for path in files}
    contents["src/VERSION"] = (version + "\n").encode()
    manifest = {"format": "f125-update-v2", "version": version,
                "build": {"channel": channel, "commit": commit, "pr": pr, "branch": branch},
                "requires": {"php": 80000, "schema": 1},
                "files": {name: hashlib.sha256(data).hexdigest() for name, data in contents.items()}}
    output = ROOT / "dist" / f"f125-update-{version}.zip"
    output.parent.mkdir(exist_ok=True)
    with zipfile.ZipFile(output, "w", zipfile.ZIP_DEFLATED) as package:
        for name, data in {"manifest.json": json.dumps(manifest, sort_keys=True).encode(), **contents}.items():
            entry = zipfile.ZipInfo(name, date_time=(1980, 1, 1, 0, 0, 0))
            entry.compress_type = zipfile.ZIP_DEFLATED
            entry.external_attr = 0o100644 << 16
            package.writestr(entry, data)
    return output


if __name__ == "__main__":
    parser = argparse.ArgumentParser()
    parser.add_argument("--version")
    parser.add_argument("--channel", choices=["alpha", "release"])
    parser.add_argument("--commit")
    parser.add_argument("--pr", type=int, default=0)
    parser.add_argument("--branch")
    print(build(**vars(parser.parse_args())))
