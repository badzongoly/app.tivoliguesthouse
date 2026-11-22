"""Read-only reports contract on the marked synthetic server."""
import http.cookiejar
import json
import pathlib
import re
import urllib.error
import urllib.parse
import urllib.request


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


class Browser:
    def __init__(self):
        self.http = urllib.request.build_opener(
            NoRedirect(), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))

    def open(self, path, fields=None):
        data = None if fields is None else urllib.parse.urlencode(fields).encode()
        try:
            response = self.http.open('http://127.0.0.1:8099' + path, data, timeout=15)
        except urllib.error.HTTPError as failure:
            response = failure
        assert response.headers.get('X-Tivoli-Test-Mode') == 'synthetic'
        body = response.read().decode('utf-8')
        assert not re.search(r'Fatal error|Parse error|SQLSTATE\[|Warning:', body), path
        return response.code, response.headers, body

    def login(self, email, password):
        token = re.search(r'name="csrf" value="([a-f0-9]{64})"', self.open('/auth/login.php')[2])[1]
        status, headers, _ = self.open('/auth/login.php', {'csrf': token, 'email': email, 'password': password})
        assert status == 302
        return headers['Location']


route = '/owner_reports.php'
assert Browser().open(route)[0] == 302
print('PASS anonymous request redirects to sign-in')
fixture = json.loads((pathlib.Path(__file__).resolve().parents[2] / '.local-setup/finance-fixture.json').read_text(encoding='utf-8'))
clerk = Browser()
assert clerk.login(fixture['clerk_email'], 'Synthetic Finance Passphrase 123!').endswith('/dashboard.php')
assert clerk.open('/dashboard.php')[0] == 200
assert clerk.open(route)[0] == 403 and clerk.open(route + '?view=cash&export=csv')[0] == 403
assert 'href="http://127.0.0.1:8099/owner_reports.php"' not in clerk.open('/dashboard.php')[2]
print('PASS clerk cannot open reports and sees no sidebar link')
admin = Browser()
assert admin.login('admin@example.invalid', 'Synthetic Admin Passphrase 123!').endswith(route)
for entry in ['/auth/login.php', '/dashboard.php']:
    status, headers, _ = admin.open(entry)
    assert status == 302 and headers['Location'].endswith(route), entry
body = admin.open(route)[2]
sidebar = re.search(r'<aside class="app-sidebar".*?</aside>', body, re.S)[0]
assert sidebar.count('title="Owner Reports"') == 1 and 'title="Today"' not in sidebar
assert re.search(r'class="app-brand"[^>]+href="[^"]+/owner_reports.php"', sidebar)
print('PASS administrator home, old dashboard bookmark and sidebar open Owner Reports')
for view, text in [('daily', 'Room Status'), ('room', 'Room-Night Calendar'),
                   ('cash', 'Cash by Recorder'), ('extras', 'Charge Detail'), ('monthly', 'Six-Month Comparison')]:
    status, headers, body = admin.open(route + '?view=' + view)
    assert status == 200 and text in body and headers.get('Cache-Control') == 'no-store', (view, status, body[-2000:])
    assert 'Private synthetic guest' not in body
    print('PASS administrator ' + view + ' view, private caching and guest-name minimisation')
assert admin.open(route, {})[0] == 405
assert admin.open(route + '?export=csv')[0] == 400
assert admin.open(route + '?view=extras&start=2026-02-30')[0] == 400
assert admin.open(route + '?view=room&room[]=1')[0] == 400
status, _, body = admin.open(route + '?view=%3Cscript%3Ebad%3C%2Fscript%3E')
assert status == 400 and '<script>bad</script>' not in body
print('PASS writes, unsupported export, malformed filters and reflected script rejected')
assert admin.open('/reports.php')[0] == 200 and admin.open('/extra_sales.php?export=csv')[0] == 200
print('PASS legacy report and extras CSV still reachable')
