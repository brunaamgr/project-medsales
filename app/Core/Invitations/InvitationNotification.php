<?php

namespace App\Core\Invitations;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class InvitationNotification extends Notification
{
    public function __construct(private readonly string $tenant, private readonly string $url) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Convite para acessar {$this->tenant}")
            ->greeting('Olá!')
            ->line("Você foi convidado(a) para acessar o sistema de {$this->tenant}.")
            ->action('Aceitar convite', $this->url)
            ->line('O link vale por 7 dias e só pode ser usado uma vez.')
            ->salutation('Equipe MedSales');
    }
}
