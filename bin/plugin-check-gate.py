#!/usr/bin/env python3
"""Compare Plugin Check's errors against the baseline this plugin was already published with.

Plugin Check finds 158 errors in code that has been on WordPress.org since 2024: unescaped output,
direct queries, and a text domain with underscores in it that cannot be renamed without orphaning
every translation anyone has made. This release does not pretend to fix them, and a gate that
refused every release until they were fixed would simply never let one through.

So the question it asks is the useful one: did THIS release add anything? The baseline records how
many errors of each kind each file already had. More of a kind, or a kind that was not there, fails
the build; the same or fewer passes and prints the difference.

  plugin-check-gate.py <wp plugin check --format=json output> [baseline.json]

Regenerate the baseline (deliberately, with the numbers read first) by writing the --counts output
to plugin-check-baseline.json.
"""
import json
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
BASELINE = os.path.join(ROOT, 'plugin-check-baseline.json')


def counts(raw):
    """{'file.php|Sniff.Code': n} for every ERROR in `wp plugin check --format=json` output.

    The output is one JSON array per file, each under a `FILE: <path>` line - not a single document.
    """
    out = {}
    for path, block in re.findall(r'FILE: (\S+)\n(\[.*?\])\n', raw, re.S):
        try:
            items = json.loads(block)
        except ValueError:
            continue
        for item in items:
            if item.get('type') != 'ERROR':
                continue
            key = '%s|%s' % (os.path.basename(path), item['code'])
            out[key] = out.get(key, 0) + 1
    return out


if __name__ == '__main__':
    args = [a for a in sys.argv[1:] if not a.startswith('--')]
    raw = open(args[0], encoding='utf-8', errors='replace').read() if args else sys.stdin.read()
    found = counts(raw)

    if '--counts' in sys.argv:
        print(json.dumps(found, indent=2, sort_keys=True))
        sys.exit(0)

    base = json.load(open(args[1] if len(args) > 1 else BASELINE, encoding='utf-8'))

    worse, better = [], []
    for key in sorted(set(found) | set(base)):
        now, was = found.get(key, 0), base.get(key, 0)
        if now > was:
            worse.append('%s: було %d, стало %d' % (key, was, now))
        elif now < was:
            better.append('%s: було %d, стало %d' % (key, was, now))

    print('Plugin Check: %d помилок, базова лінія %d' % (sum(found.values()), sum(base.values())))
    for line in better:
        print('  менше: ' + line)
    for line in worse:
        print('  БІЛЬШЕ: ' + line)
    if worse:
        print('цей реліз додав помилки, яких у опублікованому коді не було')
        sys.exit(1)
    sys.exit(0)
