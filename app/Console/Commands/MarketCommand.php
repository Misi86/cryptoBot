<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use \Binance\API;


class MarketCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'market:start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
     
     $markets = $this->getMarkets('USDT' , 5);
        


        foreach( $markets as $value){
            $val = str_replace('USDT','',$value);
            
            $balance = $this->getBalance($val);
            $minTradable = $this->getMinTradable($value);

            if ( $balance > $minTradable ) {

                print_r('Sto holdando => '.$value.'
            ');

            }else{

            } 
            
            


        }

    }


    public function getMinTradable($asset){
        
        $api = new API( env('BINANCE_API_KEY','') , env('BINANCE_SECRET_KEY', ''));
        $price = $api->price($asset);

        $result = 10 / $price;
        return $result;


    }

    public function getMarkets( $asset='USDT',$quantity = 10){

        $api = new API( env('BINANCE_API_KEY','') , env('BINANCE_SECRET_KEY', ''));

        $result = [];
        $resultKey = [];
        $resultValue = [];
        $vLength = strlen($asset) * (-1);
        $ticker = $api->prices();
      
        
        foreach($ticker as $key => $value){
            if( substr($key, $vLength) == $asset ){

                 array_push($resultKey,$key);
                 array_push($resultValue,$value);
            
            }  
        }

        $result = array_combine($resultKey,$resultValue);
        
        
       // $resultTop=[];
        $resultTopKey=[];
       // $resultTopValue=[];
        $count = 0;
        foreach( $result as $key => $value){
            
            if( (count($resultTopKey)) < $quantity){
                if( $this->yourEma(6,'4h',$key) > $this->yourEma(24,'4h',$key ) ){
                    array_push($resultTopKey ,$key);
                //    array_push($resultTopValue ,$value);    
                }
                //$resultTop = array_combine($resultTopKey,$resultTopValue);   
            }
            
        }

        return $resultTopKey;
    }

    public function getQuantity( $stake,$asset){

        $api = new API( env('BINANCE_API_KEY','') , env('BINANCE_SECRET_KEY', ''));

        $balance = ( $this->getBalance('USDT') / 100) * $stake;
        $current_price= $api->price($asset);
        $quantity =  $balance / $current_price;
        return round($quantity,1);
    }

    public function getBalance($asset='USDT'){

        $api = new API( env('BINANCE_API_KEY','') , env('BINANCE_SECRET_KEY', ''));
        $ticker = $api->prices(); // Make sure you have an updated ticker object for this to work
        $balances = $api->balances($ticker);
        //echo $asset."owned: ".$balances[$asset]['available'].PHP_EOL;
        return $balances[$asset]['available'];

    }

    public function yourEma($day,$timeF, $asset = 'DOGEUSDT' ){

        $ticks = $this->getLastStickData($asset, $timeF);
        $ticks = $this->sortByInput($ticks,'closeTime',SORT_DESC,$day);
        $ticks = array_column($ticks , 'close');
        $ticks = array_sum($ticks) / ($day+1);
        return $ticks;


    }

    public function getLastStickData($asset,$timeframe){

        $api = new API( env('BINANCE_API_KEY','') , env('BINANCE_SECRET_KEY', ''));
        $ticks = $api->candlesticks($asset, $timeframe);
        return $ticks;

    }

    public function sortByInput($array,$sortBy,$order=SORT_DESC,$toList){

        array_multisort(array_column($array , $sortBy) , $order , $array);
        $result =[$toList];
        foreach ($array as $key => $value) {

           if($key <= $toList){
               array_push($result,$value);
           }
       }

       return $result;
   }
}
