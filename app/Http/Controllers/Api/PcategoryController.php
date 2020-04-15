<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Pcategory;
use App\Product;

class PcategoryController extends Controller
{
    public $successStatus = 200;

    public function index()
    {
        $data = Pcategory::get();
        return response()->json(['data' => $data], $this->successStatus);
    }
}