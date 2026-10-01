#!/usr/bin/env python3
"""Живий прогін чекауту стенда: що намалював плагін і що сказала консоль."""
import sys
from playwright.sync_api import sync_playwright

BASE = 'https://dev.dobavki.club'
PRODUCT = 67

with sync_playwright() as p:
    b = p.chromium.launch(args=['--no-sandbox'])
    page = b.new_page(viewport={'width': 1280, 'height': 1600}, locale='bg-BG')
    errors, console = [], []
    page.on('pageerror', lambda e: errors.append(str(e)))
    page.on('console', lambda m: console.append(f'{m.type}: {m.text}') if m.type in ('error', 'warning') else None)

    page.goto(f'{BASE}/?add-to-cart={PRODUCT}', wait_until='domcontentloaded', timeout=60000)
    page.goto(f'{BASE}/porachka-2/', wait_until='networkidle', timeout=90000)
    page.wait_for_timeout(2500)

    print('заголовок:', page.title())
    for sel, name in [
        ('#shipping_to', 'селектор способу доставки'),
        ('#speedy_region_sel', 'Speedy: район'),
        ('#speedy_city_sel', 'Speedy: місто'),
        ('#speedy_office_sel', 'Speedy: офіс'),
        ('#econt_region_sel', 'Еконт: район'),
        ('#econt_city_sel', 'Еконт: місто'),
        ('#econt_office_sel', 'Еконт: офіс'),
    ]:
        el = page.query_selector(sel)
        if not el:
            print(f'  {name:28} НЕМА')
            continue
        opts = page.eval_on_selector(sel, 'e => e.tagName === "SELECT" ? e.options.length : -1')
        print(f'  {name:28} є, видимий: {el.is_visible()}, опцій: {opts}')

    have = page.evaluate('() => ({speedy: typeof speedyData, econt: typeof econtData, jq: typeof jQuery})')
    print('дані в JS:', have)

    # Проклацування: обрати Speedy (радіо shipping_to), далі район, місто, офіс.
    radios = page.query_selector_all('input[name="shipping_to"]')
    print('радіо способів доставки:', len(radios),
          [page.evaluate('e => e.value', r) for r in radios])
    try:
        if radios:
            radios[0].check()
            page.wait_for_timeout(1200)
            print('обрано:', page.evaluate('e => e.value', radios[0]))
        for sel in ('#speedy_region_sel', '#speedy_city_sel', '#speedy_office_sel'):
            el = page.query_selector(sel)
            if not el:
                print(f'  {sel}: нема')
                continue
            vis = el.is_visible()
            n = page.eval_on_selector(sel, 'e => e.options.length')
            if vis and n > 1:
                page.select_option(sel, index=1)
                page.wait_for_timeout(1200)
                print(f'  {sel}: обрано "{page.eval_on_selector(sel, "e => e.options[e.selectedIndex].text")}" (опцій {n})')
            else:
                print(f'  {sel}: видимий {vis}, опцій {n}')
    except Exception as e:
        print('проклацування впало:', e)

    page.screenshot(path='/tmp/sesh-checkout.png', full_page=True)
    print('скрін: /tmp/sesh-checkout.png')
    print('--- помилки JS:', len(errors))
    for e in errors[:10]:
        print('   ', e[:200])
    print('--- консоль (error/warning):', len(console))
    for c in console[:10]:
        print('   ', c[:200])
    b.close()
