<?php

namespace App\Http\Controllers;

use DB;
use App\Models\Users;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\MyEmailRegistration;
use App\Mail\MyEmailConfirmation;

class loginController extends Controller
{

    /* FUNCIONES ESPECIFICAS */

    private function crypto_rand_secure($min, $max)
    {
        $range = $max - $min;
        if ($range < 1) return $min; // not so random...
        $log = ceil(log($range, 2));
        $bytes = (int) ($log / 8) + 1; // length in bytes
        $bits = (int) $log + 1; // length in bits
        $filter = (int) (1 << $bits) - 1; // set all lower bits to 1
        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));
            $rnd = $rnd & $filter; // discard irrelevant bits
        } while ($rnd > $range);
        return $min + $rnd;
    }

    private function getToken($length)
    {
        $token = "";
        $codeAlphabet = "ABCDEFGHIJKLMNOPQRSTUVWXYZ";
        $codeAlphabet.= "abcdefghijklmnopqrstuvwxyz";
        $codeAlphabet.= "0123456789";
        $max = strlen($codeAlphabet); // edited

        for ($i=0; $i < $length; $i++) {
            $token .= $codeAlphabet[$this->crypto_rand_secure(0, $max-1)];
        }

        return $token;
    }

    private function checkPassword($input){

        $validator = \Validator::make($input, [
            'email' => 'required',
            'password' => 'required'
        ]);

        if ($validator->fails()) {
            return false;
        }
        
        $user = Users::where('email',$input["email"])->first();

        if ( $user ){
            
            if (password_verify($input["password"], $user->password)) {
                return $user;
            } else {
                return false;
            }

        } else {
            return false;
        }
    }

    /* FIN FUNCIONES ESPECIFICAS */


   
    /* FUNCIONES PARA API PÚBLICO
    Todos los EndPoint que sean públicos, esto quiere decir que no requieren de un inicio de sesión deben ir en este apartado.
    */

    public function foodImages(Request $request){

        $posts = DB::table('food_images_catalog')->get();

        $array["foods"]  = Array();

        foreach($posts as $post) 
        {
            
            $arrayTemp = array(
                "id" => $post->id,
                "url" => "https://api.arces.mx/public/images/foods/".$post->id.".png",
            );

            array_push($array["foods"],$arrayTemp);

        }

        return response()->json(['status'=>'success','data'=>$array],200);

    }

    public function confirmEmail(Request $request)
    {

        $input = $request->all();

        $validator = \Validator::make($input, [
            'code' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>'error','msg'=>'Fields missing or wrong format'],422);
        }

        //CHECK IF MAIL EXISTS
        $checkUser = Users::where('email_confirmation',$input["code"])->first();

        if ( $checkUser )
        {
            DB::table('users')->where('id', $checkUser->id)->update(['email_confirmed' =>'1' , 'email_confirmation' => '0' ]); //Confirm Email, expire link
            return response()->json(['status'=>'success','msg'=>'The email has been confirmed'],200);

        } else {

            return response()->json(['status'=>'error','msg'=>'Expired link'],422);

        }

    }

    public function registerUser(Request $request)
    {   
        
        $input = $request->all();

        $validator = \Validator::make($input, [
            'email' => 'required',
            'password' => 'required|String',
            'food_image' => 'required|Integer',
            'phone' => 'required|numeric|min:10'
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>'error','msg'=>'Fields missing or wrong format'],422);
        }

        //EMAIL VALIDATION
        $validator = \Validator::make($input, [
            'email' => 'email'
        ]);
        if ($validator->fails()) {
            return response()->json(['status'=>'error','msg'=>'Invalid Email'],422);
        }

        //CHECK IF MAIL EXISTS
        $checkUser = Users::where('email',$input["email"])->first();

        //CHECK IF MAIL EXISTS
        $checkPhone = Users::where('phone',$input["phone"])->first();

        if ( $checkUser || $checkPhone )
        {
            
            return response()->json(['status'=>'error','msg'=>'Email  or phone, already registered'],422);

        } else {

            $passwordHash  = password_hash($input['password'], PASSWORD_DEFAULT);

            $apiToken = $this->getToken(60);
            $emailConfirmation = $this->getToken(60);

            $newUser = new Users;
            $newUser->name = "";
            $newUser->lastname = "";
            $newUser->lastname2 = "";
            $newUser->api_token = $apiToken;
            $newUser->email = $input['email'];
            $newUser->password =  $passwordHash;
            $newUser->food_image = $input['food_image'];
            $newUser->phone = $input['phone'];
            $newUser->email_confirmation = $emailConfirmation;
            
            if ( $newUser->save() ){
                $array["api_token"] = $apiToken;
                $data["code_confirmation"] = $emailConfirmation;
                $data["email"] = $input['email'];
                Mail::to($data["email"])->send(new MyEmailRegistration($data));
                Mail::to($data["email"])->send(new MyEmailConfirmation($data));
                return response()->json(['status'=>'success','msg'=>'User created','data'=>$array],200);
            }
        }

    }

    public function prelogin(Request $request)
    {
        
        $input = $request->all();

        $validator = \Validator::make($input, [
            'email' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>'error','msg'=>'The email is required'],422);
        }
        
        $user = Users::where('email',$input["email"])->first();

        if ( $user ){
            $array["name"] =  $user->name;
            $array["lastname"] =  $user->lastname;
            $array["food_image"] =  $user->food_image;
            return response()->json(['status'=>'success','data'=>$array],200);
        } else {
            return response()->json(['status'=>'error','msg'=>'Email not found'],422);
        }

    }

    public function login(Request $request)
    {
        
        $input = $request->all();

        $check = $this->checkPassword($input);
        if ($check == false){ //WORNG EMAIL AND PASSWORD
            return response()->json(['status'=>'error','msg'=>'Wrong credentials'],422);
        } else {
            $data = $check;
            $array["api_token"] = $data['api_token'];
            return response()->json(['status'=>'success','data'=>$array],200);
        }   

    }

    public function projects(Request $request){

        $input = $request->all();

        $posts = DB::table('projects')
                        ->orderBy('arc_fechainicio', 'asc')
                        ->get();

        $array["projects"]  = Array();

        foreach($posts as $post) 
        {
            
            $arrayTemp = array(
                "id" => $post->arc_id,
                "url_image" => "https://api.arces.mx/test.png",
                "name" => $post->arc_titulo,
                "score" => 3.5,
                "status" => "Abierto",
                "expected_return" => "10%",
                "expected_time" => $post->arc_meses,
                "minimum investment" => "",
            );

            array_push($array["projects"],$arrayTemp);

        }

        return response()->json(['status'=>'success','data'=>$array],200);

    }

    /* FIN DE FUNCIONES PÚBLICAS */




    /* FUNCIONES PARA API PRIVADO
    Todos los EndPoint que sean privados, esto quiere decir que requieren de un inicio de sesión deben ir en este apartado.
    */

    public function profile(Request $request)
    {
        $input = $request->all();

        $check = $this->checkPassword($input);
        if ($check == false){ //WORNG EMAIL AND PASSWORD
            return response()->json(['status'=>'error','msg'=>'Wrong credentials'],422);
        } else {
            $data = $check;
            $array = $data['api_token'];
             return response()->json(['status'=>'success','msg'=>'Good private access'],200);
        }   


    }

    /* FIN DE FUNCIONES PRIVADAS */

}