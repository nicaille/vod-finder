const {test,expect}=require('@playwright/test');
const {createHash}=require('node:crypto');
const visitorIps=new WeakMap();
test.beforeEach(async({page},info)=>{
  const hash=createHash('sha256').update(info.testId).digest();
  const ip=`198.18.${hash[0]}.${hash[1]}`;
  visitorIps.set(page,ip);
  await page.setExtraHTTPHeaders({'X-VOD-Browser-IP':ip});
});

async function login(page,name='invite-sender') {
  await page.goto('/login');
  await page.locator('#email').fill(`${name}@example.test`);
  await page.locator('#password').fill('browser-test-password');
  await page.getByRole('button',{name:'Se connecter',exact:true}).click();
  await expect(page).toHaveURL(/\/$/);
}

for (const width of [320,390]) {
  test(`répertoire, choix des coordonnées et partage sans import à ${width}px`,async({page})=>{
    await page.setViewportSize({width,height:850});
    await page.addInitScript(()=>{
      window.inviteCalls=[];
      Object.defineProperty(navigator,'contacts',{configurable:true,value:{
        getProperties:async()=>['email','tel','name'],
        select:(properties,options)=>{
          window.inviteCalls.push({kind:'picker',properties,options,active:navigator.userActivation.isActive});
          return Promise.resolve([{email:['first@example.test','second@example.test'],tel:['+33 6 12 34 56 78','+33 7 12 34 56 78']}]);
        },
      }});
      Object.defineProperty(navigator,'share',{configurable:true,value:async data=>{window.inviteCalls.push({kind:'share',data,active:navigator.userActivation.isActive});}});
      Object.defineProperty(navigator,'clipboard',{configurable:true,value:{writeText:async text=>{window.inviteCalls.push({kind:'copy',text});}}});
    });
    const requests=[];
    await page.route('**/account/contacts/invitations',async route=>{
      const payload=route.request().postDataJSON();requests.push(payload);
      await route.fulfill({status:payload.channel==='link'?201:200,json:payload.channel==='link'
        ?{url:new URL('/invite/'+'a'.repeat(64),route.request().url()).href,message:'Lien créé.'}
        :{message:'Invitation envoyée.'}});
    });
    await login(page,width===320?'invite-small':'invite-large');
    await page.goto('/account/contacts');
    await page.locator('[data-contact-picker]').click();
    await expect(page.locator('[data-invite-email-input]')).toHaveValue('first@example.test');
    await expect(page.locator('[data-invite-phone]')).toHaveValue('+33 6 12 34 56 78');
    expect(requests).toEqual([]);
    await page.locator('[data-contact-emails]').selectOption('second@example.test');
    await page.locator('[data-contact-phones]').selectOption('+33 7 12 34 56 78');
    await page.locator('[data-invite-link] button').click();
    await expect(page.locator('[data-invite-result]')).toBeVisible();
    expect(requests).toEqual([{channel:'link'}]);
    const url=await page.locator('[data-invite-url]').inputValue();
    const sms=await page.locator('[data-invite-sms]').getAttribute('href');
    expect(sms).toContain('sms:+33712345678?body=');
    expect(decodeURIComponent(sms)).toContain(url);
    await page.locator('[data-invite-share]').click();
    await page.locator('[data-invite-copy]').click();
    await page.locator('[data-invite-email] button').click();
    await expect(page.locator('[data-invite-status]')).toContainText('Invitation envoyée');
    expect(requests).toEqual([{channel:'link'},{channel:'email',email:'second@example.test'}]);
    const calls=await page.evaluate(()=>window.inviteCalls);
    expect(calls.find(x=>x.kind==='picker')).toEqual({kind:'picker',properties:['email','tel'],options:{multiple:false},active:true});
    expect(calls.find(x=>x.kind==='share').data.url).toBe(url);
    expect(calls.find(x=>x.kind==='share').active).toBe(true);
    expect(calls.find(x=>x.kind==='copy').text).toBe(url);
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBe(true);
  });
}

test('navigateur sans répertoire ni partage : saisie manuelle et refus HTML lisible',async({page})=>{
  await page.addInitScript(()=>{
    Object.defineProperty(navigator,'contacts',{configurable:true,value:undefined});
    Object.defineProperty(navigator,'share',{configurable:true,value:undefined});
  });
  await page.route('**/account/contacts/invitations',route=>route.fulfill({status:403,contentType:'text/html',body:'<!DOCTYPE html><title>Forbidden</title>'}));
  await login(page,'invite-fallback');
  await page.goto('/account/contacts');
  await expect(page.locator('[data-contact-picker]')).toBeHidden();
  await page.locator('[data-invite-email-input]').fill('manual@example.test');
  await page.locator('[data-invite-email] button').click();
  await expect(page.locator('[data-invite-status]')).toContainText('HTTP 403');
  await expect(page.locator('[data-invite-status]')).not.toContainText('DOCTYPE');
  await expect(page.locator('[data-invite-email] button')).toBeEnabled();
});

test('invitation partagée, inscription et acceptation explicite de la relation',async({page,browser})=>{
  await login(page);
  await page.goto('/account/contacts');
  await page.locator('[data-invite-link] button').click();
  await expect(page.locator('[data-invite-result]')).toBeVisible();
  const url=await page.locator('[data-invite-url]').inputValue();
  const context=await browser.newContext({extraHTTPHeaders:{'X-VOD-Browser-IP':visitorIps.get(page)}});
  const guest=await context.newPage();
  try {
    await guest.goto(url);
    await expect(guest.locator('body')).toContainText('browser-invite-sender t’invite');
    await guest.getByRole('link',{name:'Créer mon compte',exact:true}).click();
    await guest.locator('[name="first_name"]').fill('Invitation');
    await guest.locator('[name="last_name"]').fill('Test');
    await guest.locator('[name="nickname"]').fill('browser-invited');
    await guest.locator('[name="email"]').fill('invited-browser@example.test');
    await guest.locator('[name="password"]').fill('safe-browser-invitation-password');
    await guest.locator('[name="password_confirmation"]').fill('safe-browser-invitation-password');
    await guest.locator('input[type="checkbox"][name="notify_opt_in"]').uncheck();
    await guest.locator('button[type="submit"]').click();
    await expect(guest).toHaveURL(url);
    await guest.getByRole('button',{name:'Accepter l’invitation de browser-invite-sender',exact:true}).click();
    await expect(guest).toHaveURL(/\/account\/contacts$/);
    await expect(guest.locator('body')).toContainText('Invitation acceptée');
    await page.reload();
    await expect(page.locator('.vod-contact-row').filter({hasText:'browser-invited'})).toContainText('En relation');
    await guest.goto(url);
    await expect(guest.locator('body')).toContainText('Invitation indisponible');
  } finally {await context.close();}
});
