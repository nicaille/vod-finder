const {test,expect}=require('@playwright/test');

test('administrateur : édition de compte responsive, validations et préférences sans autorisation appareil',async({page})=>{
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await page.goto('/login');
  await page.locator('#email').fill('admin@example.test');
  await page.locator('#password').fill('browser-test-password');
  await page.getByRole('button',{name:'Se connecter',exact:true}).click();
  await page.goto('/admin/user-data?q=edit-target@example.test');
  await page.getByRole('link',{name:'browser-edit-target',exact:true}).click();
  const profileURL=page.url();
  await page.getByRole('link',{name:'Modifier le compte',exact:true}).click();
  await expect(page.getByLabel('Adresse e-mail',{exact:true})).toHaveValue('edit-target@example.test');
  await expect(page.getByText('0 appareil(s) enregistré(s)',{exact:false})).toBeVisible();
  for(const width of [320,390,768,1440]){
    await page.setViewportSize({width,height:900});
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBe(true);
  }
  await page.getByLabel('Pseudo',{exact:true}).fill('browser-member');
  await page.getByRole('button',{name:'Enregistrer les modifications',exact:true}).click();
  await expect(page.getByText('Ce surnom est déjà utilisé. Choisis-en un autre.')).toBeVisible();
  await page.getByLabel('Pseudo',{exact:true}).fill('edited-account');
  await page.getByLabel('Prénom',{exact:true}).fill('Camille');
  await page.getByLabel('Nom',{exact:true}).fill('Martin');
  await page.getByLabel('Activer les notifications',{exact:true}).check();
  await page.getByLabel('Recevoir les notifications sur les appareils autorisés',{exact:true}).check();
  await page.getByRole('button',{name:'Enregistrer les modifications',exact:true}).click();
  await expect(page).toHaveURL(profileURL);
  await expect(page.getByRole('heading',{name:'edited-account',exact:true})).toBeVisible();
  await expect(page.getByRole('status')).toContainText('Compte utilisateur mis à jour');
  await page.getByRole('link',{name:'Modifier le compte',exact:true}).click();
  await expect(page.getByLabel('Activer les notifications',{exact:true})).toBeChecked();
  await expect(page.getByText('0 appareil(s) enregistré(s)',{exact:false})).toBeVisible();
  const rejected=await page.request.patch(profileURL,{data:{first_name:'Blocked',last_name:'Request',email:'edit-target@example.test'}});
  expect(rejected.status()).toBe(419);
  expect(errors).toEqual([]);
});
