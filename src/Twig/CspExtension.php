<?php

declare(strict_types=1);

namespace App\Twig;

use App\Security\Csp\CspListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose le nonce de la requête aux gabarits (Reco-04).
 *
 * Un script inline n'est autorisé par la politique que s'il porte le nonce de
 * la requête. Le projet n'en compte qu'un seul, qui l'écrit désormais
 * lui-même :
 *
 *     <script nonce="{{ csp_nonce() }}">
 *
 * C'est ce qui permet de supprimer la réécriture du HTML par expression
 * régulière que faisait l'ancien listener sur chaque réponse.
 *
 * Hors contexte HTTP — un gabarit rendu depuis une commande, par exemple pour
 * un courriel — la fonction rend une chaîne vide, ce qui est sans
 * conséquence : la politique ne s'applique pas aux courriels.
 */
final class CspExtension extends AbstractExtension
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [
            new TwigFunction('csp_nonce', $this->nonce(...)),
        ];
    }

    public function nonce(): string
    {
        $requete = $this->requestStack->getCurrentRequest();

        if (null === $requete) {
            return '';
        }

        return (string) $requete->attributes->get(CspListener::ATTRIBUT_NONCE, '');
    }
}
