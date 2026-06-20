<?php

namespace App\Http\Controllers;

use App\Models\Server;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class MailSettingsController extends BaseController
{
    // -------------- Get mail_settings ---------------\\

    public function get_config_mail(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'mail_settings', Setting::class);

        $server = Server::where('deleted_at', '=', null)->first();

        if ($server) {
            return response()->json(['server' => $server], 200);
        } else {
            return response()->json(['statut' => 'error'], 500);
        }
    }

    // -------------- Update mail settings ---------------\\

    public function update_config_mail(Request $request, $id)
    {
        $this->authorizeForUser($request->user('api'), 'mail_settings', Setting::class);

        Server::whereId($id)->update([
            'mail_mailer' => $request['mail_mailer'],
            'host' => $request['host'],
            'port' => $request['port'],
            'sender_name' => $request['sender_name'],
            'sender_email' => $request['sender_email'],
            'username' => $request['username'],
            'password' => $request['password'],
            'encryption' => $request['encryption'],
        ]);

        return response()->json(['success' => true]);

    }

    // -------------- Test mail settings ---------------\\

    public function test_config_mail(Request $request)
    {
        $this->authorizeForUser($request->user('api'), 'mail_settings', Setting::class);

        // Load mail configuration from DB
        $this->Set_config_mail();

        $user = $request->user('api');
        $settings = Setting::where('deleted_at', '=', null)->first();
        $server = Server::where('deleted_at', '=', null)->first();

        // Prefer explicitly provided test email, then current user email, then company email
        $to = $request->input('email')
            ?: ($user && $user->email ? $user->email : null)
            ?: ($settings && $settings->email ? $settings->email : null);

        if (! $to) {
            return $this->sendError('No destination email address available for test.');
        }

        try {
            Mail::raw('This is a test email to verify your mail configuration in Stocky.', function ($message) use ($to, $settings, $server) {
                $message->to($to)
                    ->subject('Test Mail Configuration');

                // Prefer sender_email from server, then fallback to settings email
                $fromEmail = ($server && $server->sender_email) 
                    ? $server->sender_email 
                    : ($settings && $settings->email ? $settings->email : null);
                
                if ($fromEmail) {
                    $fromName = ($server && $server->sender_name) 
                        ? $server->sender_name 
                        : ($settings && $settings->CompanyName ? $settings->CompanyName : 'Stocky');
                    $message->from($fromEmail, $fromName);
                }
            });

            return $this->sendResponse(null, 'Test email sent successfully to '.$to);
        } catch (\Exception $e) {
            return $this->sendError('Failed to send test email.', $e->getMessage());
        }
    }
}
