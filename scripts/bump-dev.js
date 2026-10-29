#!/usr/bin/env node
/**
 * Increments the internal dev counter (4th version segment after -).
 * Example: 1.0.4-2 → 1.0.4-3
 * If no - is present (e.g. after a release bump), starts at -1.
 */

const fs = require('fs');
const path = require('path');

const PACKAGE_JSON = path.resolve(__dirname, '..', 'package.json');
const pkg = JSON.parse(fs.readFileSync(PACKAGE_JSON, 'utf8'));

const [base, dev] = pkg.version.split('-');
const next = (parseInt(dev, 10) || 0) + 1;
pkg.version = base + '-' + next;

fs.writeFileSync(PACKAGE_JSON, JSON.stringify(pkg, null, 2) + '\n');
console.log('[bump-dev] ' + pkg.version);