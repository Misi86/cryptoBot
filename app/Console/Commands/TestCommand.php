<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use \League\CLImate\CLImate;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;

use \Binance\API;
use Telegram;

class TestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:start {--asset=DOGEUSDT} {--limit=100} {--quantity=0.1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'First command on artisan';

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

        //$climate = new Climate;
        $api = new API( env('BINANCE_API_KEY','') , env('BINANCE_SECRET_KEY', ''));

        // Define indicators
        $ema7 = $this->yourEma(6,'4h','XLMUSDT');
        $ema25 = $this->yourEma(24,'4h','XLMUSDT');
        $ema90 = $this->yourEma(89,'4h','XLMUSDT');
        // $prevDay = $api->prevDay($this->option('asset'));
        // $depth = $api->depth($this->option('asset'));
        // $trades = $api->aggTrades("BTCUSDT");
       
        
        // Get Balance
        $balanceUSDT = $this->getBalance('USDT');
        $balanceAsset = $this->getBalance('XLM');
     
        // Check if we hold something
        if( $balanceAsset > 50 ){
              $buy_price = DB::table('buy_prices')->select('amount')->latest('id')->first();
        
        }

        $price = $api->price($this->option('asset'));
      //   $this->calculatePercent(10,'-',40);
      //  $this->getMarket('BTC');    
       
        if( $ema25 < $ema7 && $balanceAsset <= 50){
            
            
            DB::table('buy_prices')->insert(
                [
                    'asset' => $this->option('asset'),
                    'amount' => $price
                ]
            );
            $this->buy($price, $this->getQuantity(75,'XLMUSDT'),$this->option('asset'));

        }else if( $ema25 > $ema7 && $balanceAsset > 50   ){
            
            if($price > $buy_price->amount){
                $this->sell($price,$balanceAsset,$this->option('asset'));
                DB::table('buy_prices')->delete();
            }else{
             Telegram::setAsyncRequest(true)->sendMessage(['chat_id' => '-1001220588338', 'text'=>'nn vendo perche'.$price.'minore di '.$buy_price->amount]); 
            }

                
        }else if ( $balanceAsset > 50 && ($buy_price->amount <=  $this->calculatePercent($buy_price->amount,'-',10) ) ){
            
            $this->sell($price,$balanceAsset,$this->option('asset'));
            DB::table('buy_prices')->delete();    
        
        }else{
          
            if($balanceAsset > 50){
                Telegram::setAsyncRequest(true)->sendMessage(['chat_id' => '-1001220588338', 'text'=>'Sto holdando '.$balanceAsset.' ad un prezzo di '.$buy_price->amount.' ed il prezzo attuale e '.$price]);
            }else{
              Telegram::setAsyncRequest(true)->sendMessage(['chat_id' => '-1001220588338', 'text'=>'Condizioni non soddisfatte']);
            }      
                  
        }

    }


    public function calculatePercent($price,$sign,$percentage){

        $result;

        if($sign == '+'){
            $result = ($price / 100) * $percentage;
            $result = $price + $result;    
        }else{
            $result = ($price / 100) * $percentage;     
            $result = $price - $result;
        }
       print_r($result);
        return $result;
    }

    public function getMarket( $asset='USDT'){

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

        $resultTop=[];
        $resultTopKey=[];
        $resultTopValue=[];
        foreach( $result as $key => $value){

            if( $this->yourEma(6,'4h',$key) > $this->yourEma(24,'4h',$key) ){
                //print_r( 'Maggiore della sua ema '.$key);
                array_push($resultTopKey ,$key);
                array_push($resultTopValue ,$value);    
            }

        }
        $resultTop = array_combine($resultTopKey,$resultTopValue);

        print_r($resultTop);

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
        echo $asset."owned: ".$balances[$asset]['available'].PHP_EOL;
        return $balances[$asset]['available'];

    }

    public function getLastStickData($asset,$timeframe){

        $api = new API( env('BINANCE_API_KEY','') , env('BINANCE_SECRET_KEY', ''));
        $ticks = $api->candlesticks($asset, $timeframe);
        return $ticks;

    }

    public function yourEma($day,$timeF, $asset = 'DOGEUSDT' ){

        $ticks = $this->getLastStickData($asset, $timeF);
        $ticks = $this->sortByInput($ticks,'closeTime',SORT_DESC,$day);
        $ticks = array_column($ticks , 'close');
        $ticks = array_sum($ticks) / ($day+1);
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

    public function sell($price, $quantity,$asset){
   
        $api = new API( env('BINANCE_API_KEY','') , env('BINANCE_SECRET_KEY', ''));

       // $api->sell($asset, $quantity, $price);
        $order = $api->sell($asset, $quantity,$price);
        Telegram::setAsyncRequest(true)->sendMessage(['chat_id' => '-1001220588338', 'text'=>'Ordine vendita market eseguito a '.$price]);
    }

    public function buy($price, $quantity,$asset){
      
        $price = floatval($price);
        $api = new API( env('BINANCE_API_KEY','') , env('BINANCE_SECRET_KEY', ''));
        $order = $api->buy($asset,$quantity,$price);

        Telegram::setAsyncRequest(true)->sendMessage(['chat_id' => '-1001220588338', 'text'=>'Ordine acquisto limit eseguito a '.$price]);

    }

    // Used example

 // } else if( $ema13 < $ema5 && $price /*+5%*/>= price){
            // sell(asset,quantity);
            // Telegram::setAsyncRequest(true)->sendMessage(['chat_id' => '-1001220588338', 'text'=>'Avrei venduto a '.$price]);
        //}


    /*$stringa = $this->ask('Quale e il tuo nome??');
       $this->line('Il tuo nonme è: '.$stringa);
       $climate->bold('Whoa now this text is red.');
        $climate->blue('Blue? Wow!');
    */
     /*
       $stringa = $this->ask('Quale asset vuoi vedere??');
      */

       /* Cosi lo si passa da riga di comando


        $price = $api->price($stringa);

        $climate->red($price);

       while(true){
                // tutto il cosdice

                if($order){break;}
                sleep(5);
         }
        */


    //      if(floatval($trades['price']) <= floatval($price)){
    //         $order=false;
    //         //print_r($trades);
    //         print_r('Prezzo aumentao'.floatval($price).'--> COmparato'.floatval($trades['price']).'minore di'.floatval($price).'

    //         ');
    //    // echo '<br/>';
    //     }else{
    //         print_r('Niente di nuovo rispetto a '.$trades['price'].' minore di '.$price).'

    //         ';
    //     }

    //      });

        //---------Esempio coso------------------------
        // if( intval($trades[0]['quantity']) > intval($this->option('limit')) ){

        //     $climate->green('Trovato -->'.$trades[0]['price'] );
        //     $this->buy($trades[0]['price'], true);

        //     $order = true;
        // } else {

        //     $climate->red('Nessun risultato > di '.$this->option('limit'));
        //     $climate->blue($trades[0]['quantity']);
        //     $climate->blue($trades[1]['quantity']);
        //     Telegram::setAsyncRequest(true)->sendMessage(['chat_id' => '-1001220588338', 'text'=>'Ritentant sarai piu fortunato']);
        // }

}
