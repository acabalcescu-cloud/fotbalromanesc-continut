#!/usr/bin/env python3
"""Profilurile cluburilor (stadion, antrenor, lot): de două ori pe zi, câte câteva cluburi.
Fără AI, alege cluburile fără profil (sau cu profil mai vechi de 45 de zile), în ordinea
SuperLiga, Liga 2, Liga 3; apoi pornește Claude să le completeze din surse verificate.

Rulare manuală:  python3 mac/cluburi.py              (următoarele cluburi + Claude)
                 python3 mac/cluburi.py --doar-lista  (ce ar urma, fără Claude)
                 python3 mac/cluburi.py --club fcsb   (un club anume)
"""
import datetime as dt
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from comun import DEPOZIT, STARE, acum, blocheaza, curl_json, git_pull, incarca, jurnal, ruleaza_claude, salveaza

SITE = 'https://fotbalromanesc.ro/wp-json/fr/v1/cluburi'
PE_TURA = 5
VECHIME_ZILE = 45
INCERCARI = os.path.join(STARE, 'cluburi-incercate.json')


def prioritate(c):
    comp = c.get('competitie') or ''
    if comp == 'superliga':
        return 0
    if comp == 'liga-2':
        return 1
    if comp.startswith('liga-3'):
        return 2
    return 3


def de_facut(toate, incercate):
    azi = acum().date()
    rez = []
    for c in toate:
        p = incarca(os.path.join(DEPOZIT, 'date', 'cluburi', c['slug'] + '.json'), None)
        if p:
            try:
                vechime = (azi - dt.date.fromisoformat(str(p.get('actualizat', ''))[:10])).days
            except ValueError:
                vechime = 999
            if vechime < VECHIME_ZILE:
                continue
        # Un club încercat recent fără rezultat așteaptă 7 zile.
        ultima = incercate.get(c['slug'])
        if ultima and (azi - dt.date.fromisoformat(ultima)).days < 7:
            continue
        rez.append(c)
    rez.sort(key=lambda c: (prioritate(c), c['competitie'], c['nume']))
    return rez


def main():
    blocare = blocheaza('cluburi')
    if not blocare:
        return
    git_pull()
    toate = curl_json(SITE)
    if not isinstance(toate, list) or not toate:
        jurnal('cluburi', 'Nu am putut citi lista cluburilor de pe site.')
        return
    incercate = incarca(INCERCARI, {})
    if '--club' in sys.argv:
        cerut = sys.argv[sys.argv.index('--club') + 1]
        lista = [c for c in toate if c['slug'] == cerut]
    else:
        lista = de_facut(toate, incercate)[:PE_TURA]
    if '--doar-lista' in sys.argv:
        for c in de_facut(toate, incercate)[:30]:
            print(f"- {c['nume']} ({c['competitie']}) -> date/cluburi/{c['slug']}.json")
        return
    if not lista:
        jurnal('cluburi', 'Toate profilurile sunt la zi.')
        return
    salveaza(os.path.join(STARE, 'cluburi-de-facut.json'), lista)
    jurnal('cluburi', 'Profiluri: ' + ', '.join(c['nume'] for c in lista))
    ruleaza_claude('cluburi', 'prompt-cluburi.md', 'Cluburile de completat sunt în `.stare-mac/cluburi-de-facut.json`.',
                   max_turns=120)
    azi = acum().date().isoformat()
    for c in lista:
        incercate[c['slug']] = azi
    salveaza(INCERCARI, incercate)


if __name__ == '__main__':
    main()
