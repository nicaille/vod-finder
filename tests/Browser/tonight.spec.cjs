const {test,expect}=require('@playwright/test');
const {mkdirSync}=require('node:fs');
test('sélection ce soir responsive, filtres conservés et fiche accessible',async({page})=>{
  const errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  await page.route('https://**',route=>route.fulfill({status:200,contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="20" height="30"></svg>'}));
  await page.goto('/login');
  await page.locator('#email').fill('member@example.test');
  await page.locator('#password').fill('browser-test-password');
  await page.getByRole('button',{name:'Se connecter',exact:true}).click();
  await page.getByRole('link',{name:/Pas d’idée/}).click();
  await expect(page.locator('[data-tonight-state]')).toHaveAttribute('data-tonight-state','initial');
  await page.getByLabel('Temps disponible').selectOption('180');
  await page.getByLabel('Format',{exact:true}).selectOption('movie');
  await page.getByLabel('Genre',{exact:true}).selectOption('fantasy');
  await page.getByLabel('Ton envie').selectOption('escape');
  await page.getByRole('button',{name:'Trouver une idée',exact:true}).click();
  await expect(page.locator('[data-tonight-results] .vod-media-card')).toHaveCount(1);
  await expect(page.locator('[data-tonight-results]')).toContainText('Dune');
  await expect(page.locator('[data-tonight-results]')).toContainText('155 min');
  await expect(page.getByLabel('Genre',{exact:true})).toHaveValue('fantasy');
  await expect(page.getByLabel('Ton envie')).toHaveValue('escape');
  mkdirSync('/tmp/vod-tonight-screenshots',{recursive:true});
  for(const width of [320,390,768,1440]){
    await page.setViewportSize({width,height:950});
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBe(true);
    await expect(page.locator('.vod-tonight-duration')).toBeVisible();
    await expect(page.getByRole('button',{name:'Trouver une idée',exact:true})).toBeEnabled();
    await page.screenshot({path:`/tmp/vod-tonight-screenshots/tonight-${width}.png`,fullPage:true});
  }
  await page.getByRole('link',{name:'Voir la fiche de Dune'}).click();
  await expect(page).toHaveURL(/\/content\/movie\/42/);
  await expect(page.locator('body')).toContainText('Synopsis du film de test.');
  expect(errors).toEqual([]);
});
