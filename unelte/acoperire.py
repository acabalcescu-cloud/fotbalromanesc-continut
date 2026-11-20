"""Cât de prezent e un subiect în presa sportivă națională în ultimele 24 de ore (Google News).

Rulare:  python3 -I unelte/acoperire.py "Reghecampf FCSB" "Lucescu convocare" ...
Pentru fiecare căutare: numărul de publicații naționale distincte și numărul de articole, plus titlurile.
Scorul de acoperire = numărul de publicații naționale distincte (o publicație cu 5 articole contează o dată).
"""
import email.utils, datetime as dt, html, subprocess, sys, urllib.parse, xml.etree.ElementTree as ET

NATIONALE = ['gsp.ro', 'prosport.ro', 'digisport.ro', 'sport.ro', 'fanatik.ro', 'as.ro', 'primasport.ro',
             'golazo.ro', 'iamsport.ro', 'telekomsport.ro', 'orangesport.ro', 'sportpesurse.ro', 'spotmedia.ro',
             'gazetasporturilor.ro', 'agerpres.ro', 'hotnews.ro', 'digi24.ro', 'mediafax.ro', 'libertatea.ro',
             'adevarul.ro', 'stiripesurse.ro', 'antenasport.ro', 'playsport.ro', 'fotbalpentrutine.ro', 'cancan.ro',
             'romaniatv.net', 'b1tv.ro', 'g4media.ro', 'frf.ro', 'lpf.ro']
UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36'


def acoperire(q, ore=24):
    url = 'https://news.google.com/rss/search?q=' + urllib.parse.quote(q + ' when:1d') + '&hl=ro&gl=RO&ceid=RO:ro'
    xml = subprocess.run(['curl', '-s', '-L', '--max-time', '30', '-A', UA, url], capture_output=True, text=True, errors='ignore').stdout
    try:
        root = ET.fromstring(xml.encode('utf-8', 'ignore'))
    except ET.ParseError:
        return {'cautare': q, 'publicatii': 0, 'articole': 0, 'titluri': []}
    limita = dt.datetime.now(dt.timezone.utc) - dt.timedelta(hours=ore)
    publicatii, titluri = set(), []
    for it in root.iter('item'):
        try:
            data = email.utils.parsedate_to_datetime(it.findtext('pubDate') or '')
        except (TypeError, ValueError):
            continue
        if data < limita:
            continue
        src = it.find('source')
        dom = urllib.parse.urlparse(src.get('url', '') if src is not None else '').netloc.lower().removeprefix('www.')
        if not any(dom == n or dom.endswith('.' + n) for n in NATIONALE):
            continue
        publicatii.add(dom)
        titluri.append(f"{dom}: {html.unescape(it.findtext('title') or '')}")
    return {'cautare': q, 'publicatii': len(publicatii), 'articole': len(titluri), 'titluri': titluri[:15]}


if __name__ == '__main__':
    for q in sys.argv[1:]:
        r = acoperire(q)
        print(f"===== {r['cautare']}: {r['publicatii']} publicatii nationale, {r['articole']} articole")
        print('\n'.join(r['titluri']))
