<?php

namespace App\Support;

class AboutContent
{
    public static function defaults(): array
    {
        return [
            'slug' => 'about',
            'title' => 'À propos de VOD Finder',
            'body' => <<<'MD'
## Moins de temps à chercher, plus de temps à regarder

VOD Finder vous aide à choisir votre prochain film ou votre prochaine série et à trouver les possibilités de visionnage sur vos plateformes.

## Un espace pour vos envies

Recherchez un titre ou une personne, consultez sa fiche et gardez vos découvertes dans votre playlist. Créez des listes pour organiser vos envies et retrouvez vos coups de cœur au même endroit.

## Ne manquez plus vos séries

Suivez vos séries pour consulter les prochaines diffusions annoncées. Vous pouvez choisir de recevoir les alertes par e-mail, dans votre navigateur ou de les consulter dans l’application, selon vos préférences.

## Partagez vos découvertes

Invitez vos proches à rejoindre vos contacts. Une fois la mise en relation acceptée, échangez des recommandations et retrouvez les titres reçus dans votre compte. L’apparition dans l’annuaire et le partage de votre nom sont des choix personnels.

## Des disponibilités qui évoluent

Les catalogues changent selon le pays, la formule d’abonnement et la date. Une offre en location, à l’achat ou via une option payante ne signifie pas qu’un titre est inclus dans votre abonnement principal. Vérifiez les conditions affichées par la plateforme avant de lancer une lecture ou un achat.

Les données sont actualisées régulièrement et peuvent présenter un décalage avec les plateformes. Les dates de diffusion annoncées peuvent aussi être modifiées.

## Sur ordinateur et sur mobile

VOD Finder s’adapte à votre écran. Utilisez le bouton « Installer » pour ajouter un accès rapide sur votre appareil lorsque votre navigateur le permet.
MD,
        ];
    }
}
