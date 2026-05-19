<?php

namespace App\Http\Controllers;

use App\Jobs\SendBroadcastMessage;
use App\Models\BroadcastMessage;
use App\Models\Caregiver;
use App\Models\SmsBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

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
        $fromDigits = preg_replace('/\D/', '', (string) $request->input('From'));
        $body = strtoupper(trim((string) $request->input('Body')));

        if (in_array($body, ['STOP', 'STOPALL', 'UNSUBSCRIBE', 'CANCEL', 'END', 'QUIT'])) {
            $caregiver = Caregiver::whereNotNull('phone')
                ->get()
                ->first(function ($c) use ($fromDigits) {
                    $phoneDigits = preg_replace('/\D/', '', $c->phone);

                    return $phoneDigits === $fromDigits
                        || (strlen($fromDigits) >= 10
                            && strlen($phoneDigits) >= 10
                            && substr($phoneDigits, -10) === substr($fromDigits, -10));
                });

            if ($caregiver) {
                $caregiver->update(['sms_opted_out' => true]);
            }
        }

        return response()->json(['ok' => true]);
    }
}
