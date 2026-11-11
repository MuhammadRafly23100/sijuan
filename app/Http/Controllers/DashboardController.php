<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        return view('dashboard', [
            'user' => $user,
            'awaiting' => Submission::awaiting($user)->with('user')->oldest('submitted_at')->get(),
            'mine' => $user->submissions()->latest('id')->limit(5)->get(),
            'counts' => $user->submissions()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ]);
    }
}
