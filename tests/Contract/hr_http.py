"""Synthetic HR HTTP contracts; no alternate target accepted."""
import pathlib,urllib.request,urllib.parse,urllib.error,http.cookiejar,re,json,secrets,subprocess,html
ROOT=pathlib.Path(__file__).resolve().parents[2];BASE='http://127.0.0.1:8099';count=0
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args):return None
class Browser:
 def __init__(self):self.jar=http.cookiejar.CookieJar();self.opener=urllib.request.build_opener(NoRedirect(),urllib.request.HTTPCookieProcessor(self.jar))
 def request(self,path,data=None,files=None):
  headers={};body=None
  if files:
   boundary='HR'+secrets.token_hex(12);chunks=[]
   for k,v in data.items():chunks.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+k+'"\r\n\r\n'+str(v)+'\r\n').encode())
   for k,entries in files.items():
    for name,content,mime in (entries if isinstance(entries,list) else [entries]):chunks.append(('--'+boundary+'\r\nContent-Disposition: form-data; name="'+k+'"; filename="'+name+'"\r\nContent-Type: '+mime+'\r\n\r\n').encode()+content+b'\r\n')
   body=b''.join(chunks)+('--'+boundary+'--\r\n').encode();headers['Content-Type']='multipart/form-data; boundary='+boundary
  elif data is not None:body=urllib.parse.urlencode(data).encode()
  req=urllib.request.Request(BASE+path,data=body,headers=headers)
  try:r=self.opener.open(req,timeout=20)
  except urllib.error.HTTPError as e:r=e
  raw=r.read();assert r.headers.get('X-Tivoli-Test-Mode')=='synthetic','Unverified HTTP server'
  text=raw.decode('utf-8','replace');assert not any(s in text for s in ['Fatal error','Warning:','Parse error','SQLSTATE[']), 'Unexpected PHP error'
  return r.code,r.headers,text,raw
 def form(self,path,action=None):
  status,headers,body,raw=self.request(path)
  assert status==200,(path,status)
  forms=re.findall(r'<form\b[^>]*>(.*?)</form>',body,re.S)
  if action:forms=[s for s in forms if 'name="action" value="'+action+'"' in s]
  assert forms,(path,action,'missing form')
  return {html.unescape(k):html.unescape(v) for k,v in re.findall(r'<input\b[^>]*name="([^"]+)"[^>]*value="([^"]*)"',forms[0])}
 def login(self,email,password):return self.request('/auth/login.php',{**self.form('/auth/login.php'), 'email':email,'password':password})
def check(ok,label):
 global count
 if not ok:raise RuntimeError('FAIL '+label)
 count+=1;print('PASS '+label,flush=True)
admin=Browser();check(admin.login('admin@example.invalid','Synthetic Admin Passphrase 123!')[0]==302,'HR administrator signs in')
for path in ['/staff.php','/staff.php?new=1','/hr-settings.php','/hr-settings.php?section=documents']:
 status,headers,body,raw=admin.request(path);check(status==200 and 'id="app-sidebar"' in body,'shared HR page '+path)

draft_fields=admin.form('/staff.php?new=1','create');draft_result=admin.request('/staff.php?new=1',{**draft_fields,'name':'Synthetic partial draft','reason':'Synthetic partial onboarding'});check(draft_result[0]==302,'partial onboarding draft saves before employment details are complete')
partial_path='/staff.php?id='+re.search(r'id=(\d+)',draft_result[1]['Location'])[1];check(admin.request(partial_path,{**admin.form(partial_path+'&tab=employment','activate'),'reason':'Synthetic incomplete approval attempt'})[0]==400,'partial draft cannot activate before required employment details')
bad_fields=admin.form('/staff.php?new=1','create');bad_result=admin.request('/staff.php?new=1',{**bad_fields,'name':'Synthetic retained draft','email':'not-an-email','reason':'Synthetic validation failure'});check(bad_result[0]==400 and 'value="Synthetic retained draft"' in bad_result[2],'invalid profile retains safe submitted fields for correction')

token=secrets.token_hex(5);profile={'name':'Synthetic HTTP HR','email':'hr-http-'+token+'@example.invalid','job':'Reception','department':'Front office','employment':'Full-time','start':'2026-11-09','contract':'1','identity':'1','sop':'1','reason':'Synthetic HTTP staff onboarding'}
fields=admin.form('/staff.php?new=1','create');status,headers,body,raw=admin.request('/staff.php?new=1',{**fields,**profile});check(status==302,'onboarding draft saves through HTTP');staff_id=int(re.search(r'id=(\d+)',headers['Location'])[1]);path='/staff.php?id='+str(staff_id)
for tab in ['overview','employment','salary','leave','documents','access','activity']:
 status,headers,body,raw=admin.request(path+'&tab='+tab);check(status==200 and 'id="workspace-main"' in body,'staff profile tab '+tab)
body=admin.request(path+'&tab=leave')[2];check('name="action" value="request_leave"' not in body,'draft employment does not advertise an invalid leave action')
body=admin.request(path+'&tab=documents')[2];check('hr-document-type-form' not in body and 'Manage document types in Settings' in body,'document type management is under Settings')
check(admin.request(path,{'action':'save_profile'})[0]==403,'HR mutations require CSRF')
activation=admin.form(path+'&tab=employment','activate');check(admin.request(path,{**activation,'reason':'Synthetic activation approval'})[0]==302,'administrator activates complete onboarding')
body=admin.request(path+'&tab=leave')[2];check('<textarea name="reason"' in body,'active leave request has multiline reason')
document_form=admin.form(path+'&tab=documents','upload_document');body=admin.request(path+'&tab=documents')[2];type_id=re.search(r'name="type_id".*?<option value="(\d+)"',body,re.S)[1]
pdf=b'%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF'
payload={**document_form,'type_id':type_id};check(admin.request(path,payload,{'document':('signed-contract.pdf',pdf,'application/pdf')})[0]==302,'private signed document uploads through HTTP')
body=admin.request(path+'&tab=documents')[2];doc_id=int(re.search(r'hr-document.php\?id=(\d+)',body)[1]);status,headers,body,raw=admin.request('/hr-document.php?id='+str(doc_id));check(status==200 and raw==pdf and 'attachment' in headers.get('Content-Disposition',''),'authorised download returns exact original attachment')

new_body=admin.request('/staff.php?new=1')[2];identity_type=re.search(r'name="identity_type_id".*?<option value="(\d+)" selected',new_body,re.S)[1]
optional_fields=admin.form('/staff.php?new=1','create');optional_result=admin.request('/staff.php?new=1',{**optional_fields,'name':'Synthetic optional IDs','identity_type_id':identity_type,'reason':'Synthetic optional batch upload'},{'identity_file[]':[('id-front.pdf',pdf,'application/pdf'),('id-back.pdf',pdf+b'\n%back','application/pdf')]})
check(optional_result[0]==302,'onboarding accepts optional multiple ID documents')
optional_path='/staff.php?id='+re.search(r'id=(\d+)',optional_result[1]['Location'])[1];optional_body=admin.request(optional_path+'&tab=documents')[2];check('id-front.pdf' in optional_body and 'id-back.pdf' in optional_body,'both optional ID documents are attached to the saved staff draft')
check(admin.request(optional_path,{**admin.form(optional_path+'&tab=employment','activate'),'reason':'Synthetic verification separation'})[0]==400,'document attachments never auto-complete identity verification')

storage=ROOT/'storage/hr-documents-test';before_files=set(storage.glob('*.enc'));check(admin.request(path,payload,{'document':('signed-contract.pdf',pdf,'application/pdf')})[0]==302,'duplicate document submission is safely replayed');check(set(storage.glob('*.enc'))==before_files,'duplicate document does not leave extra encrypted files')
check(all(b'%PDF' not in p.read_bytes() for p in before_files),'stored documents are encrypted')
check(admin.request(path,payload,{'document':('executable.php',pdf,'application/pdf')})[0]==400,'executable extension rejected')
check(admin.request(path,payload,{'document':('fake.png',b'not an image','image/png')})[0]==400,'forged image content rejected')
settings_fields=admin.form('/hr-settings.php?section=payments','catalogue');check(admin.request('/hr-settings.php?section=payments',{**settings_fields,'group_name':'momo','name':'Synthetic HTTP MoMo '+token,'active':'1','reason':'Synthetic list update'})[0]==302,'administrator adds MoMo provider under Settings')
body=admin.request(path+'&tab=salary')[2];check('Synthetic HTTP MoMo '+token in body and 'name="bank_id"' not in body and 'name="momo_id"' in body,'salary provider dropdowns use updated Settings')
check('name="bank_number"' not in body and 'name="momo_number" type="password"' in body,'salary destination fields masked by default')

salary_body=admin.request(path+'&tab=salary')[2];bank_id=re.search(r'<option value="(\d+)"[^>]*>Synthetic HTTP MoMo '+token+r'</option>',salary_body)[1]
salary_payload={**admin.form(path+'&tab=salary','save_salary'),'amount':'987.65','frequency':'monthly','effective_date':'2026-11-09','preferred':'momo','momo_id':bank_id,'momo_name':'Synthetic HTTP holder','momo_number':'000099887766','account_type':'savings','confirmed':'1','reason':'Synthetic HTTP salary revision'}
check(admin.request(path,salary_payload)[0]==302,'salary revision saves through HTTP')
settings_body=admin.request('/hr-settings.php?section=payments')[2];entry_form=next(f for f in re.findall(r'<form\b[^>]*>(.*?)</form>',settings_body,re.S) if 'name="id" value="'+bank_id+'"' in f);entry_fields={html.unescape(k):html.unescape(v) for k,v in re.findall(r'<input\b[^>]*name="([^"]+)"[^>]*value="([^"]*)"',entry_form)}
check(admin.request('/hr-settings.php?section=payments',{**entry_fields,'group_name':'momo','name':'Synthetic HTTP MoMo '+token,'active':'0','reason':'Synthetic provider deactivation'})[0]==302,'MoMo provider can be deactivated in Settings')
salary_body=admin.request(path+'&tab=salary')[2];selected=re.search(r'<option value="'+bank_id+r'"[^>]*selected[^>]*>',salary_body);check(selected is not None and 'disabled' not in selected[0],'existing inactive provider remains a submitted selection')
check(admin.request(path,{**salary_payload,**admin.form(path+'&tab=salary','save_salary'),'amount':'999.00'})[0]==302,'salary revision can retain an inactive existing MoMo provider')

fixture=json.loads((ROOT/'.local-setup/hr-http-fixture.json').read_text());clerk=Browser();check(clerk.login(fixture['email'],fixture['password'])[0]==302,'existing linked clerk signs in')
change=clerk.form('/auth/change-password.php');new='Synthetic HR HTTP Changed Passphrase 456!';check(clerk.request('/auth/change-password.php',{**change,'current_password':fixture['password'],'password':new,'confirm_password':new})[0]==302,'linked existing account changes temporary password')
check(clerk.login(fixture['email'],new)[0]==302,'linked clerk signs back in')
for private_path in ['/hr-settings.php',path,path+'&tab=salary',path+'&tab=documents','/hr-document.php?id='+str(doc_id)]:check(clerk.request(private_path)[0]==403,'clerk denied '+private_path)
check(clerk.request('/staff.php?id='+str(fixture['staff_id']))[0]==200,'clerk can view only own linked staff profile')
subprocess.run([r'C:\xampp\php\php.exe',str(ROOT/'tests/Integration/audit_fault.php'),'enable'],check=True)
try:
 check(admin.request(path+'&tab=documents')[0]==503,'audit failure blocks document view')
 check(admin.request('/hr-document.php?id='+str(doc_id))[0]==503,'audit failure blocks document download')
 check(admin.request(path,payload,{'document':('audit-test.pdf',pdf+b'\n% changed','application/pdf')})[0]==503,'audit failure rolls back document upload')
 check(set(storage.glob('*.enc'))==before_files,'failed upload cleans private filesystem artifact')
finally:subprocess.run([r'C:\xampp\php\php.exe',str(ROOT/'tests/Integration/audit_fault.php'),'disable'],check=True)
check(admin.request(path+'&tab=documents')[0]==200,'document view recovers after audit storage recovery')

own='/staff.php?id='+str(fixture['staff_id'])
check(admin.request(own,{**admin.form(own+'&tab=employment','activate'),'reason':'Synthetic shared browser account test'})[0]==302,'activate linked clerk employment for shared-browser test')
check(admin.request(own,{**admin.form(own+'&tab=employment','suspend'),'reason':'Synthetic suspension test','current_password':'Synthetic Admin Passphrase 123!'})[0]==302,'staff suspension succeeds through protected HTTP action')
check(clerk.request('/auth/login.php')[0]==200,'suspended staff cookie becomes an anonymous login session')
check(clerk.login('admin@example.invalid','Synthetic Admin Passphrase 123!')[0]==302,'another staff member can sign in on the same browser after suspension')

(ROOT/'.local-setup/hr-browser-fixture.json').write_text(json.dumps({'staff_id':staff_id}))
print(f'TOTAL {count} HR HTTP assertions passed.')
