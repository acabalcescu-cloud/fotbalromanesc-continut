#!/usr/bin/env python3
"""Liga 2: cronica fiecărui meci terminat. Rulează la 10 minute, fără AI.

Pornește Claude doar dacă există meciuri terminate de cel puțin 2 ore (și de cel mult 36),
fără cronică în articole/. Un meci încercat fără succes se reîncearcă după 60 de minute.

Rulare manuală:  python3 mac/liga2.py              (verificare + Claude dacă e cazul)
                 python3 mac/liga2.py --doar-lista  (meciurile care ar primi cronică)
"""
import datetime as dt
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from comun import (DEPOZIT, LIVESCORE, RO, STARE, acum, blocheaza, curl_json, git_pull, incarca, jurnal,
                   ruleaza_claude, salveaza, slug)

INCERCARI = os.path.join(STARE, 'liga2-incercari.json')
MAX_PE_RULARE = 6


def meciuri_de_scris():
    d = curl_json(LIVESCORE + '/stage/soccer/romania/liga-ii/0?MD=1')
    if not d or not d.get('Stages'):
        jurnal('liga2', 'LiveScore nu a răspuns.')
        return []
    incercari = incarca(INCERCARI, {})
    noi = []
    for e in d['Stages'][0].get('Events', []):
        if e.get('Eps') not in ('FT', 'AET', 'AP'):
            continue
        try:
            start = dt.datetime.strptime(str(e['Esd'])[:14], '%Y%m%d%H%M%S').replace(tzinfo=dt.timezone.utc).astimezone(RO)
        except (KeyError, ValueError):
            continue
        ore = (acum() - start).total_seconds() / 3600
        if ore < 2 or ore > 36:
            continue
        gazde, oaspeti = e['T1'][0]['Nm'], e['T2'][0]['Nm']
        ident = f"{start:%Y-%m-%d}-{slug(gazde)}-{slug(oaspeti)}"
        if os.path.exists(os.path.join(DEPOZIT, 'articole', ident + '.json')):
            continue
        if acum().timestamp() - incercari.get(ident, 0) < 3600:
            continue
        noi.append({'id': ident, 'eid': e['Eid'], 'start': start.isoformat(), 'runda': e.get('ErnInf', ''),
                    'gazde_livescore': gazde, 'oaspeti_livescore': oaspeti,
                    'scor': [int(e['Tr1']), int(e['Tr2'])], 'pauza': [e.get('Trh1'), e.get('Trh2')]})
    return noi[:MAX_PE_RULARE]


def main():
    blocare = blocheaza('liga2')
    if not blocare:
        jurnal('liga2', 'O rulare anterioară încă lucrează, ies.')
        return
    git_pull()
    meciuri = meciuri_de_scris()
    if '--doar-lista' in sys.argv:
        for m in meciuri:
            print(m['id'], m['scor'])
        return
    if not meciuri:
        return
    salveaza(os.path.join(STARE, 'liga2-meciuri.json'), meciuri)
    jurnal('liga2', f"{len(meciuri)} meciuri fără cronică: {', '.join(m['id'] for m in meciuri)}. Pornesc Claude.")
    incercari = incarca(INCERCARI, {})
    for m in meciuri:
        incercari[m['id']] = acum().timestamp()
    salveaza(INCERCARI, {k: v for k, v in incercari.items() if acum().timestamp() - v < 7 * 86400})
    ruleaza_claude('liga2', 'prompt-liga2.md', 'Meciurile de azi (și din `.stare-mac/liga2-meciuri.json`):\n' +
                   '\n'.join(f"- {m['id']}: {m['gazde_livescore']} {m['scor'][0]}-{m['scor'][1]} {m['oaspeti_livescore']}, "
                             f"etapa {m['runda']}, start {m['start']}, Eid {m['eid']}" for m in meciuri))


if __name__ == '__main__':
    main()
