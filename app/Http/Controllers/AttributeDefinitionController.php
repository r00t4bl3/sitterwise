<?php

namespace App\Http\Controllers;

use App\Models\AttributeDefinition;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AttributeDefinitionController extends Controller
{
    public function index()
    {
        $attributes = AttributeDefinition::orderBy('entity_type')->orderBy('sort_order')->orderBy('name')->get();

        return Inertia::render('superadmin/attributes/index', [
            'attributes' => $attributes,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:boolean,date,text,number,select',
            'entity_type' => 'required|in:caregiver,client,both',
            'options' => 'nullable|array',
        ]);

        $maxOrder = AttributeDefinition::max('sort_order') ?? 0;
        $validated['sort_order'] = $maxOrder + 1;

        $attribute = new AttributeDefinition;
        $attribute->fill($validated);
        $attribute->slug = $validated['name'];
        $attribute->save();

        return redirect()->route('attributes.index')
            ->with('success', 'Attribute created successfully');
    }

    public function update(Request $request, AttributeDefinition $attribute)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'type' => 'required|in:boolean,date,text,number,select',
            'entity_type' => 'required|in:caregiver,client,both',
            'options' => 'nullable|array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ]);

        $attribute->slug = $validated['name'];
        $attribute->fill($validated);
        $attribute->save();

        return redirect()->route('attributes.index')
            ->with('success', 'Attribute updated successfully');
    }

    public function destroy(AttributeDefinition $attribute)
    {
        $attribute->delete();

        return redirect()->route('attributes.index')
            ->with('success', 'Attribute deleted successfully');
    }
}
