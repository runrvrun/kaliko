<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Faq;

class FaqController extends Controller
{
    public $successStatus = 200;

    public function index()
    {
        $data = Faq::paginate(10);
        return response()->json(['data' => $data], $this->successStatus);
    }
    public function get($id)
    {
        $data = Faq::select('id','title','body')->find($id); 
        return response()->json(['data' => $data], $this->successStatus);
    }
}