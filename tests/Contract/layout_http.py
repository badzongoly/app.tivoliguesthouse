"""Authenticated shell checks against verified synthetic HTTP only."""
import pathlib, urllib.request, urllib.error, urllib.parse, http.cookiejar, re, hashlib
ROOT=pathlib.Path(__file__).resolve().parents[2]
BASE='http://127.0.0.1:8099'
class StopRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args): return None
jar=http.cookiejar.CookieJar()
browser=urllib.request.build_opener(StopRedirect(),urllib.request.HTTPCookieProcessor(jar))
def request(path,data=None):
    req=urllib.request.Request(BASE+path,data=None if data is None else urllib.parse.urlencode(data).encode())
    try: res=browser.open(req,timeout=15)
    except urllib.error.HTTPError as error: res=error
    assert res.headers.get('X-Tivoli-Test-Mode')=='synthetic','Unverified HTTP server'
    return res.code,res.headers,res.read().decode('utf-8')
_,_,login=request('/auth/login.php');assert 'app-sidebar' not in login
token=re.search(r'name="csrf" value="([^" ]+)"',login)[1]
assert request('/auth/login.php',dict(csrf=token,email='admin@example.invalid',password='Synthetic Admin Passphrase 123!'))[0]==302
pages=['owner_reports.php','bookings.php','checkin.php?room_id=2','checkout.php?booking_id=1','rooms.php','extras.php','stock.php','extra_sales.php','payments.php','reports.php','admin_recovery.php','expenses.php','users.php','audit.php','auth/change-password.php','auth/logout.php']
for page in pages:
    status,headers,body=request('/'+page)
    assert status==200,(page,status)
    assert not any(s in body for s in ['Fatal error','Warning:','Parse error','SQLSTATE[']),(page,'PHP error')
    for marker in ['id="app-sidebar"','class="app-topbar"','id="workspace-main"','class="app-footer"','assets/css/app.css','assets/js/app.js']:
        assert marker in body,(page,marker)
    assert body.count('<main ')==1,(page,'main landmark')
    assert body.count('id="app-sidebar"')==1,(page,'duplicate shell')
    print('PASS shared shell '+page)
assert request('/rooms.php',{'name':'Must not write'})[0]==405
assert request('/rooms.php?delete=1')[0]==405
print('PASS synthetic legacy writes blocked')
# Exports must exit before presentation is emitted.
for page in ['/reports.php?export=csv&which=revenue_daily','/extra_sales.php?export=csv']:
    status,headers,body=request(page)
    assert status==200 and '<html' not in body and 'app-sidebar' not in body,page
print('PASS exports contain no layout markup')
# Verify actual privilege-based navigation and the first-password-change shell.
import secrets
def csrf(path):
    return re.search(r'name="csrf" value="([^" ]+)"',request(path)[2])[1]
email='layout-'+secrets.token_hex(6)+'@example.invalid'
temporary='Synthetic Layout Temporary Passphrase 123!'
changed='Synthetic Layout Changed Passphrase 456!'
assert request('/users.php',dict(csrf=csrf('/users.php'),action='save_user',name='Synthetic Layout Clerk',email=email,password=temporary,reason='Synthetic shared layout verification',current_password='Synthetic Admin Passphrase 123!'))[0]==302
assert request('/auth/logout.php',dict(csrf=csrf('/auth/logout.php')))[0]==302
assert request('/auth/login.php',dict(csrf=csrf('/auth/login.php'),email=email,password=temporary))[0]==302
locked=request('/auth/change-password.php')[2]
assert 'id="app-sidebar"' in locked and '/bookings.php' not in locked and '/audit.php' not in locked
assert request('/auth/change-password.php',dict(csrf=csrf('/auth/change-password.php'),current_password=temporary,password=changed,confirm_password=changed))[0]==302
assert request('/auth/login.php',dict(csrf=csrf('/auth/login.php'),email=email,password=changed))[0]==302
body=request('/expenses.php')[2]
navigation=re.search(r'<nav class="app-navigation".*?</nav>',body,re.S)[0]
assert '/expenses.php' in navigation and '/bookings.php' in navigation
assert all('/'+path not in navigation for path in ['users.php','audit.php','reports.php','admin_recovery.php'])
assert request('/users.php')[0]==403 and request('/audit.php')[0]==403
assert request('/auth/logout.php',dict(csrf=csrf('/auth/logout.php')))[0]==302
print('PASS locked account and clerk navigation; direct permission gates retained')
