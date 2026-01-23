<?php

namespace App\Http\Controllers;

use App\Models\Dispatcher;
use App\Services\OpenSIPSMIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DispatcherController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'setid' => 'required|integer',
            'destination' => 'required|string|max:192|regex:/^sip:((\[([0-9a-fA-F]{0,4}:){2,7}[0-9a-fA-F]{0,4}\]|([0-9]{1,3}\.){3}[0-9]{1,3}|([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}))(:[0-9]{1,5})?$/',
            'weight' => 'nullable|integer|min:0',
            'priority' => 'nullable|integer|min:0',
            'state' => 'nullable|integer|in:0,1',
            'description' => 'required|string|max:64',
        ]);

        try {
            $dispatcher = Dispatcher::create([
                'setid' => $request->setid,
                'destination' => $request->destination,
                'weight' => $request->weight ?? 1,
                'priority' => $request->priority ?? 0,
                'state' => $request->state ?? 0,
                'description' => $request->description,
                'probe_mode' => 0,
                'socket' => null,
                'attrs' => null,
            ]);

            // Reload OpenSIPS modules after creation
            try {
                $miService = app(OpenSIPSMIService::class);
                $miService->dispatcherReload();
            } catch (\Exception $e) {
                \Log::warning('OpenSIPS MI reload failed after destination creation', ['error' => $e->getMessage()]);
            }

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Destination created successfully', 'dispatcher' => $dispatcher]);
            }

            return redirect()->back()->with('success', 'Destination created successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to create destination', ['error' => $e->getMessage()]);
            
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to create destination: ' . $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Failed to create destination');
        }
    }

    public function update(Request $request, Dispatcher $dispatcher)
    {
        $request->validate([
            'destination' => 'required|string|max:192|regex:/^sip:((\[([0-9a-fA-F]{0,4}:){2,7}[0-9a-fA-F]{0,4}\]|([0-9]{1,3}\.){3}[0-9]{1,3}|([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,}))(:[0-9]{1,5})?$/',
            'weight' => 'nullable|integer|min:0',
            'priority' => 'nullable|integer|min:0',
            'state' => 'nullable|integer|in:0,1',
            'description' => 'nullable|string|max:64',
        ]);

        try {
            $dispatcher->update([
                'destination' => $request->destination,
                'weight' => $request->weight ?? $dispatcher->weight,
                'priority' => $request->priority ?? $dispatcher->priority,
                'state' => $request->state ?? $dispatcher->state,
                'description' => $request->description ?? $dispatcher->description,
            ]);

            // Reload OpenSIPS modules after update
            try {
                $miService = app(OpenSIPSMIService::class);
                $miService->dispatcherReload();
            } catch (\Exception $e) {
                \Log::warning('OpenSIPS MI reload failed after destination update', ['error' => $e->getMessage()]);
            }

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Destination updated successfully', 'dispatcher' => $dispatcher]);
            }

            return redirect()->back()->with('success', 'Destination updated successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to update destination', ['error' => $e->getMessage(), 'dispatcher_id' => $dispatcher->id]);
            
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to update destination: ' . $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Failed to update destination');
        }
    }

    public function destroy(Request $request, Dispatcher $dispatcher)
    {
        try {
            DB::transaction(function () use ($dispatcher) {
                $dispatcher->delete();
            });

            // Reload OpenSIPS modules after deletion
            try {
                $miService = app(OpenSIPSMIService::class);
                $miService->dispatcherReload();
            } catch (\Exception $e) {
                \Log::warning('OpenSIPS MI reload failed after destination deletion', ['error' => $e->getMessage()]);
            }

            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => true, 'message' => 'Destination deleted successfully']);
            }

            return redirect()->back()->with('success', 'Destination deleted successfully');
        } catch (\Exception $e) {
            \Log::error('Failed to delete destination', ['error' => $e->getMessage(), 'dispatcher_id' => $dispatcher->id]);
            
            if ($request->expectsJson() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Failed to delete destination'], 500);
            }

            return redirect()->back()->with('error', 'Failed to delete destination');
        }
    }
}
