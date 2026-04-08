<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mime\Email;

class MessengerSubscriber implements EventSubscriberInterface
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageHandledEvent::class => 'onMessageHandled',
        ];
    }

    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $message = $envelope->getMessage();

        if ($message->getMessage() instanceof Email) {
            $this->unlinkEmailAttachement($message->getMessage());
        }
    }

    private function unlinkEmailAttachement(Email $email): void
    {
        if ($email->getHeaders()->has('X-Attachment-Path')) {
            $attachmentPath = $email->getHeaders()->get('X-Attachment-Path')->getBodyAsString();
            if (file_exists($attachmentPath)) {
                unlink($attachmentPath);
                $this->logger->info('Attachment deleted successfully: ' . $attachmentPath);
            } else {
                $this->logger->warning('Attachment file not found: ' . $attachmentPath);
            }
        }
    }
}