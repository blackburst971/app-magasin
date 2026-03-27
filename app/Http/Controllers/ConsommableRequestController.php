<?php

namespace App\Http\Controllers;

use App\Models\Consommable;
use App\Models\ConsommableMovement;
use App\Models\ConsommableRequest;
use App\Models\User;
use App\Notifications\ConsommableRequestPendingNotification;
use Illuminate\Http\Request;

class ConsommableRequestController extends Controller
{
    // ─── STORE : utilisateur fait une demande ─────────────────────────────
    public function store(Request $request, Consommable $consommable)
    {
        if (!$consommable->is_requestable) {
            abort(403, 'Ce consommable n\'accepte pas les demandes.');
        }

        $data = $request->validate([
            'quantity' => "required|integer|min:1|max:{$consommable->quantity}",
            'notes'    => 'nullable|string|max:500',
        ]);

        $consommableRequest = ConsommableRequest::create([
            'user_id'        => $request->user()->id,
            'consommable_id' => $consommable->id,
            'quantity'       => $data['quantity'],
            'notes'          => $data['notes'] ?? null,
            'status'         => 'pending',
        ]);

        // Notifier tous les admins
        $admins = User::role('admin')->get();
        foreach ($admins as $admin) {
            try {
                $admin->notify(new ConsommableRequestPendingNotification($consommableRequest));
            } catch (\Exception $e) {
                // Ne pas bloquer la demande si la notification échoue
            }
        }

        return back()->with('success', 'Demande envoyée. Un administrateur la traitera prochainement.');
    }

    // ─── APPROVE : admin approuve ─────────────────────────────────────────
    public function approve(Request $request, ConsommableRequest $consommableRequest)
    {
        if ($consommableRequest->status !== 'pending') {
            return back()->withErrors(['error' => 'Cette demande a déjà été traitée.']);
        }

        $data = $request->validate([
            'admin_notes' => 'nullable|string|max:500',
        ]);

        $consommable = $consommableRequest->consommable;

        if ($consommable->quantity < $consommableRequest->quantity) {
            return back()->withErrors([
                'error' => "Stock insuffisant : {$consommable->quantity} {$consommable->unit}(s) disponible(s).",
            ]);
        }

        $oldQty = $consommable->quantity;
        $newQty = $oldQty - $consommableRequest->quantity;

        $consommable->update(['quantity' => $newQty]);

        ConsommableMovement::create([
            'consommable_id'         => $consommable->id,
            'old_quantity'           => $oldQty,
            'new_quantity'           => $newQty,
            'quantity_change'        => -$consommableRequest->quantity,
            'user_id'                => $request->user()->id,
            'action'                 => 'request_approved',
            'consommable_request_id' => $consommableRequest->id,
            'notes'                  => $data['admin_notes'] ?? null,
        ]);

        $consommableRequest->update([
            'status'      => 'approved',
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'admin_notes' => $data['admin_notes'] ?? null,
        ]);

        return back()->with('success', 'Demande approuvée. Stock déduit.');
    }

    // ─── REJECT : admin refuse ────────────────────────────────────────────
    public function reject(Request $request, ConsommableRequest $consommableRequest)
    {
        if ($consommableRequest->status !== 'pending') {
            return back()->withErrors(['error' => 'Cette demande a déjà été traitée.']);
        }

        $data = $request->validate([
            'admin_notes' => 'required|string|max:500',
        ]);

        $consommableRequest->update([
            'status'      => 'rejected',
            'admin_notes' => $data['admin_notes'],
        ]);

        return back()->with('success', 'Demande refusée.');
    }

    // ─── CANCEL : utilisateur annule sa demande ───────────────────────────
    public function cancel(Request $request, ConsommableRequest $consommableRequest)
    {
        if ($consommableRequest->user_id !== $request->user()->id) {
            abort(403);
        }

        if ($consommableRequest->status !== 'pending') {
            return back()->withErrors(['error' => 'Seules les demandes en attente peuvent être annulées.']);
        }

        $consommableRequest->delete();

        return back()->with('success', 'Demande annulée.');
    }
}
