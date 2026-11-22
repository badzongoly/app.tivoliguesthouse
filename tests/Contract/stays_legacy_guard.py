"""CR01 contract for legacy routes, including an incomplete stay schema."""
import http.cookiejar
import os
import pathlib
import re
import socket
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[2]
PHP = 'C:/xampp/php/php.exe'
with socket.socket() as listener:
    listener.bind(('127.0.0.1', 0))
    port = listener.getsockname()[1]
origin = f'http://127.0.0.1:{port}'
env = dict(os.environ, TIVOLI_TEST_MODE='1', TIVOLI_TEST_ROUTE_PORT=str(port))

HASH_SOURCE = r"""<?php
require 'bootstrap/security.php';
$r=access_service()->repo;
if ($r->run('SELECT @@port')->fetchColumn()!=3307
    || $r->run('SELECT DATABASE()')->fetchColumn()!=='tivoli_auth_test'
    || $r->run('SELECT marker FROM security_test_marker')->fetchColumn()!=='synthetic-auth-only-v1') exit(2);
foreach (['bookings'=>'id','rooms'=>'id','payments'=>'id','booking_extras'=>'id','extras'=>'id',
          'expenses'=>'id','stock_movements'=>'id','stay_receipts'=>'payment_id','stay_refunds'=>'id',
          'stay_departures'=>'booking_id','finance_events'=>'id','finance_schema'=>'id'] as $table=>$key) {
    $rows=$r->run("SELECT * FROM $table ORDER BY $key")->fetchAll();
    echo $table,':',hash('sha256',json_encode($rows,JSON_THROW_ON_ERROR)),"\n";
}
echo 'denials:',$r->run("SELECT COUNT(*) FROM security_audit WHERE action='stay.legacy_write_denied'")->fetchColumn(),"\n";
"""


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *args, **kwargs):
        return None


def evidence():
    rows = subprocess.check_output([PHP], input=HASH_SOURCE.encode(), cwd=ROOT, env=env).decode().splitlines()
    assert len(rows) == 13, 'Incomplete protected row evidence'
    return rows[:-1], int(rows[-1].split(':')[1])


def request(path, method='GET', fields=None):
    data = urllib.parse.urlencode(fields).encode() if fields is not None else None
    try:
        response = browser.open(urllib.request.Request(origin+path, data=data, method=method), timeout=15)
    except urllib.error.HTTPError as failure:
        response = failure
    assert response.headers.get('X-Tivoli-Test-Mode') == 'synthetic'
    return response.code, response.headers, response.read().decode('utf-8')


browser = urllib.request.build_opener(NoRedirect(), urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
with subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', 'public',
                       'tests/Fixtures/stay_marker_overlay.php'],
                      cwd=ROOT, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL) as server:
    try:
        for _ in range(30):
            try:
                status, _, login = request('/auth/login.php')
                assert status == 200
                break
            except urllib.error.URLError:
                time.sleep(.15)
        else:
            raise RuntimeError('Synthetic legacy route server did not start')
        assert request('/rooms.php?delete=2147483647')[0] == 302
        csrf = re.search(r'name="csrf" value="([^"]+)"', login)[1]
        assert request('/auth/login.php', 'POST', {
            'csrf': csrf, 'email': 'admin@example.invalid', 'password': 'Synthetic Admin Passphrase 123!'
        })[0] == 302
        assert request('/bookings.php?export=csv')[0] == 302
        assert request('/occupancy.php')[0] == 200
        assert request('/admin_recovery.php')[0] == 200
        assert request('/bookings.php?_test_no_stay_marker=1&export=csv')[0] == 200

        before, denials = evidence()
        probes = 0
        for suffix in ['', '&_test_no_stay_marker=1']:
            for route in ['bookings', 'checkin', 'checkout', 'rooms', 'extras', 'stock', 'admin_recovery']:
                path = f'/{route}.php?probe=1' + suffix
                assert request(path, 'POST', {'action': 'legacy_probe'})[0] == 409, path
                probes += 1
            for route in ['rooms', 'extras']:
                for method in ['GET', 'HEAD']:
                    path = f'/{route}.php?delete=2147483647' + suffix
                    assert request(path, method)[0] == 409, (method, path)
                    probes += 1
        after, audited = evidence()
        assert after == before, 'Protected rows changed'
        assert audited-denials == probes, 'Every blocked authenticated write must be audited'
        print(f'PASS {probes} denials and audits, preserved reads, and 12 unchanged full-table row hashes')
    finally:
        server.terminate()

