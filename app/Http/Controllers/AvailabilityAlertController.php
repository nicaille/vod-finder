<?php

namespace App\Http\Controllers;

use App\Models\AvailabilityAlert;
use Illuminate\Http\Request;

class AvailabilityAlertController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['state'=>'nullable|in:all,unread']); $state = $request->input('state','all');
        $alerts = $request->user()->availabilityAlerts()->when($state === 'unread',fn ($q) => $q->whereNull('read_at'))->latest()->paginate(24)->withQueryString();
        return view('availability.index',compact('alerts','state'));
    }
    public function read(Request $request, AvailabilityAlert $alert)
    {
        abort_unless($alert->user_id === $request->user()->id,404);
        $alert->update(['read_at'=>now()]);
        return back()->with('status','Alerte marquée comme lue.');
    }
}
