# Limites des API utilisées

Vérification des documentations publiques le **10 octobre 2026**, heure de Paris. L’utilisateur indique utiliser des offres gratuites : Streaming Availability via RapidAPI et Brevo directement.

| Fournisseur | Limite pertinente | Source officielle |
|---|---|---|
| TMDb | Protection autour de **40 requêtes/seconde**, variable. La documentation ne présente pas de quota mensuel pour cet usage. L’ancienne limite de 40 requêtes par 10 secondes est supprimée depuis décembre 2019. Respecter les réponses 429. | [Rate limiting](https://developer.themoviedb.org/docs/rate-limiting) |
| TVmaze | **Au moins 20 appels par 10 secondes et par IP** ; des restrictions temporaires plus fortes restent possibles. Le cache du fournisseur peut permettre davantage d’appels, sans garantie. | [Rate limiting](https://www.tvmaze.com/api#rate-limiting) |
| Streaming Availability / RapidAPI | Offre publique **Basic gratuite : 1 000 appels par mois**. Pro : 25 000/mois, Ultra : 100 000/mois, Mega : 1 000 000/mois. Le forfait actuellement attaché à la clé et la date de remise à zéro doivent être confirmés dans le compte RapidAPI ou via les en-têtes du fournisseur. | [Plans publics de Streaming Availability](https://rapidapi.com/movie-of-the-night-movie-of-the-night-default/api/streaming-availability/pricing) |
| Brevo | Offre gratuite : **300 envois d’e-mail par jour**, sans report des crédits inutilisés. Séparément, `POST /v3/smtp/email` dispose au niveau général de **1 000 requêtes/seconde et 3 600 000/heure**. Les limites de cadence ne remplacent pas les crédits d’envoi. | [Offre gratuite](https://help.brevo.com/hc/en-us/articles/208580669-About-Brevo-s-pricing-plans), [cadence API](https://developers.brevo.com/docs/api-limits), [en-têtes](https://developers.brevo.com/docs/limit-headers) |

Le quota mensuel Basic de Streaming Availability est le principal budget à préserver : 1 000 appels/mois correspond à environ 33 appels/jour en moyenne, **pas à une limite quotidienne**. Une recherche peut produire plusieurs appels, selon le nombre de titres et de données nécessaires. Les caches de disponibilité, de fiches et de recherches réduisent ces appels ; les renouvellements dus aux expirations et les tâches automatiques consomment également le quota.

Sur OVH mutualisé, l’adresse IP sortante peut être partagée ; TVmaze applique son contrôle par IP. Une limitation peut donc apparaître même si le trafic de VOD Finder seul paraît faible.

## Mesures dans l’administration

Administration → Statistiques API conserve les appels HTTP, leurs échecs et les réponses 429 par API et période. Le suivi des 429 commence avec sa migration : les anciennes périodes ne sont pas reconstituées.

Les réponses peuvent annoncer leur quota. L’application conserve uniquement une liste fermée d’en-têtes numériques : limites, capacité restante, réinitialisation et `Retry-After` numérique. Elle n’enregistre pas les clés, cookies, URL ni corps des réponses. Les en-têtes affichés sont la **dernière observation**, indépendante du filtre temporel. Une absence d’en-tête ne signifie pas que le service est illimité.

Pour Brevo, `x-sib-ratelimit-reset` annonce le temps restant dans l’unité de la fenêtre, généralement des secondes ; les en-têtes de cadence ne donnent pas les 300 crédits d’e-mail journaliers. L’application n’interroge pas le compte Brevo pour récupérer le solde de crédits. Pour RapidAPI, la fenêtre de quota peut suivre le cycle du forfait : le nombre d’appels pendant un mois calendaire dans VOD Finder n’est pas forcément la consommation exacte du cycle facturé. D’autres applications utilisant la même clé peuvent également consommer ce budget.

Les mesures du cache sont celles du **serveur**, à chaque opération des services TMDb, TVmaze et Streaming Availability. Brevo n’utilise pas de cache d’envoi ; le cache de session du navigateur n’est pas mesuré par ce suivi. Les métadonnées d’expiration sont conservées 120 jours sous une clé hachée ; les historiques agrégés restent en base. Les mesures sont enregistrées à la fin d’une requête ou commande et ne doivent pas empêcher le fonctionnement du service en cas d’échec de leur enregistrement.

Les appels évités et le temps gagné sont des **estimations** tirées du nombre d’appels HTTP de la même API et de la durée du dernier remplissage. Les réponses déjà en cache avant l’instrumentation, sans ces métadonnées, sont exclues des économies estimées. Ces durées décrivent les opérations de cache, avec le chargement lors d’une absence, et pas le temps complet d’affichage d’une page. Une mesure sur une entrée composite peut chevaucher celles de ses dépendances ; les économies ne sont pas une mesure du temps total gagné par les utilisateurs. Médiane et p95 utilisent des histogrammes de durée avec bornes supérieures. Le service ne distribue pas actuellement de données périmées en secours.

Le taux de succès du cache se calcule comme `hits / (hits + misses)`. Une lecture du cache économise un chargement, mais ce chargement peut nécessiter zéro, un ou plusieurs appels HTTP : les courbes cache et HTTP ne s’additionnent pas.
