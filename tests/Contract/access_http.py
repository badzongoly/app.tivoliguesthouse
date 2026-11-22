"""Synthetic HTTP tests. Never accepts another server or real staff credentials."""
import urllib.request, urllib.parse, urllib.error, http.cookiejar, re, json, pathlib, secrets, subprocess
BASE = 'http://127.0.0.1:8099'
ROOT = pathlib.Path(__file__).resolve().parents[2]
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl): return None
class Browser:
    def __init__(self):
        self.cookies = http.cookiejar.CookieJar()
        self.opener = urllib.request.build_opener(NoRedirect(), urllib.request.HTTPCookieProcessor(self.cookies))
    def request(self, path, data=None):
        request = urllib.request.Request(BASE+path, data=urllib.parse.urlencode(data or {}, doseq=True).encode() if data is not None else None)
        try: response = self.opener.open(request, timeout=20)
        except urllib.error.HTTPError as error: response = error
        body = response.read().decode('utf-8', 'replace')
        if response.headers.get('X-Tivoli-Test-Mode') != 'synthetic': raise RuntimeError('Refusing an unverified HTTP test server')
        if any(x in body for x in ['Fatal error', 'Warning:', 'Parse error', 'SQLSTATE[']): raise RuntimeError('Unexpected application error')
        return response.code, response.headers, body
    def csrf(self, path):
        status, headers, body = self.request(path)
        match = re.search(r'name="csrf" value="([a-f0-9]{64})"', body)
        if not match: raise RuntimeError('Missing CSRF token')
        return match.group(1)
    def session_id(self): return next((c.value for c in self.cookies if c.name == 'PHPSESSID'), None)
count = 0
def check(value, label):
    global count
    if not value: raise RuntimeError('FAIL '+label)
    count += 1; print('PASS '+label, flush=True)
admin = Browser(); csrf = admin.csrf('/auth/login.php'); before = admin.session_id()
status, headers, body = admin.request('/auth/login.php', {'email':'admin@example.invalid','password':'Synthetic Admin Passphrase 123!'})
check(status == 403, 'login rejects missing CSRF')
status, headers, body = admin.request('/auth/login.php', {'email':'admin@example.invalid','password':'Synthetic Admin Passphrase 123!','csrf':csrf})
check(status == 302 and headers['Location'].endswith('/owner_reports.php'), 'administrator login opens Owner Reports')
check(admin.session_id() != before, 'successful login regenerates session ID')
status, headers, body = admin.request('/users.php')
check(status == 200 and 'Staff and access' in body and 'Create role' in body, 'management screen renders authenticated forms')
check('data-password-toggle' in body, 'password visibility buttons present')
status, headers, body = admin.request('/audit.php')
check(status == 200 and 'Audit trail' in body, 'audit viewer renders')
status, headers, body = admin.request('/audit.php?actor=not-a-number')
check(status == 400, 'audit viewer validates filters')
status, headers, body = admin.request('/auth/logout.php')
check(status == 200 and admin.request('/users.php')[0] == 200, 'GET logout does not end session')
check(admin.request('/auth/logout.php', {})[0] == 403, 'POST logout requires CSRF')
check(admin.request('/users.php')[0] == 200, 'invalid logout preserves session')
email = 'http-'+secrets.token_hex(5)+'@example.invalid'
temporary = 'Synthetic HTTP Temporary Passphrase 123!'
csrf = admin.csrf('/users.php')
status, headers, body = admin.request('/users.php', {'csrf':csrf,'action':'save_user','name':'Synthetic HTTP Staff','email':email,'password':temporary,'reason':'Synthetic HTTP account test','current_password':'Synthetic Admin Passphrase 123!'})
check(status == 302, 'staff creation through authorized HTTP form')
staff = Browser(); csrf = staff.csrf('/auth/login.php')
status, headers, body = staff.request('/auth/login.php', {'csrf':csrf,'email':email,'password':temporary})
check(status == 302 and 'change-password.php' in headers['Location'], 'new account forced to change password')
check('change-password.php' in staff.request('/users.php')[1].get('Location',''), 'temporary account cannot bypass password change')
csrf = staff.csrf('/auth/change-password.php'); changed = 'Synthetic HTTP Changed Passphrase 456!'
status, headers, body = staff.request('/auth/change-password.php', {'csrf':csrf,'current_password':temporary,'password':changed,'confirm_password':changed})
check(status == 302 and 'login.php?changed=1' in headers['Location'], 'password change signs current browser out')
csrf = staff.csrf('/auth/login.php'); staff.request('/auth/login.php', {'csrf':csrf,'email':email,'password':changed})
check(staff.request('/users.php')[0] == 403, 'clerk cannot reach management directly')
check(staff.request('/audit.php')[0] == 403, 'clerk cannot read audit trail')
check(staff.request('/payments.php')[0] == 403, 'clerk cannot bypass report permission through direct URL')
recover = Browser(); csrf = recover.csrf('/auth/forgot-password.php')
status, headers, known = recover.request('/auth/forgot-password.php', {'csrf':csrf,'email':email})
status, headers, unknown = recover.request('/auth/forgot-password.php', {'csrf':csrf,'email':'unknown-'+secrets.token_hex(5)+'@example.invalid'})
message = 'If an eligible account matches, a password-reset link will be delivered.'
check(message in known and message in unknown, 'recovery response does not disclose account existence')
subprocess.run([r'C:\xampp\php\php.exe', str(ROOT/'scripts/send-reset-mail.php'), '--test'], check=True, stdout=subprocess.DEVNULL)
previews = [json.loads(p.read_text()) for p in (ROOT/'storage/mail-test').glob('*.json')]
preview = next(p for p in previews if p['to'] == email)
check('#token=' in preview['url'] and '?token=' not in preview['url'], 'reset token excluded from HTTP URL query and access logs')
token = urllib.parse.parse_qs(urllib.parse.urlparse(preview['url']).fragment)['token'][0]
csrf = recover.csrf('/auth/reset-password.php'); recovered = 'Synthetic HTTP Recovered Passphrase 789!'
status, headers, body = recover.request('/auth/reset-password.php', {'csrf':csrf,'token':token,'password':recovered,'confirm_password':recovered})
check(status == 302 and 'changed=1' in headers['Location'], 'password recovery completes through HTTP')
check(staff.request('/auth/change-password.php')[0] == 302, 'password reset revokes an existing browser session')
status, headers, body = recover.request('/auth/reset-password.php', {'csrf':csrf,'token':token,'password':recovered,'confirm_password':recovered})
check('invalid or expired' in body, 'reset replay rejected over HTTP')
csrf = admin.csrf('/auth/logout.php'); status, headers, body = admin.request('/auth/logout.php', {'csrf':csrf})
check(status == 302 and admin.request('/users.php')[0] == 302, 'valid logout invalidates session')
# Ensure a failed audit-view log never reveals rows fetched before the failure.
fault_browser = Browser(); csrf = fault_browser.csrf('/auth/login.php')
fault_browser.request('/auth/login.php', {'csrf':csrf,'email':'admin@example.invalid','password':'Synthetic Admin Passphrase 123!'})
subprocess.run([r'C:\xampp\php\php.exe', str(ROOT/'tests/Integration/audit_fault.php'), 'enable'], check=True)
try:
    status, headers, body = fault_browser.request('/audit.php')
    check(status == 503 and 'auth.login' not in body and 'No matching events.' in body, 'audit-view logging failure does not disclose fetched events')
finally:
    subprocess.run([r'C:\xampp\php\php.exe', str(ROOT/'tests/Integration/audit_fault.php'), 'disable'], check=True)
print(f'TOTAL {count} synthetic HTTP assertions passed.', flush=True)