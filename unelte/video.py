"""Caută pe YouTube rezumatul video oficial al unui meci și îl potrivește după echipe, scor și dată.

Doar canale oficiale (ligă, federație, televiziuni cu drepturi). Reîncărcările altor canale sunt ignorate.

Rulare:
  python3 -I unelte/video.py "Farul Constanța" "Universitatea Cluj" 2-3 2026-11-20
  python3 -I unelte/video.py "România" "Polonia" 2-1 2026-11-14 --competitie nationala
Afișează videoclipul găsit (id, canal, titlu) ca JSON, sau nimic.
Pentru a-l adăuga pe site: date/video.json → "video": { "<id articol>": [ {"id","titlu","canal","data"} ] }.
"""
import datetime as dt
import html
import json
import re
import subprocess
import sys
import time
import unicodedata
import urllib.parse

UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36'

# Canalele oficiale, în ordinea preferinței, pe competiții.
CANALE = {
    'superliga': ['Superliga România', 'Prima Sport', 'Orange Sport Romania', 'Digi Sport'],
    'liga-2': ['FRF TV', 'Casa Pariurilor', 'Prima Sport', 'Digi Sport', 'Orange Sport Romania'],
    'liga-3': ['FRF TV'],
    'nationala': ['FRF TV', 'Prima Sport', 'Digi Sport', 'Orange Sport Romania'],
}
GENERICE = {'universitatea', 'bucuresti', 'fc', 'sc', 'cs', 'csm', 'acs', 'asu', 'afc', 'cso', 'csl', 'csc', 'csu', 'acso',
            'cf', 'fk', 'club', 'sportiv', 'scm', 'ksc', 'cfr2', 'municipal', 'echipa', 'nationala'}


def fara_diacritice(s):
    s = unicodedata.normalize('NFD', str(s).lower())
    return ''.join(c for c in s if unicodedata.category(c) != 'Mn')


def cuvinte(s):
    return [w for w in re.split(r'[^a-z0-9]+', fara_diacritice(s)) if w]


def distinctive(echipa):
    """Cuvintele după care recunoaștem echipa într-un titlu („Universitatea Cluj” → cluj; „CFR Cluj” → cfr, cluj)."""
    c = [w for w in cuvinte(echipa) if w not in GENERICE and not re.fullmatch(r'(19|20)\d\d', w)]
    return c or cuvinte(echipa)


def cauta(q):
    url = 'https://www.youtube.com/results?search_query=' + urllib.parse.quote(q)
    h = subprocess.run(['curl', '-s', '-m', '30', '-A', UA, '-H', 'Accept-Language: ro-RO,ro;q=0.9', url],
                       capture_output=True, text=True, errors='ignore').stdout
    m = re.search(r'var ytInitialData = (\{.*?\});</script>', h)
    if not m:
        return None  # pagină blocată sau schimbată
    rez = []

    def umbla(o):
        if isinstance(o, dict):
            v = o.get('videoRenderer')
            if v:
                rez.append({
                    'id': v.get('videoId', ''),
                    'titlu': html.unescape(''.join(r.get('text', '') for r in v.get('title', {}).get('runs', []))),
                    'canal': ''.join(r.get('text', '') for r in v.get('ownerText', {}).get('runs', [])),
                    'vechime': v.get('publishedTimeText', {}).get('simpleText', ''),
                })
            for x in o.values():
                umbla(x)
        elif isinstance(o, list):
            for x in o:
                umbla(x)

    umbla(json.loads(m.group(1)))
    return rez


def zile_vechime(text):
    """„acum 2 săpt.” → intervalul de zile posibil (YouTube rotunjește în jos): (14, 20). None dacă nu se înțelege."""
    t = fara_diacritice(text)
    m = re.search(r'(\d+)\s*(min|h|or|zi|sapt|lun|an)', t)
    if not m:
        return None
    n, u = int(m.group(1)), m.group(2)
    if u in ('min', 'h', 'or'):
        return 0, 1
    if u == 'zi':
        return n - 1, n + 1
    if u == 'sapt':
        return 7 * n - 1, 7 * n + 7
    if u == 'lun':
        return 30 * n - 3, 30 * n + 31
    return 365 * n - 30, 365 * n + 365


def embeddable(vid):
    r = subprocess.run(['curl', '-s', '-o', '/dev/null', '-w', '%{http_code}', '-m', '20',
                        f'https://www.youtube.com/oembed?url=https://www.youtube.com/watch?v={vid}&format=json'],
                       capture_output=True, text=True).stdout
    return r == '200'


def potriveste(gazde, oaspeti, scor, data_meci, competitie='superliga', interogare=None, azi=None):
    """Întoarce videoclipul oficial potrivit (dict) sau None."""
    canale = CANALE.get(competitie, CANALE['superliga'])
    azi = azi or dt.date.today()
    asteptat = (azi - data_meci).days
    a, b = [int(x) for x in scor.split('-')]
    q = interogare or f'rezumat {gazde} {oaspeti} {a}-{b}'
    gasite = cauta(q)
    if gasite is None:
        raise RuntimeError('YouTube nu a răspuns la căutare (limită de cereri?)')
    bune = []
    for v in gasite:
        if v['canal'] not in canale or not v['id']:
            continue
        t = fara_diacritice(v['titlu'])
        if not re.search(rf'(?<!\d){a}\s*[-–:]\s*{b}(?!\d)', t):
            continue
        tc = set(cuvinte(v['titlu']))
        if not (set(distinctive(gazde)) & tc and set(distinctive(oaspeti)) & tc):
            continue
        if 'live' in t.split() or 'conferinta' in t:
            continue
        vech = zile_vechime(v['vechime'])
        # Rezumatul apare în ziua meciului sau în următoarele 4 zile.
        if vech is None or vech[1] < asteptat - 4 or vech[0] > asteptat + 1:
            continue
        bune.append(v)
    bune.sort(key=lambda v: canale.index(v['canal']))
    for v in bune:
        if embeddable(v['id']):
            return {'id': v['id'], 'titlu': v['titlu'], 'canal': v['canal'], 'data': data_meci.isoformat()}
        time.sleep(1)
    return None


if __name__ == '__main__':
    arg = [x for x in sys.argv[1:] if not x.startswith('--')]
    comp = 'superliga'
    if '--competitie' in sys.argv:
        comp = sys.argv[sys.argv.index('--competitie') + 1]
        arg = [x for x in arg if x != comp]
    if len(arg) < 4:
        print(__doc__)
        sys.exit(1)
    r = potriveste(arg[0], arg[1], arg[2], dt.date.fromisoformat(arg[3]), comp)
    if r:
        print(json.dumps(r, ensure_ascii=False))
