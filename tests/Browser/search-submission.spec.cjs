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


for (const [status, message] of [
  [403, 'refusée par une protection de sécurité (HTTP 403)'],
  [429, 'Trop de recherches.'],
  [500, 'La recherche a échoué (HTTP 500)'],
]) {
  test(`un refus HTTP ${status} indique sa cause et permet de relancer la recherche`, async ({page}) => {
    let calls = 0;
    await page.route('**/autocomplete?*', route => route.fulfill({json:{results:[]}}));
    await page.route('**/search?*', route => {
      calls++;
      return calls === 1
        ? route.fulfill({status,contentType:'text/html',body:'<!DOCTYPE html><title>Refused</title>'})
        : route.fulfill({json:{results:[]}});
    });
    await page.goto('/');
    await page.locator('#q').fill('his and hers');
    await page.locator('#search-button').click();
    await expect(page.locator('#error')).toContainText(message);
    await expect(page.locator('#loader')).toBeHidden();
    await expect(page.locator('#search-button')).toBeEnabled();
    await page.locator('#search-button').click();
    await expect(page.locator('#error')).toBeHidden();
    await expect(page.locator('#loader')).toBeHidden();
    expect(calls).toBe(2);
  });
}

const hisHersResult = {
  id:259731,type:'tv',title:'HIS & HERS',year:'2026',overview:'Une enquête relie deux anciens époux.',
  providers:[{slug:'netflix',name:'Netflix',access:'flatrate'}],poster:null,
};
for (const trigger of ['Entrée','Rechercher','Loupe']) {
  test(`${trigger} conserve and dans la requête et affiche un titre contenant une esperluette`,async({page})=>{
    await page.route('**/autocomplete?*',route=>route.fulfill({json:{results:[
      {type:'tv',id:259731,title:'HIS & HERS',year:'2026'},
    ]}}));
    await page.route('**/search?*',route=>route.fulfill({json:{results:[hisHersResult]}}));
    await page.goto('/');
    await page.locator('#q').fill('his and hers');
    const request=page.waitForRequest(r=>new URL(r.url()).pathname==='/search');
    await submit(page,trigger);
    expect(new URL((await request).url()).searchParams.get('q')).toBe('his and hers');
    await expect(page.locator('#results .vod-media-card')).toHaveCount(1);
    await expect(page.locator('#results')).toContainText('HIS & HERS');
    await expect(page.locator('#error')).toBeHidden();
  });
}

for (const [title, id] of [["M'entends-tu?",85789], ['His And Hers',24223]]) {
  test(`choisir ${title} ouvre sa fiche même sans offre et sans relancer la recherche`,async({page})=>{
    await page.route('**/autocomplete?*',route=>route.fulfill({json:{results:[
      {type:'tv',id,title,year:'2019'},
    ]}}));
    await page.route(`**/title/tv/${id}?*`,route=>route.fulfill({contentType:'text/html',body:
      `<div id="popup-container"><div id="popup-content"><button id="popup-close">Fermer</button><h1>${title}</h1><p>Aucune disponibilité référencée.</p></div></div>`}));
    const searches=[];
    page.on('request',request=>{if(new URL(request.url()).pathname==='/search') searches.push(request.url());});
    await page.goto('/');
    await page.locator('#q').fill(title);
    const request=page.waitForRequest(r=>new URL(r.url()).pathname===`/title/tv/${id}`);
    await page.locator(`#autocomplete button[data-id="${id}"]`).click();
    expect(new URL((await request).url()).searchParams.get('country')).toBe('FR');
    await expect(page.locator('#popup-content')).toContainText(title);
    await expect(page.locator('#popup-content')).toContainText('Aucune disponibilité référencée.');
    await expect(page.locator('#search-button')).toBeEnabled();
    expect(searches).toEqual([]);
    await page.locator('#popup-close').click();
    await expect(page.locator('#modal-overlay')).toBeHidden();
  });
}

test('une recherche sans offre explique les filtres, aussi après lecture du cache',async({page})=>{
  await page.route('**/autocomplete?*',route=>route.fulfill({json:{results:[]}}));
  let calls=0;
  await page.route('**/search?*',route=>{calls++;return route.fulfill({json:{results:[],pagination:{total:1,next_offset:null,kind:'titles'}}});});
  await page.goto('/');
  await page.locator('#q').fill("M'entends tu ?");
  await page.locator('#search-button').click();
  await expect(page.locator('#results')).toContainText('Un titre peut exister sans disponibilité référencée');
  await page.locator('#search-button').click();
  await expect(page.locator('#results')).toContainText('Un titre peut exister sans disponibilité référencée');
  expect(calls).toBe(1);
});
