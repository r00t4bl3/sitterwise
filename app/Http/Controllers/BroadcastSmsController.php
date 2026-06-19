<?php

namespace App\Http\Controllers;

use App\Jobs\SendBroadcastMessage;
use App\Models\BroadcastMessage;
use App\Models\Caregiver;
use App\Models\Client;
use App\Models\SmsBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Twilio\Security\RequestValidator;

class BroadcastSmsController extends Controller
{
    public function index(): Response
    {
        $recipientCount = Caregiver::activeForSms()->count();

        return Inertia::render('superadmin/broadcast-sms/index', [
            'recipientCount' => $recipientCount,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message_body' => ['required', 'string', 'max:918'],
        ]);

        $user = Auth::user();

        $complianceFooter = "\n\nReply STOP to opt out.";
        $fullMessage = $validated['message_body'].$complianceFooter;

        $caregivers = Caregiver::activeForSms()->get();

        if ($caregivers->isEmpty()) {
            return redirect()->back()->with('error', 'No eligible caregivers found.');
        }

        $broadcast = SmsBroadcast::create([
            'sent_by_user_id' => $user->id,
            'message_body' => $fullMessage,
            'recipient_count' => $caregivers->count(),
        ]);

        $messages = [];

        foreach ($caregivers as $i => $caregiver) {
            $broadcastMessage = BroadcastMessage::create([
                'broadcast_id' => $broadcast->id,
                'caregiver_id' => $caregiver->id,
                'phone_number' => $caregiver->phone,
                'message_body' => $fullMessage,
                'status' => 'queued',
            ]);

            $messages[] = $broadcastMessage;
        }

        foreach ($messages as $i => $broadcastMessage) {
            SendBroadcastMessage::dispatch($broadcastMessage)
                ->delay(now()->addSeconds($i));
        }

        $estimatedMinutes = (int) ceil($caregivers->count() / 60);

        $message = "Broadcast queued. {$caregivers->count()} messages will send over approximately {$estimatedMinutes} minutes.";

        return redirect()->back()->with('success', $message);
    }

    public function statusCallback(Request $request): JsonResponse
    {
        $this->validateTwilioRequest($request);

        $messageSid = $request->input('MessageSid');
        $messageStatus = $request->input('MessageStatus');

        if ($messageSid && $messageStatus) {
            BroadcastMessage::where('twilio_message_sid', $messageSid)
                ->update(['status' => $messageStatus]);
        }

        return response()->json(['ok' => true]);
    }

    public function inboundSms(Request $request): JsonResponse
    {
        $this->validateTwilioRequest($request);

        $fromDigits = preg_replace('/\D/', '', (string) $request->input('From'));
        $body = strtoupper(trim((string) $request->input('Body')));

        if (in_array($body, ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'])) {
            $normPhoneDigits = fn ($phone) => preg_replace('/\D/', '', $phone);
            $matchPhone = fn ($phone) => $normPhoneDigits($phone) === $fromDigits
                || (strlen($fromDigits) >= 10
                    && strlen((string) $normPhoneDigits($phone)) >= 10
                    && substr($normPhoneDigits($phone), -10) === substr($fromDigits, -10));

            $caregiver = Caregiver::whereNotNull('phone')
                ->get()
                ->first(fn ($c) => $matchPhone($c->phone));

            if ($caregiver) {
                $caregiver->update(['sms_opted_out' => true]);
            } else {
                $client = Client::whereNotNull('phone')
                    ->get()
                    ->first(fn ($c) => $matchPhone($c->phone));

                if ($client) {
                    $client->update(['sms_opted_out' => true]);
                }
            }
        }

        return response()->json(['ok' => true]);
    }

    protected function validateTwilioRequest(Request $request): void
    {
        if (! app()->isProduction()) {
            return;
        }

        $authToken = config('services.twilio.auth_token');
        $signature = $request->header('X-Twilio-Signature');

        if (! $authToken || ! $signature) {
            abort(401, 'Missing Twilio signature.');
        }

        $validator = new RequestValidator($authToken);
        $isValid = $validator->validate(
            $signature,
            $request->fullUrl(),
            $request->post(),
        );

        if (! $isValid) {
            abort(401, 'Invalid Twilio signature.');
        }
    }
}
