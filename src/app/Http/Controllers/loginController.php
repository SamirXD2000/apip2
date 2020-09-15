<?php

namespace App\Http\Controllers;

use DB;

/* MODELS */
use App\Models\Users;
use App\Models\UserSessions;

/* LIBRERIES */
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\MyEmailRegistration;
use App\Mail\MyEmailConfirmation;
use Jenssegers\Agent\Agent;

class loginController extends Controller
{


    /* FUNCIONES ESPECIFICAS */

    private function errorsSession($error)
    {
       
        $array["error_code"] = "";
        $array["error_msg"] = "";

        switch($error)
        {
            case 0:
                $array["error_code"] = "001";
                $array["error_msg"] = "Wrong credentials";
                break;

            case 1:
                $array["error_code"] = "002";
                $array["error_msg"] = "Session expired";
                break;

            case 2:
                $array["error_code"] = "003";
                $array["error_msg"] = "Session already open";
                break;

             case 3:
                $array["error_code"] = "004";
                $array["error_msg"] = "Session already another device";
                break;

            default:
                $array["error_code"] = "000";
                $array["error_msg"] = "Generic error";
        }

        return $array;

    }

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

    private function ip_info($ip = NULL, $purpose = "location", $deep_detect = TRUE) {
        $output = NULL;
        if (filter_var($ip, FILTER_VALIDATE_IP) === FALSE) {
            $ip = $_SERVER["REMOTE_ADDR"];
            if ($deep_detect) {
                if (filter_var(@$_SERVER['HTTP_X_FORWARDED_FOR'], FILTER_VALIDATE_IP))
                    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
                if (filter_var(@$_SERVER['HTTP_CLIENT_IP'], FILTER_VALIDATE_IP))
                    $ip = $_SERVER['HTTP_CLIENT_IP'];
            }
        }
        $purpose    = str_replace(array("name", "\n", "\t", " ", "-", "_"), NULL, strtolower(trim($purpose)));
        $support    = array("country", "countrycode", "state", "region", "city", "location", "address");
        $continents = array(
            "AF" => "Africa",
            "AN" => "Antarctica",
            "AS" => "Asia",
            "EU" => "Europe",
            "OC" => "Australia (Oceania)",
            "NA" => "North America",
            "SA" => "South America"
        );
        if (filter_var($ip, FILTER_VALIDATE_IP) && in_array($purpose, $support)) {
            $ipdat = @json_decode(file_get_contents("http://www.geoplugin.net/json.gp?ip=" . $ip));
            if (@strlen(trim($ipdat->geoplugin_countryCode)) == 2) {
                switch ($purpose) {
                    case "location":
                        $output = array(
                            "city"           => @$ipdat->geoplugin_city,
                            "state"          => @$ipdat->geoplugin_regionName,
                            "country"        => @$ipdat->geoplugin_countryName,
                            "country_code"   => @$ipdat->geoplugin_countryCode,
                            "continent"      => @$continents[strtoupper($ipdat->geoplugin_continentCode)],
                            "continent_code" => @$ipdat->geoplugin_continentCode
                        );
                        break;
                    case "address":
                        $address = array($ipdat->geoplugin_countryName);
                        if (@strlen($ipdat->geoplugin_regionName) >= 1)
                            $address[] = $ipdat->geoplugin_regionName;
                        if (@strlen($ipdat->geoplugin_city) >= 1)
                            $address[] = $ipdat->geoplugin_city;
                        $output = implode(", ", array_reverse($address));
                        break;
                    case "city":
                        $output = @$ipdat->geoplugin_city;
                        break;
                    case "state":
                        $output = @$ipdat->geoplugin_regionName;
                        break;
                    case "region":
                        $output = @$ipdat->geoplugin_regionName;
                        break;
                    case "country":
                        $output = @$ipdat->geoplugin_countryName;
                        break;
                    case "countrycode":
                        $output = @$ipdat->geoplugin_countryCode;
                        break;
                }
            }
        }
        return $output;
    }

    private function checkSession($request = false, $isLogin=false) //Retur 0 on wron credentials, return 1 on sesion expired, return user object 
    {

        $api_token = $request->header('api_token');

        $input = $request->all();

        $validator = \Validator::make($input, [
            'email' => 'required',
            'password' => 'required'
        ]);

        if ( $validator->fails() ) { //NO SE ENCUENTRA LAS VARIABLES DE CREDENCIALES DE ACCESO
            return 0;
        }
        
        $user = Users::where('email',$input["email"])->first(); //SE CONSULTA LA BD 

        if ( $user ){

            if (password_verify($input["password"], $user->password)  )
            {
            
            } else {
                return 0;
            }

            //DEBEMOS CONSULTAR QUE LA SESION ACTIVA, SEA DEL MISMO DISPOSITIVO
            $sessionGet = DB::table('user_sessions')->where( array('user_id' => $user->id,'status' => 1))->first();
               
            //OBTENEMOS LOS DATOS DE LA SESION
            $headersData = $this->getRequestData($request);

            //echo $headersData["ip"];
            //print_r( $this->ip_info($headersData["ip"]) );

            //exit;
            
            if ( $isLogin == false ) //SI ES PETICION SUBSECUENTE AL LOGIN
            {

                if ( $api_token != $user->api_token ) //VALIDA QUE EL API TOKEN SEA DEL USUARIO QUE ME ESTA CONSULTANDO
                {
                    return 0;
                }

                if ( $sessionGet ){ //SE ENCONTRO UNA SESION SUPUESTAMENTE ABIERTA

                    //SI ES DEL MISMO DISPOSITIVO VALIDO SI NO HA EXPIRADO
                    if ( new \DateTime(date('Y-m-d H:i:s')) > new \DateTime($sessionGet->expired_at) ) { //SESION EXPIRADA

                        DB::table('user_sessions')->where('id', $sessionGet->id)->update(['status' => 0]); //ESTABLECE LA SESION A EXPIRADA EN CASO NO ESTARLO
                        return 1;
                    }

                    //VALIDAMOS SI ES DEL MISMO DISPOSITIVO
                    if ( $sessionGet->user_agent == $headersData["userAgent"] && $sessionGet->device == $headersData["device"] &&  $sessionGet->platform == $headersData["platform"] &&  $sessionGet->browser == $headersData["browser"] &&  $sessionGet->robot == $headersData["robotName"] ){ // ES EL MISMO DISPOSIITO
                    } else {
                        return 3; //SESION ACTIVA EN OTRO DISPOSITIVO
                    }

                } else { //NO SE ENCONTRO SESION ACTIVA; QUIERE DECIR QUE YA VENCIÓ
                    return 1;
                }

                //SI LAS CREDENCIALES SON CORRECTAS, TOKEN CORRECTO, RENUEVO SESION
                $dateTime = new \DateTime(date('Y-m-d H:i:s'));
                $dateTime = $dateTime->modify('+5 minutes');
                $dateTime = $dateTime->format("Y-m-d H:i:s");

                DB::table('user_sessions')->where('id', $sessionGet->id)->update(['expired_at' => $dateTime ]); //Confirm Email, expire link   

            } else { //SI ES DE LOGIN, REVISAMOS SI EXISTE UNA SESIÓN EXISTENTE

                if ( $sessionGet ){ //SE ENCONTRO UNA SESION SUPUESTAMENTE ABIERTA

                    if ( new \DateTime(date('Y-m-d H:i:s')) > new \DateTime($sessionGet->expired_at) )
                    { //SESION EXPIRADA, POR LO QUE NO REPRESENTA UNA SESION ACTIVA EN OTRO DISPOSITIVO

                        DB::table('user_sessions')->where('id', $sessionGet->id)->update(['status' => 0]); //ESTABLECE LA SESION A EXPIRADA EN CASO NO ESTARLO

                    } else { //LA SESION AÚN ESTA ABIERTA

                        //REVISO QUE SEA DEL MISMO DISPOSITIVO
                        if ( $sessionGet->user_agent == $headersData["userAgent"] && $sessionGet->device == $headersData["device"] &&  $sessionGet->platform == $headersData["platform"] &&  $sessionGet->browser == $headersData["browser"] &&  $sessionGet->robot == $headersData["robotName"] ){ 
                            return 2;//SESION ACTIVA EN OTRO DISPOSITIVO
                        
                        } else {
                            return 3; //SESION ACTIVA EN OTRO DISPOSITIVO
                        
                        }
                    }
                }
            }

            return $user;

        } else {

            return 0;

        }
    }


    private function getRequestData($request){

        $agent = new Agent();

        $ip = $request->getClientIps()[0];

        $userAgent =  $request->header('User-Agent');

        if ($agent->device()){
            $device = $agent->device();
        } else {
            $device = "";
        }

        if ($agent->platform()){
            $platform = $agent->platform().", version:".$agent->version($agent->platform());
        } else {
            $platform = "";
        }

        if ($agent->browser()){
            $browser = $agent->browser().", version:".$agent->version($agent->browser());
        } else {
            $browser = "";
        }

        $isRobot = $agent->isRobot();
        if ($isRobot){
            $robotName = $agent->robot();  
        } else {
            $robotName = "";
        }

        $array["ip"] = $ip;
        $array["userAgent"] = $userAgent;
        $array["device"] = $device;
        $array["platform"] = $platform;
        $array["browser"] = $browser;
        $array["robotName"] = $robotName;

        return $array;

    }


    private function setSesionData($request,$user){

        $startedAt = new \DateTime(date('Y-m-d H:i:s'));
        $startedAt = $startedAt->format("Y-m-d H:i:s");

        $expiredAt = new \DateTime(date('Y-m-d H:i:s'));
        $expiredAt = $expiredAt->modify('+5 minutes');
        $expiredAt = $expiredAt->format("Y-m-d H:i:s");

        $lastRequest = $startedAt;
        
        $agent = new Agent();

        $ip = $request->getClientIps()[0];

        $userAgent =  $request->header('User-Agent');

        if ($agent->device()){
            $device = $agent->device();
        } else {
            $device = "";
        }

        if ($agent->platform()){
            $platform = $agent->platform().", version:".$agent->version($agent->platform());
        } else {
            $platform = "";
        }

        if ($agent->browser()){
            $browser = $agent->browser().", version:".$agent->version($agent->browser());
        } else {
            $browser = "";
        }

        $isRobot = $agent->isRobot();
        if ($isRobot){
            $robotName = $agent->robot();  
        } else {
            $robotName = "";
        }

        $newSession = new UserSessions;
        $newSession->user_id = $user->id;
        $newSession->started_at = $startedAt;
        $newSession->expired_at = $expiredAt;
        $newSession->last_request = $lastRequest;
        $newSession->request_ip = $ip;
        $newSession->user_agent =  $userAgent;
        $newSession->device = $device;
        $newSession->platform = $platform;
        $newSession->browser = $browser;
        $newSession->robot = $robotName;
        $newSession->status = 1;

        if ( $newSession->save()){
            return true;
        }

        return false;

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
            if ($check->email_confirmed == 1) {
                 return response()->json(['status'=>'error','msg'=>'Email already confirmed'],422);
            }

            DB::table('users')->where('id', $checkUser->id)->update(['email_confirmed' =>'1' , 'email_confirmation' => '0' , 'email_confirmation_date' => date('Y-m-d H:i:s') ]); //Confirm Email, expire link
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

            $apiToken = $this->getToken(60); //GENERA API TOKEN
            $emailConfirmation = $this->getToken(30); //GENERA CONFIRMACION DE CORREO
            $codePhoneConfirmation = mt_rand(100000,999999); //GENERA UN CODIGO ALEATORIO DE 6 DÍGITOS

            $dateTime = new \DateTime(date('Y-m-d H:i:s'));
            $dateTime = $dateTime->modify('+5 minutes');
            $dateTime = $dateTime->format("Y-m-d H:i:s");

            $newUser = new Users;
            $newUser->name = "";
            $newUser->lastname = "";
            $newUser->lastname2 = "";
            $newUser->api_token = $apiToken;
            $newUser->email = $input['email'];
            $newUser->password =  $passwordHash;
            $newUser->food_image = $input['food_image'];
            $newUser->phone = $input['phone'];
            $newUser->phone_code_for_confirm = $codePhoneConfirmation;
            $newUser->email_confirmation = $emailConfirmation;
            $newUser->token_expiration = $dateTime;
            
            if ( $newUser->save() ){
                $array["api_token"] = $apiToken;
                $data["code_confirmation"] = $emailConfirmation;
                $data["email"] = $input['email'];
                Mail::to($data["email"])->send(new MyEmailRegistration($data));
                Mail::to($data["email"])->send(new MyEmailConfirmation($data));

                $check = Users::where('email',$input["email"])->first(); //CONSULTO PARA OBTENER EL USUARIO NUEVO

                $this->setSesionData($request,$check); //CREO PRIMERA SESION

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

        $check = $this->checkSession($request,true);
        
        if ( !is_object($check) ){

            $errors = $this->errorsSession($check);

            return response()->json(['status'=>'error','error_code' => $errors["error_code"] ,'msg'=>$errors["error_msg"]],401);
        
        } else {

            $data = $check;
            $array["api_token"] = $data['api_token'];

            $dateTime = new \DateTime(date('Y-m-d H:i:s'));
            $dateTime = $dateTime->modify('+5 minutes');
            $dateTime = $dateTime->format("Y-m-d H:i:s");

            $this->setSesionData($request,$check); //CREO SESIÓN

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
    Todos los EndPoint que sean privados, esto quiere decir todos los que requieren de un inicio de sesión, deberán ir en este apartado.
    */

    public function profile(Request $request)
    {
        $input = $request->all();

        $check = $this->checkSession($request);
        
        if ( !is_object($check) ){

            $errors = $this->errorsSession($check);

            return response()->json(['status'=>'error','error_code' => $errors["error_code"] ,'msg'=>$errors["error_msg"]],401);
        
        } else {

            return response()->json(['status'=>'success','msg'=>'Good private access'],200);
        }
    }

    public function sendEmailConfirmation(Request $request)
    {
        $input = $request->all();
 
        if ( !is_object($check) ){

            $errors = $this->errorsSession($check);

            return response()->json(['status'=>'error','error_code' => $errors["error_code"] ,'msg'=>$errors["error_msg"]],401);
        
        } else {
            $data = $check;
            $array = $data['api_token'];
            $emailConfirmation = $this->getToken(30);

            $data["code_confirmation"] = $emailConfirmation;
            $data["email"] = $check->email;
            
            DB::table('users')->where('id', $check->id)->update(['email_confirmation' => $emailConfirmation  ]);

            Mail::to($data["email"])->send(new MyEmailConfirmation($data));

            return response()->json(['status'=>'success','msg'=>'Confirmation email sent'],200);
        }   
    }

     public function confirmPhone(Request $request)
    {
        $input = $request->all();

        $validator = \Validator::make($input, [
            'codenumber' => 'required|Integer'
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>'error','msg'=>'Fields missing or wrong format'],422);
        }

        $check = $this->checkSession($request,false);
       
        if ( !is_object($check) ){

            $errors = $this->errorsSession($check);

            return response()->json(['status'=>'error','error_code' => $errors["error_code"] ,'msg'=>$errors["error_msg"]],401);
        
        } else {

            if ($check->phone_confirmed == 1) {
                 return response()->json(['status'=>'error','msg'=>'Phone already confirmed'],422);
            }

            if ( $input["codenumber"] != $check->phone_code_for_confirm )
            {
                return response()->json(['status'=>'error','msg'=>'Wrong code'],422);
            } else {

                DB::table('users')->where('id', $check->id)->update(['phone_confirmed' =>'1' , 'phone_code_for_confirm' => '0' , 'phone_confirmation_date' => date('Y-m-d H:i:s') ]); //Confirm 

                return response()->json(['status'=>'success','msg'=>'Phone Confirmed'],200);
            }
            
        }   
    }


    public function createNip(Request $request)
    {
        $input = $request->all();

        $validator = \Validator::make($input, [
           'nip' => 'required|Integer|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>'error','msg'=>'Fields missing or wrong format'],422);
        }

       
        if ( !is_object($check) ){

            $errors = $this->errorsSession($check);

            return response()->json(['status'=>'error','error_code' => $errors["error_code"] ,'msg'=>$errors["error_msg"]],401);
        
        } else {

            if ( $check->nip_confirmed == 1 )
            {
                return response()->json(['status'=>'error','msg'=>'The Nip has already been created'],422);
            } else {

                $nip  = password_hash($input["nip"], PASSWORD_DEFAULT);

                DB::table('users')->where('id', $check->id)->update(['nip_confirmed' =>'1' , 'nip' => $nip , 'nip_creation_date' => date('Y-m-d H:i:s') ]); //Confirm 

                return response()->json(['status'=>'success','msg'=>'Nip created'],200);
            }
        }
    }

     public function validateNip(Request $request)
    {
        $input = $request->all();

        $validator = \Validator::make($input, [
            'nip' => 'required|Integer|min:6'
        ]);

        if ($validator->fails()) {
            return response()->json(['status'=>'error','msg'=>'Fields missing or wrong format'],422);
        }

        if ( !is_object($check) ){

            $errors = $this->errorsSession($check);

            return response()->json(['status'=>'error','error_code' => $errors["error_code"] ,'msg'=>$errors["error_msg"]],401);
        
        } else {

            if ( !password_verify($input["nip"], $check->nip) )
            {
                return response()->json(['status'=>'error','msg'=>'Wrong Nip'],422);
            } else {

                return response()->json(['status'=>'success','msg'=>'Good Nip'],200);
            }
        }
    }

    /* FIN DE FUNCIONES PRIVADAS */
}