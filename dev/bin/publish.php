<?php
declare(strict_types=1);

require __DIR__ . '/package.php';

/** GitHub publication transport; secrets never appear in exceptions or release notes. */
function github(string $method, string $path, mixed $body = null, bool $binary = false): ?array
{
    $token = getenv('GH_TOKEN');
    if (!$token) { throw new RuntimeException('GH_TOKEN is required for publication.'); }
    $curl = curl_init('https://' . ($binary ? 'uploads.github.com' : 'api.github.com') . '/repos/millaredos/f1-25-setup-engineer/' . $path);
    $headers = ['Authorization: Bearer ' . $token, 'User-Agent: SetupEngineer-Publisher', 'Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
    if ($body !== null) { $headers[] = 'Content-Type: ' . ($binary ? 'application/zip' : 'application/json'); }
    curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers, CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 120, CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2]);
    if ($body !== null) { curl_setopt($curl, CURLOPT_POSTFIELDS, $binary ? $body : json_encode($body, JSON_THROW_ON_ERROR)); }
    try {
        $result = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($status === 404 && $method === 'GET') { return null; }
        if (!is_string($result) || $status < 200 || $status >= 300) { throw new RuntimeException('GitHub publication request failed: HTTP ' . $status); }
        return $result === '' ? [] : json_decode($result, true, 64, JSON_THROW_ON_ERROR);
    } finally { curl_close($curl); }
}

/** Read complete release/tag listings; Alpha numbers are reserved per target version. */
function githubPages(string $path): array
{
    $rows = [];
    for ($page = 1; ; $page++) {
        $result = github('GET', $path . '?per_page=100&page=' . $page) ?? [];
        $rows = array_merge($rows, $result);
        if (count($result) < 100) { return $rows; }
        if ($page > 1000) { throw new RuntimeException('Unexpected GitHub pagination limit.'); }
    }
}

/** Publish only same-repository release PR events; merging prepares a stable draft, never publishes it. */
function publish(): void
{
    if (getenv('GITHUB_EVENT_NAME') !== 'pull_request') { throw new RuntimeException('Publication requires a release PR event.'); }
    $event = json_decode(file_get_contents(getenv('GITHUB_EVENT_PATH')), true, 64, JSON_THROW_ON_ERROR);
    $pr = $event['pull_request'];
    $branch = $pr['head']['ref'] ?? '';
    if (($pr['head']['repo']['full_name'] ?? '') !== 'millaredos/f1-25-setup-engineer' || !preg_match('/^release\/(\d+\.\d+\.\d+)$/D', $branch, $match)) { throw new RuntimeException('Untrusted publication branch.'); }
    $stable = ($event['action'] ?? '') === 'closed' && ($pr['merged'] ?? false) === true;
    if (($event['action'] ?? '') === 'closed' && !$stable) { return; }
    $commit = $stable ? $pr['merge_commit_sha'] : $pr['head']['sha'];
    if (!preg_match('/^[a-f0-9]{40}$/D', $commit)) { throw new RuntimeException('Invalid commit identity.'); }
    $root = dirname(__DIR__, 2);
    $process = proc_open(['git', 'rev-parse', 'HEAD'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
    if (!is_resource($process)) { throw new RuntimeException('Cannot verify checkout.'); }
    $head = trim(stream_get_contents($pipes[1])); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($process) !== 0 || $head !== $commit) { throw new RuntimeException('Checkout does not match the requested commit.'); }
    $live = github('GET', 'pulls/' . (int) $pr['number']);
    if (!$stable && (($live['state'] ?? '') !== 'open' || ($live['head']['sha'] ?? '') !== $commit)) { return; }
    if (!$stable) {
        $active = array_filter(githubPages('pulls'), static fn (array $row): bool => ($row['head']['repo']['full_name'] ?? '') === 'millaredos/f1-25-setup-engineer' && preg_match('/^release\/\d+\.\d+\.\d+$/D', $row['head']['ref'] ?? '') === 1);
        if (count($active) !== 1) { throw new RuntimeException('Exactly one release PR must be active.'); }
    }
    $target = $match[1];
    if (explode('-alpha.', trim(file_get_contents($root . '/src/VERSION')))[0] !== $target) { throw new RuntimeException('Source version and release branch disagree.'); }
    $existing = null;
    if ($stable) {
        $version = $target;
        $existing = github('GET', 'releases/tags/v' . $version);
    } else {
        $maximum = 0;
        $publishedNumber = 0;
        $previousCommit = $pr['base']['sha'] ?? '';
        foreach (array_merge(githubPages('releases'), githubPages('tags')) as $row) {
            $tag = $row['tag_name'] ?? $row['name'] ?? '';
            if (preg_match('/^v' . preg_quote($target, '/') . '-alpha\.(\d+)$/D', $tag, $number)) {
                if ((int) $number[1] > $publishedNumber && isset($row['target_commitish']) && ($row['draft'] ?? true) === false) { $previousCommit = $row['target_commitish']; $publishedNumber = (int) $number[1]; }
                $maximum = max($maximum, (int) $number[1]);
                if (($row['target_commitish'] ?? '') === $commit) { $existing = $row; }
            }
        }
        if (!$existing && preg_match('/^[a-f0-9]{40}$/D', $previousCommit)) {
            $comparison = github('GET', 'compare/' . $previousCommit . '...' . $commit);
            $functional = false;
            foreach ($comparison['files'] ?? [] as $file) {
                if (!preg_match('~^(?:docs/|README\.md$|CHANGELOG\.md$|ROADMAP\.md$|AGENTS\.md$)~', $file['filename'])) { $functional = true; break; }
            }
            if (!$functional) { return; }
        }
        $version = $existing ? substr($existing['tag_name'], 1) : $target . '-alpha.' . ($maximum + 1);
    }
    $identity = ['channel' => $stable ? 'release' : 'alpha', 'commit' => $commit, 'pr' => (int) $pr['number'], 'branch' => $branch];
    $temp = tempnam(sys_get_temp_dir(), 'f125-package-');
    if (!$temp) { throw new RuntimeException('Cannot prepare package.'); }
    try {
        buildPackage($root, $temp, $version, $identity);
        $digest = hash_file('sha256', $temp);
        $marker = '<!-- f125-update ' . json_encode($identity + ['format' => 'f125-update-v3'], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . ' -->';
        $release = $existing ?? github('POST', 'releases', ['tag_name' => 'v' . $version, 'target_commitish' => $commit, 'name' => ($stable ? 'Release ' : 'Alpha ') . $version, 'body' => ($live['body'] ?? $pr['title']) . "\n\n" . $marker, 'draft' => true, 'prerelease' => !$stable]);
        if (($release['target_commitish'] ?? '') !== $commit) { throw new RuntimeException('Version already belongs to another commit.'); }
        $name = 'f125-update-' . $version . '.zip';
        $assets = array_values(array_filter($release['assets'] ?? [], static fn (array $asset): bool => $asset['name'] === $name));
        if (!($release['draft'] ?? true)) {
            if (count($assets) === 1 && ($assets[0]['digest'] ?? '') === 'sha256:' . $digest) { return; }
            throw new RuntimeException('Published content cannot be replaced.');
        }
        $asset = $assets[0] ?? github('POST', 'releases/' . $release['id'] . '/assets?name=' . rawurlencode($name), file_get_contents($temp), true);
        if (($asset['digest'] ?? '') !== 'sha256:' . $digest || ($asset['state'] ?? '') !== 'uploaded') { throw new RuntimeException('GitHub did not verify the package digest.'); }
        if (!$stable) {
            $latest = github('GET', 'pulls/' . (int) $pr['number']);
            if (($latest['state'] ?? '') === 'open' && ($latest['head']['sha'] ?? '') === $commit) { github('PATCH', 'releases/' . $release['id'], ['draft' => false, 'prerelease' => true, 'make_latest' => 'false']); }
        }
    } finally { if (is_file($temp)) { unlink($temp); } }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    try { publish(); } catch (Throwable $error) { fwrite(STDERR, $error->getMessage() . PHP_EOL); exit(1); }
}
