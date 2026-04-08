<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class MailerService
{

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly LoggerInterface $logger,
        private readonly string $defaultFrom
    ) {
    }

    /**
     * Send a simple HTML email.
     *
     * @param string $to
     * @param string $subject
     * @param string $htmlBody
     * @param string|null $from
     * @return bool True on success, false on failure (logged)
     */
    public function sendSimpleEmail(string $to, string $subject, string $htmlBody, ?string $from = null): bool
    {
        $from = $from ?? $this->defaultFrom;

        $email = (new TemplatedEmail())
            ->from(new Address($from))
            ->to(new Address($to))
            ->subject($subject)
            ->html($htmlBody);

        try {
            $this->mailer->send($email);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Mail send failed', ['exception' => $e]);

            return false;
        }
    }

    /**
     * Send a templated email using a Twig template.
     *
     * @param string $to
     * @param string $subject
     * @param string $template Twig template path (e.g. 'emails/welcome.html.twig')
     * @param array $context
     * @param string|null $from
     * @return bool
     */
    public function sendTemplateEmail(string $to, string $subject, string $template, array $context = [], ?string $from = null): bool
    {
        $from = $from ?? $this->defaultFrom;

        $email = (new TemplatedEmail())
            ->from(new Address($from))
            ->to(new Address($to))
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context);

        try {
            $this->mailer->send($email);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Mail send failed', ['exception' => $e]);

            return false;
        }
    }

    /**
     * Render a Twig template and return the rendered HTML.
     */
    public function renderTemplate(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }

    /**
     *
     * Send a generic email with support for multiple recipients, CC, BCC, and attachments.
     *
     * @param string $subject
     * @param string $body
     * @param string $from
     * @param string|array|null $address To address(es) - string, array of emails, or associative array ['email' => 'Name']
     * @param string|null $addressName Name for To address (used if $address is string)
     * @param string $contentType 'text/html' or 'text/plain'
     * @param string $charset Character encoding (default UTF-8)
     * @param string|array|null $addressBcc BCC address(es)
     * @param string|null $addressNameBcc Name for BCC address
     * @param string|array|null $addressCc CC address(es)
     * @param string|null $addressNameCc Name for CC address
     * @param string|null $attachment File path for attachment
     * @return int Returns 0 if no recipients or on failure, 1 on success (compatible with old SwiftMailer return)
     */
    public function sendGeneriqueEmail(
        string $subject,
        string $body,
        ?string $from = null,
        $address = null,
        ?string $addressName = null,
        string $contentType = 'text/html',
        string $charset = 'UTF-8',
        $addressBcc = null,
        ?string $addressNameBcc = null,
        $addressCc = null,
        ?string $addressNameCc = null,
        ?string $attachment = null,
        array $embeddedImages = [],
        bool $removeAttachmentsAfterSend = false
    ): int {
        if (!$address && !$addressBcc && !$addressCc) {
            return 0;
        }
        $from = $from ?? $this->defaultFrom;
        $email = new Email();
        $email->subject($subject);
        $email->from($from);

        // Helper pour transformer array associatif en Address[]
        $normalizeRecipients = function($recipients, $name = null) {
            if (is_array($recipients)) {
                $result = [];
                foreach ($recipients as $k => $v) {
                    if (is_int($k)) {
                        // array d'emails simples
                        $result[] = $v;
                    } else {
                        // array associatif email => nom
                        $result[] = new Address($k, $v);
                    }
                }
                return $result;
            } elseif (is_string($recipients) && $name) {
                return [new Address($recipients, $name)];
            } elseif ($recipients) {
                return [$recipients];
            }
            return [];
        };

        // TO
        foreach ($normalizeRecipients($address, $addressName) as $to) {
            $email->addTo($to);
        }
        // BCC
        foreach ($normalizeRecipients($addressBcc, $addressNameBcc) as $bcc) {
            $email->addBcc($bcc);
        }
        // CC
        foreach ($normalizeRecipients($addressCc, $addressNameCc) as $cc) {
            $email->addCc($cc);
        }

        // EMBED DES IMAGES SI PRÉSENTES
        foreach ($embeddedImages as $placeholder => $imagePath) {
            if (is_file($imagePath)) {
                $email->embedFromPath($imagePath, basename($imagePath));
                $cid = 'cid:' . basename($imagePath);

                $body = str_replace(
                    $placeholder,
                    $cid,
                    $body
                );
            }
        }

        // Set body
        if (str_contains($contentType, 'html')) {
            $email->html($body);
        } else {
            $email->text($body);
        }
        if ($attachment && file_exists($attachment)) {
            $email->attachFromPath($attachment);
            if ($removeAttachmentsAfterSend) {
                $email->getHeaders()->addTextHeader('X-Attachment-Path', $attachment);
            }
        }
        try {
            $this->mailer->send($email);
            return 1;
        } catch (TransportExceptionInterface $e) {
            $this->logger->error('Email send failed (Transport)', [
                'exception' => $e->getMessage(),
                'subject' => $subject
            ]);
            return 0;
        } catch (\Throwable $e) {
            $this->logger->error('Email send failed', [
                'exception' => $e->getMessage(),
                'subject' => $subject
            ]);
            return 0;
        }
    }
}