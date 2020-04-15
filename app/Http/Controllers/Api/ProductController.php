<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Product;
use App\City;
use App\ProductPcategory;
use App\StoreProductStock;
use DB;
use Carbon\Carbon;

class ProductController extends Controller
{
    public $successStatus = 200;

    public function index()
    {
        // $user = Product::get();
        // return response()->json(['data' => $user,'data' => $user], $this->successStatus);
    }

    public function get($id)
    {
        $user = Product::where('id',$id)->get();
        return response()->json(['data' => $user], $this->successStatus);
    }

    public function getSingle(Request $request)
    {
        return $this->search($request);
    }

    public function nearest(Request $request)
    {
        return $this->search($request);
    }

    public function getByCategory(Request $request)
    {
        return $this->search($request);
    }

    public function search(Request $request)
    {
        /*
            param:
            product_id = product_id (for get single product)
            store_id = store_id (for get single product)
            name = product_name or store_name or merchant_name
            lat
            lng
            max_distance = maximum distance
            min_stock = minimum stock
            max_stock = maximum stock
            sort1 = first sort
            sort2 = second sort
            sort3 = third sort
            product_ids = '1,2,3' get specific ids (used in similar)
        */
        /*
            SELECT merchants.name as merchant_name, stores.name as store_name, lat, lng, SQRT(
                POW(69.1 * (lat - -6.2264887), 2) +
                POW(69.1 * (106.8301736 - lng) * COS(lat / 57.3), 2)) AS distance
            FROM stores 
            INNER JOIN merchants ON merchants.id = stores.merchant_id
            ORDER BY distance ASC
        */
        $min_stock = $request->min_stock ?? 0;
        $max_stock = $request->max_stock ?? 99999;
        $max_distance = $request->max_distance ?? 99999;
        $select = 'products.id as product_id, stores.id as store_id, merchants.id as merchant_id, merchants.name as merchant_name,merchants.id as merchant_id, stores.name as store_name, stores.location as store_location, cities.name as city_name';
        $select .= ',(SELECT GROUP_CONCAT(pcategories.name SEPARATOR \', \') FROM pcategories 
        INNER JOIN product_pcategories ON pcategories.id=product_pcategories.pcategory_id WHERE product_id = products.id) AS categories';
        $select .= ', products.name as product_name, products.image, products.images, products.description, stock, start_stock, valid_start, valid_end, saved_amount, stores.lat, stores.lng, 
        products.created_at as product_created';
        if(!empty($request->lat) && !empty($request->lng)){
            $distq = 'SQRT(POW(69.1 * (stores.lat - '.$request->lat.'), 2) + POW(69.1 * ('.$request->lng.' - stores.lng) * COS(stores.lat / 57.3), 2))';
            $select .= ','.$distq.' AS distance';
        }else{
            $distq = '0';
            $select .= ','.$distq.' AS distance';
        }
        $select .= ', IFNULL(user_store_product_stocks_likes.user_id,0) as liked';

        $query = Product::query();
        $query->select(DB::raw($select))
        ->join('merchants','merchants.id','products.merchant_id')
        ->join('stores','stores.merchant_id','merchants.id')
        ->join('cities','stores.city_id','cities.id')
        ->join('store_product_stocks', function($join)
        {
            $join->on('store_product_stocks.store_id', '=', 'stores.id');
            $join->on('store_product_stocks.product_id', '=', 'products.id');
            $join->where('store_product_stocks.valid_start', '<=', Carbon::now());
            $join->where('store_product_stocks.valid_end', '>=', Carbon::now());
            $join->where('store_product_stocks.stock', '>', 0);
        })
        ->leftJoin('user_store_product_stocks_likes', function($join)
        {
            $join->on('store_product_stock_id', '=', 'store_product_stocks.id');
            $join->on('user_id', '=', DB::raw(Auth::user()->id));
        });
        $query->where('valid_end','>=',Carbon::now());
        $query->where('stock','>','0');
        $query->whereRaw($distq.'<='.$max_distance);
        $query->whereBetween('stock',array($min_stock,$max_stock));
        $query->where(function($query) use ($request){
            $query->whereRaw('LOWER(products.name) LIKE \'%'.strtolower($request->name).'%\'');
            $query->orWhereRaw('LOWER(stores.name) LIKE \'%'.strtolower($request->name).'%\'');
            $query->orWhereRaw('LOWER(merchants.name) LIKE \'%'.strtolower($request->name).'%\'');
        });
        if(isset($request->product_id)){
            $query->where('products.id',$request->product_id);
        }
        if(isset($request->product_ids)){
            // dd($request->product_ids); 
            $query->whereRaw('products.id in ('.$request->product_ids.')');
        }
        if(isset($request->store_id)){
            $query->where('stores.id',$request->store_id);
        }
        if(isset($request->pcategory_id)){
            $query->whereRaw('products.id IN (SELECT product_id FROM product_pcategories WHERE pcategory_id='.$request->pcategory_id.')');
        }
        if(isset($request->sort1)){
            $sort = explode(' ',$request->sort1);
            $query->orderBy($sort[0],$sort[1]);
        }
        if(isset($request->sort2)){
            $sort = explode(' ',$request->sort2);
            $query->orderBy($sort[0],$sort[1]);
        }
        if(isset($request->sort3)){
            $sort = explode(' ',$request->sort3);
            $query->orderBy($sort[0],$sort[1]);
        }
        $query->orderBy('distance','ASC')->orderBy('stock','ASC');
        $data = $query->paginate(10);

        foreach($data as $key=>$value){
            $images = json_decode($value->images);
            $value->images = $images;
        }

        // Update user city_id
        if(!empty($request->lat) && !empty($request->lng)){
            $user = Auth::user();
            /*
            SELECT * FROM cities ORDER BY SQRT(POW(69.1 * (lat - '-6.9'), 2) + POW(69.1 * ('107.6' - lng) * COS(lat / 57.3), 2)) ASC LIMIT 1
            */
            $nearest_city = City::orderByRaw('SQRT(POW(69.1 * (lat - '.$request->lat.'), 2) + POW(69.1 * ('.$request->lng.' - lng) * COS(lat / 57.3), 2)) ASC')->first();
            $user->update(['city_id'=>$nearest_city->id]);
        }

        return response()->json(['data' => $data], $this->successStatus);
    }

    public function getSimilar(Request $request){
        /*
        SELECT GROUP_CONCAT(product_id) product_ids FROM
        (SELECT product_id, count(1) as similarity FROM `product_pcategories` 
                WHERE pcategory_id in (SELECT pcategory_id FROM product_pcategories WHERE product_id=3) 
                AND product_id != 3 
                GROUP BY product_id ORDER BY similarity DESC LIMIT 3 )sim
        */
        $similar = DB::select(DB::Raw('SELECT GROUP_CONCAT(product_id) product_ids FROM
        (SELECT product_id, count(1) as similarity FROM `product_pcategories` 
                WHERE pcategory_id in (SELECT pcategory_id FROM product_pcategories WHERE product_id='.$request->product_id.') 
                AND product_id != '.$request->product_id.' 
                GROUP BY product_id ORDER BY similarity DESC LIMIT 3 )sim'));
        // dd($similar);
        $requestn = new \Illuminate\Http\Request();
        $requestn->replace(['product_ids' => $similar[0]->product_ids]);
        // $requestn = new Request([],[],['product_ids' => $similar[0]->product_ids]);
        // dd($requestn);
        return $this->search($requestn);
    }

    public function like(Request $request){
        $product = StoreProductStock::where('store_id',$request->store_id)->where('product_id',$request->product_id)->first();
        $product->likes()->detach( Auth::user()->id);
        $product->likes()->attach( Auth::user()->id);
        return response()->json(['status' => 'success'], $this->successStatus);
    }
    
    public function unlike(Request $request){
        $product = StoreProductStock::where('store_id',$request->store_id)->where('product_id',$request->product_id)->first();
        $product->likes()->detach( Auth::user()->id);
        return response()->json(['status' => 'success'], $this->successStatus);
    }

    public function refreshStock(){
        $requestData["valid_start"] = Carbon::now();
        $requestData["valid_end"] = Carbon::now()->addDays(14);
        StoreProductStock::where('valid_end','<=',Carbon::now())->update(['stock'=>DB::raw('start_stock')]);
        StoreProductStock::where('valid_end','<=',Carbon::now())->update($requestData);
        return response()->json(['status' => 'success'], $this->successStatus);
    }
}