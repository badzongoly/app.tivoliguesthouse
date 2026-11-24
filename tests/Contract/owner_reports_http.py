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
            response = self.http.open('http://127.0.0.1:8099' + path, data, timeout=90)
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
assert sidebar.count('title="Dashboard"') == 1 and 'title="Today"' not in sidebar
assert re.search(r'class="app-brand"[^>]+href="[^"]+/owner_reports.php"', sidebar)
assert '<title>Dashboard · Tivoli Guesthouse</title>' in body
assert 'aria-label="Dashboard reports"' in body and 'Report Date' in body
for removed in ('Tivoli Guesthouse · Owner Workspace', '<h1>Dashboard</h1>', 'Report Period',
                'Open Payment Log for This Period', 'Open Owner Exceptions', 'Accra property time'):
    assert removed not in body, removed
print('PASS administrator home, old dashboard bookmark and sidebar open Dashboard')
today_input = re.search(r'name="end" value="(\d{4}-\d{2}-\d{2})" max="\1"', body)
assert today_input and 'Current snapshot' in body and 'Open Room Board' in body
status, _, historical = admin.open(route + '?view=daily&end=2014-01-28')
assert status == 200 and 'name="end" value="2014-01-28"' in historical
assert 'Recorded occupancy at the end of 28/01/2014' in historical
assert 'Current snapshot' not in historical and 'Open Room Board' not in historical
assert 'Review Cash &amp; MoMo Discrepancies' not in historical
assert 'view=daily&amp;start=2014-01-28&amp;end=2014-01-28&amp;detail=arrivals' in historical
print('PASS Daily Overview defaults to today and selected dates scope historical room status and drill-downs')
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

for query, title in [
    ('view=daily&end=2014-01-28&detail=arrivals','Recorded Arrivals'),
    ('view=daily&end=2014-01-28&detail=departures','Recorded Departures'),
    ('view=daily&end=2014-01-28&detail=room-charges','Room Charges at Departure'),
    ('view=daily&end=2014-01-28&detail=momo','MoMo Receipt Coverage'),
    ('view=cash&start=2014-01-27&end=2014-02-02&method=card&detail=refunds','Recorded Refunds'),
    ('view=cash&start=2014-01-27&end=2014-02-02&method=cash&detail=custody','Tracked Cash Receipts'),
    ('view=cash&start=2014-01-27&end=2014-02-02&detail=deposits','Cash Deposit Movements'),
    ('view=monthly&month=2014-01&detail=expenses','Recorded Expenses'),
]:
    status, headers, body = admin.open(route + '?' + query)
    assert status == 200 and 'id="source-detail"' in body and title in body, (query, status)
    assert headers.get('Cache-Control') == 'no-store' and 'Private synthetic guest' not in body
    if 'method=' in query:
        link = re.search(r'<a href="([^"]+)">Back to Report</a>', body)[1]
        assert 'method=' in link and 'start=2014-01-27' in link and 'end=2014-02-02' in link
    print('PASS drill-down ' + title, flush=True)
assert clerk.open(route+'?view=cash&detail=refunds')[0] == 403
assert admin.open(route+'?view=cash&detail=expenses')[0] == 400
assert admin.open(route+'?view=monthly&detail=expenses&detail_category[]=1')[0] == 400
print('PASS drill-down authorisation and filter validation')
