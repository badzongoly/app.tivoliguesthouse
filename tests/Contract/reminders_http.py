"""Protected real application UI on the verified synthetic server only."""
import pathlib
ROOT=pathlib.Path(__file__).resolve().parents[2]
assert (ROOT/'public/reminders.php').is_file(),'Approved reminder endpoint is missing'
exec((ROOT/'tests/Contract/owner_exceptions_http.py').read_text(encoding='utf-8').split('n=0\n')[0])
n=0
def check(value,label):
 global n
 assert value,label
 n+=1;print('PASS '+label,flush=True)
root='/reminders.php';anon=Browser();check(anon.request(root)[0]==302,'Reminder endpoint requires login')
subprocess.run(['C:/xampp/php/php.exe',str(ROOT/'tests/Integration/weekly_audit_http_seed.php')],cwd=ROOT,check=True,capture_output=True)
fixture=json.loads((ROOT/'.local-setup/weekly-audit-http-fixture.json').read_text(encoding='utf-8'));owner=Browser();staff=Browser();admin=Browser();owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
for b,name in [(staff,'staff'),(admin,'admin')]:b.login(fixture['users'][name]['email'],'Synthetic Audit Passphrase 123!')
for b in [owner,staff,admin]:check(b.request(root+'?page=settings')[0]==200,'Any active individual can open own notification preferences')
body=staff.request(root+'?page=settings')[2];check('My Preferences' in body and 'Contact Verification' not in body,'Staff preferences do not expose global administration')
fields=staff.fields(root+'?page=settings','preferences');check(staff.request(root,fields|{'csrf':'bad'})[0]==403,'Forged notification preference rejected');check(staff.request(root,fields|{'user_id':str(fixture['users']['admin']['id'])})[0]==400,'Submitted recipient cannot edit another account')
fields=staff.fields(root+'?page=settings','preferences');check(staff.request(root,fields|{'email':'1'})[0]==302,'Own preference saves through protected POST');check('Verification Required' in staff.request(root+'?page=settings')[2],'Enabled delivery remains pending contact verification')
check(admin.request(root+'?page=settings&tab=rules')[0]==403 and staff.request(root+'?page=settings&tab=contacts')[0]==403,'Shared rules and verification remain configured-owner only')
fields=owner.fields(root+'?page=settings&tab=rules','rules');check(staff.request(root,fields)[0]==403,'Copied owner rule form cannot grant authority')
check('reminders.php' in staff.request('/weekly-audits.php')[2],'Existing workspace header exposes reminders')
check(anon.request('/notification-status.php',{'unsigned':'1'})[0] in [400,403,503],'Unsigned external delivery callback is rejected')
print(f'Reminder HTTP: {n} assertions passed')
