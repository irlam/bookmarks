<?php
// ─── Bookmarks API ───────────────────────────────────────────────────────────
require_once __DIR__ . '/auth.php';

require_login();

header('Content-Type: application/json; charset=utf-8');

// ── Helpers ──────────────────────────────────────────────────────────────────

function read_bookmarks(): array
{
    $file = DATA_FILE;
    if (!file_exists($file)) {
        return [];
    }
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

function write_bookmarks(array $bookmarks): void
{
    $file = DATA_FILE;
    $dir  = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0750, true);
    }
    $tmp = $file . '.tmp';
    $fp  = fopen($tmp, 'w');
    if (!$fp) {
        throw new RuntimeException('Cannot open data file for writing.');
    }
    flock($fp, LOCK_EX);
    fwrite($fp, json_encode(array_values($bookmarks), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    rename($tmp, $file);
}

function json_error(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function json_ok(mixed $data = null): never
{
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function new_id(): string
{
    return bin2hex(random_bytes(8));
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function sanitize_tags(mixed $tags): array
{
    if (!is_array($tags)) {
        $tags = array_filter(array_map('trim', explode(',', (string) $tags)));
    }
    $tags = array_map(fn($t) => mb_strtolower(trim((string) $t)), $tags);
    $tags = array_filter($tags, fn($t) => $t !== '');
    return array_values(array_unique($tags));
}

// ── Routing ──────────────────────────────────────────────────────────────────

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// Parse JSON body if Content-Type is application/json
$body = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($contentType, 'application/json')) {
    $raw  = file_get_contents('php://input');
    $body = json_decode($raw, true) ?? [];
} else {
    $body = $_POST;
}

// CSRF check for mutating actions
$mutating = ['add', 'update', 'delete'];
if (in_array($action, $mutating, true)) {
    $token = $body['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verify_csrf($token)) {
        json_error('Invalid CSRF token.', 403);
    }
}

// ── Actions ──────────────────────────────────────────────────────────────────

switch ($action) {

    // ── List ─────────────────────────────────────────────────────────────────
    case 'list':
        $bookmarks = read_bookmarks();
        $q   = strtolower(trim($_GET['q'] ?? ''));
        $tag = strtolower(trim($_GET['tag'] ?? ''));

        if ($q !== '') {
            $bookmarks = array_filter($bookmarks, function ($bm) use ($q) {
                return str_contains(strtolower($bm['title'] ?? ''), $q)
                    || str_contains(strtolower($bm['url'] ?? ''), $q)
                    || str_contains(strtolower($bm['description'] ?? ''), $q)
                    || !empty(array_filter($bm['tags'] ?? [], fn($t) => str_contains(strtolower($t), $q)));
            });
        }

        if ($tag !== '') {
            $bookmarks = array_filter($bookmarks, fn($bm) => in_array($tag, array_map('strtolower', $bm['tags'] ?? []), true));
        }

        // Sort newest first
        usort($bookmarks, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));

        json_ok(array_values($bookmarks));
        break;

    // ── All tags ─────────────────────────────────────────────────────────────
    case 'tags':
        $bookmarks = read_bookmarks();
        $tags = [];
        foreach ($bookmarks as $bm) {
            foreach ($bm['tags'] ?? [] as $t) {
                $t = strtolower(trim($t));
                if ($t !== '') {
                    $tags[$t] = ($tags[$t] ?? 0) + 1;
                }
            }
        }
        ksort($tags);
        json_ok($tags);
        break;

    // ── Add ──────────────────────────────────────────────────────────────────
    case 'add':
        if ($method !== 'POST') {
            json_error('POST required.', 405);
        }
        $title = trim($body['title'] ?? '');
        $url   = trim($body['url'] ?? '');
        if ($title === '') {
            json_error('Title is required.');
        }
        if ($url === '') {
            json_error('URL is required.');
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            json_error('Invalid URL.');
        }

        $bm = [
            'id'          => new_id(),
            'title'       => $title,
            'url'         => $url,
            'description' => trim($body['description'] ?? ''),
            'tags'        => sanitize_tags($body['tags'] ?? []),
            'created_at'  => now(),
            'updated_at'  => now(),
        ];

        $bookmarks   = read_bookmarks();
        $bookmarks[] = $bm;
        write_bookmarks($bookmarks);
        json_ok($bm);
        break;

    // ── Update ───────────────────────────────────────────────────────────────
    case 'update':
        if ($method !== 'POST') {
            json_error('POST required.', 405);
        }
        $id = trim($body['id'] ?? '');
        if ($id === '') {
            json_error('ID is required.');
        }
        $title = trim($body['title'] ?? '');
        $url   = trim($body['url'] ?? '');
        if ($title === '') {
            json_error('Title is required.');
        }
        if ($url === '') {
            json_error('URL is required.');
        }
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            json_error('Invalid URL.');
        }

        $bookmarks = read_bookmarks();
        $found     = false;
        foreach ($bookmarks as &$bm) {
            if ($bm['id'] === $id) {
                $bm['title']       = $title;
                $bm['url']         = $url;
                $bm['description'] = trim($body['description'] ?? '');
                $bm['tags']        = sanitize_tags($body['tags'] ?? []);
                $bm['updated_at']  = now();
                $found = true;
                $updated = $bm;
                break;
            }
        }
        unset($bm);

        if (!$found) {
            json_error('Bookmark not found.', 404);
        }
        write_bookmarks($bookmarks);
        json_ok($updated);
        break;

    // ── Delete ───────────────────────────────────────────────────────────────
    case 'delete':
        if ($method !== 'POST') {
            json_error('POST required.', 405);
        }
        $id = trim($body['id'] ?? '');
        if ($id === '') {
            json_error('ID is required.');
        }

        $bookmarks = read_bookmarks();
        $filtered  = array_filter($bookmarks, fn($bm) => $bm['id'] !== $id);
        if (count($filtered) === count($bookmarks)) {
            json_error('Bookmark not found.', 404);
        }
        write_bookmarks(array_values($filtered));
        json_ok(null);
        break;

    // ── Fetch page title ─────────────────────────────────────────────────────
    case 'fetch_title':
        if ($method !== 'POST') {
            json_error('POST required.', 405);
        }
        $url = trim($body['url'] ?? '');
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            json_error('Invalid URL.');
        }

        $ctx = stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => 5,
                'follow_location' => 1,
                'max_redirects'   => 5,
                'header'          => "User-Agent: Mozilla/5.0 (compatible; BookmarksApp/1.0)\r\n",
            ],
            'ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
            ],
        ]);

        $html = @file_get_contents($url, false, $ctx);
        if ($html === false) {
            json_error('Could not fetch URL.');
        }

        // Extract <title>
        $title = '';
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $m)) {
            $title = html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            // Truncate overly long titles
            if (mb_strlen($title) > 200) {
                $title = mb_substr($title, 0, 200);
            }
        }
        json_ok(['title' => $title]);
        break;

    // ── CSRF token ───────────────────────────────────────────────────────────
    case 'csrf':
        json_ok(csrf_token());
        break;

    default:
        json_error('Unknown action.', 400);
}
