"""Group layer-diff findings by element class, property and value change."""
import json, sys, collections, re

d = json.load(open(sys.argv[1]))
errors = [f for f in d if 'error' in f]
rows = [f for f in d if 'error' not in f]
print(f"findings: {len(rows)}  errors: {len(errors)}")
for e in errors[:10]:
    print('  ERROR', e['persona'], e['route'], e['width'], e['error'][:160])

def leaf(key):
    state = ''
    if ' | ' in key:
        state, key = key.split(' | ', 1)
        state = '[' + state.split(' ', 1)[0] + '] '
    last = key.split('>')[-1]
    last = re.sub(r':\d+(?=(::|$))', '', last)
    return state + last

groups = collections.defaultdict(lambda: {'n': 0, 'where': set()})
for f in rows:
    g = groups[(leaf(f['key']), f['prop'], f['a'], f['b'])]
    g['n'] += 1
    g['where'].add(f"{f['persona']}{f['route']}@{f['width']}/{f['theme']}")
for (el, prop, a, b), g in sorted(groups.items(), key=lambda kv: (-kv[1]['n'], kv[0])):
    where = sorted(g['where'])
    print(f"{g['n']:5d}  {el[:70]:70s} {prop:28s} {a[:38]!s:38s} -> {b[:38]}")
    print(f"       e.g. {', '.join(where[:4])}{' …' if len(where) > 4 else ''} ({len(where)} contexts)")
