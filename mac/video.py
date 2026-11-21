#!/usr/bin/env python3
"""Rezumatele video: la 2 ore, fără AI. Pentru articolele cu meciuri din ultimele 6 zile (SuperLiga, Liga 2,
Liga 3, naționale) caută rezumatul oficial pe YouTube (unelte/video.py: doar canale oficiale, potrivire după
echipe, scor și dată) și îl trece în date/video.json. Site-ul îl afișează în articol în cel mult 15 minute.

Rulare manuală:  python3 mac/video.py              (ultimele 6 zile)
                 python3 mac/video.py --zile 30    (perioadă mai lungă)
                 python3 mac/video.py --doar-lista (doar ce ar căuta)
"""
import datetime as dt
import html
import json
import os
import re
import subprocess
import sys
import time

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), 'unelte'))
from comun import DEPOZIT, acum, blocheaza, git_pull, incarca, jurnal  # noqa: E402
import video as V  # noqa: E402

FISIER = os.path.join(DEPOZIT, 'date', 'video.json')


def competitie(c):
    if c == 'superliga':
        return 'superliga'
    if c == 'liga-2':
        return 'liga-2'
    if c.startswith('liga-3'):
        return 'liga-3'
    if c.startswith('nationala'):
        return 'nationala'
    return None


def text(s):
    return html.unescape(re.sub(r'<[^>]+>', '', s)).strip()


def meciuri(d):
    """Meciurile unui articol: linia de rezultat a cronicii sau tabelul rezultatelor din rezumatul de rundă."""
    c = d.get('continut', '')
    if competitie(d.get('competitie', '')) == 'liga-3' and 'runda' in d.get('id', ''):
        out = []
        for rand in re.findall(r'<tr>(.*?)</tr>', c, re.S):
            td = re.findall(r'<td>(.*?)</td>', rand, re.S)
            if len(td) >= 2:
                m = re.match(r'(.+?)\s+[–-]\s+(.+)$', text(td[0]))
                s = re.match(r'(\d+)\s*-\s*(\d+)$', text(td[1]))
                if m and s:
                    out.append((m.group(1), m.group(2), f'{s.group(1)}-{s.group(2)}'))
        return out[:4]
    m = re.search(r'<strong>(.*?)</strong>', c)
    if not m:
        return []
    m2 = re.match(r'(.+?)\s+–\s+(.+?)\s+(\d+)-(\d+)\b', text(m.group(1)))
    return [(m2.group(1), m2.group(2), f'{m2.group(3)}-{m2.group(4)}')] if m2 else []


def main():
    blocare = blocheaza('video')
    if not blocare:
        return
    git_pull()
    zile = int(sys.argv[sys.argv.index('--zile') + 1]) if '--zile' in sys.argv else 6
    limita = acum().date() - dt.timedelta(days=zile)
    harta = incarca(FISIER, {}).get('video', {})
    idx = incarca(os.path.join(DEPOZIT, 'index.json'), {}).get('articole', [])
    de_cautat = []
    for cale in idx:
        d = incarca(os.path.join(DEPOZIT, cale), None)
        if not d or d.get('id') in harta or not competitie(d.get('competitie', '')):
            continue
        try:
            data = dt.date.fromisoformat(d['id'][:10])
        except ValueError:
            continue
        if data < limita:
            continue
        ms = meciuri(d)
        if ms:
            de_cautat.append((d, data, ms))
    if '--doar-lista' in sys.argv:
        for d, data, ms in de_cautat:
            print('-', d['id'], ms)
        return
    noi = []
    for d, data, ms in de_cautat:
        comp = competitie(d['competitie'])
        lista = []
        for g, o, sc in ms:
            q = f'SuperScore Liga 3 {g} {o} {sc}' if comp == 'liga-3' else None
            try:
                v = V.potriveste(g, o, sc, data, comp, interogare=q, azi=acum().date())
            except RuntimeError as e:
                jurnal('video', f'Oprit: {e}')
                break
            time.sleep(2)
            if v and v['id'] not in [x['id'] for x in lista]:
                lista.append(v)
            if len(lista) >= 3:
                break
        if lista:
            harta[d['id']] = lista
            noi.append(d['id'])
    if not noi:
        jurnal('video', f'Niciun rezumat nou ({len(de_cautat)} articole verificate).')
        return
    with open(FISIER, 'w', encoding='utf-8') as f:
        json.dump({'video': harta}, f, ensure_ascii=False, indent=1)
        f.write('\n')
    subprocess.run(['git', '-C', DEPOZIT, 'add', 'date/video.json'], capture_output=True)
    subprocess.run(['git', '-C', DEPOZIT, 'commit', '-q', '-m', f'Video: {len(noi)} rezumate'], capture_output=True)
    for _ in range(3):
        if subprocess.run(['git', '-C', DEPOZIT, 'push', '-q', 'origin', 'HEAD:main'], capture_output=True).returncode == 0:
            break
        subprocess.run(['git', '-C', DEPOZIT, 'pull', '-q', '--rebase', 'origin', 'main'], capture_output=True)
    jurnal('video', f'{len(noi)} rezumate video noi: ' + ', '.join(noi))


if __name__ == '__main__':
    main()
