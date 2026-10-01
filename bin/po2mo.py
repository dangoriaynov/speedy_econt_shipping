#!/usr/bin/env python3
"""Compile a .po into the two formats WordPress reads: .mo and .l10n.php.

Gettext tooling is not installed on this box, and a shipped catalogue must not depend
on whether it was. WordPress 6.5+ reads the .php file first and falls back to the .mo,
so both are written from the same source and stay in step.
"""
import re, struct, sys, os

def unquote(parts):
    out = []
    for p in parts:
        p = p.strip()
        if not (p.startswith('"') and p.endswith('"')):
            raise ValueError('не рядок у лапках: %r' % p)
        out.append(p[1:-1])
    s = ''.join(out)
    return re.sub(r'\\(n|t|r|"|\\)', lambda m: {'n':'\n','t':'\t','r':'\r','"':'"','\\':'\\'}[m.group(1)], s)

def parse(path):
    entries = []
    cur = {}
    key = None
    buf = []
    def flush():
        nonlocal key, buf
        if key is not None:
            cur[key] = unquote(buf) if buf else ''
        key, buf = None, []
    with open(path, encoding='utf-8') as fh:
        for raw in fh:
            line = raw.rstrip('\n')
            if not line.strip():
                flush()
                if cur.get('msgid') is not None:
                    entries.append(cur)
                cur = {}
                continue
            if line.startswith('#'):
                flush()
                if line.startswith('#,') and 'fuzzy' in line:
                    cur['fuzzy'] = True
                continue
            m = re.match(r'^(msgctxt|msgid_plural|msgid|msgstr(?:\[\d+\])?)\s+(.*)$', line)
            if m:
                flush()
                key, buf = m.group(1), [m.group(2)]
            elif line.strip().startswith('"'):
                buf.append(line)
            else:
                raise ValueError('незрозумілий рядок: %r' % line)
    flush()
    if cur.get('msgid') is not None:
        entries.append(cur)
    return entries

def pairs(entries):
    """(mo key, translation) for every translated entry, header included."""
    out = []
    for e in entries:
        if e.get('fuzzy') and e.get('msgid'):
            continue
        mid = e.get('msgid', '')
        if 'msgid_plural' in e:
            forms = [e[k] for k in sorted(k for k in e if k.startswith('msgstr[')) ]
            if not any(forms):
                continue
            key = mid + '\0' + e['msgid_plural']
            val = '\0'.join(forms)
        else:
            val = e.get('msgstr', '')
            if mid and not val:
                continue
            key = mid
        if 'msgctxt' in e:
            key = e['msgctxt'] + '\x04' + key
        out.append((key, val))
    return out

def write_mo(items, path):
    items = sorted(items, key=lambda kv: kv[0].encode('utf-8'))
    n = len(items)
    off_orig = 28
    off_trans = off_orig + n * 8
    start = off_trans + n * 8
    otab, ttab, blob = b'', b'', b''
    for k, v in items:
        kb, vb = k.encode('utf-8'), v.encode('utf-8')
        otab += struct.pack('<II', len(kb), start + len(blob))
        blob += kb + b'\0'
    for k, v in items:
        vb = v.encode('utf-8')
        ttab += struct.pack('<II', len(vb), start + len(blob))
        blob += vb + b'\0'
    with open(path, 'wb') as fh:
        # magic, revision, count, offset of originals, offset of translations, hash size, hash offset
        fh.write(struct.pack('<7I', 0x950412de, 0, n, off_orig, off_trans, 0, 0))
        fh.write(otab + ttab + blob)
    return n

def write_php(items, path, domain):
    hdr = dict()
    msgs = {}
    for k, v in items:
        if k == '':
            for line in v.split('\n'):
                if ':' in line:
                    a, b = line.split(':', 1)
                    hdr[a.strip()] = b.strip()
            continue
        msgs[k] = v
    def lit(s):
        return "'" + s.replace('\\', '\\\\').replace("'", "\\'") + "'"
    keep = ('Project-Id-Version', 'Report-Msgid-Bugs-To', 'PO-Revision-Date', 'Last-Translator',
            'Language-Team', 'Language', 'Plural-Forms', 'X-Generator', 'X-Domain')
    with open(path, 'w', encoding='utf-8') as fh:
        fh.write("<?php\nreturn [\n")
        fh.write("\t'domain' => %s,\n" % lit(domain))
        fh.write("\t'plural-forms' => %s,\n" % lit(hdr.get('Plural-Forms', 'nplurals=2; plural=(n != 1);')))
        for h in keep:
            if h in hdr:
                fh.write("\t%s => %s,\n" % (lit(h.lower()), lit(hdr[h])))
        fh.write("\t'messages' => [\n")
        for k in sorted(msgs):
            fh.write("\t\t%s => %s,\n" % (lit(k), lit(msgs[k])))
        fh.write("\t],\n];\n")
    return len(msgs)

if __name__ == '__main__':
    po = sys.argv[1]
    base = po[:-3]
    domain = os.path.basename(base).rsplit('-', 1)[0]
    items = pairs(parse(po))
    n = write_mo(items, base + '.mo')
    m = write_php(items, base + '.l10n.php', domain)
    print('%s: %d записів у .mo, %d рядків у .l10n.php' % (os.path.basename(po), n, m))
