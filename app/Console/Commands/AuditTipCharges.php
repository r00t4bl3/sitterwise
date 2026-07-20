<?php

namespace App\Console\Commands;

use App\Models\Booking;
use App\Models\ClientPayment;
use App\Models\PricingRule;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AuditTipCharges extends Command
{
    protected $signature = 'payments:audit-tip-charges {--apply : Repoint the mislabeled bookings\' stripe_payment_intent_id/actual_amount to their real service charge}';

    protected $description = 'Report (and optionally repair) bookings whose service payment_status/PI was corrupted by a tip PaymentIntent.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        // Bookings whose recorded "charge" PI is actually a tip PaymentIntent — the
        // symptom of the webhook that stamped a tip success onto the service charge.
        $tipPaymentIds = ClientPayment::query()
            ->whereRaw("JSON_EXTRACT(metadata, '$.type') = 'tip'")
            ->pluck('provider_payment_id')
            ->filter()
            ->unique()
            ->all();

        $bookings = empty($tipPaymentIds) ? collect() : Booking::query()
            ->whereIn('stripe_payment_intent_id', $tipPaymentIds)
            ->orderBy('id')
            ->get();

        if ($bookings->isEmpty()) {
            $this->info('No bookings reference a tip PaymentIntent as their charge. Nothing to reconcile.');

            return self::SUCCESS;
        }

        $rows = [];
        $toFix = [];

        foreach ($bookings as $booking) {
            $payments = ClientPayment::where('booking_id', $booking->id)->orderBy('created_at')->get();
            $tip = $payments->firstWhere('provider_payment_id', $booking->stripe_payment_intent_id);
            $servicePayments = $payments->filter(fn ($p) => ($p->metadata['type'] ?? null) !== 'tip');
            $serviceSucceeded = $servicePayments->firstWhere('status', 'succeeded');

            $verdict = match (true) {
                $serviceSucceeded !== null => 'SERVICE_OK_PI_MISLABELED',
                // Non-Stripe bookings (payroll/invoice) are never Stripe-charged, so a
                // missing service charge is expected — the tip webhook only cosmetically
                // set payment_status. Not owed.
                $booking->payment_form !== PricingRule::PAYMENT_FORM_STRIPE => 'NON_STRIPE_OK',
                $servicePayments->isEmpty() => 'SERVICE_NEVER_CHARGED',
                $servicePayments->contains(fn ($p) => $p->status === 'failed') => 'SERVICE_FAILED',
                default => 'SERVICE_UNCLEAR',
            };

            // Only the mislabeled rows (service genuinely charged) are safe to repair
            // — repoint the denormalized fields to the real service charge. The
            // money-at-risk verdicts are never mutated here.
            $action = '';
            if ($verdict === 'SERVICE_OK_PI_MISLABELED') {
                $toFix[] = ['booking' => $booking, 'service' => $serviceSucceeded];
                $action = $apply ? 'FIXED' : 'would-fix';
            }

            $rows[] = [
                $booking->id,
                $booking->status,
                $booking->payment_status,
                $booking->payment_form,
                '$'.number_format((float) $booking->actual_amount, 2),
                $booking->stripe_payment_intent_id,
                $tip ? '$'.number_format((float) $tip->amount, 2) : '—',
                $serviceSucceeded ? 'succeeded' : ($servicePayments->last()->status ?? 'none'),
                $verdict,
                $action,
            ];
        }

        $this->table(
            ['Booking', 'Status', 'Payment', 'Pay form', 'Actual', 'Charge PI (=tip)', 'Tip $', 'Service charge', 'Verdict', 'Action'],
            $rows,
        );
        $this->newLine();

        if ($apply && $toFix) {
            $fixed = 0;
            DB::transaction(function () use ($toFix, &$fixed) {
                foreach ($toFix as $entry) {
                    $entry['booking']->update([
                        'stripe_payment_intent_id' => $entry['service']->provider_payment_id,
                        'actual_amount' => $entry['service']->amount,
                    ]);
                    Log::info('AuditTipCharges: repointed mislabeled booking to its real service charge', [
                        'booking_id' => $entry['booking']->id,
                        'service_payment_intent_id' => $entry['service']->provider_payment_id,
                        'service_amount' => $entry['service']->amount,
                    ]);
                    $fixed++;
                }
            });
            $this->info("Repointed {$fixed} mislabeled booking(s) to their real service charge (stripe_payment_intent_id + actual_amount). payment_status/status were left unchanged.");
        } elseif ($toFix) {
            $this->line(count($toFix).' mislabeled booking(s) would be repointed — re-run with --apply to fix.');
        }

        $atRisk = collect($rows)->filter(fn ($r) => in_array($r[8], ['SERVICE_NEVER_CHARGED', 'SERVICE_FAILED', 'SERVICE_UNCLEAR'], true))->count();
        if ($atRisk > 0) {
            $this->warn("{$atRisk} booking(s) still owe / need review (NEVER_CHARGED / FAILED / UNCLEAR) — NOT touched by --apply. Verify against Stripe and correct by hand.");
        }

        return self::SUCCESS;
    }
}
