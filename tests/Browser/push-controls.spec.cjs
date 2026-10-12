const {test,expect}=require('@playwright/test');

test.beforeEach(async({page})=>{
  await page.route('https://**',route=>route.fulfill({status:200,contentType:'image/svg+xml',body:'<svg xmlns="http://www.w3.org/2000/svg"/>'}));
  await page.addInitScript(()=>{
    let subscription={endpoint:'https://fcm.googleapis.com/fcm/send/browser-test',unsubscribe:async()=>{subscription=null;window.devicePushStopped=true;return true;}};
    const worker={pushManager:{getSubscription:async()=>subscription}};
    Object.defineProperty(navigator,'serviceWorker',{value:{register:async()=>worker,ready:Promise.resolve(worker)}});
    Object.defineProperty(window,'Notification',{value:{permission:'granted'}});
    window.PushManager=class {};
  });
  await page.route('**/notifications/push/status',route=>route.fulfill({json:{registered:true}}));
  await page.goto('/login');
  await page.locator('#email').fill('admin@example.test');
  await page.locator('#password').fill('browser-test-password');
  await page.getByRole('button',{name:'Se connecter',exact:true}).click();
  await expect(page).toHaveURL(/\/$/);
  await page.goto('/account');
  await expect(page.locator('[data-test-push]')).toBeEnabled();
});

test('test push : refus HTML lisible et confirmation admin accessible',async({page})=>{
  const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await page.route('**/notifications/push/test',route=>route.fulfill({status:403,contentType:'text/html',body:'<!DOCTYPE html><html>Forbidden</html>'}));
  await page.locator('[data-test-push]').click();
  await expect(page.locator('[data-push-status]')).toContainText('HTTP 403');
  await expect(page.locator('[data-test-push]')).toBeEnabled();
  await page.unroute('**/notifications/push/test');
  await page.route('**/notifications/push/test',route=>route.fulfill({status:423,json:{message:'Password confirmation required.'}}));
  await page.locator('[data-test-push]').click();
  await expect(page.locator('[data-push-status]')).toContainText('Confirme ton mot de passe');
  await expect(page.locator('[data-confirm-push-admin]')).toBeVisible();
  await expect(page.locator('[data-confirm-push-admin]')).toHaveAttribute('href',/\/confirm-password$/);
  for(const [status,message] of [[401,'Ta session a expiré'],[419,'Cette page a expiré']]) {
    await page.unroute('**/notifications/push/test');
    await page.route('**/notifications/push/test',route=>route.fulfill({status,contentType:'text/html',body:'<!DOCTYPE html><html>Expired</html>'}));
    await page.locator('[data-test-push]').click();
    await expect(page.locator('[data-push-status]')).toContainText(message);
    await expect(page.locator('[data-confirm-push-admin]')).toBeHidden();
  }
  expect(errors).toEqual([]);
});

for(const blocked of [false,true]) {
  test(`désactivation locale et POST serveur (${blocked?'refus HTML':'succès'})`,async({page})=>{
    const errors=[];page.on('pageerror',error=>errors.push(error.message));
    let method;
    await page.route('**/notifications/push/unsubscribe',route=>{
      method=route.request().method();
      return route.fulfill(blocked?{status:403,contentType:'text/html',body:'<!DOCTYPE html><html>Forbidden</html>'}:{json:{ok:true}});
    });
    await page.locator('[data-disable-push]').click();
    await expect(page.locator('[data-push-status]')).toContainText('Notifications désactivées sur cet appareil');
    if(blocked) await expect(page.locator('[data-push-status]')).toContainText('HTTP 403');
    expect(method).toBe('POST');
    expect(await page.evaluate(()=>window.devicePushStopped)).toBe(true);
    await expect(page.locator('[data-test-push]')).toBeDisabled();
    await expect(page.locator('[data-disable-push]')).toBeHidden();
    await expect(page.locator('[data-enable-push]')).toBeVisible();
    expect(errors).toEqual([]);
  });
}
