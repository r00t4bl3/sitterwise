<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttributeDefinitionRequest;
use App\Http\Requests\UpdateAttributeDefinitionRequest;
use App\Models\AttributeDefinition;
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

    public function store(StoreAttributeDefinitionRequest $request)
    {
        $validated = $request->validated();

        $maxOrder = AttributeDefinition::max('sort_order') ?? 0;
        $validated['sort_order'] = $maxOrder + 1;

        $attribute = new AttributeDefinition;
        $attribute->fill($validated);
        $attribute->slug = $validated['name'];
        $attribute->save();

        return redirect()->route('attributes.index')
            ->with('success', 'Attribute created successfully');
    }

    public function update(UpdateAttributeDefinitionRequest $request, AttributeDefinition $attribute)
    {
        $validated = $request->validated();

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
