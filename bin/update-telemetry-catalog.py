#!/usr/bin/env python3
"""Refresh public signal names from Tesla. Review the diff before committing.

Offline reproduction: --html available-data.html --proto vehicle_data.proto
New protocol-only fields get a conservative metadata fallback; review their types.
"""

import argparse
import html
import json
import re
from pathlib import Path
from urllib.request import urlopen

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--html', type=Path)
parser.add_argument('--proto', type=Path)
parser.add_argument('--output', type=Path, default=Path(__file__).resolve().parents[1] / 'resources/data/telemetry-fields.json')
args = parser.parse_args()

def source(path, url):
    return path.read_text() if path else urlopen(url, timeout=30).read().decode()

page = source(args.html, 'https://developer.tesla.com/docs/fleet-api/fleet-telemetry/available-data')
proto = source(args.proto, 'https://raw.githubusercontent.com/teslamotors/fleet-telemetry/main/protos/vehicle_data.proto')
previous = json.loads(args.output.read_text()) if args.output.exists() else {}
fields = {}
tables = re.findall(r'<table[^>]*>(.*?)</table>', page, re.S)
if not tables:
    raise SystemExit('Tesla documentation format changed: no table found; catalog not modified.')
for row in re.findall(r'<tr[^>]*>(.*?)</tr>', tables[0], re.S):
    cells = [html.unescape(re.sub('<[^>]+>', '', cell)).strip()
             for cell in re.findall(r'<t[hd][^>]*>(.*?)</t[hd]>', row, re.S)]
    if len(cells) == 4 and cells[0] != 'Field':
        fields[cells[0]] = {'category': cells[1], 'type': cells[2]}

enum = proto.split('enum Field {', 1)[1].split('}', 1)[0]
names = re.findall(r'^\s*(\w+)\s*=\s*\d+', enum, re.M)
for name in names:
    if name == 'Unknown' or name.startswith(('Experimental_', 'Deprecated_')):
        continue
    fields.setdefault(name, previous.get(name, {'category': 'Other', 'type': 'Protocol field'}))

if len(fields) < 200 or not {'VehicleSpeed', 'Location', 'PackVoltage'}.issubset(fields):
    raise SystemExit('Incomplete catalog received; catalog not modified.')
args.output.write_text(json.dumps(dict(sorted(fields.items())), indent=2) + '\n')
print(f'Wrote {len(fields)} fields to {args.output}. Review metadata and compatibility before publishing.')
