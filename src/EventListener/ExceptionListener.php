<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class ExceptionListener
{
    public function onKernelException(ExceptionEvent $event)
    {
        $exception = $event->getThrowable();
        $message = $exception->getMessage();

        // Affiche le message d'exception dans la réponse (HTML simple)
        $response = new Response();
        $response->setContent('<h1>Exception capturée</h1><pre>' . htmlspecialchars($message) . '</pre>');
        $response->setStatusCode($exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500);
        $event->setResponse($response);
    }
}

