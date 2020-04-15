<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Contact;

class ContactController extends Controller
{
    public $successStatus = 200;

    public function index()
    {
        $data = Contact::paginate(10);
        return response()->json(['data' => $data], $this->successStatus);
    }
    public function get($id)
    {
        $data = Contact::select('id','title','body')->find($id); 
        return response()->json(['data' => $data], $this->successStatus);
    }
}