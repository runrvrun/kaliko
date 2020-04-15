<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Redeem;
use App\User;
use App\Merchant;
use App\Store;
use App\Product;
use App\StoreProductStock;
use DB;
use Carbon\Carbon;

class RedeemController extends Controller
{
    public $successStatus = 200;

    public function index()
    {
        $data = Redeem::get();
        return response()->json(['data' => $data,'data' => $data], $this->successStatus);
    }

    public function get($id)
    {
        $data = Redeem::where('id',$id)->get();
        return response()->json(['data' => $data], $this->successStatus);
    }
    
    public function redeem(Request $request)
    {
        // check input
        $merchant = Merchant::find($request->merchant_id);
        if(!$merchant){
            return response('Merchant not found',400);
        }
        $store = Store::find($request->store_id);
        if(!$store){
            return response('Store not found',400);
        }
        $product = Product::find($request->product_id);
        if(!$product){
            return response('Product not found',400);
        }
        // check PIN
        if($store->pin != $request->pin){
            return response()->json(['status'=>'failed','message'=>'Incorrect PIN'], 400);
        }
        // check past redeem. cannot redeem in same merchant for 2 weeks
        $storeproductstock = StoreProductStock::
        where('merchant_id',$request->merchant_id)->where('store_id',$request->store_id)->where('product_id',$request->product_id)
        ->first();

        $recently_redeem = Redeem::where('device_id',$request->device_id)
        ->where('redeems.merchant_id',$request->merchant_id)->where('redeems.store_id',$request->store_id)->where('redeems.product_id',$request->product_id)
        ->where('redeems.created_at','>',$storeproductstock->valid_start)
        ->where('redeems.created_at','>',Carbon::now()->subDays(14))->first();
        if($recently_redeem){
            return response()->json(['status'=>'failed','message'=>'Recently reedemed. Limit reached.'], 400);
        }
        $recently_redeem = Redeem::where('user_id',$request->device_id)
        ->where('redeems.merchant_id',$request->merchant_id)->where('redeems.store_id',$request->store_id)->where('redeems.product_id',$request->product_id)
        ->where('redeems.created_at','>',$storeproductstock->valid_start)
        ->where('redeems.created_at','>',Carbon::now()->subDays(14))->first();
        if($recently_redeem){
          return response()->json(['status'=>'failed','message'=>'Recently reedemed. Limit reached.'], 400);
        }
        // dd($recently_redeem);
        $requestData = ([
                'user_id' => Auth::user()->id,
                'merchant_id' => $request->merchant_id,
                'store_id' => $request->store_id,
                'product_id' => $request->product_id,
                'device_id' => $request->device_id,
        ]);
        $data = Redeem::create($requestData);
        //reduce stock
        StoreProductStock::where('store_id',$request->store_id)->where('product_id',$request->product_id)->update(['stock'=>DB::raw('stock-1')]);
        return response()->json(['status'=>'success','data' => $data], $this->successStatus);
    }
}