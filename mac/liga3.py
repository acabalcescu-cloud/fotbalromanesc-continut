#!/usr/bin/env python3
"""Liga 3: rezumatul fiecărei serii după rundă. Rulează sâmbătă seara, duminică și luni dimineața, fără AI.

Runda se joacă vineri și sâmbătă. Pentru fiecare serie 1–8 care nu are încă articol pentru weekendul
curent (articole/<data>-liga3-seriaN-*.json, cu data vineri…luni), pornește câte o sesiune Claude.
O serie încercată fără succes se reîncearcă la rularea următoare.

Rulare manuală:  python3 mac/liga3.py                 (toate seriile fără articol)
                 python3 mac/liga3.py --doar-lista     (doar arată seriile)
                 python3 mac/liga3.py --serie 3        (doar Seria 3)
"""
import datetime as dt
import glob
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
from comun import DEPOZIT, acum, blocheaza, git_pull, jurnal, ruleaza_claude


def vinerea_rundei():
    """Vinerea weekendului curent (sau a celui care tocmai s-a încheiat, luni–joi)."""
    azi = acum().date()
    return azi - dt.timedelta(days=(azi.weekday() - 4) % 7)


def are_articol(serie, vineri):
    for z in range(4):  # vineri, sâmbătă, duminică, luni
        zi = vineri + dt.timedelta(days=z)
        if glob.glob(os.path.join(DEPOZIT, 'articole', f'{zi:%Y-%m-%d}-liga3-seria{serie}-*.json')):
            return True
    return False


def main():
    blocare = blocheaza('liga3')
    if not blocare:
        jurnal('liga3', 'O rulare anterioară încă lucrează, ies.')
        return
    git_pull()
    vineri = vinerea_rundei()
    serii = [int(sys.argv[sys.argv.index('--serie') + 1])] if '--serie' in sys.argv else range(1, 9)
    lipsa = [s for s in serii if not are_articol(s, vineri)]
    if '--doar-lista' in sys.argv:
        print(f'Weekendul din {vineri:%d.%m}: serii fără articol: {lipsa or "niciuna"}')
        return
    if not lipsa:
        jurnal('liga3', f'Toate seriile au articol pentru weekendul din {vineri:%d.%m}.')
        return
    for s in lipsa:
        git_pull()
        jurnal('liga3', f'Seria {s}, weekendul din {vineri:%d.%m}: pornesc Claude.')
        ruleaza_claude('liga3', 'prompt-liga3.md',
                       f'Seria de azi: **Seria {s}**. Weekendul rundei: vineri {vineri:%Y-%m-%d} și sâmbătă '
                       f'{vineri + dt.timedelta(days=1):%Y-%m-%d}. Fișierul de date: `date/liga-3-seria-{s}.json` '
                       f'(dacă nu există, îl creezi și adaugi `liga-3-seria-{s}` în `date/index.json`).',
                       max_turns=120, timeout=5400)


if __name__ == '__main__':
    main()
