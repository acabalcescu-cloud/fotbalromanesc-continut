#!/usr/bin/env python3
"""Știrile mari: de trei ori pe zi (09:47, 14:47 și 20:47), cu știrea zilei din SuperLiga/Naționale. Fără AI, caută în Google News titluri noi din ultimele 24 de ore
pe temele urmărite; pornește Claude doar dacă există candidați pe care nu i-am mai văzut.

Rulare manuală:  python3 mac/stiri.py              (verificare + Claude dacă e cazul)
                 python3 mac/stiri.py --doar-lista  (candidații, fără Claude)
"""
import datetime as dt
import email.utils
import html
import os
import sys
import urllib.parse
import xml.etree.ElementTree as ET

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from comun import DEPOZIT, STARE, blocheaza, curl, git_pull, incarca, jurnal, ruleaza_claude, salveaza

CAUTARI = [
    'SuperLiga', 'SuperLiga antrenor demis', 'SuperLiga transfer', 'naționala României', 'selecționerul României',
    'FCSB', 'CFR Cluj', 'Universitatea Craiova', 'Rapid București', 'Dinamo București',
    'Liga 2 fotbal antrenor', 'Liga 2 transfer', 'Liga 3 fotbal antrenor', 'naționala României convocare',
    'naționala de tineret România', 'antrenor demis Liga 2', 'FRF Liga 2 sancțiune',
]
VAZUTE_LOCAL = os.path.join(STARE, 'stiri-vazute.json')


def candidati():
    vazute = set(incarca(os.path.join(DEPOZIT, 'presa', 'vazute.json'), {}).keys()) | set(incarca(VAZUTE_LOCAL, []))
    limita = dt.datetime.now(dt.timezone.utc) - dt.timedelta(hours=24)
    noi, titluri = [], set()
    for q in CAUTARI:
        url = 'https://news.google.com/rss/search?q=' + urllib.parse.quote(q + ' when:1d') + '&hl=ro&gl=RO&ceid=RO:ro'
        try:
            root = ET.fromstring(curl(url).encode('utf-8', 'ignore'))
        except ET.ParseError:
            continue
        for it in root.iter('item'):
            titlu = html.unescape((it.findtext('title') or '').strip())
            link = (it.findtext('link') or '').strip()
            try:
                data = email.utils.parsedate_to_datetime(it.findtext('pubDate') or '')
            except (TypeError, ValueError):
                continue
            if data < limita or link in vazute or titlu in titluri:
                continue
            titluri.add(titlu)
            noi.append({'titlu': titlu, 'link': link, 'cautare': q, 'data': data.isoformat()})
    return noi[:80]


def main():
    blocare = blocheaza('stiri')
    if not blocare:
        return
    git_pull()
    noi = candidati()
    if '--doar-lista' in sys.argv:
        for c in noi:
            print('-', c['titlu'])
        return
    if not noi:
        jurnal('stiri', 'Nimic nou.')
        return
    salveaza(os.path.join(STARE, 'stiri-candidati.json'), noi)
    jurnal('stiri', f'{len(noi)} titluri noi, pornesc Claude.')
    ruleaza_claude('stiri', 'prompt-stiri.md', 'Candidații sunt în `.stare-mac/stiri-candidati.json`.')
    vazute = set(incarca(VAZUTE_LOCAL, []))
    vazute.update(c['link'] for c in noi)
    salveaza(VAZUTE_LOCAL, sorted(vazute)[-5000:])


if __name__ == '__main__':
    main()
