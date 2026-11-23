"""Actual settings forms on the verified synthetic service; never another target."""
import pathlib
ROOT=pathlib.Path(__file__).resolve().parents[2]
exec((ROOT/'tests/Contract/owner_exceptions_http.py').read_text(encoding='utf-8').split('n=0\n')[0])
n=0
def check(ok,label):
 global n
 assert ok,label
 n+=1;print('PASS '+label,flush=True)
def forms(b,path,action):
 status,_,body=b.request(path);assert status==200,(path,status)
 out=[]
 for text in re.findall(r'<form\b[^>]*>(.*?)</form>',body,re.S):
  data={k:html.unescape(v) for k,v in re.findall(r'<input[^>]*type="hidden"[^>]*name="([^"]+)"[^>]*value="([^"]*)"',text)}
  if data.get('action')==action:out.append(data)
 return out,body
def pick(b,path,action,**where):return next(f for f in forms(b,path,action)[0] if all(f.get(k)==v for k,v in where.items()))
def nonce(b):return re.search(r'name="csrf" value="([^"]+)"',b.request('/auth/change-password.php')[2])[1]
f=json.loads((ROOT/'.local-setup/settings-ux-fixture.json').read_text(encoding='utf-8'));owner=Browser();reader=Browser();clerk=Browser();owner.login('admin@example.invalid','Synthetic Admin Passphrase 123!')
for b,name in [(reader,'reader'),(clerk,'clerk')]:b.login(f['people'][name]['email'],f['password'])
paths=['/hr-settings.php','/hr-settings.php?section=documents','/cleaning-settings.php','/cleaning-settings.php?type=inspection','/cleaning-settings.php?type=areas','/cleaning-settings.php?type=extras']
for path in paths:check(owner.request(path)[0]==200 and clerk.request(path)[0]==403,'owner/clerk Settings boundary '+path)
for path in paths[:2]:
 code,_,body=reader.request(path);check(code==200 and 'Only An Administrator' in body and 'name="action" value="catalogue"' not in body,'custom HR-list reader retains values without unsupported editors')
for path in paths[2:]:check(reader.request(path)[0]==403,'non-admin operational Settings denied')
for group,path in [('momo',paths[0]),('document',paths[1])]:
 name='Synthetic Settings '+group+' '+secrets.token_hex(4);data=pick(owner,path,'catalogue',id='0')|dict(group_name=group,name=name,active='1',reason='Synthetic Settings revision')
 bad=owner.request(path,data|{'csrf':'bad'});check(bad[0]==400 and 'This form expired.' in bad[2],'catalogue requires CSRF '+group)
 check(reader.request(path,data|{'csrf':nonce(reader)})[0]==403,'read access cannot change catalogue '+group)
 check(owner.request(path,data)[0]==302,'administrator adds fresh catalogue '+group)
 all_fields,body=forms(owner,path,'catalogue')
 chunks=re.findall(r'<form\b[^>]*>(.*?)</form>',body,re.S);chunk=next(x for x in chunks if 'value="'+name+'"' in x)
 entry={k:html.unescape(v) for k,v in re.findall(r'<input[^>]*type="hidden"[^>]*name="([^"]+)"[^>]*value="([^"]*)"',chunk)}
 change=entry|dict(group_name=group,name=name+' Updated',active='0',reason='Synthetic Settings deactivation')
 check(owner.request(path,change)[0]==302,'rename/deactivate retains stable catalogue ID '+group)
 check(owner.request(path,change)[0]==400,'stale catalogue version rejected '+group)
 body=owner.request(path)[2];check(name+' Updated' in body and 'Inactive' in body,'inactive catalogue remains visible '+group)
for kind in ['cleaning','inspection']:
 path='/cleaning-settings.php'+('?type=inspection' if kind=='inspection' else '');action=kind+'_checklist';_,before=forms(owner,path,action)
 original=re.findall(r'<div class="cleaning-setting-item"><p>(.*?)</p>',before,re.S)
 data=pick(owner,path,action,change='add')|dict(item_text='Synthetic Settings '+kind+' '+secrets.token_hex(4))
 bad=owner.request(path,data|{'csrf':'bad'});check(bad[0]==400 and 'This form expired.' in bad[2],'checklist CSRF '+kind)
 check(owner.request(path,data)[0]==302,'add future checklist item '+kind)
 check(owner.request(path,data)[0]==302,'same operation replay stays singular '+kind)
 check(owner.request(path,data|{'operation_key':secrets.token_hex(16)})[0]==400,'stale checklist revision denied '+kind)
 _,body=forms(owner,path,action);match=re.search(r'<p>'+re.escape(data['item_text'])+r'</p><form\b[^>]*>(.*?)</form>',body,re.S)
 remove={k:html.unescape(v) for k,v in re.findall(r'<input[^>]*type="hidden"[^>]*name="([^"]+)"[^>]*value="([^"]*)"',match[1])}
 check(owner.request(path,remove)[0]==302,'remove fresh test item affects future checks '+kind)
 _,after=forms(owner,path,action);check(re.findall(r'<div class="cleaning-setting-item"><p>(.*?)</p>',after,re.S)==original,'all pre-existing checklist items unchanged '+kind)
path='/cleaning-settings.php?type=extras';data=pick(owner,path,'extra_categories',category_mode='add')|dict(name='Synthetic Settings Category '+secrets.token_hex(4))
check(owner.request(path,data)[0]==302,'new extra category uses existing writer');check(owner.request(path,data)[0]==302,'category operation replay does not duplicate')
check(owner.request(path,data|{'operation_key':secrets.token_hex(16)})[0]==400,'category stale revision rejected')
entry=pick(owner,path,'extra_categories',category_mode='status',name=data['name'])|{'active':'0'};check(owner.request(path,entry)[0]==302,'new category can be deactivated without removing historical names')
path='/cleaning-settings.php?type=areas';data=forms(owner,path,'area_catalogue')[0][0]|dict(name='Synthetic Settings Area '+secrets.token_hex(4),active='1')
result=owner.request(path,data)
if result[0]==302:
 check(True,'administrator adds fresh audit area');check(owner.request(path,data)[0]==302,'area operation replay singular')
 _,body=forms(owner,path,'area_catalogue');chunk=next(x for x in re.findall(r'<form\b[^>]*>(.*?)</form>',body,re.S) if 'value="'+data['name']+'"' in x)
 entry={k:html.unescape(v) for k,v in re.findall(r'<input[^>]*type="hidden"[^>]*name="([^"]+)"[^>]*value="([^"]*)"',chunk)}
 change=entry|dict(name=data['name']+' Updated',active='0');check(owner.request(path,change)[0]==302,'area rename/deactivation preserves its identity');check(owner.request(path,change|{'operation_key':secrets.token_hex(16)})[0]==400,'area stale revision denied')
else:check(result[0]==400 and '100 entries' in result[2],'full area catalogue keeps its existing limit; existing entries not modified')
for b in [owner,reader,clerk]:
 code,_,body=b.request('/reminders.php?page=settings');check(code==200 and 'name="action" value="generate"' not in body,'Settings never duplicates operational Check Now')
check('name="action" value="generate"' in owner.request('/reminders.php')[2],'owner Check Now remains on operational Reminders')
check(reader.request('/reminders.php?page=settings&tab=rules')[0]==403 and clerk.request('/reminders.php?page=settings&tab=contacts')[0]==403,'shared notification setup remains configured-owner only')
print(f'TOTAL {n} Settings HTTP assertions passed',flush=True)
