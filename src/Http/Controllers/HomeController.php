<?php

namespace Laravel\Package\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\App;
use Laravel\Package\Http\Middleware\Authenticate;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware(Authenticate::class);
    }

    /**
     * Single page application catch-all route.
     *
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function index()
    {
        return view('laravel-package::layout', [
            'scriptVariables' => [
                'path' => config('laravel-package.path'),
            ],
            'isDownForMaintenance' => App::isDownForMaintenance(),
        ]);
    }
}
