<?php

namespace App\Http\Controllers;

use App\Enums\ServiceType;
use App\Http\Requests\SimulatePricingRequest;
use App\Models\PricingRule;
use Inertia\Inertia;

class PricingSimulatorController extends Controller
{
    public function index()
    {
        $pricingRules = PricingRule::orderBy('service_type')
            ->orderBy('number_of_children')
            ->get();

        $serviceTypes = [];
        foreach (ServiceType::cases() as $case) {
            $serviceTypes[] = [
                'value' => $case->value,
                'label' => $case->label(),
            ];
        }

        $maxChildren = PricingRule::max('number_of_children') ?? 10;

        return Inertia::render('superadmin/pricing-rules/simulator', [
            'pricingRules' => $pricingRules,
            'serviceTypes' => $serviceTypes,
            'maxChildren' => max($maxChildren, 10),
        ]);
    }

    public function simulate(SimulatePricingRequest $request)
    {
        $validated = $request->validated();

        $serviceType = $validated['service_type'];
        $isForPets = $validated['is_for_pets'] ?? false;
        $numberOfChildren = $validated['number_of_children'] ?? null;

        // Same lookup logic as Booking::calculateHourlyRate()
        $maxChildren = PricingRule::where('service_type', $serviceType)->max('number_of_children');
        $cappedChildren = $numberOfChildren !== null ? min($numberOfChildren, $maxChildren ?? 0) : null;

        $query = PricingRule::where('service_type', $serviceType)
            ->where('number_of_children', $cappedChildren);

        if ($serviceType === 'petsitter') {
            $query->where('is_for_pets', $isForPets);
        }

        $pricingRule = $query->first();
        $isFallback = false;

        if (! $pricingRule) {
            $pricingRule = PricingRule::where('service_type', $serviceType)->first();
            $isFallback = true;
        }

        $hours = (float) ($validated['hours'] ?? 0);

        $result = [
            'matched_rule' => $pricingRule ? [
                'id' => $pricingRule->id,
                'service_type' => $pricingRule->service_type,
                'number_of_children' => $pricingRule->number_of_children,
                'is_for_pets' => $pricingRule->is_for_pets,
                'charge_to_client' => (float) $pricingRule->charge_to_client,
                'paid_to_caregiver' => (float) $pricingRule->paid_to_caregiver,
                'payment_form' => $pricingRule->payment_form,
                'sitterwise_cut' => (float) $pricingRule->sitterwise_cut,
            ] : null,
            'is_fallback' => $isFallback,
        ];

        if ($pricingRule) {
            $chargeToClient = round((float) $pricingRule->charge_to_client * $hours, 2);
            $paidToCaregiver = round((float) $pricingRule->paid_to_caregiver * $hours, 2);
            $sitterwiseCut = round((float) $pricingRule->sitterwise_cut * $hours, 2);

            $result['hourly'] = [
                'charge_to_client' => (float) $pricingRule->charge_to_client,
                'paid_to_caregiver' => (float) $pricingRule->paid_to_caregiver,
                'sitterwise_cut' => (float) $pricingRule->sitterwise_cut,
            ];

            $result['totals'] = [
                'charge_to_client' => $chargeToClient,
                'paid_to_caregiver' => $paidToCaregiver,
                'sitterwise_cut' => $sitterwiseCut,
            ];
        } else {
            $result['hourly'] = null;
            $result['totals'] = null;
        }

        return response()->json($result);
    }
}
