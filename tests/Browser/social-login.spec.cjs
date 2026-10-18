const {test,expect}=require('@playwright/test');
test('inscription Google, préférences explicites et compte associé sur mobile',async({page,baseURL})=>{
  test.skip(process.env.VOD_BROWSER_OAUTH_TEST!=='1','Requires disposable OAuth simulation.');
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await page.route('https://accounts.google.com/**',route=>{
    const state=new URL(route.request().url()).searchParams.get('state');
    return route.fulfill({status:302,headers:{location:`${baseURL}/auth/google/callback?state=${encodeURIComponent(state)}&code=browser-fake-code`},body:''});
  });
  await page.setViewportSize({width:390,height:900});
  await page.goto('/login');
  await expect(page.locator('[data-oauth-provider]')).toHaveCount(3);
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBe(true);
  await page.getByRole('link',{name:'Continuer avec Google',exact:true}).click();
  await expect(page).toHaveURL(/\/oauth\/signup$/);
  await expect(page.getByLabel('Prénom',{exact:true})).toHaveValue('OAuth');
  await expect(page.getByLabel('Adresse e-mail',{exact:true})).toHaveValue('oauth-browser@example.test');
  await expect(page.getByLabel('Activer les notifications',{exact:true})).not.toBeChecked();
  await page.getByLabel('Pseudo (facultatif)',{exact:true}).fill('oauth-browser');
  await page.getByRole('button',{name:'Créer mon compte',exact:true}).click();
  await expect(page).toHaveURL(/\/account$/);
  await expect(page.locator('#connections')).toContainText('Google · Associé');
  await expect(page.locator('#connections')).toContainText('Ta méthode de connexion actuelle');
  await expect(page.locator('#connections').getByRole('button',{name:'Retirer Google'})).toHaveCount(0);
  for(const width of [320,390,768,1440]){
    await page.setViewportSize({width,height:900});
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBe(true);
  }
  expect(errors).toEqual([]);
});
