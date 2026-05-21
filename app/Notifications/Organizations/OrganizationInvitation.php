<?php

namespace App\Notifications\Organizations;

use App\Models\OrganizationInvitation as OrganizationInvitationModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrganizationInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public OrganizationInvitationModel $invitation)
    {
        //
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $org = $this->invitation->organization;
        $inviter = $this->invitation->inviter;

        return (new MailMessage)
            ->subject(__("You've been invited to join :orgName", ['orgName' => $org->name]))
            ->line(__(':inviterName has invited you to join the :orgName organization.', [
                'inviterName' => $inviter->name,
                'orgName' => $org->name,
            ]))
            ->action(__('Accept invitation'), url("/invitations/{$this->invitation->code}/accept"));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'invitation_id' => $this->invitation->id,
            'organization_id' => $this->invitation->organization_id,
            'organization_name' => $this->invitation->organization->name,
        ];
    }
}
