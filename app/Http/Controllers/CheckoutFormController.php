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
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'required|in:inactive,optional,required_before,required_after',
            'fields'          => 'array',
            'fields.*.type'   => 'required|in:text,textarea,select,checkbox,date,number,email,phone,signature',
            'fields.*.label'  => 'required|string|max:255',
            'fields.*.required' => 'boolean',
            'fields.*.options'  => 'nullable|array',
            'fields.*.col_span' => 'nullable|integer|in:1,2',
        ]);

        $form = CheckoutForm::create([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'status'      => $data['status'],
            'created_by'  => $request->user()->id,
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
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'required|in:inactive,optional,required_before,required_after',
            'fields'          => 'array',
            'fields.*.id'     => 'nullable|integer',
            'fields.*.type'   => 'required|in:text,textarea,select,checkbox,date,number,email,phone,signature',
            'fields.*.label'  => 'required|string|max:255',
            'fields.*.required' => 'boolean',
            'fields.*.options'  => 'nullable|array',
            'fields.*.col_span' => 'nullable|integer|in:1,2',
        ]);

        $form->update([
            'name'        => $data['name'],
            'description' => $data['description'] ?? null,
            'status'      => $data['status'],
        ]);

        // Préserver les IDs existants pour ne pas casser les réponses déjà enregistrées
        $existingIds = $form->fields()->pluck('id')->toArray();
        $keptIds = [];

        foreach ($data['fields'] ?? [] as $i => $field) {
            $fieldId  = isset($field['id']) && in_array((int) $field['id'], $existingIds)
                ? (int) $field['id']
                : null;

            $fieldData = [
                'type'        => $field['type'],
                'label'       => $field['label'],
                'placeholder' => $field['placeholder'] ?? null,
                'options'     => $field['options'] ?? null,
                'required'    => $field['required'] ?? false,
                'col_span'    => $field['col_span'] ?? 1,
                'order'       => $i,
            ];

            if ($fieldId) {
                $form->fields()->where('id', $fieldId)->update($fieldData);
                $keptIds[] = $fieldId;
            } else {
                $created   = $form->fields()->create($fieldData);
                $keptIds[] = $created->id;
            }
        }

        // Supprimer les champs retirés
        if (!empty($keptIds)) {
            $form->fields()->whereNotIn('id', $keptIds)->delete();
        } else {
            $form->fields()->delete();
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
        $user    = request()->user();
        $isAdmin = $user->hasRole('admin');

        // Vérifier l'accès : propriétaire, admin ou participant
        if ($reservation->user_id !== $user->id && !$isAdmin) {
            $isParticipant = $reservation->additionalUsers()->where('users.id', $user->id)->exists();
            if (!$isParticipant) abort(403);
        }

        // Formulaire inactif : seul l'admin peut encore y accéder
        if ($form->status === 'inactive' && !$isAdmin) {
            abort(403, 'Ce formulaire est inactif.');
        }

        return Inertia::render('CheckoutForms/Fill', [
            'form'             => $form->load('fields'),
            'reservation'      => $reservation->load('assets'),
            'existingResponse' => CheckoutFormResponse::where([
                'checkout_form_id' => $form->id,
                'reservation_id'   => $reservation->id,
            ])->first(),
            'isAdmin'          => $isAdmin,
        ]);
    }

    public function submit(Request $request, CheckoutForm $form, Reservation $reservation)
    {
        $user    = $request->user();
        $isAdmin = $user->hasRole('admin');

        // Vérifier l'accès
        if ($reservation->user_id !== $user->id && !$isAdmin) {
            $isParticipant = $reservation->additionalUsers()->where('users.id', $user->id)->exists();
            if (!$isParticipant) abort(403);
        }

        $request->validate(['responses' => 'required|array']);

        $snapshot = $form->fields->map(fn($f) => [
            'id'       => $f->id,
            'type'     => $f->type,
            'label'    => $f->label,
            'options'  => $f->options,
            'required' => $f->required,
            'col_span' => $f->col_span ?? 1,
            'order'    => $f->order,
        ])->values()->all();

        CheckoutFormResponse::updateOrCreate(
            ['checkout_form_id' => $form->id, 'reservation_id' => $reservation->id],
            [
                'user_id'         => $user->id,
                'responses'       => $request->responses,
                'fields_snapshot' => $snapshot,
                'submitted_at'    => now(),
            ]
        );

        return redirect()->route('reservations.show', $reservation)
            ->with('success', 'Formulaire soumis avec succès.');
    }
}
