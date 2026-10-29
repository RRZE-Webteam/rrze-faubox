#!/usr/bin/env node

/**
 * Synchronizes version and compatibility values from package.json
 * into the plugin header (rrze-faubox.php) and readme.txt.
 *
 * package.json is the canonical source. Run via: npm run sync
 * Automatically called at the end of: npm run build
 */

const fs = require('fs');
const path = require('path');

const ROOT = path.resolve(__dirname, '..');
const PACKAGE_JSON = path.join(ROOT, 'package.json');
const PLUGIN_FILE = path.join(ROOT, 'rrze-faubox.php');
const README_TXT = path.join(ROOT, 'readme.txt');

function fail(message) {
    console.error('[sync-version] ' + message);
    process.exit(1);
}

function replaceOrFail(content, pattern, replacement, file, label) {
    if (!pattern.test(content)) {
        fail('Could not find "' + label + '" in ' + file);
    }
    return content.replace(pattern, replacement);
}

function main() {
    if (!fs.existsSync(PACKAGE_JSON)) {
        fail('Missing: ' + PACKAGE_JSON);
    }

    const pkg = JSON.parse(fs.readFileSync(PACKAGE_JSON, 'utf8'));
    const fullVersion = pkg.version;
    const version = fullVersion.split('-')[0];
    const compat = pkg.compatibility || {};

    // --- Plugin header (rrze-faubox.php) ---
    if (fs.existsSync(PLUGIN_FILE)) {
        let plugin = fs.readFileSync(PLUGIN_FILE, 'utf8');

        plugin = replaceOrFail(plugin,
            /Version:\s*[^\r\n]+/,
            'Version:            ' + version,
            PLUGIN_FILE, 'Version');

        if (compat.wprequires) {
            plugin = replaceOrFail(plugin,
                /Requires at least:\s*[^\r\n]+/,
                'Requires at least:  ' + compat.wprequires,
                PLUGIN_FILE, 'Requires at least');
            plugin = replaceOrFail(plugin,
                /const FAUBOX_WP_VERSION\s*=\s*'[^']*';/,
                "const FAUBOX_WP_VERSION = '" + compat.wprequires + "';",
                PLUGIN_FILE, 'FAUBOX_WP_VERSION');
        }

        if (compat.phprequires) {
            plugin = replaceOrFail(plugin,
                /Requires PHP:\s*[^\r\n]+/,
                'Requires PHP:       ' + compat.phprequires,
                PLUGIN_FILE, 'Requires PHP');
            plugin = replaceOrFail(plugin,
                /const FAUBOX_PHP_VERSION\s*=\s*'[^']*';/,
                "const FAUBOX_PHP_VERSION = '" + compat.phprequires + "';",
                PLUGIN_FILE, 'FAUBOX_PHP_VERSION');
        }

        plugin = replaceOrFail(plugin,
            /define\('RRZE_FAUBOX_VERSION',\s*'[^']*'\);/,
            "define('RRZE_FAUBOX_VERSION', '" + version + "');",
            PLUGIN_FILE, 'RRZE_FAUBOX_VERSION');

        fs.writeFileSync(PLUGIN_FILE, plugin);
    }

    // --- block.json (src) ---
    const BLOCK_JSON = path.join(ROOT, 'src', 'block', 'block.json');
    if (fs.existsSync(BLOCK_JSON)) {
        let blockJson = JSON.parse(fs.readFileSync(BLOCK_JSON, 'utf8'));
        blockJson.version = version;
        fs.writeFileSync(BLOCK_JSON, JSON.stringify(blockJson, null, 2) + '\n');
    }

    // --- readme.txt ---
    if (fs.existsSync(README_TXT)) {
        let readme = fs.readFileSync(README_TXT, 'utf8');

        readme = replaceOrFail(readme,
            /Stable tag:\s*[^\r\n]+/,
            'Stable tag:         ' + version,
            README_TXT, 'Stable tag');

        if (compat.wprequires) {
            readme = replaceOrFail(readme,
                /Requires at least:\s*[^\r\n]+/,
                'Requires at least:  ' + compat.wprequires,
                README_TXT, 'Requires at least');
        }

        if (compat.wptestedup) {
            readme = replaceOrFail(readme,
                /Tested up to:\s*[^\r\n]+/,
                'Tested up to:       ' + compat.wptestedup,
                README_TXT, 'Tested up to');
        }

        if (compat.phprequires) {
            readme = replaceOrFail(readme,
                /Requires PHP:\s*[^\r\n]+/,
                'Requires PHP:       ' + compat.phprequires,
                README_TXT, 'Requires PHP');
        }

        fs.writeFileSync(README_TXT, readme);
    }

    console.log('[sync-version] Synchronized version ' + version + ' (internal: ' + fullVersion + ') to plugin header and readme.txt.');
}

main();