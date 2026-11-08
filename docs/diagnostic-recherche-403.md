# Recherche « his and hers » : refus HTTP 403 en amont de Laravel

Diagnostic effectué le 22 octobre 2026, vers 12 h 15 (Europe/Paris), par des requêtes GET publiques avec User-Agent Firefox et `Accept: application/json`.

## Résultats observés

| Requête | Résultat |
| --- | --- |
| `/search?q=his+and+hers&type=all&country=FR` | HTTP 403, page HTML Apache de 199 octets |
| `/search?q=his%20and%20hers&type=all&country=FR` | HTTP 403, même page Apache |
| `/autocomplete?q=his%20and%20hers` | HTTP 403 |
| `/search?q=Dune&type=all&country=FR` | HTTP 200 JSON |
| `/search?q=his%20hers&type=all&country=FR` | HTTP 200 JSON |
| `/search?q=HIS+%26+HERS&type=all&country=FR` | HTTP 200 JSON |
| `/title/tv/259731?country=FR`, avec `X-Requested-With: XMLHttpRequest` | HTTP 200, fiche HTML |

Les refus portent `X-VOD-Origin-Check: match` : la clé d’origine est reconnue. Aucun `cf-mitigated: challenge` n’est présent. Leur corps est la page Apache générique « Forbidden », pas une réponse Laravel. Cela situe le refus côté origine ; un faux positif du pare-feu applicatif OVH est probable, mais l’identifiant de règle et le journal de sécurité OVH sont nécessaires pour le confirmer.

Le clic sur un titre proposé par l’autocomplétion relance une recherche textuelle avec son libellé : un titre contenant l’expression bloquée rencontre donc le même problème. L’ouverture directe de la fiche récente fonctionne.

## Résolution confirmée

Après désactivation du pare-feu applicatif OVH pour ce domaine, l’utilisateur confirme la disparition des erreurs et le retour des résultats pour « his and hers ». La recherche publique a également renvoyé HTTP 200 et la série « HIS & HERS ». Le proxy Cloudflare et la protection privée de l’origine restent en place. Les éléments de ticket ci-dessous sont conservés comme diagnostic historique, pour une éventuelle correction ciblée par OVH.

## Mesures

- Contournement utilisateur vérifié : rechercher `HIS & HERS`.
- Le front distingue désormais les refus HTTP 403, limitations 429 et erreurs serveur, sans afficher le HTML reçu. Cette modification de message ne lève pas le filtre de l’hébergement et est incluse dans la release du correctif de recherche.
- Ne pas altérer arbitrairement les titres, encoder doublement les requêtes, ou désactiver la protection privée de l’origine pour masquer le problème.
- Demander à OVH d’identifier la règle déclenchée et de proposer une correction ciblée sur les recherches textuelles. Conserver les protections Cloudflare et la restriction d’origine.

## Texte pour le support OVH

Bonjour,

Sur l’hébergement du site https://app-vod.venoix.fr, les requêtes publiques suivantes reçoivent une page Apache HTTP 403 avant Laravel :

- GET /search?q=his%20and%20hers&type=all&country=FR
- GET /autocomplete?q=his%20and%20hers

Il s’agit de la recherche légitime d’un titre de série. Les mêmes parcours avec « Dune », « his hers » ou « HIS & HERS » renvoient HTTP 200 JSON. La fiche /title/tv/259731?country=FR s’ouvre normalement avec l’en-tête X-Requested-With: XMLHttpRequest.

Reproduction le 22/10/2026 vers 12:15, heure de Paris (10:15 UTC), avec User-Agent Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:157.0) Gecko/20100101 Firefox/157.0 et Accept: application/json.

Le domaine passe par Cloudflare, mais les refus ne portent pas cf-mitigated: challenge. La réponse d’origine contient X-VOD-Origin-Check: match, confirmant que notre authentification privée Cloudflare/origine autorise la requête. Le corps refusé est la page Apache générique de 199 octets, Content-Type: text/html; charset=iso-8859-1. Aucun en-tête X-OVHRequest-Id n’est exposé dans cette réponse publique.

Pouvez-vous vérifier le journal du pare-feu applicatif/ModSecurity, communiquer l’identifiant de la règle éventuellement déclenchée et corriger ce faux positif par un réglage ciblé ? Nous souhaitons conserver les protections générales.

Merci.
