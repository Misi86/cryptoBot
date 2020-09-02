<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\test;

class controllerTest extends Controller
{
    //
    public function test() {

     /*   $records = test::create([
            'label' => 'Stringaq'
        ]);   
        return view('test');
        */

        $record = test::find(1)->first();
        echo $record->label;

        $records = test::create([
            'label' => 'Swerti nsdaind dnasn ai'
        ]);  

        echo $record->id.": ".$record->label;

    }
}
