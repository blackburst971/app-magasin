<?php

namespace App\Http\Controllers;

use App\Models\CheckoutForm;
use App\Models\CheckoutFormField;
use App\Models\CheckoutFormResponse;
use App\Models\Reservation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckoutFormController extends Controller
{
    public function index()
    {
        return Inertia::render('CheckoutForms/Index', [
            'forms' => CheckoutForm::with(['fields', 'creator'])->latest()->get(),
        ]);
    }

    public function create()
    {
        return Inertia::render('CheckoutForms/Builder', ['form' => null]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'fields' => 'array',
            'fields.*.type' => 'required|in:text,textarea,select,checkbox,date,number,email,phone,signature',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.required' => 'boolean',
            'fields.*.options' => 'nullable|array',
            'is_required_for_reservation' => 'boolean',
        ]);

        $form = CheckoutForm::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? false,
            'is_required_for_reservation' => $data['is_required_for_reservation'] ?? false,
            'created_by' => $request->user()->id,
        ]);

        foreach ($data['fields'] ?? [] as $i => $field) {
            $form->fields()->create([...$field, 'order' => $i]);
        }

        return redirect()->route('checkout-forms.index')->with('success', 'Formulaire créé.');
    }

    public function edit(CheckoutForm $form)
    {
        return Inertia::render('CheckoutForms/Builder', [
            'form' => $form->load('fields'),
        ]);
    }

    public function update(Request $request, CheckoutForm $form)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'is_required_for_reservation' => 'boolean',
            'fields' => 'array',
        ]);

        $form->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? false,
            'is_required_for_reservation' => $data['is_required_for_reservation'] ?? false,
        ]);

        $form->fields()->delete();
        foreach ($data['fields'] ?? [] as $i => $field) {
            $form->fields()->create([...$field, 'order' => $i]);
        }

        return redirect()->route('checkout-forms.index')->with('success', 'Formulaire mis à jour.');
    }

    public function destroy(CheckoutForm $form)
    {
        $form->delete();
        return back()->with('success', 'Formulaire supprimé.');
    }

    public function fill(CheckoutForm $form, Reservation $reservation)
    {
        return Inertia::render('CheckoutForms/Fill', [
            'form' => $form->load('fields'),
            'reservation' => $reservation->load('assets'),
            'existingResponse' => CheckoutFormResponse::where([
                'checkout_form_id' => $form->id,
                'reservation_id' => $reservation->id,
            ])->first(),
        ]);
    }

    public function submit(Request $request, CheckoutForm $form, Reservation $reservation)
    {
        $request->validate(['responses' => 'required|array']);

        CheckoutFormResponse::updateOrCreate(
            ['checkout_form_id' => $form->id, 'reservation_id' => $reservation->id],
            [
                'user_id' => $request->user()->id,
                'responses' => $request->responses,
                'submitted_at' => now(),
            ]
        );

        return redirect()->route('reservations.show', $reservation)
            ->with('success', 'Formulaire soumis.');
    }
}
