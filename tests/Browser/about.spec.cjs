const {test,expect}=require('@playwright/test');

test('conditions et confidentialité publiques lisibles sur mobile et ordinateur',async({page})=>{
  await page.goto('/about');
  await page.getByRole('link',{name:'Confidentialité',exact:true}).click();
  await expect(page).toHaveURL(/\/about#confidentialite$/);
  await expect(page.locator('#confidentialite')).toContainText('vod-finder@venoix.fr');
  await expect(page.locator('#confidentialite a[href="/profile"]')).toBeVisible();
  await page.getByRole('link',{name:'Conditions d’utilisation',exact:true}).click();
  await expect(page).toHaveURL(/\/about#conditions-utilisation$/);
  for(const width of [320,390,768,1440]){
    await page.setViewportSize({width,height:900});
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBe(true);
  }
});
