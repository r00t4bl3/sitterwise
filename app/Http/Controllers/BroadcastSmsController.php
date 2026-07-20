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
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;
use Twilio\Security\RequestValidator;

class BroadcastSmsController extends Controller
{
    /**
     * Footer appended to every broadcast. Single source of truth, forwarded to
     * the compose page so its preview/char-count always matches what's sent. The
     * STOP opt-out disclosure is appended by Twilio, so it isn't repeated here.
     */
    public const COMPLIANCE_FOOTER = "\n\nPause your account to stop.";

    public function index(): Response
    {
        $recipientCount = Caregiver::activeForSms()->count();

        return Inertia::render('admin/broadcast-sms/index', [
            'recipientCount' => $recipientCount,
            'complianceFooter' => self::COMPLIANCE_FOOTER,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message_body' => ['required', 'string', 'max:918'],
        ]);

        $user = Auth::user();

        $fullMessage = $validated['message_body'].self::COMPLIANCE_FOOTER;

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

            Log::info('Twilio status callback: updated', [
                'message_sid' => $messageSid,
                'status' => $messageStatus,
            ]);
        } else {
            Log::warning('Twilio status callback: missing MessageSid or MessageStatus', [
                'message_sid' => $messageSid,
                'message_status' => $messageStatus,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function inboundSms(Request $request): JsonResponse
    {
        $this->validateTwilioRequest($request);

        $from = (string) $request->input('From');
        $fromDigits = preg_replace('/\D/', '', $from);
        $body = strtoupper(trim((string) $request->input('Body')));

        $maskedFrom = strlen($fromDigits) >= 4
            ? '***'.substr($fromDigits, -4)
            : '***';

        if (! $from || ! $body) {
            Log::warning('Twilio inbound: missing From or Body', [
                'from' => $maskedFrom,
                'body' => $body,
            ]);
        }

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

                Log::info('Twilio inbound: caregiver opted out', [
                    'from' => $maskedFrom,
                    'caregiver_id' => $caregiver->id,
                ]);
            } else {
                $client = Client::whereNotNull('phone')
                    ->get()
                    ->first(fn ($c) => $matchPhone($c->phone));

                if ($client) {
                    $client->update(['sms_opted_out' => true]);

                    Log::info('Twilio inbound: client opted out', [
                        'from' => $maskedFrom,
                        'client_id' => $client->id,
                    ]);
                } else {
                    Log::warning('Twilio inbound: opt-out from unknown number', [
                        'from' => $maskedFrom,
                    ]);
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
            Log::warning('Twilio webhook: missing auth token or signature', [
                'url' => $request->fullUrl(),
                'has_auth_token' => ! is_null($authToken),
                'has_signature' => ! is_null($signature),
                'content_type' => $request->header('Content-Type'),
                'method' => $request->method(),
            ]);

            abort(401, 'Missing Twilio signature.');
        }

        $validator = new RequestValidator($authToken);
        $isValid = $validator->validate(
            $signature,
            $request->fullUrl(),
            $request->post(),
        );

        if (! $isValid) {
            Log::warning('Twilio webhook: invalid signature', [
                'url' => $request->fullUrl(),
                'scheme' => $request->getScheme(),
                'host' => $request->getHost(),
                'port' => $request->getPort(),
                'content_type' => $request->header('Content-Type'),
                'post_param_keys' => array_keys($request->post() ?? []),
                'post_param_count' => count($request->post() ?? []),
                'signature_prefix' => substr($signature, 0, 20),
                'auth_token_configured' => strlen($authToken),
            ]);

            abort(401, 'Invalid Twilio signature.');
        }
    }
}
