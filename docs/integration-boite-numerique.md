# Intégration de La Boîte Numérique du Calvados

Étude réalisée le **8 octobre 2026** sur <https://laboitenumerique.calvados.fr/>. Les vérifications se limitent à quelques pages publiques, un flux cinéma et une recherche anonyme ; aucun catalogue complet n’a été aspiré et aucun compte de bibliothèque n’a été utilisé.

## Conclusion

**Une intégration est techniquement possible.** Le portail Syracuse d’Archimed fournit une recherche JSON avec des métadonnées structurées et des flux RSS de sélections. La voie recommandée est un accord du gestionnaire pour un export ou une API partenaire, puis un adaptateur de disponibilités propre à cette source. Aucun fournisseur ni aucune offre n’a été ajouté automatiquement à VOD Finder pendant cette étude.

Le service est gratuit **avec inscription et rattachement à une bibliothèque**, selon les [informations pratiques](https://laboitenumerique.calvados.fr/informations-pratiques.aspx). La [page cinéma](https://laboitenumerique.calvados.fr/laboitenumerique/cinema-num.aspx) présente Médiathèque Numérique, plus de 10 000 films/documentaires/séries/spectacles/magazines et jusqu’à **5 crédits par mois** : 0,5 pour un court-métrage, 1 pour un film sorti en salles il y a plus de 12 mois, 2 pour un film de moins de 12 mois. Elle mentionne aussi des programmes hors décompte. Afficher simplement « gratuit et illimité » pour tout le catalogue serait inexact.

## Moteur de recherche vérifié

Le formulaire public utilise `/search.aspx` avec `SC=CINEMA` et `QUERY`. Le portail charge les scripts Syracuse `portal-front-all.js` et `portal/front/search-list/search_list.js`, où figurent ces services :

| Service | Observation |
| --- | --- |
| `POST /Portal/Recherche/Search.svc/Search` | Recherche JSON interne du portail, accessible anonymement pour le test effectué |
| `/Portal/Recherche/Search.svc/GetRecord` | Service de notice identifié dans le JavaScript ; non appelé pendant l’étude |
| `/Portal/recherche/Search.svc/SolrSuggest` | Autocomplétion identifiée dans le JavaScript ; non appelée pendant l’étude |
| `/Portal/Recherche/Search.svc/SearchRss` | Flux RSS explicitement proposés sur les pages publiques, dont le cinéma |

La recherche anonyme « Morgan Freeman », scénario `CINEMA`, a répondu **HTTP 200**, `success: true`, avec **5 résultats**. L’endpoint rend `d.Results`, `d.SearchInfo`, `d.Query`, `d.HtmlResult` et des facettes/tri. Chaque résultat expose un `FieldList`, un `FriendlyUrl` et une identité de ressource.

Champs observés : `Identifier`, `id`, `Title`, `Author`, `Contributor`, `ProductionYear`, `YearOfPublication`, `DateOfPublication`, `DateOfInsertion`, `Supplier`, `Supplier_exact`, `ArteCredits`, `PrimaryDocAvailable_exact` et les liens de consultation. Le fournisseur identifié dans ces résultats est **Médiathèque Numérique**. Les liens de lecture passent par son authentification SSO ; VOD Finder doit ouvrir la notice officielle et laisser le portail gérer l’accès.

Exemple de corps de recherche utilisé, à titre de preuve technique ; ceci n’est pas une configuration d’import en production :

```json
{
  "query": {
    "QueryString": "Morgan Freeman",
    "ScenarioCode": "CINEMA",
    "Page": 0,
    "PageRange": 3,
    "ResultSize": 3,
    "InitialSearch": true,
    "SearchContext": 0,
    "InjectFields": true,
    "InjectOpenFind": true
  }
}
```

Le serveur a normalisé la taille demandée à **10**, malgré `ResultSize: 3`, et retourné les 5 résultats correspondants. Son contrat et ses tailles de page doivent être confirmés avant un import. Il s’agit d’une interface interne observable, **pas d’une API partenaire documentée et garantie**. Aucune documentation OpenAPI, export CSV complet ou endpoint OAI-PMH public n’a été confirmé. Des champs de provenance OAI existent dans les notices ; ils constituent une piste à demander au gestionnaire, sans établir l’existence d’un accès public.

## Flux RSS vérifié

La page cinéma propose plusieurs flux de sélections. [L’un d’eux](https://laboitenumerique.calvados.fr/Portal/Recherche/Search.svc/SearchRss?key=0fdb995e4f00dae210b91160f1718b3d&useSearchSort=false&useSearchResultSize=true) a répondu **HTTP 200**, au format RSS 2.0, avec **10 notices** lors du test. Il fournit titre, lien de notice, description avec réalisateur, date et image. Parmi les titres reçus : *Only God Forgives*, *Cosmos*, *Le Chant du loup*.

Ce flux est une **sélection**, pas une preuve de couverture exhaustive ni un historique complet des entrées/sorties du catalogue. Le paramètre `key` provient du lien public et identifie la recherche associée ; il ne doit pas être traité comme une clé d’API privée ou supposé permanent sans confirmation.

Attention aux dates : les notices RSS affichaient 2026 pour des films plus anciens. Dans la réponse JSON, `ProductionYear` est distinct de `YearOfPublication`/`DateOfInsertion`. Ces dernières ne doivent jamais remplacer la date de sortie cinéma TMDb. Même `ProductionYear` et les liens fournisseurs doivent être recoupés : une notice observée associait « Insaisissables 2 », une année de production 2015 et un lien de lecture portant un autre suffixe de titre. Une correspondance automatique uniquement sur titre/année serait fragile.

## Réutilisation et méthode recommandée

Les [mentions légales](https://laboitenumerique.calvados.fr/mentions-legales-boite-numerique.aspx) encadrent l’extraction de la base, la republication des textes et l’usage des images, et demandent un accord pour les réutilisations qu’elles décrivent. Le [robots.txt](https://laboitenumerique.calvados.fr/robots.txt) contient `User-agent: * / Disallow: /`, avec des règles distinctes pour Googlebot et Bingbot. Cela s’oppose à l’idée d’un crawl automatique général du portail. Un RSS accessible ou une recherche JSON ouverte ne donne pas, à eux seuls, une autorisation de réutiliser tout le catalogue.

Contact publié dans les mentions légales : **bibliotheque@calvados.fr**. Aucun message n’a été envoyé.

Demande à préparer auprès du gestionnaire :

> Nous développons VOD Finder, qui aide les utilisateurs à repérer les plateformes où consulter un film ou une série. Nous souhaitons référencer La Boîte Numérique avec ses conditions d’accès, en renvoyant les usagers vers vos notices officielles. Proposez-vous une API, un export de catalogue ou un flux partenaire autorisant cette réutilisation ? Pourriez-vous préciser les droits sur les métadonnées, les quotas d’interrogation, la gestion des retraits et les identifiants IMDb/TMDb ou autres identifiants de correspondance disponibles ?

Après clarification de la source et des droits :

1. Créer un adaptateur indépendant des données TMDb et Streaming Availability. Préférer un export/API officiel à une dépendance au HTML ou aux sessions du portail.
2. Importer uniquement les identifiants, métadonnées nécessaires au rapprochement, lien officiel, conditions d’accès, coût en crédits, état de disponibilité, provenance et date de vérification. Garder les fiches détaillées et images TMDb selon leurs conditions existantes.
3. Rapprocher par identifiant externe commun en priorité. Sinon croiser titre, réalisateur, année de production et durée, avec validation des cas ambigus. Conserver les divergences comme ambiguïtés au lieu de déclarer une disponibilité certaine.
4. Synchroniser avec un cache et des quotas convenus. Vérifier les retraits ; ne pas déduire qu’un film est absent du catalogue simplement parce qu’il n’apparaît plus dans une sélection RSS.
5. Proposer un libellé comme « Bibliothèque · inscription requise », avec les limites de crédits pertinentes. L’utilisateur choisit de suivre cette source s’il y a accès. Ouvrir la fiche officielle, sans intégrer de lecteur, copier les vidéos ou stocker ses identifiants de bibliothèque.
