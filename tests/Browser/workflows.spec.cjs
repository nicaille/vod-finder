const {test,expect} = require('@playwright/test');
async function login(page,name='member') {
  await page.goto('/login');
  await page.locator('#email').fill(`${name}@example.test`);
  await page.locator('#password').fill('browser-test-password');
  await page.getByRole('button',{name:'Se connecter',exact:true}).click();
  await expect(page).toHaveURL(/\/$/);
}
function collectErrors(page) {
  const errors=[];
  page.on('pageerror',error=>errors.push(error.message));
  page.on('console',message=>{if(message.type()==='error') errors.push(message.text());});
  return errors;
}
async function expectNoHorizontalOverflow(page, path) {
  const layout = await page.evaluate(() => ({
    viewport: innerWidth,
    width: document.documentElement.scrollWidth,
    overflowing: [...document.querySelectorAll('body *')].filter(element => {
      const bounds = element.getBoundingClientRect();
      return bounds.width > 0 && bounds.right > innerWidth + 1;
    }).slice(0, 10).map(element => ({tag: element.tagName, class: element.className, right: element.getBoundingClientRect().right}))
  }));
  expect(layout.width, `${path}: ${JSON.stringify(layout)}`).toBeLessThanOrEqual(layout.viewport + 1);
}
test.beforeEach(async ({page}) => {
  // Avoid third-party images/links in this fully isolated browser suite.
  await page.route('https://**',route=>route.fulfill({status:200,contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"></svg>'}));
});
for(const width of [320,390,768,1440]) {
 test(`navigation et formulaires sans débordement à ${width}px`,async({page})=>{
   await page.setViewportSize({width,height:900});
   const errors=collectErrors(page);
   for(const path of ['/','/login','/register','/about']) {
     const response=await page.goto(path);expect(response.status()).toBe(200);
     await expectNoHorizontalOverflow(page, path);
   }
   await login(page);
   for(const path of ['/account','/watchlist','/lists','/series','/account/contacts','/account/recommendations']) {
     const response=await page.goto(path);expect(response.status()).toBe(200);
     await expectNoHorizontalOverflow(page, path);
   }
   expect(errors).toEqual([]);
 });
}
test('recherche combinée, autocomplete personne et fiche sans blocage',async({page})=>{
 const errors=collectErrors(page);
 await page.goto('/');await expect(page.locator('#type')).toHaveValue('all');
 await page.locator('#q').fill('Dune');await page.locator('#search-field-button').click();
 await expect(page.locator('#results .vod-media-card')).toHaveCount(2);
 await expect(page.locator('#loader')).toBeHidden();
 await page.locator('#results .vod-media-card').filter({hasText:'Dune'}).locator('.vod-card-open').click();
 await expect(page.locator('#popup-content')).toBeVisible();
 await expect(page.locator('#popup-content')).toContainText('Synopsis du film de test.');
 await page.locator('#popup-close').click();
 const autocompleteStarted=Date.now();
 const autocompleteResponse=page.waitForResponse(response=>response.url().includes('/autocomplete?q=Tom%20Cruise'));
 await page.locator('#q').fill('Tom Cruise');
 const suggestionResponse=await autocompleteResponse;
 expect(suggestionResponse.status()).toBe(200);
 const suggestions=await suggestionResponse.json();
 expect(suggestions.results.some(item=>item.title==='Tom Cruise')).toBe(true);
 console.log(`Autocomplete HTTP 200: ${Date.now()-autocompleteStarted} ms`);
 await expect(page.locator('#autocomplete')).toBeVisible();
 await page.locator('#autocomplete button').filter({hasText:'Tom Cruise'}).click();
 await expect(page.locator('#results .vod-media-card')).toHaveCount(1);
 await expect(page.locator('#loader')).toBeHidden();
 expect(errors).toEqual([]);
});
test('playlist, favori, suivi et annulation sans quitter la fiche',async({page})=>{
 const errors=collectErrors(page);await login(page);
 await page.locator('#q').fill('Dune');await page.locator('#search-button').click();
 await expect(page.locator('#results .vod-media-card')).toHaveCount(2);
 await page.locator('#results .vod-media-card').filter({hasText:'MobLand'}).locator('.vod-card-open').click();
 await expect(page.locator('#popup-content')).toBeVisible();
 const playlist=page.locator('#popup-content [data-action="playlist"]');
 await playlist.click();await expect(playlist).toHaveAttribute('aria-pressed','true');
 const favorite=page.locator('#popup-content [data-action="favorite"]');
 await favorite.click();await expect(favorite).toHaveAttribute('aria-pressed','true');
 const follow=page.locator('#popup-content [data-action="follow"]');
 await follow.click();await expect(follow).toHaveAttribute('aria-pressed','true');
 await expect(page.locator('#popup-content')).toBeVisible();expect(page.url()).not.toContain('/series');
 await follow.click();await expect(follow).toHaveAttribute('aria-pressed','false');
 await expect(page.locator('#popup-content')).toBeVisible();expect(page.url()).not.toContain('/series');
 await playlist.click();await expect(playlist).toHaveAttribute('aria-pressed','false');
 await favorite.click();await expect(favorite).toHaveAttribute('aria-pressed','false');
 expect(errors).toEqual([]);
});
test('administration, statistiques interactives et droits',async({page})=>{
 const errors=collectErrors(page);await login(page);
 await page.goto('/account');
 await expect(page.locator('[data-test-push]')).toHaveCount(0);
 expect((await page.goto('/admin')).status()).toBe(403);
 await expect.poll(()=>errors.length).toBe(1);
 expect(errors[0]).toContain('403');errors.length=0;
 await page.context().clearCookies();await login(page,'admin');
 await page.goto('/account');
 await expect(page.locator('[data-test-push]')).toBeVisible();
 await expect(page.locator('[data-test-push]')).toBeDisabled();
 for(const path of ['/admin','/admin/health','/admin/logs','/admin/user-data','/admin/users','/admin/notifications/email']) expect((await page.goto(path)).status()).toBe(200);
 await page.goto('/admin/api-statistics');
 await expect(page.locator('[data-streaming-budget]')).toHaveCount(2);
 await expect(page.locator('[data-streaming-budget]').filter({hasText:'Movie of the Night · direct'})).toContainText('Accès non configuré');
 await expect(page.locator('#api-statistics-chart')).toBeVisible();
 await page.locator('#api-chart-metric').selectOption('failures');
 await expect(page.locator('#api-chart-metric')).toHaveValue('failures');
 await page.setViewportSize({width:390,height:900});
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBe(true);
 expect(errors).toEqual([]);
});
test('inscription et validation des erreurs sur session fichier',async({page})=>{
 const errors=collectErrors(page);await page.goto('/register');
 await page.locator('[name="first_name"]').fill('Browser');await page.locator('[name="last_name"]').fill('New');
 await page.locator('[name="nickname"]').fill('browser-member');await page.locator('[name="email"]').fill('new-browser@example.test');
 await page.locator('[name="password"]').fill('browser-new-password');await page.locator('[name="password_confirmation"]').fill('browser-new-password');
 await page.locator('button[type="submit"]').click();
 await expect(page.locator('body')).toContainText('déjà');
 await page.locator('[name="nickname"]').fill('browser-new-member');
 await page.locator('[name="password"]').fill('browser-new-password');await page.locator('[name="password_confirmation"]').fill('browser-new-password');
 await page.locator('button[type="submit"]').click();
 await expect(page).toHaveURL(/\/account#notifications$/);
 await expect(page.locator('[data-push-onboarding]')).toBeVisible();
 await page.goto('/account');await expect(page.locator('[name="nickname"]')).toHaveValue('browser-new-member');
 expect(errors).toEqual([]);
});

test('compteurs du compte et recommandations cohérents sur mobile',async({page})=>{
 const errors=collectErrors(page);
 await page.setViewportSize({width:390,height:900});
 await login(page,'recipient');
 await page.goto('/account');
 await expect(page.locator('[data-account-badge]')).toHaveText('7');
 await expect(page.locator('[data-account-activity] [data-activity-kind="recommendations"]')).toContainText('6 recommandation(s)');
 await expect(page.locator('[data-account-activity] [data-activity-kind="contacts"]')).toContainText('1 acceptation(s)');
 await expect(page.locator('.vod-social-inbox')).toContainText('Aperçu de 5 sur 6');
 await expectNoHorizontalOverflow(page,'/account');
 await page.locator('[data-account-activity] [data-activity-kind="contacts"]').click();
 await expect(page.locator('[data-account-badge]')).toHaveText('6');
 await page.goto('/account');
 await page.locator('[data-account-activity] [data-activity-kind="recommendations"]').click();
 await expect(page.locator('.vod-recommendation-card')).toHaveCount(6);
 await expect(page.locator('[data-recommendation-total]')).toContainText('6 recommandation(s)');
 await page.locator('.vod-recommendation-card h2').first().click();
 await expect(page.locator('[data-account-badge]')).toHaveText('5');
 await page.goto('/account');
 await expect(page.locator('[data-account-activity] [data-activity-kind="recommendations"]')).toContainText('5 recommandation(s)');
 await expect(page.locator('[data-account-activity] [data-activity-kind="contacts"]')).toHaveCount(0);
 expect(errors).toEqual([]);
});
