<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\User;
use App\City;
use App\Redeem;
use App\Notification;
use App\NotificationAllow;
use App\UserStoreProductStocksLike;
use App\SocialAccount;
use Validator;
use \Carbon\Carbon;
use Mail;
use Hash;
use DB; 
use Illuminate\Validation\Rule;
use LaravelFCM\Message\OptionsBuilder;
use LaravelFCM\Message\PayloadDataBuilder;
use LaravelFCM\Message\PayloadNotificationBuilder;
use FCM;

class UserController extends Controller
{
    public $successStatus = 200;

    public function login()
    {
        if(Auth::attempt(['email' => request('email'), 'password' => request('password')])) {
            $user = Auth::user();
            $tokenResult = $user->createToken('Kaliko Android App');
            $token = $tokenResult->token;
            $token->expires_at = Carbon::now()->addWeeks(1);
            $token->save();
            $success['token'] = $tokenResult->accessToken;
            $success['token_type'] = 'Bearer';
            $success['expires_at'] = Carbon::parse(
                $tokenResult->token->expires_at
            )->toDateTimeString();
            if(!empty(request('firebase_token'))) $data = Notification::updateOrCreate(['user_id'=>$user->id],['token'=>request('firebase_token')]);//set firebase token            
            return response()->json(['success' => $success], $this->successStatus);
        }else{
            return response()->json(['error'=>'Unauthorised'], 401);
        }
    }

    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'company' => 'required',
            'password' => 'required',
            'confirm_password' => 'required|same:password',
        ]);

        if ($validator->fails()) {
            $messages = $validator->errors()->messages();
            $message = '';
            foreach($messages as $key=>$errs){
                foreach($errs as $key=>$err){
                    $message .= $err;
                }
            }
            return response()->json(['message' => $message], 401);
        }

        $input = $request->all();
        $input['password'] = bcrypt($input['password']);
        $user = User::create($input);

        // $success['token'] =  $user->createToken('Breakpoin Appliction')->accessToken;
        $tokenResult = $user->createToken('Kaliko Android App');
        $token = $tokenResult->token;
        $token->expires_at = Carbon::now()->addWeeks(1);
        $token->save();
        $success['token'] = $tokenResult->accessToken;
        $success['token_type'] = 'Bearer';
        // $success['refresh_token'] =  ;
        $success['expires_at'] = Carbon::parse(
            $tokenResult->token->expires_at
        )->toDateTimeString();
            
        $success['name'] =  $user->name;

        // notify via email
        try{
            $to_name = $user->email;
            $to_email = $user->email;
            $data = array('fullname'=>$user->fullname);
                
            Mail::send('email.register_success', $data, function($message) use ($to_name, $to_email) {
                $message->to($to_email, $to_name)
                        ->subject('Welcome to Kaliko');
                $message->from('noreply@kaliko.com','Kaliko');
            });
        }catch(Exception $e){
            $return = [
                'status' => 'Fail sending email'
            ];
            return response($return,500);
        }

        return response()->json(['data' => $success], $this->successStatus);
    }

    public function socialLogin(Request $request)
    {
        $socialuser = SocialAccount::where('provider',$request->provider)->where('provider_user_id', $request->provider_user_id)->first();
        if(!$socialuser){
            $user = User::create([
                'email'    => $request->provider_user_id.'@'.$request->provider,
                'password' => Hash::make($request->provider)
            ]);
            $socialuser = SocialAccount::create([
                'user_id' => $user->id,
                'provider' => $request->provider,
                'provider_user_id' => $request->provider_user_id
            ]);
        }
        $user = User::where('id',$socialuser->user_id)->first();
        Auth::attempt(['email' => $user->email, 'password' => $request->provider]);
        $user = Auth::user();
        $tokenResult = $user->createToken('Kaliko Android App');
        $token = $tokenResult->token;
        $token->expires_at = Carbon::now()->addWeeks(1);
        $token->save();
        $success['token'] = $tokenResult->accessToken;
        $success['token_type'] = 'Bearer';
        $success['expires_at'] = Carbon::parse(
            $tokenResult->token->expires_at
        )->toDateTimeString();
        if(!empty(request('firebase_token'))) $data = Notification::updateOrCreate(['user_id'=>$user->id],['token'=>request('firebase_token')]);//set firebase token            
        return response()->json(['success' => $success], $this->successStatus);
    }

    public function details()
    {
        $user_id = Auth::user()->id;
        $user = User::with('notification_allows')->where('id',$user_id)->first();
        return response()->json(['data' => $user], $this->successStatus);
    }

    public function refreshToken(Request $request){
        $request->user()->token()->revoke();//error: Call to a member function token() on null

        $user = Auth::user();

        $tokenResult = $user->createToken('Kaliko Android App');
        $token = $tokenResult->token;
        $token->expires_at = Carbon::now()->addWeeks(1);
        $token->save();
        $success['token'] = $tokenResult->accessToken;
        $success['token_type'] = 'Bearer';
        $success['expires_at'] = Carbon::parse(
            $tokenResult->token->expires_at
        )->toDateTimeString();
        return response()->json(['success' => $success], $this->successStatus);
    }

    public function logout(Request $request){
        $request->user()->token()->revoke();
        return response()->json([
            'message' => 'Successfully logged out'
        ]);
    }

    public function forgetPassword(Request $request){
        $user = User::where('email',$request->email)->first();   
        if ($user){
            //generate random OTP; 6 digit, all caps
            $otp = $this->makeotp();
            $user->update(['otp'=>$otp]);
            //send email OTP
            try{
                $to_name = $user->email;
                $to_email = $user->email;
                $data = array('otp'=>$otp);
                    
                Mail::send('email.otp', $data, function($message) use ($to_name, $to_email) {
                    $message->to($to_email, $to_name)
                            ->subject('Kaliko OTP');
                    $message->from('noreply@kaliko.com','Kaliko');
                });
            }catch(Exception $e){
                $return = [
                    'status' => 'Fail sending email'
                ];
                return response($return,500);
            }

            $response["id"] = $user->id;
            return $response;
        }else{
            $return = [
                'status' => 'User not found'
            ];
            return response($return,400);
        }    
    }
        
    private function makeotp() {
        $generator = "1230987645";
        $result = ""; 
  
        for ($i = 1; $i <= 6; $i++) { 
            $result .= substr($generator, (rand()%(strlen($generator))), 1); 
        } 
    
        return $result;  
    }

    public function checkOtp(Request $request){
        $user = User::where('id',$request->user_id)->where('otp',$request->otp)->first();
        if ($user){
            $tokenResult = $user->createToken('Kaliko Android App');
            $token = $tokenResult->token;
            $token->expires_at = Carbon::now()->addWeeks(1);
            $token->save();
            $success['token'] = $tokenResult->accessToken;
            $success['token_type'] = 'Bearer';
            $success['expires_at'] = Carbon::parse(
                $tokenResult->token->expires_at
            )->toDateTimeString();
            return response()->json(['success' => $success], $this->successStatus);
        }else{
            $return = [
                'status' => 'Invalid OTP'
            ];
            return response($return,400);
        }
    }

    public function edit(Request $request) {
        $requestData = $request->all();      
        unset($requestData["user_id"]);
        $user = User::with('notification_allows')->find(Auth::id());

        $validator = Validator::make($request->all(), [
            'email' => 'email|'.Rule::unique('users')->ignore(Auth::user()->id),
        ]);
        if ($validator->fails()) {
            $messages = $validator->errors()->messages();
            $message = '';
            foreach($messages as $key=>$errs){
                foreach($errs as $key=>$err){
                    $message .= $err;
                }
            }
            return response()->json(['message' => $message], 401);
        }

        unset($requestData["user_id"]);
        if(isset($requestData["password"])){
            $requestData["password"] =  Hash::make($requestData["password"]);
            //send email notif that password changed
            if($requestData["password"] != $user->password){
                try{
                    $to_name = $user->email;
                    $to_email = $user->email;
                        
                    Mail::send('email.password_changed', array(), function($message) use ($to_name, $to_email) {
                        $message->to($to_email, $to_name)
                                ->subject('Kaliko Password Changed');
                        $message->from('noreply@kaliko.com','Kaliko');
                    });
                }catch(Exception $e){
                    $return = [
                        'status' => 'Fail sending email'
                    ];
                    return response($return,500);
                }
            }
            // send push notif that password changed
            $this->notifyChangedPassword($user);
        }
        $user->update($requestData);        
        $return = [
            'status' => 'User updated',
            'data' => $user
        ];
        return response($return,$this->successStatus);
    }
    
    public function edito(Request $request) {  
        $requestData = $request->all();     
        
        $validator = Validator::make($request->all(), [
            'email' => 'email|unique:users',
        ]);
        if ($validator->fails()) {
            $messages = $validator->errors()->messages();
            $message = '';
            foreach($messages as $key=>$errs){
                foreach($errs as $key=>$err){
                    $message .= $err;
                }
            }
            return response()->json(['message' => $message], 401);
        }
          
        $user = User::with('notification_allows')->find($request->user_id); 
        // $user = Auth::user();
        unset($requestData["user_id"]);
        if(isset($requestData["password"])){
            $requestData["password"] =  Hash::make($requestData["password"]);
        }
        $user->update($requestData);        
        $return = [
            'status' => 'User updated',
            'data' => $user
        ];
        return response($return,$this->successStatus);
    }

    public function editAvatar(Request $request) { 
        $user = Auth::user();
        $requestData = $request->all();     

        //upload photo to server
        if ($request->hasFile('avatar')) {
            $avatar = $request->file('avatar');
            $name = 'avatar_'.$user->id.'_'.time().'.'.$avatar->getClientOriginalExtension();
            $destinationPath = 'users';
            $avatar->move(base_path('public/storage/'.$destinationPath), $name);  
            $requestData['avatar']=$destinationPath.'/'.$name;            
            // dd($avatar);
        }else{
            $return = [
                'status' => 'failed',
                'message' => 'no file uploaded'
            ];
            return response($return,400);    
        }
        $user->update($requestData);
        $return = [
            'status' => 'User updated',
            'data' => $user
        ];
        return response($return,$this->successStatus);
    }

    public function changePassword(Request $request){
        $validator = Validator::make($request->all(), [
            'old_password' => 'required',
            'new_password' => 'min:4|required|confirmed|different:old_password',
            'new_password_confirmation' => 'min:4|required_with:new_password'
        ]);
        if ($validator->fails()) {
            $messages = $validator->errors()->messages();
            $message = '';
            foreach($messages as $key=>$errs){
                foreach($errs as $key=>$err){
                    $message .= $err;
                }
            }
            return response()->json(['message' => $message], 401);
        }

        $user = Auth::user();
        if(!Hash::check($request->old_password, $user->password)) {
            return response()->json(['message' => 'Incorrect password'], 400);
        }

        $requestData["password"] =  Hash::make($request->new_password);
        $user->update($requestData);        
        // send email notif that password changed
        if($requestData["password"] != $user->password){
            try{
                $to_name = $user->email;
                $to_email = $user->email;
                    
                Mail::send('email.password_changed', array(), function($message) use ($to_name, $to_email) {
                    $message->to($to_email, $to_name)
                            ->subject('Kaliko Password Changed');
                    $message->from('noreply@kaliko.com','Kaliko');
                });
            }catch(Exception $e){
                $return = [
                    'status' => 'Fail sending email'
                ];
                return response($return,500);
            }
        }
        // send push notif that password changed
        // $this->notifyChangedPassword($user);

        $return = [
            'status' => 'Password changed',
        ];
        return response($return,$this->successStatus);
    }

    public function changePasswordOtp(Request $request){
        $validator = Validator::make($request->all(), [
            'otp' => 'required',
            'new_password' => 'min:4|required|confirmed',
            'new_password_confirmation' => 'min:4|required_with:new_password'
        ]);
        if ($validator->fails()) {
            $messages = $validator->errors()->messages();
            $message = '';
            foreach($messages as $key=>$errs){
                foreach($errs as $key=>$err){
                    $message .= $err;
                }
            }
            return response()->json(['message' => $message], 401);
        }

        $user = Auth::user();
        if($user->otp != $request->otp) {
            return response()->json(['message' => 'Incorrect OTP'], 400);
        }

        $requestData["password"] =  Hash::make($request->new_password);
        $user->update($requestData);        
        // send email notif that password changed
        if($requestData["password"] != $user->password){
            try{
                $to_name = $user->email;
                $to_email = $user->email;
                    
                Mail::send('email.password_changed', array(), function($message) use ($to_name, $to_email) {
                    $message->to($to_email, $to_name)
                            ->subject('Kaliko Password Changed');
                    $message->from('noreply@kaliko.com','Kaliko');
                });
            }catch(Exception $e){
                $return = [
                    'status' => 'Fail sending email'
                ];
                return response($return,500);
            }
        }
        // send push notif that password changed
        // $this->notifyChangedPassword($user);

        $return = [
            'status' => 'Password changed',
        ];
        return response($return,$this->successStatus);
    }


    public function nearestCity(Request $request)
    {
        $data = City::select(DB::raw('name, lat, lng, SQRT(
            POW(69.1 * (lat - '.$request->lat.'), 2) +
            POW(69.1 * ('.$request->lng.' - lng) * COS(lat / 57.3), 2)) AS distance'))
            ->orderBy('distance','ASC')->first();
        // Update user city_id
        if(!empty($request->lat) && !empty($request->lng)){
            $user = Auth::user();
            $user->update(['city_id'=>$data->id]);
        }
        return response()->json(['data' => $data], $this->successStatus);
    }

    public function redeemHistory() {
        $data = Redeem::select('redeems.id','user_id', 'fullname','redeems.merchant_id','merchants.name AS merchant_name',
        'redeems.store_id', 'stores.name AS store_name','product_id', 'products.name AS product_name', 'saved_amount', 'products.image as product_image','redeems.created_at')
        ->addSelect(DB::Raw('(SELECT GROUP_CONCAT(pcategories.name SEPARATOR \', \') FROM pcategories 
        INNER JOIN product_pcategories ON pcategories.id=product_pcategories.pcategory_id WHERE product_id = products.id) AS categories'))
        ->leftJoin('users','users.id','user_id')
        ->leftJoin('merchants','merchants.id','redeems.merchant_id')
        ->leftJoin('stores','stores.id','redeems.store_id')
        ->leftJoin('products','products.id','product_id')
        ->where('user_id',Auth::user()->id)->orderBy('created_at','DESC')->paginate(10);
        $return = [
            'data' => $data
        ];
        return $return;
    }
    
    public function liked() {
        $data = UserStoreProductStocksLike::select('user_id','stores.merchant_id','merchants.name AS merchant_name',
        'store_product_stocks.store_id', 'stores.name AS store_name','store_product_stocks.product_id', 'stock', 
        'products.name AS product_name','products.image AS product_image', 'valid_start', 'valid_end')
        ->addSelect(DB::Raw('(SELECT GROUP_CONCAT(pcategories.name SEPARATOR \', \') FROM pcategories 
        INNER JOIN product_pcategories ON pcategories.id=product_pcategories.pcategory_id WHERE product_id = products.id) AS categories'))
        ->join('store_product_stocks','store_product_stocks.id','store_product_stock_id')
        ->join('users','users.id','user_id')
        ->join('stores','stores.id','store_product_stocks.store_id')
        ->join('merchants','merchants.id','stores.merchant_id')
        ->join('products','products.id','product_id')
        ->where('user_id',Auth::user()->id)->orderBy('merchants.name','ASC')->orderBy('stores.name','ASC')->orderBy('products.name','ASC')->paginate(10);
        $return = [
            'data' => $data
        ];
        return $return;
    }

    public function notifStore(Request $request){
        $requestData['user_id'] = $request->user_id;
        $requestData['token'] = $request->token;
        $data = Notification::create($requestData);
        return response()->json(['data' => $data], $this->successStatus);
    }

    public function notifAllow(Request $request){
        NotificationAllow::where('user_id', $request->user_id)->delete();
        $requestData['user_id'] = $request->user_id;
        $requestData['notification_name'] = 'give_promotion';
        $requestData['allow'] = $request->give_promotion;
        $data = NotificationAllow::create($requestData);
        $requestData['notification_name'] = 'send_gratisan';
        $requestData['allow'] = $request->send_gratisan;
        $data = NotificationAllow::create($requestData);
        return response()->json(['status' => 'Notification settings saved'], $this->successStatus);
    }

    public function notifDelete(Request $request){
        $data = Notification::where('token',$request->token)->delete();
        return response()->json(['status' => 'Notification removed'], $this->successStatus);
    }

    private function notifyChangedPassword($user){      
        $optionBuilder = new OptionsBuilder();
        $optionBuilder->setTimeToLive(60*20);

        $notificationBuilder = new PayloadNotificationBuilder('Kaliko');
        $notificationBuilder->setTitle('Kaliko Password Changed')
                            ->setBody('Your Kaliko password has been changed')
                            ->setSound('default')
                            ->setBadge('https://kaliko.oriadesoft.id/images/kaliko_logo_square.png');

        $dataBuilder = new PayloadDataBuilder();
        $dataBuilder->addData(['a_data' => 'my_data']);

        $option = $optionBuilder->build();
        $notification = $notificationBuilder->build();
        $data = $dataBuilder->build();
        
        // You must change it to get your tokens
        $tokens = Notification::where('user_id',$user->id)->pluck('token')->toArray();

        $downstreamResponse = FCM::sendTo($tokens, $option, $notification, $data);

        $downstreamResponse->numberSuccess();
        $downstreamResponse->numberFailure();
        $downstreamResponse->numberModification();

        // return Array - you must remove all this tokens in your database
        $downstreamResponse->tokensToDelete();

        // return Array (key : oldToken, value : new token - you must change the token in your database)
        $downstreamResponse->tokensToModify();

        // return Array - you should try to resend the message to the tokens in the array
        $downstreamResponse->tokensToRetry();

        // return Array (key:token, value:error) - in production you should remove from your database the tokens present in this array
        $downstreamResponse->tokensWithError();
    }
}