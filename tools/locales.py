#!/usr/bin/env python3
"""
Traduções do NPSphere (gettext), sem depender do pacote gettext.

    python3 tools/locales.py extract   # gera locales/npsphere.pot
    python3 tools/locales.py update    # atualiza os .po com o .pot
    python3 tools/locales.py compile   # gera os .mo a partir dos .po

Os textos do código-fonte estão em português (pt_BR): pt_BR.po e
pt_PT.po ficam sem tradução de propósito -- existem só para o GLPI não
cair no en_GB.mo para quem usa o GLPI em português. Lê as chamadas
__(), __s(), _x(), _sx(), _n(), _sn(), _nx() com o domínio 'npsphere' nos
arquivos PHP e Twig, e t('...') nos arquivos JS do plugin (ver
public/js/i18n.js).
"""
import os
import re
import struct
import sys
from collections import OrderedDict

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
LOCALES = os.path.join(RAIZ, 'locales')
DOMINIO = 'npsphere'
PASTAS = ['setup.php', 'hook.php', 'src', 'front', 'ajax', 'templates', 'public/js', 'install']

# função -> (posição do contexto, singular, plural) entre os argumentos
FUNCOES = {
    '__': (None, 0, None), '__s': (None, 0, None),
    '_x': (0, 1, None), '_sx': (0, 1, None),
    '_n': (None, 0, 1), '_sn': (None, 0, 1),
    '_nx': (0, 1, 2),
}
PLURAIS = {
    'pt_BR': 'nplurals=2; plural=(n > 1);',
    'pt_PT': 'nplurals=2; plural=(n != 1);',
    'en_GB': 'nplurals=2; plural=(n != 1);',
    'en_US': 'nplurals=2; plural=(n != 1);',
    'es_ES': 'nplurals=2; plural=(n != 1);',
}


def ler_string(src, i):
    """Lê um literal '...' ou "..." a partir de src[i]; devolve (texto, fim)."""
    q = src[i]
    j = i + 1
    out = []
    while j < len(src):
        c = src[j]
        if c == '\\' and j + 1 < len(src):
            n = src[j + 1]
            if q == "'":
                out.append(n if n in "'\\" else c + n)
            else:
                out.append({'n': '\n', 't': '\t', '"': '"', '\\': '\\', '$': '$'}.get(n, c + n))
            j += 2
            continue
        if c == q:
            return ''.join(out), j + 1
        out.append(c)
        j += 1
    raise ValueError('string sem fim')


def argumentos(src, i):
    """Argumentos de uma chamada a partir do '(' em src[i]. Cada argumento
    é um texto (se for um literal único, ou literais concatenados com . ou
    ~) ou None (expressão)."""
    args, atual, literal_ok, prof = [], [], True, 0
    j = i + 1
    while j < len(src):
        c = src[j]
        if c in '\'"':
            s, j = ler_string(src, j)
            atual.append(s)
            continue
        if c in '([{':
            prof += 1
            literal_ok = False
        elif c in ')]}':
            if prof == 0:
                args.append(''.join(atual) if literal_ok and atual else None)
                return args
            prof -= 1
        elif c == ',' and prof == 0:
            args.append(''.join(atual) if literal_ok and atual else None)
            atual, literal_ok = [], True
        elif not c.isspace() and c not in '.~':
            literal_ok = False
        j += 1
    return args


def arquivos():
    for p in PASTAS:
        caminho = os.path.join(RAIZ, p)
        if os.path.isfile(caminho):
            yield caminho
            continue
        for base, _, nomes in os.walk(caminho):
            for n in sorted(nomes):
                if n.endswith(('.php', '.twig', '.js')):
                    yield os.path.join(base, n)


def extrair():
    entradas = OrderedDict()  # (ctx, id) -> {'plural':..., 'refs':[...]}
    avisos = []
    padrao = re.compile(r'(?<![\w$>:.])(__s|__|_sx|_x|_sn|_nx|_n|t)\s*\(')
    for arq in sorted(arquivos()):
        src = open(arq, encoding='utf-8').read()
        rel = os.path.relpath(arq, RAIZ)
        eh_js = arq.endswith('.js')
        for m in padrao.finditer(src):
            nome = m.group(1)
            if (nome == 't') != eh_js:
                continue
            linha = src.count('\n', 0, m.start()) + 1
            try:
                args = argumentos(src, m.end() - 1)
            except ValueError:
                continue
            if nome == 't':
                spec, dominio = (None, 0, None), DOMINIO
                if not args or args[0] is None:
                    continue
            else:
                spec = FUNCOES[nome]
                nargs = 1 + (spec[0] is not None) + (spec[2] is not None) * 2
                if len(args) < nargs + 1:
                    continue  # sem domínio: texto do próprio GLPI
                dominio = args[nargs] if spec[2] is None else args[-1]
                if dominio != DOMINIO:
                    continue
            ctx = args[spec[0]] if spec[0] is not None else None
            sing = args[spec[1]]
            plur = args[spec[2]] if spec[2] is not None else None
            if sing is None or (spec[2] is not None and plur is None) or (spec[0] is not None and ctx is None):
                avisos.append(f'{rel}:{linha}: argumento não literal em {nome}()')
                continue
            e = entradas.setdefault((ctx, sing), {'plural': plur, 'refs': []})
            if plur and not e['plural']:
                e['plural'] = plur
            e['refs'].append(f'{rel}:{linha}')
    return entradas, avisos


def esc(s):
    return s.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n').replace('\t', '\\t')


def po_str(chave, s):
    if '\n' in s.rstrip('\n') or len(s) > 70:
        partes = re.split(r'(?<=\n)', s)
        linhas = [f'{chave} ""']
        for p in partes:
            if p:
                linhas.append(f'"{esc(p)}"')
        return '\n'.join(linhas)
    return f'{chave} "{esc(s)}"'


def cabecalho(lang=None):
    h = ('Project-Id-Version: NPSphere\\n"\n"Report-Msgid-Bugs-To: '
         'https://github.com/MichaelRodriguesOficial/NPS-GLPi/issues\\n"\n'
         '"MIME-Version: 1.0\\n"\n"Content-Type: text/plain; charset=UTF-8\\n"\n'
         '"Content-Transfer-Encoding: 8bit\\n"\n')
    if lang:
        h += f'"Language: {lang}\\n"\n"Plural-Forms: {PLURAIS.get(lang, "nplurals=2; plural=(n != 1);")}\\n"\n'
    return 'msgid ""\nmsgstr ""\n"' + h


def gravar_pot(entradas):
    out = ['# Textos do plugin NPSphere para tradução.',
           '# Gerado por tools/locales.py extract -- não editar à mão.',
           '# Idioma de origem: português (Brasil).',
           cabecalho()]
    for (ctx, sing), e in entradas.items():
        bloco = ['#: ' + ' '.join(e['refs'][:6])]
        if ctx is not None:
            bloco.append(po_str('msgctxt', ctx))
        bloco.append(po_str('msgid', sing))
        if e['plural']:
            bloco.append(po_str('msgid_plural', e['plural']))
            bloco += ['msgstr[0] ""', 'msgstr[1] ""']
        else:
            bloco.append('msgstr ""')
        out.append('\n'.join(bloco) + '\n')
    open(os.path.join(LOCALES, f'{DOMINIO}.pot'), 'w', encoding='utf-8').write('\n'.join(out))


def ler_po(caminho):
    """Devolve OrderedDict (ctx, id) -> {'plural', 'str': [..]} (sem o cabeçalho)."""
    entradas = OrderedDict()
    if not os.path.exists(caminho):
        return entradas
    atual, campo = {}, None

    def fecha():
        if 'msgid' in atual and atual['msgid'] != '':
            strs = [atual[k] for k in sorted(k for k in atual if k.startswith('msgstr'))]
            entradas[(atual.get('msgctxt'), atual['msgid'])] = {
                'plural': atual.get('msgid_plural'), 'str': strs or ['']}

    for linha in open(caminho, encoding='utf-8').read().split('\n') + ['']:
        linha = linha.strip()
        if not linha or linha.startswith('#'):
            if not linha and atual:
                fecha()
                atual, campo = {}, None
            continue
        m = re.match(r'^(msgctxt|msgid_plural|msgid|msgstr(?:\[(\d)\])?)\s+"(.*)"$', linha)
        if m:
            campo = m.group(1)
            if m.group(2) is not None:
                campo = f'msgstr[{m.group(2)}]'
            atual[campo] = des(m.group(3))
        elif linha.startswith('"') and campo:
            atual[campo] += des(linha[1:-1])
    return entradas


def des(s):
    return re.sub(r'\\(.)', lambda m: {'n': '\n', 't': '\t'}.get(m.group(1), m.group(1)), s)


def gravar_po(lang, entradas_pot, traducoes):
    out = [f'# Tradução do NPSphere: {lang}.', cabecalho(lang)]
    faltando = 0
    for (ctx, sing), e in entradas_pot.items():
        t = traducoes.get((ctx, sing))
        bloco = ['#: ' + ' '.join(e['refs'][:6])]
        if ctx is not None:
            bloco.append(po_str('msgctxt', ctx))
        bloco.append(po_str('msgid', sing))
        if e['plural']:
            bloco.append(po_str('msgid_plural', e['plural']))
            strs = (t['str'] if t else []) + ['', '']
            for i in range(2):
                bloco.append(po_str(f'msgstr[{i}]', strs[i]))
            faltando += not (t and all(t['str'][:2]))
        else:
            s = t['str'][0] if t else ''
            bloco.append(po_str('msgstr', s))
            faltando += not s
        out.append('\n'.join(bloco) + '\n')
    open(os.path.join(LOCALES, f'{lang}.po'), 'w', encoding='utf-8').write('\n'.join(out))
    return faltando


def gravar_mo(lang):
    entradas = ler_po(os.path.join(LOCALES, f'{lang}.po'))
    cab = open(os.path.join(LOCALES, f'{lang}.po'), encoding='utf-8').read()
    m = re.search(r'msgid ""\nmsgstr ""\n((?:".*"\n)+)', cab)
    header = des(''.join(l[1:-1] for l in m.group(1).strip().split('\n'))) if m else ''
    pares = {b'': header.encode('utf-8')}
    for (ctx, sing), e in entradas.items():
        if not any(e['str']):
            continue
        chave = sing if not e['plural'] else sing + '\0' + e['plural']
        if ctx is not None:
            chave = ctx + '\x04' + chave
        pares[chave.encode('utf-8')] = '\0'.join(e['str']).encode('utf-8')
    chaves = sorted(pares)
    ids = b''
    strs = b''
    oids, ostrs = [], []
    for k in chaves:
        oids.append((len(k), len(ids)))
        ids += k + b'\0'
        v = pares[k]
        ostrs.append((len(v), len(strs)))
        strs += v + b'\0'
    n = len(chaves)
    inicio_ids = 7 * 4 + 16 * n
    inicio_strs = inicio_ids + len(ids)
    tab = []
    for l, o in oids:
        tab += [l, o + inicio_ids]
    for l, o in ostrs:
        tab += [l, o + inicio_strs]
    dados = struct.pack('Iiiiiii', 0x950412de, 0, n, 7 * 4, 7 * 4 + n * 8, 0, 0)
    dados += struct.pack(f'{len(tab)}i', *tab) + ids + strs
    open(os.path.join(LOCALES, f'{lang}.mo'), 'wb').write(dados)
    return len(pares) - 1


def idiomas():
    return sorted(f[:-3] for f in os.listdir(LOCALES) if f.endswith('.po'))


if __name__ == '__main__':
    acao = sys.argv[1] if len(sys.argv) > 1 else ''
    if acao == 'extract':
        entradas, avisos = extrair()
        gravar_pot(entradas)
        for a in avisos:
            print('aviso:', a)
        print(f'{len(entradas)} textos em locales/{DOMINIO}.pot')
    elif acao == 'update':
        entradas, _ = extrair()
        for lang in (sys.argv[2:] or idiomas()):
            falta = gravar_po(lang, entradas, ler_po(os.path.join(LOCALES, f'{lang}.po')))
            if lang.startswith('pt_'):
                print(f'{lang}: idioma de origem (os textos do código valem como estão)')
            else:
                print(f'{lang}: {len(entradas) - falta}/{len(entradas)} traduzidos')
    elif acao == 'compile':
        for lang in idiomas():
            print(f'{lang}.mo: {gravar_mo(lang)} textos')
    else:
        print(__doc__)
