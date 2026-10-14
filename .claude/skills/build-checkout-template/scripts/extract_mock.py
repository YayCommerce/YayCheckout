#!/usr/bin/env python3
"""Unpack one template from the checkout-templates mock bundle into plain HTML.

The bundle stores each template as a gzip+base64 entry in <script type="__bundler/manifest">,
keyed by UUID; <script type="__bundler/ext_resources"> maps "t01".."t12" to those UUIDs.

Usage:
  extract_mock.py [--mock PATH] --list
  extract_mock.py [--mock PATH] <id> [--out DIR]

<id> is the mock's number ("06" or "6") or its label ("Minimal steps"). Writes two files to DIR:
  t<id>.html         the template's own page; open it in a browser to see it (it unpacks itself).
  t<id>.source.html  its readable source: markup with {{ bindings }}, data-woo-block hints, its own
                     styles and the logic class holding the sample data. The shared WordPress design-system
                     stylesheets every template carries are cut out.
"""
import argparse
import base64
import gzip
import json
import os
import re
import sys

# The design-system stylesheets every template repeats; they say nothing about the template.
SHARED_STYLE = re.compile(r'<style>\s*/\*\s*=+\s*WordPress (Design System|Components UI Kit).*?</style>', re.S)

DEFAULT_MOCK = os.path.expanduser('~/Downloads/WooCommerce Checkout Templates_v1.1.html')


def block(source, kind):
    m = re.search(r'<script type="__bundler/%s">(.*?)</script>' % kind, source, re.S)
    if not m:
        sys.exit('Not a mock bundle: no __bundler/%s script.' % kind)
    return json.loads(m.group(1))


def labels(source):
    m = re.search(r'var T=(\[.*?\]);', source)
    if not m:
        return {}
    raw = m.group(1).replace('\\"', '"')
    return {t['id']: t['label'] for t in json.loads(raw)}


def decode(entry):
    data = base64.b64decode(entry['data'])
    return gzip.decompress(data) if entry.get('compressed') else data


def main():
    p = argparse.ArgumentParser()
    p.add_argument('id', nargs='?')
    p.add_argument('--mock', default=DEFAULT_MOCK)
    p.add_argument('--out', default='.')
    p.add_argument('--list', action='store_true')
    a = p.parse_args()

    if not os.path.isfile(a.mock):
        sys.exit('Mock not found: %s. Pass --mock <path to the bundle .html>.' % a.mock)
    source = open(a.mock, encoding='utf-8').read()
    manifest = block(source, 'manifest')
    resources = {r['id']: r['uuid'] for r in block(source, 'ext_resources')}
    names = labels(source)

    if a.list or not a.id:
        for rid in sorted(resources):
            num = rid[1:]
            print('%s  %s' % (num, names.get(num, '?')))
        return

    want = a.id.strip()
    num = next((n for n, l in names.items() if l.lower() == want.lower()), None) or want.zfill(2)
    uuid = resources.get('t' + num)
    if not uuid or uuid not in manifest:
        sys.exit('No template %r in the mock. Run with --list.' % a.id)

    html = decode(manifest[uuid]).decode('utf-8')
    os.makedirs(a.out, exist_ok=True)
    page = os.path.join(a.out, 't%s.html' % num)
    with open(page, 'w', encoding='utf-8') as f:
        f.write(html)

    # Each template is itself a bundle; its markup is the JSON string in __bundler/template.
    inner = re.search(r'<script type="__bundler/template">(.*?)</script>', html, re.S)
    src = json.loads(inner.group(1)) if inner else html
    src = SHARED_STYLE.sub('', src)
    source = os.path.join(a.out, 't%s.source.html' % num)
    with open(source, 'w', encoding='utf-8') as f:
        f.write(src)

    print('%s  %s\n  page:   %s\n  source: %s (%d lines)' % (num, names.get(num, '?'), page, source, src.count('\n')))


if __name__ == '__main__':
    main()
