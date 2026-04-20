<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePricingRuleRequest;
use App\Http\Requests\UpdatePricingRuleRequest;
use App\Models\PricingRule;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PricingRuleController extends Controller
{
    public function index()
    {
        $pricingRules = PricingRule::orderBy('service_type')
            ->orderBy('number_of_children')
            ->get();

        return Inertia::render('superadmin/pricing-rules/index', [
            'pricingRules' => $pricingRules,
        ]);
    }

    public function store(StorePricingRuleRequest $request)
    {
        $validated = $request->validated();

        PricingRule::create($validated);

        return redirect()->route('pricing-rules.index')
            ->with('success', 'Pricing Rule created successfully');
    }

    public function update(UpdatePricingRuleRequest $request, PricingRule $pricingRule)
    {
        $validated = $request->validated();

        $pricingRule->update($validated);

        return redirect()->route('pricing-rules.index')
            ->with('success', 'Pricing Rule updated successfully');
    }

    public function destroy(PricingRule $pricingRule)
    {
        $pricingRule->delete();

        return redirect()->route('pricing-rules.index')
            ->with('success', 'Pricing Rule deleted successfully');
    }

    public function search(Request $request)
    {
        $query = $request->input('q', '');

        $pricingRules = PricingRule::where('service_type', 'like', "%{$query}%")
            ->orWhere('number_of_children', 'like', "%{$query}%")
            ->limit(10)
            ->get(['id', 'service_type', 'number_of_children'])
            ->map(fn ($rule) => [
                'id' => $rule->id,
                'name' => $rule->service_type.($rule->number_of_children ? ' ('.$rule->number_of_children.' children)' : ''),
            ]);

        return response()->json($pricingRules);
    }
}
