<?php

/*
 * Points the "laravel-boost" MCP server in .mcp.json at the Docker container.
 *
 * boost:install writes a server entry that calls a local `php`, which does not exist on
 * the Windows host. This replaces that entry with one that runs Boost inside the app
 * container (other servers in the file are kept). Run it inside the container:
 *
 *   docker compose exec -T app php docker/scripts/write-mcp-json.php
 */

$path = dirname(__DIR__, 2).'/.mcp.json';

$config = [];

if (is_file($path)) {
    $decoded = json_decode((string) file_get_contents($path), true);

    if (is_array($decoded)) {
        $config = $decoded;
    } else {
        fwrite(STDERR, "Existing .mcp.json is not valid JSON; it will be replaced.\n");
    }
}

if (! isset($config['mcpServers']) || ! is_array($config['mcpServers'])) {
    $config['mcpServers'] = [];
}

$config['mcpServers']['laravel-boost'] = [
    'command' => 'docker',
    'args' => ['compose', 'exec', '-T', 'app', 'php', 'artisan', 'boost:mcp'],
];

$json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

if (file_put_contents($path, $json."\n") === false) {
    fwrite(STDERR, "Could not write {$path}\n");
    exit(1);
}

echo "Wrote .mcp.json (laravel-boost runs via: docker compose exec -T app php artisan boost:mcp)\n";
