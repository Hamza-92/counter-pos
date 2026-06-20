<?php

namespace App\Notifications;

use App\Mail\CustomEmail;
use App\Models\Asset;
use App\Models\EmailMessage;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class AssetValidationDueNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @var \App\Models\Asset
     */
    protected $asset;

    /**
     * Create a new notification instance.
     *
     * @param  \App\Models\Asset  $asset
     * @return void
     */
    public function __construct(Asset $asset)
    {
        $this->asset = $asset;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['database', 'mail'];
    }

    /**
     * Build replacement tags for the template.
     *
     * @return array
     */
    protected function getReplacementTags(): array
    {
        $settings = Setting::whereNull('deleted_at')->first();
        $businessName = $settings ? $settings->CompanyName : config('app.name');
        $assetName = $this->asset->name ?: $this->asset->tag;
        $nextValidation = $this->asset->next_validation ? $this->asset->next_validation->format('Y-m-d') : '—';
        $assetEditUrl = url('/app/assets/edit/' . $this->asset->id);

        return [
            '{asset_name}' => $assetName,
            '{asset_tag}' => $this->asset->tag,
            '{next_validation}' => $nextValidation,
            '{asset_edit_url}' => $assetEditUrl,
            '{business_name}' => $businessName,
        ];
    }

    /**
     * Get the mail representation using the dynamic template from Notification Templates.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Mail\Mailable|null
     */
    public function toMail($notifiable)
    {
        $emailMessage = EmailMessage::getForLocale('asset_validation_due');
        $tags = $this->getReplacementTags();

        $subject = __('messages.Asset validation due') . ': ' . $tags['{asset_name}'];
        $body = __('messages.The following asset is due for validation (or is overdue). Please verify the tool or equipment.') . '<br><br>'
            . __('messages.Asset') . ': ' . $tags['{asset_name}'] . ' (' . $tags['{asset_tag}'] . ')<br>'
            . __('messages.Next validation date') . ': ' . $tags['{next_validation}'] . '<br><br>'
            . '<a href="' . $tags['{asset_edit_url}'] . '">' . __('messages.View asset') . '</a>';

        if ($emailMessage && (trim($emailMessage->subject ?? '') !== '' || trim($emailMessage->body ?? '') !== '')) {
            $subject = str_replace(array_keys($tags), array_values($tags), $emailMessage->subject ?? $subject);
            $body = str_replace(array_keys($tags), array_values($tags), $emailMessage->body ?? $body);
        }

        $email = [
            'subject' => $subject,
            'body' => $body,
            'company_name' => $tags['{business_name}'],
        ];

        return (new CustomEmail($email));
    }

    /**
     * Get the array representation of the notification for database.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            'type' => 'asset_validation_due',
            'asset_id' => $this->asset->id,
            'asset_name' => $this->asset->name,
            'asset_tag' => $this->asset->tag,
            'next_validation' => $this->asset->next_validation ? $this->asset->next_validation->format('Y-m-d') : null,
            'message' => ($this->asset->name ?: $this->asset->tag) . ' (' . $this->asset->tag . ') – ' . ($this->asset->next_validation ? $this->asset->next_validation->format('Y-m-d') : '') . ' ' . __('messages.Next validation date'),
        ];
    }
}
