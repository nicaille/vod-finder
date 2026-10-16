const {test,expect}=require('@playwright/test');

async function submit(page,trigger){
  if(trigger==='Entrée') await page.locator('#q').press('Enter');
  else await page.locator(trigger==='Rechercher'?'#search-button':'#search-field-button').click();
}
for(const trigger of ['Entrée','Rechercher','Loupe']){
  test(`${trigger} reconnaît Tom Cruise et recherche ses crédits`,async({page})=>{
    await page.goto('/');
    await page.locator('#q').fill('Tom Cruise');
    const request=page.waitForRequest(r=>new URL(r.url()).pathname==='/search');
    await submit(page,trigger);
    const params=new URL((await request).url()).searchParams;
    expect(params.get('person_id')).toBe('500');
    expect(params.get('type')).toBe('all');
    expect(params.get('q')).toBe('Tom Cruise');
    await expect(page.locator('#results .vod-media-card')).toHaveCount(1);
    await expect(page.locator('#results')).toContainText('Dune');
    await expect(page.locator('#loader')).toBeHidden();
  });
  test(`${trigger} privilégie un titre exact sur une personne homonyme`,async({page})=>{
    await page.route('**/autocomplete?*',route=>route.fulfill({json:{results:[
      {type:'person',id:999,title:'Dune'},{type:'movie',id:42,title:'Dune'}
    ]}}));
    await page.goto('/');
    await page.locator('#q').fill('Dune');
    const request=page.waitForRequest(r=>new URL(r.url()).pathname==='/search');
    await submit(page,trigger);
    const params=new URL((await request).url()).searchParams;
    expect(params.has('person_id')).toBe(false);
    expect(params.has('person_ids[]')).toBe(false);
    await expect(page.locator('#results .vod-media-card')).toHaveCount(2);
    await expect(page.locator('#loader')).toBeHidden();
  });
}

test('une personne explicitement choisie reste sélectionnée au prochain envoi',async({page})=>{
  await page.route('**/autocomplete?*',route=>route.fulfill({json:{results:[
    {type:'person',id:500,title:'Tom Cruise'}, {type:'person',id:999,title:'Tom Cruise'}
  ]}}));
  await page.route('**/search?*',route=>route.fulfill({json:{results:[]}}));
  await page.goto('/');
  await page.locator('#q').fill('Tom Cruise');
  await page.locator('#autocomplete summary').click();
  const first=page.waitForRequest(r=>new URL(r.url()).pathname==='/search');
  await page.locator('#autocomplete button[data-id="999"]').click();
  expect(new URL((await first).url()).searchParams.get('person_id')).toBe('999');
  await expect(page.locator('#loader')).toBeHidden();
  await page.evaluate(()=>sessionStorage.clear());
  const next=page.waitForRequest(r=>new URL(r.url()).pathname==='/search');
  await page.locator('#search-button').click();
  expect(new URL((await next).url()).searchParams.get('person_id')).toBe('999');
});

test('modifier la saisie pendant la reconnaissance ignore la réponse devenue obsolète',async({page})=>{
  let release;
  const pending=new Promise(resolve=>release=resolve);
  await page.route('**/autocomplete?*',async route=>{
    await pending;
    await route.fulfill({json:{results:[{type:'person',id:500,title:'Tom Cruise'}]}});
  });
  const searches=[];
  page.on('request',request=>{if(new URL(request.url()).pathname==='/search') searches.push(request.url());});
  await page.goto('/');
  await page.locator('#q').fill('Tom Cruise');
  const lookup=page.waitForRequest(r=>new URL(r.url()).pathname==='/autocomplete');
  await page.locator('#search-button').click();
  await lookup;
  await page.locator('#q').fill('Dune');
  const response=page.waitForResponse(r=>new URL(r.url()).pathname==='/autocomplete');
  release();
  await response;
  await page.waitForLoadState('networkidle');
  expect(searches).toEqual([]);
});
