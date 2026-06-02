<?php
/**
 * includes/game_assets.php
 * Reads the Vite manifest and emits the correct <link> / <script> tags for a game.
 *
 * Usage (in a game PHP page):
 *   require __DIR__ . '/includes/game_assets.php';
 *   // in <head>:
 *   echo game_asset_tags('memory');
 */

function game_asset_tags(string $game): string
{
    static $manifest = null;

    if ($manifest === null) {
        $path = dirname(__DIR__) . '/assets/games/.vite/manifest.json';
        if (!is_file($path)) {
            return "<!-- Game manifest not found. Run: pnpm run build:games -->\n";
        }
        $manifest = json_decode(file_get_contents($path), true) ?: [];
    }

    $entryKey = "src/entries/{$game}.tsx";
    if (!isset($manifest[$entryKey])) {
        return "<!-- Game '{$game}' entry not found in manifest -->\n";
    }

    $base    = '/newhittest/assets/games/';
    $css     = [];
    $preload = [];
    $visited = [];

    // Collect CSS + JS chunks from entry and all its (static) imports recursively.
    $collect = function (string $key) use (&$collect, &$manifest, &$css, &$preload, &$visited): void {
        if (isset($visited[$key])) return;
        $visited[$key] = true;

        $chunk = $manifest[$key] ?? null;
        if (!$chunk) return;

        // CSS files
        foreach ($chunk['css'] ?? [] as $cssFile) {
            $css[$cssFile] = true;
        }

        // Non-entry JS chunks → preload
        if (isset($chunk['file']) && empty($chunk['isEntry']) && empty($chunk['isDynamicEntry'])) {
            $preload[$chunk['file']] = true;
        }

        // Recurse into static imports (not dynamicImports)
        foreach ($chunk['imports'] ?? [] as $imp) {
            $collect($imp);
        }
    };

    $collect($entryKey);

    $html = '';

    foreach ($css as $f => $_) {
        $html .= '<link rel="stylesheet" href="' . $base . $f . '">' . "\n";
    }
    foreach ($preload as $f => $_) {
        $html .= '<link rel="modulepreload" href="' . $base . $f . '">' . "\n";
    }

    $entryFile = $manifest[$entryKey]['file'];
    $html .= '<script type="module" src="' . $base . $entryFile . '"></script>' . "\n";

    return $html;
}
