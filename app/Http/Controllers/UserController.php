<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class UserController extends Controller
{
    /**
     * Display the user account panel
     *
     * @return \Illuminate\Http\Response
     */
     public function upload()
     {
         return view('user/account');
     }
}
