"""Liga 3 din fluxul SuperScore (partenerul oficial): rezultatele, programul și clasamentul unei serii.

Rulare:  python3 -I unelte/superscore.py 4          (Seria 4: ultimele rezultate, runda următoare, clasament)
"""
import datetime as dt
import json
import re
import subprocess
import sys

UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36'
FLUX = 'https://scorealarm-stats.freetls.fastly.net/v2/soccer/'


def cere(url):
    return subprocess.run(['curl', '-s', '-L', '-m', '40', '-A', UA, url], capture_output=True, text=True).stdout


def main():
    serie = int(sys.argv[1]) if len(sys.argv) > 1 else 1
    pag = cere('https://superscore.live/ro-RO/fotbal/competitii/liga-3/i5ry2e11')
    m = re.search(r'[?&]season=([A-Za-z0-9]{10,40})', pag)
    if not m:
        sys.exit('Nu găsesc sezonul pe superscore.live')
    sezon = m.group(1)
    cl = json.loads(cere(FLUX + 'competitions/standings/season/rosuperscore/ro-RO?season-id=' + sezon))
    grup = next(g for g in cl['standings_groups'] if re.search(rf'Group\s+{serie}\b', g.get('name', '')))
    fx = json.loads(cere(FLUX + 'fixtures/season/rosuperscore/ro-RO?season-id=' + sezon + '&with-upcoming=yes'))
    ev = [e for e in fx['events'] if (e.get('table_id') or {}).get('value') == grup['id']]
    ev.sort(key=lambda e: e['match_date']['seconds'])
    acum = dt.datetime.now(dt.timezone.utc).timestamp()
    jucate = [e for e in ev if e['match_state'] == 2]
    viitoare = [e for e in ev if e['match_date']['seconds'] > acum and e['match_state'] == 0]
    ultima = jucate[-1]['week']['value'] if jucate else None
    print(f'Seria {serie} (SuperScore, sezon {sezon})')
    print(f'\nRunda {ultima}:')
    for e in jucate:
        if e['week']['value'] == ultima:
            s = next((x for x in e['scores'] if x['type'] == 0), None)
            print(f"  {e['team1']['name']} - {e['team2']['name']} {s['team1']}-{s['team2']}" if s else '')
    if viitoare:
        r = viitoare[0]['week']['value']
        print(f'\nRunda {r} (ora României):')
        for e in viitoare:
            if e['week']['value'] == r:
                t = dt.datetime.fromtimestamp(e['match_date']['seconds'], dt.timezone(dt.timedelta(hours=2)))
                print(f"  {t:%a %d.%m %H:%M}  {e['team1']['name']} - {e['team2']['name']}")
    print('\nClasament (meciuri, golaveraj, puncte | V-E-I | forma):')
    for r in grup['rows_total']:
        v = [x['value'].get('display_value') or x['value'].get('draw_value') for b in r['data'] for x in b['values']]
        print(f"  {r['rank']:>2}. {r['competitor_name']:<32} {v[0]:>2} {v[1]:>7} {v[2]:>3} | {v[3]}-{v[4]}-{v[5]} | {v[6]}")


if __name__ == '__main__':
    main()
