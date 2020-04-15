<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Merchant;
use App\FeaturedEvent;
use App\Store;
use DB;

class MerchantController extends Controller
{
    public $successStatus = 200;

    public function index()
    {
        $data = Merchant::get();
        return response()->json(['data' => $data], $this->successStatus);
    }

    public function get($id)
    {
        $data = Merchant::where('id',$id)->get();
        return response()->json(['data' => $data], $this->successStatus);
    }
    
    public function featured()
    {
        $data = Merchant::where('is_featured',1)->get();
        return response()->json(['data' => $data], $this->successStatus);
    }
    
    public function featuredContent()
    {
        $featured = FeaturedEvent::select(DB::raw('id,name,featured_image,url,\'event\' as type'));
        $data = Merchant::select(DB::raw('id,name,featured_image,null as url,\'merchant\' as type'))->where('is_featured',1)
        ->union($featured)->get();
        return response()->json(['data' => $data], $this->successStatus);
    }

}