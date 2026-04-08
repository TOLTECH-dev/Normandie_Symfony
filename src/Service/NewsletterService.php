<?php

namespace App\Service;

class NewsletterService
{
    private MailerService $mailerService;

    public function __construct(MailerService $mailerService)
    {
        $this->mailerService = $mailerService;
    }


    /**
     * @param string $subject
     * @param string $body
     * @param string $contentType
     * @param array $listDestinataireBcc
     * @return int
     */
    public function sendNewsletter(
        string $subject,
        string $body,
        string $contentType,
        array $listDestinataireBcc
    ): int {

        $isSent = $this->mailerService->sendGeneriqueEmail(
            $subject,
            $body,
            null,
            null,
            null,
            $contentType,
            'UTF-8',
            $listDestinataireBcc
        );

        return $isSent;
    }

}
