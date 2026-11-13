<?php

namespace App\Http\Controllers\Bd;

use App\Http\Controllers\Controller;
use App\Services\TutorialVideoService;

/** "How To Do ?" — the BD tutorial videos. */
class HowToController extends Controller
{
    public function index(TutorialVideoService $tutorials)
    {
        return view('bd.how-to', $tutorials->forRole('bd'));
    }
}
