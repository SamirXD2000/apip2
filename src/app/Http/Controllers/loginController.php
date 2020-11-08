<?php

namespace App\Http\Controllers;

use DB;

/* LIBRERIES */
use Illuminate\Http\Request;


class loginController extends Controller
{
    
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
                "url" => env('PUBLIC_URL')."images/foods/".$post->id.".png",
            );

            array_push($array["foods"],$arrayTemp);

        }

        return response()->json(['status'=>'success','data'=>$array],200);

    }

    /* FIN DE FUNCIONES PRIVADAS */
}