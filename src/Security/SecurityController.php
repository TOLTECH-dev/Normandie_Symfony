<?php

namespace App\Security;

use App\Service\DemandeServiceBO;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    private CacheItemPoolInterface $cache;

    public function __construct(CacheItemPoolInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function loginAdmin(Request $request, AuthenticationUtils $authenticationUtils): Response
    {
        /* /////////////////////////////////////////////////////////////////
                                GET RedisAdapter
        ///////////////////////////////////////////////////////////////// */
        $cachedUserIsCaptchaItemValue = false;

        if ($this->getParameter('captcha_enabled')) {
            $cachedUserIsCaptchaItemValue = $this->cache->getItem(hash('sha512', $request->getClientIp()) . '-is-captcha');
            $cachedUserIsCaptchaItemValue = ($cachedUserIsCaptchaItemValue->isHit()) ? $cachedUserIsCaptchaItemValue->get() : false;
        }

        if ($this->getUser()) {
            return $this->redirectToRoute('demande_list_all');
        }

        return $this->render('BackOffice/Security/login.html.twig', array(
            'last_username'                 => $authenticationUtils->getLastUsername(),
            'error'                         => $authenticationUtils->getLastAuthenticationError(),
            'recaptcha_client_key'          => $this->getParameter('google_recaptcha_site_key'),
            'cachedUserIsCaptchaItemValue'  => $cachedUserIsCaptchaItemValue
        ));
    }

    /**
     * @param Request $request
     * @param AuthenticationUtils $authenticationUtils
     * @param DemandeServiceBO $demandeServiceBO
     * @return Response
     * @throws InvalidArgumentException
     */
    public function loginUser(
        Request $request,
        AuthenticationUtils $authenticationUtils,
        DemandeServiceBO $demandeServiceBO
    ): Response
    {
        /* /////////////////////////////////////////////////////////////////
                                GET RedisAdapter
        ///////////////////////////////////////////////////////////////// */
        $cachedUserIsCaptchaItemValue = false;

        if ($this->getParameter('captcha_enabled')) {
            $cachedUserIsCaptchaItemValue = $this->cache->getItem(hash('sha512', $request->getClientIp()) . '-is-captcha');
            $cachedUserIsCaptchaItemValue = ($cachedUserIsCaptchaItemValue->isHit()) ? $cachedUserIsCaptchaItemValue->get() : false;
        }

        if ($this->getUser()) {
            return $this->redirectToRoute('fo_dashboard');
        }

        return $this->render('Main/Security/login.html.twig', [
            'last_username'                 => $authenticationUtils->getLastUsername(),
            'error'                         => $authenticationUtils->getLastAuthenticationError(),
            'recaptcha_client_key'         => $this->getParameter('google_recaptcha_site_key'),
            'cachedUserIsCaptchaItemValue' => $cachedUserIsCaptchaItemValue,
            'isShowDemandeCreateAction'    => !empty($demandeServiceBO->checkIsOkDemandeCreateActionByDate())
        ]);
    }
}
