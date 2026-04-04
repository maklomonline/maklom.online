<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use App\Models\UserStat;
use App\Services\RatingService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    public function showForm()
    {
        $registrableRanks = RatingService::registrableRanks();
        return view('auth.register', compact('registrableRanks'));
    }

    public function store(RegisterRequest $request)
    {
        $rank        = $request->initial_rank;
        $rankPoints  = RatingService::initialRatingForRank($rank);

        $user = User::create([
            'name'        => $request->name,
            'username'    => $request->username,
            'email'       => $request->email,
            'password'    => $request->password,
            'locale'      => 'th',
            'rank'        => $rank,
            'rank_points' => $rankPoints,
        ]);

        UserStat::create(['user_id' => $user->id]);

        event(new Registered($user));
        Auth::login($user);

        return redirect()->route('verification.notice');
    }
}
