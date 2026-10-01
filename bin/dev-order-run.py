#!/usr/bin/env python3
"""Повний прогін: кошик, вибір офісу Еконт, оформлення замовлення на стенді."""
from playwright.sync_api import sync_playwright

BASE = 'https://dev.dobavki.club'
PRODUCT = 67

with sync_playwright() as p:
    b = p.chromium.launch(args=['--no-sandbox'])
    page = b.new_page(viewport={'width': 1280, 'height': 1800}, locale='bg-BG')
    errors = []
    page.on('pageerror', lambda e: errors.append(str(e)))

    page.goto(f'{BASE}/?add-to-cart={PRODUCT}', wait_until='domcontentloaded', timeout=60000)
    page.goto(f'{BASE}/porachka-2/', wait_until='networkidle', timeout=90000)
    page.wait_for_timeout(2500)

    page.check('input[name="shipping_to"][value="econt"]')
    page.wait_for_timeout(1200)

    def opts(sel):
        return page.eval_on_selector(sel, 'e => e.options.length')

    # Перший район і місто, у яких реально є офіси - не кожна комбінація їх має.
    office = None
    for ri in range(1, opts('#econt_region_sel')):
        page.select_option('#econt_region_sel', index=ri)
        page.wait_for_timeout(900)
        for ci in range(1, min(opts('#econt_city_sel'), 6)):
            page.select_option('#econt_city_sel', index=ci)
            page.wait_for_timeout(900)
            if opts('#econt_office_sel') > 1:
                page.select_option('#econt_office_sel', index=1)
                page.wait_for_timeout(600)
                office = page.eval_on_selector('#econt_office_sel', 'e => e.options[e.selectedIndex].text')
                break
        if office:
            print('район:', page.eval_on_selector('#econt_region_sel', 'e => e.options[e.selectedIndex].text'),
                  '| місто:', page.eval_on_selector('#econt_city_sel', 'e => e.options[e.selectedIndex].text'))
            break
    print('обраний офіс:', (office or 'НЕ ЗНАЙДЕНО')[:90])

    fill = {
        '#billing_first_name': 'Клод',
        '#billing_last_name': 'Проверка',
        '#billing_phone': '0888123456',
        '#billing_email': 'claude-check@example.invalid',
    }
    for sel, val in fill.items():
        if page.query_selector(sel):
            page.fill(sel, val)
    page.wait_for_timeout(500)

    # Спосіб оплати: перший доступний.
    pay = page.query_selector_all('input[name="payment_method"]')
    print('способи оплати:', [page.evaluate('e => e.value', r) for r in pay])
    if pay:
        page.evaluate('e => e.click()', pay[0])
    terms = page.query_selector('#terms')
    if terms and terms.is_visible():
        terms.check()

    page.screenshot(path='/tmp/sesh-before-order.png', full_page=True)
    page.click('#place_order')
    try:
        page.wait_for_url('**/order-received/**', timeout=60000)
    except Exception:
        page.wait_for_timeout(8000)
    print('URL після оформлення:', page.url)
    body = page.inner_text('body')
    for marker in ('Благодаря', 'Thank you', 'поръчка', 'грешка', 'error'):
        if marker.lower() in body.lower():
            print('  маркер на сторінці:', marker)
    notices = page.query_selector_all('.woocommerce-error li, .woocommerce-error')
    for n in notices[:5]:
        print('  ПОВІДОМЛЕННЯ ПРО ПОМИЛКУ:', n.inner_text()[:160])
    page.screenshot(path='/tmp/sesh-after-order.png', full_page=True)
    print('помилок JS:', len(errors))
    for e in errors[:5]:
        print('   ', e[:200])
    b.close()
