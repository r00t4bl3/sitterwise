<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Models\Setting;
use App\Support\Settings;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(): Response
    {
        $settingGroups = Setting::orderBy('group')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Setting $setting) => [
                'key' => $setting->key,
                'value' => $setting->castedValue(),
                'type' => $setting->type,
                'group' => $setting->group,
                'label' => $setting->label,
                'description' => $setting->description,
            ])
            ->groupBy('group');

        return Inertia::render('superadmin/settings/index', [
            'settingGroups' => $settingGroups,
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $known = Setting::pluck('key')->all();

        foreach ($request->validated()['settings'] as $key => $value) {
            if (in_array($key, $known, true)) {
                Settings::set($key, $value);
            }
        }

        return back()->with('success', 'Settings updated.');
    }
}
