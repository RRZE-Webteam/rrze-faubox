#!/usr/bin/env node
/**
 * Increments the internal dev counter (4th version segment after _).
 * Example: 1.0.4_2 → 1.0.4_3
 * If no _ is present (e.g. after a release bump), starts at _1.
 */

const fs = require('fs');
const path = require('path');

const PACKAGE_JSON = path.resolve(__dirname, '..', 'package.json');
const pkg = JSON.parse(fs.readFileSync(PACKAGE_JSON, 'utf8'));

const [base, dev] = pkg.version.split('_');
const next = (parseInt(dev, 10) || 0) + 1;
pkg.version = base + '_' + next;

fs.writeFileSync(PACKAGE_JSON, JSON.stringify(pkg, null, 2) + '\n');
console.log('[bump-dev] ' + pkg.version);