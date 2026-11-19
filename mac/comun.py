"""Funcțiile comune ale rutinelor de pe Mac (fără inteligență artificială)."""
import datetime as dt
import fcntl
import json
import os
import re
import subprocess
import time
import unicodedata
from zoneinfo import ZoneInfo

DEPOZIT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
STARE = os.path.join(DEPOZIT, '.stare-mac')
JURNAL = os.path.join(STARE, 'jurnal.log')
RO = ZoneInfo('Europe/Bucharest')
UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124 Safari/537.36'
LIVESCORE = 'https://prod-public-api.livescore.com/v1/api/app'
UNELTE_CLAUDE = ['Read', 'Write', 'Edit', 'Glob', 'Grep', 'Bash(git:*)', 'Bash(python3:*)', 'Bash(curl:*)',
                 'Bash(ls:*)', 'Bash(cat:*)', 'Bash(head:*)', 'Bash(grep:*)', 'Bash(wc:*)', 'Bash(date:*)',
                 'WebSearch', 'WebFetch']
ERORI_CLAUDE = re.compile(r'api error|cloudflare|unauthori|authenticat|please run /login|oauth|invalid api key|'
                          r'credit balance|rate limit|overloaded|(error|eroare|status|http)\D{0,12}\b(401|403|429|5\d\d)\b', re.I)


def acum():
    return dt.datetime.now(RO)


def jurnal(rutina, msg):
    os.makedirs(STARE, exist_ok=True)
    rand = f"{acum():%Y-%m-%d %H:%M} [{rutina}] {msg}"
    print(rand)
    with open(JURNAL, 'a', encoding='utf-8') as f:
        f.write(rand + '\n')


def slug(s):
    s = ''.join(c for c in unicodedata.normalize('NFD', s.lower()) if unicodedata.category(c) != 'Mn')
    return re.sub(r'[^a-z0-9]+', '-', s).strip('-')


def curl(url, timeout=30):
    r = subprocess.run(['curl', '-s', '-L', '--max-time', str(timeout), '-A', UA, url],
                       capture_output=True, text=True, errors='ignore')
    return r.stdout


def curl_json(url):
    try:
        return json.loads(curl(url) or 'null')
    except ValueError:
        return None


def incarca(cale, implicit):
    try:
        with open(cale, encoding='utf-8') as f:
            return json.load(f)
    except (OSError, ValueError):
        return implicit


def salveaza(cale, date):
    os.makedirs(os.path.dirname(cale), exist_ok=True)
    with open(cale, 'w', encoding='utf-8') as f:
        json.dump(date, f, ensure_ascii=False, indent=1)


def blocheaza(nume):
    """Un singur proces pe rutină. Întoarce fișierul blocat (ținut deschis) sau None."""
    os.makedirs(STARE, exist_ok=True)
    f = open(os.path.join(STARE, f'.blocare-{nume}'), 'w')
    try:
        fcntl.flock(f, fcntl.LOCK_EX | fcntl.LOCK_NB)
        return f
    except OSError:
        return None


def git_pull():
    subprocess.run(['git', '-C', DEPOZIT, 'pull', '-q', '--rebase', 'origin', 'main'], capture_output=True)


def notifica(text, titlu='fotbalromanesc.ro'):
    text = text.replace('"', "'")[:200]
    subprocess.run(['osascript', '-e', f'display notification "{text}" with title "{titlu}"'], capture_output=True)


def ruleaza_claude(rutina, fisier_prompt, context, max_turns=80, timeout=3600):
    """Pornește Claude Code cu promptul din mac/<fisier_prompt> + contextul dat. Întoarce prima linie a raportului."""
    with open(os.path.join(DEPOZIT, 'mac', fisier_prompt), encoding='utf-8') as f:
        prompt = f.read() + '\n\n' + context
    start = time.time()
    try:
        r = subprocess.run(['claude', '-p', prompt, '--allowedTools', *UNELTE_CLAUDE, '--max-turns', str(max_turns),
                            '--output-format', 'text'], cwd=DEPOZIT, capture_output=True, text=True, timeout=timeout)
        raport = (r.stdout or r.stderr or '').strip()
    except subprocess.TimeoutExpired:
        raport = f'Timp depășit ({timeout}s)'
    prima = raport.splitlines()[0] if raport else 'Fără raport'
    jurnal(rutina, f'Claude a terminat în {int(time.time() - start)}s: {prima}')
    with open(os.path.join(STARE, f'ultimul-raport-{rutina}.txt'), 'w', encoding='utf-8') as f:
        f.write(raport)
    if ERORI_CLAUDE.search(prima):
        alerta = os.path.join(STARE, 'ultima-alerta-eroare')
        try:
            recenta = time.time() - os.path.getmtime(alerta) < 3 * 3600
        except OSError:
            recenta = False
        if not recenta:
            open(alerta, 'w').close()
            notifica('Claude nu a putut rula (autentificare sau rețea). Reîncerc singur. Dacă se repetă: claude, apoi /login.')
    elif not prima.lower().startswith('nimic nou'):
        notifica(prima)
    return prima
