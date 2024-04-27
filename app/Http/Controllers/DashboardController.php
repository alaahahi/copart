<?php

namespace App\Http\Controllers;
use App\Http\Controllers\AccountingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use App\Models\Info;
use App\Models\User;
use App\Models\Car;
use App\Models\Company;
use App\Models\Name;
use App\Models\ExitCar;
use App\Models\Contract;
use App\Models\CarModel;
use App\Models\Color;
use App\Models\Wallet;
use App\Models\UserType;
use App\Models\ExpensesType;
use Illuminate\Support\Facades\DB;
use App\Models\Transactions;
use App\Models\Expenses;
use App\Helpers\UploadHelper;
use Illuminate\Support\Facades\Auth;


use Carbon\Carbon;

use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(AccountingController $accountingController)
    {
    $this->accountingController = $accountingController;
    $this->url = env('FRONTEND_URL');
    $this->userAdmin =  UserType::where('name', 'admin')->first()->id;
    $this->userClient =  UserType::where('name', 'client')->first()->id;
    $this->userAccount =  UserType::where('name', 'account')->first()->id;

    $this->mainAccount= User::with('wallet')->where('type_id', $this->userAccount)->where('email','main@account.com');
    $this->inAccount= User::with('wallet')->where('type_id', $this->userAccount)->where('email','in@account.com');
    $this->outAccount= User::with('wallet')->where('type_id', $this->userAccount)->where('email','out@account.com');
    $this->debtAccount= User::with('wallet')->where('type_id', $this->userAccount)->where('email','debt@account.com');
    $this->transfersAccount= User::with('wallet')->where('type_id', $this->userAccount)->where('email','transfers@account.com');
    $this->outSupplier= User::with('wallet')->where('type_id', $this->userAccount)->where('email','supplier-out');
    $this->debtSupplier= User::with('wallet')->where('type_id', $this->userAccount)->where('email','supplier-debt');
    
    $this->howler= User::with('wallet')->where('type_id', $this->userAccount)->where('email','howler');
    $this->shippingCoc= User::with('wallet')->where('type_id', $this->userAccount)->where('email','shipping-coc');
    $this->border= User::with('wallet')->where('type_id', $this->userAccount)->where('email','border');
    $this->iran= User::with('wallet')->where('type_id', $this->userAccount)->where('email','iran');
    $this->dubai= User::with('wallet')->where('type_id', $this->userAccount)->where('email','dubai');
    $this->mainBox= User::with('wallet')->where('type_id', $this->userAccount)->where('email','mainBox@account.com');

    }
    public function __invoke(Request $request)
    {
        $results = null;
        return Inertia::render('dashboard', ['url'=>$this->url]);   
    }
    public function index(Request $request)
    {
        return Inertia::render('Dashboard');   
    }
    public function purchases(Request $request)
    {
        $owner_id=Auth::user()->owner_id;
        $car = Car::all()->where('owner_id',$owner_id);
        $allCars = $car->count();
        $client = User::where('type_id', $this->userClient)->where('owner_id',$owner_id)->get();
        
        return Inertia::render('purchases', ['client'=>$client ]);   
    }
    public function sales(Request $request)
    {
        $owner_id=Auth::user()->owner_id;
        $car = Car::all()->where('owner_id',$owner_id);
        $allCars = $car->count();
        $client = User::where('owner_id',$owner_id)->where('type_id', $this->userClient)->get();
        return Inertia::render('Sales', ['client'=>$client ]);   
    }
    public function totalInfo(Request $request)
    {
        $owner_id=Auth::user()->owner_id;
        $mainBoxId=$this->mainBox->where('owner_id', $owner_id)->first()->wallet->id;

        $transactionIn = (int) Transactions::where('wallet_id', $mainBoxId)
        ->where('currency', '$')
        ->whereIn('type', ['in', 'inUserBox'])
        ->sum('amount');

        $transactionOut =(int) Transactions::where('wallet_id', $mainBoxId)
        ->where('currency', '$')
        ->whereIn('type', ['out', 'debt'])
        ->sum('amount');

        $car = Car::all()->where('owner_id',$owner_id);
        $contarts = Contract::all()->where('owner_id',$owner_id);
        $exitCar = ExitCar::all()->where('owner_id',$owner_id)->count();
        $sumTotal = $car->sum('total');
        $sumTotalS = $car->sum('total_s');
        $client = User::where('type_id', $this->userClient)->where('owner_id',$owner_id)->pluck('id');
        $sumDebit =Wallet::whereIn('user_id', $client)->sum('balance');
        $sumPaid = $car->sum('paid')+ $car->sum('discount');
        $sumProfit = $car->where('results',2)->sum('profit');
        $sumContrat = $contarts->sum('paid') ;
        $sumContratDinar = $contarts->sum('paid_dinar') ;
        $debtSumContrat = $contarts->sum('price') -$sumContrat;
        $debtSumContratDinar = $contarts->sum('price_dinar') - $sumContratDinar;
        $data = [
        'contarts'=>$contarts->count(),
        'exitCar'=>$exitCar,
        'mainAccount'=>$sumTotal -$sumPaid ,
        'onlineContracts'=>$sumContrat,
        'onlineContractsDinar'=>$sumContratDinar,
        'debtOnlineContractsDinar'=>$debtSumContratDinar,
        'howler'=>$this->howler->where('owner_id',$owner_id)->first()->wallet->balance??0,
        'shippingCoc'=>$this->shippingCoc->where('owner_id',$owner_id)->first()->wallet->balance??0,
        'border'=>$this->border->where('owner_id',$owner_id)->first()->wallet->balance??0,
        'iran'=>$this->iran->where('owner_id',$owner_id)->first()->wallet->balance??0,
        'dubai'=>$this->dubai->where('owner_id',$owner_id)->first()->wallet->balance??0,
        'sumTotal'=>$sumTotal,
        'sumPaid'=>$sumPaid,
        'sumDebit'=>$sumDebit,
        'sumProfit'=>$sumProfit,
        'debtOnlineContracts'=>$debtSumContrat,
        'allCars'=>$car->count()??0,
        'purchasesCost'=>$sumTotalS??0,
        'clientPaid'=>$sumPaid??0,
        'clientDebit'=>$sumDebit ?? 0,
        'mainBoxDollar'=>$this->mainBox->where('owner_id',$owner_id)->first()->wallet->balance??0,
        'mainBoxDinar'=>$this->mainBox->where('owner_id',$owner_id)->first()->wallet->balance_dinar??0,
        'mainBoxDollarNew'=>$transactionIn+$transactionOut

        
        ];
        return response()->json(['data'=>$data]); 

    }
    public function client(Request $request)
    {
        $owner_id=Auth::user()->owner_id;

        $client = User::where('type_id', $this->userClient)->where('owner_id',$owner_id)->with('wallet')->paginate(10);
        return response()->json($client); 
    }
    public function getcount(Request $request)
    {
        $owner_id=Auth::user()->owner_id;
        $profile=  Car::all()->where('owner_id',$owner_id);
        $start = $request->get('start');
        $end = $request->get('end');
        if($start && $end ){
            $countComp =Car::whereBetween('created_at', [$start, $end])->where('user_accepted','!=',null)->count();
        }
        else{
            $countComp =Car::where('user_accepted','!=',null)->count();  
        }
        return response()->json($countComp); 
    }
    public function addCar(Request $request)
    {
        $owner_id=Auth::user()->owner_id;
        $car_id=$request->id??0;
        $maxNo = Car::max('no');
        if($car_id){
            $no = $request->no;
        }else{
            $no = $maxNo + 1;
        }
        $images=[];
        $paid_amount = $request->paid_amount??0;
        $purchase_price =$request->purchase_price;
        $debt_price =$purchase_price - $paid_amount;
        if($request->image ){
            foreach ($request->image as $image) {
                $imageName = $image->getClientOriginalName().$no;
                $filename = pathinfo($imageName, PATHINFO_FILENAME);
                $imagePath = UploadHelper::upload('image', $image, $filename, 'storage/car');
                $images[] = $imagePath;
            }    
        }

        if(!$car_id){
        $car=Car::create([
            'company_id' =>$request->company_id,
            'name_id'=> $request->name_id,
            'model_id'=> $request->model_id,
            'color_id'=> $request->color_id,
            'pin'=> $request->pin,
            'purchase_data'=> $request->purchase_data,
            'purchase_price'=> $purchase_price,
            'paid_amount'=> $paid_amount,
            'note'=> $request->note??'',
            'image'=>$images ? json_encode($images):"",
            'user_id'=> $request->user_id??0,
            'no'=>$no,
            'owner_id'=>$owner_id
             ]);
             if($paid_amount){
                $desc=trans('text.payCar').' '.$purchase_price.trans('text.payDone').$paid_amount;
             
                $this->accountingController->decreaseWallet($paid_amount, $desc,$this->mainAccount->id,$car->id,'App\Models\Car');
                $this->accountingController->increaseWallet($paid_amount, $desc,$this->outAccount->id,$car->id,'App\Models\Car' );
                $this->accountingController->increaseWallet($paid_amount, $desc,$this->outSupplier->id,$car->id,'App\Models\Car');
                if($debt_price){
                   $this->accountingController->increaseWallet($debt_price, $desc,$this->debtSupplier->id,$car->id,'App\Models\Car');
                }
             }
 
        }else{
            $car=Car::find($car_id);
            $purchase_price_old=$car->purchase_price;
            if($purchase_price > $purchase_price_old){
                $purchase_price_new = $purchase_price - $purchase_price_old;
                $desc=trans('text.editCar').' '.trans('text.from').$purchase_price_old.trans('text.to').$purchase_price;
                $this->accountingController->decreaseWallet($purchase_price_new, $desc,$this->mainAccount->id,$car->id,'App\Models\Car');
                $this->accountingController->increaseWallet($purchase_price_new, $desc,$this->outAccount->id,$car->id,'App\Models\Car' );
                $this->accountingController->decreaseWallet($purchase_price_new, $desc,$this->inAccount->id,$car->id,'App\Models\Car');
                $this->accountingController->increaseWallet($purchase_price_new, $desc,$this->outSupplier->id,$car->id,'App\Models\Car');
                $this->accountingController->increaseWallet($purchase_price_new, $desc,$this->debtSupplier->id,$car->id,'App\Models\Car');
            }
            if($purchase_price < $purchase_price_old){
                $purchase_price_new =$purchase_price_old - $purchase_price;
                $desc=trans('text.editCar').' '.trans('text.from').$purchase_price_old.trans('text.to').$purchase_price;
                $this->accountingController->increaseWallet($purchase_price_new, $desc,$this->mainAccount->id,$car->id,'App\Models\Car');
                $this->accountingController->decreaseWallet($purchase_price_new, $desc,$this->outAccount->id,$car->id,'App\Models\Car' );
                $this->accountingController->increaseWallet($purchase_price_new, $desc,$this->inAccount->id,$car->id,'App\Models\Car');
                $this->accountingController->decreaseWallet($purchase_price_new, $desc,$this->outSupplier->id,$car->id,'App\Models\Car');
                $this->accountingController->decreaseWallet($purchase_price_new, $desc,$this->debtSupplier->id,$car->id,'App\Models\Car');

            }
            $car->update([
                'company_id' =>$request->company_id,
                'name_id'=> $request->name_id,
                'model_id'=> $request->model_id,
                'color_id'=> $request->color_id,
                'pin'=> $request->pin,
                'purchase_data'=> $request->purchase_data,
                'purchase_price'=> $purchase_price,
                'paid_amount'=> $paid_amount,
                'note'=> $request->note??'',
                'image'=>$images ? json_encode($images):"",
                'no'=>$no
                 ]);
        }

        return Response::json('ok', 200);    
    }
    public function addCars(Request $request)
    {
        $owner_id=Auth::user()->owner_id;
        $year_date=Carbon::now()->format('Y');
        $client_id =$request->client_id;
        $car_id=$request->id??0;
        $maxNo = Car::max('no');

        if($car_id){
            $no = $request->no;
        }else{
            $no = $maxNo + 1;
        }
        $results=0;
        $checkout=$request->checkout ?? 0;
        $shipping_dolar=$request->shipping_dolar ?? 0;
        $coc_dolar=$request->coc_dolar ?? 0;
        $dinar=$request->dinar ?? 0;
        $dolar_price=$request->dolar_price ?? 1;
        $expenses=$request->expenses ?? 0;
        $paid=$request->paid ?? 0;
        
        if($dolar_price==0){
            $dolar_price=1;
        }elseif($dolar_price > 9999){
            $dolar_price=$dolar_price/100;
        }else{
            $dolar_price=$dolar_price;
        }
        $dolar_custom=(int)($dinar/($dolar_price)) ??0;
        $total_amount = $checkout+$shipping_dolar+$expenses+ $coc_dolar +$dolar_custom;
        if( $client_id==0){
            $client = new User;
            $client->name = $request->client_name;
            $client->phone = $request->client_phone;
            $client->created =Carbon::now()->format('Y-m-d');
            $client->type_id = $this->userClient;
            $client->owner_id = $owner_id;
            $client->year_date = $year_date;
            $client->save();
            Wallet::create(['user_id' => $client->id,'balance'=>0]);
            $client_id=$client->id;
        }
        $car=Car::create([
            'note'=> $request->note??'',
            'no'=>$no,
            'car_owner'=> $request->car_owner,
            'car_type'=> $request->car_type,
            'vin'=> $request->vin,
            'car_number'=> $request->car_number,
            'dinar'=> $request->dinar,
            'dolar_price'=> $request->dolar_price,
            'shipping_dolar'=> $request->shipping_dolar,
            'coc_dolar'=> $request->coc_dolar,
            'checkout'=> $request->checkout,
            'total'=> $total_amount,
            'year'=> $request->year,
            'year_date'=>$year_date,
            'car_color'=> $request->car_color,
            'date'=> $request->date,
            'expenses'=> $expenses,
            'client_id'=>$client_id,
            'results'=> $results,
            'owner_id'=>$owner_id,
            'profit'=>($total_amount*-1)
             ]);
                if($total_amount){
                    $desc='اضافة سيارة من المشتريات رقم شانصى '.$request->vin;
                    if($total_amount){
                        $this->accountingController->decreaseWallet(($total_amount),$desc,$this->mainAccount->where('owner_id',$owner_id)->first()->id,$car->id,'App\Models\Car');
                    }
                }


        return Response::json('ok', 200);    
    }
    public function updateCarsP(Request $request)
    {
        $owner_id=Auth::user()->owner_id;

        $car_id=$request->id??0;
        $maxNo = Car::max('no');

        if($car_id){
            $no = $request->no;
        }else{
            $no = $maxNo + 1;
        }
        $car=Car::with('client')->find($request->id);


        if(!isset($no)){
            $no=$car->no;
           
        }
        $images=[];
        $checkout=$request->checkout ;
        $shipping_dolar=$request->shipping_dolar ;
        $coc_dolar=$request->coc_dolar;
        $dinar=$request->dinar;
        $expenses=($request->expenses??0);
        $dolar_price=$request->dolar_price ;
        if($dolar_price==0){
            $dolar_price=1;
        }elseif($dolar_price > 9999){
            $dolar_price=$dolar_price/100;
        }else{
            $dolar_price=$dolar_price;
        }

        $total = (int)(($checkout+$shipping_dolar+ $coc_dolar +(int)($dinar / ($dolar_price))+$expenses) ??0);
        if($car->client_id == $request->client_id)
        {

        }else{
            $desc="نقل السيارة";
            if($car->results==0){
                if($car->total_s){
                    $this->accountingController->decreaseWallet($car->total_s, $desc,$car->client_id,$car->id,'App\Models\User');
                    $this->accountingController->increaseWallet($car->total_s, $desc,$request->client_id,$car->id,'App\Models\User');
                }
            }
            if($car->results==1){
                if($car->total_s){
                    $this->accountingController->decreaseWallet($car->total_s-$car->paid, $desc,$car->client_id,$car->id,'App\Models\User');
                    $this->accountingController->increaseWallet($car->total_s-$car->paid, $desc,$request->client_id,$car->id,'App\Models\User');

                
                }
            }
        }
            $dataToUpdate = $request->all();

            // If 'purchase_price' and 'paid_amount' are calculated separately, add them to $dataToUpdate
            $dataToUpdate['total']=$total;
            if($total >$car->total){
                $descClient = trans('text.addExpenses').' '.($total-$car->total).' '.trans('text.for_car').$car->car_type.' '.$car->vin;
                $this->accountingController->decreaseWallet(($total-$car->total), $descClient,$this->mainAccount->where('owner_id',$owner_id)->first()->id,$car->id,'App\Models\Car');
            }else{
                $descClient = 'مرتجع للصندوق مصاريف';
                $this->accountingController->increaseWallet(($car->total-$total), $descClient,$this->mainAccount->where('owner_id',$owner_id)->first()->id,$car->id,'App\Models\Car');

            }
            if($car->paid){
                if($total > $car->paid +$car->discount){
                    $dataToUpdate['results'] = 1  ;
                }elseif($total == $car->paid+$car->discount){
                    $dataToUpdate['results'] = 2  ;
                }else{
                    $dataToUpdate['results'] = 0  ;
                }
            }

            //$this->accountingController->increaseWallet(($total-$car->total), $descClient,$car->client_id,$car->id,'App\Models\User');
            $car->update($dataToUpdate);


        return Response::json('ok', 200);    
    }
    public function updateCarsS(Request $request)
    {
        $owner_id=Auth::user()->owner_id;

        $car_id=$request->id??0;

        $maxNo = Car::max('no');

        if($car_id){
            $no = $request->no;
        }else{
            $no = $maxNo + 1;
        }
        $car=Car::find($car_id);
        if(!isset($no)){
            $no=$car->no;
           
        }
        $images=[];
        $checkout_s=$request->checkout_s ;
        $shipping_dolar_s=$request->shipping_dolar_s ;
        $coc_dolar_s=$request->coc_dolar_s ;
        $dinar_s=$request->dinar_s ;
        $expenses_s=($request->expenses_s??0);
        $dolar_price_s=$request->dolar_price_s ;
        if($dolar_price_s==0){
            $dolar_price_s=1;
        }elseif($dolar_price_s > 9999){
            $dolar_price_s=$dolar_price_s/100;
        }else{
            $dolar_price_s=$dolar_price_s;
        }
        $total_s = (($checkout_s+$shipping_dolar_s+ $coc_dolar_s +(int)($dinar_s / ($dolar_price_s))+$expenses_s) ??0);
        $profit=$total_s-$car->total;
        $descClient = trans('text.editExpenses').' '.$total_s-$car->total_s.' '.trans('text.for_car').$car->car_type.' '.$car->vin;
        $this->accountingController->increaseWallet($total_s-$car->total_s, $descClient,$car->client_id,$car->id,'App\Models\User');
            // Extract the relevant fields from the $request object
            $dataToUpdate = $request->all();
            // If 'purchase_price' and 'paid_amount' are calculated separately, add them to $dataToUpdate
            $dataToUpdate['total_s']=$total_s;
            $dataToUpdate['profit']=$profit;

            if($car->paid){
                if($total_s >($car->paid+$car->discount)){
                    $dataToUpdate['results'] = 1  ;
                }elseif($total_s==$car->paid+$car->discount){
                    $dataToUpdate['results'] = 2  ;
                }else{
                    $dataToUpdate['results'] = 0  ;
                }
            }


            // Update the car model
            $car->update($dataToUpdate);
            

        return Response::json('ok', 200);    
    }
    public function getIndexExpenses () {

        $expenses = Expenses::with('user')->paginate(10);
        return Response::json($expenses, 200);    

    }

    public function payCar(Request $request)
    {
        $authUser = auth()->user();   
        $client_id =$request->client_id;
        $pay_price =(int)$request->pay_price??0;
        $paid_amount_pay =(int)$request->paid_amount_pay??0;

        if( $client_id==0){
            $client = new User;
            $client->name = $request->client_name;
            $client->phone = $request->client_phone;
            $client->type_id = $this->userClient;
            $client->created =Carbon::now()->format('Y-m-d');
            $client->save();
            Wallet::create(['user_id' => $client->id,'balance'=>$pay_price-$paid_amount_pay]);
            $client_id=$client->id;
        }else{
            $wallet = Wallet::where('user_id',$client_id)->first();
            $wallet->increment('balance',$pay_price-$paid_amount_pay); 
        }

        $car=Car::find($request->id);
        if($car->id){
                $car->update([
                'note_pay' =>$request->note_pay,
                'client_id'=> $client_id ,
                'pay_data'=> Carbon::now()->format('Y-m-d'),
                'pay_price'=>$pay_price,
                'paid_amount_pay' =>  $paid_amount_pay,
                'results'=>1
                 ]);
                $desc=trans('text.buyCar').' '.$car->pay_price.trans('text.payDone').$car->paid_amount_pay;
                $this->accountingController->increaseWallet($car->paid_amount_pay, $desc,$this->mainAccount->where('owner_id',$owner_id)->first()->id,$car->id,'App\Models\Car');
                $this->accountingController->increaseWallet($car->paid_amount_pay, $desc,$this->inAccount->id,$car->id,'App\Models\Car');
                if($pay_price-$paid_amount_pay >= 0){
                    $this->accountingController->increaseWallet($pay_price-$paid_amount_pay, $desc,$this->debtAccount->id,$car->id,'App\Models\Car');
                }
                if($pay_price==$paid_amount_pay){
                    $car->increment('results'); 
                }
            }
            return Response::json('ok', 200);    
    }
    public function getIndexCar(Request $request)
    {
        $owner_id=Auth::user()->owner_id;
        $car_have_expenses = $request->car_have_expenses ?? '';
        $user_id =$_GET['user_id'] ?? '';
        $q = $_GET['q']??'';
        $from =  $_GET['from'] ?? 0;
        $to =$_GET['to'] ?? 0;
        $limit =$_GET['limit'] ?? 0;
        if($car_have_expenses||$car_have_expenses==1){
            $data = Car::with('contract','CarImages', 'exitcar','client','carexpenses.user')->where('owner_id', $owner_id)->where('car_have_expenses', $car_have_expenses);
            
        }else{
            $data = Car::with('contract','CarImages', 'exitcar', 'client')->where('owner_id', $owner_id);
        }

        if ($from && $to) {
            $data->whereBetween('date', [$from, $to]);
        }
        
        $resultsDinar = $data->sum('dinar');
        $resultsDollar = $data->sum('total');
        $resultsTotalS = $data->sum('total_s');
        $resultsProfit = $data->sum('profit');
        $resultsPaid = $data->sum('paid');
        $totalCars = $data->count();
        
        $type = $_GET['type'] ?? '';
        
        if ($type == 'debitContract') {
            $data->whereHas('contract', function ($query) use ($q) {
                $query->where('name', 'LIKE', '%' . $q . '%');
            });
        } elseif ($type) {
            $data->where('results', $type);
        }
        if($q){
            $data->where(function ($query) use ($q) {
                $query->where('car_number', 'LIKE', '%' . $q . '%')
                    ->orWhere('vin', 'LIKE', '%' . $q . '%')
                    ->orWhere('car_type', 'LIKE', '%' . $q . '%')
                    ->orWhereHas('client', function ($subquery) use ($q) {
                        $subquery->where('name', 'LIKE', '%' . $q . '%');
                    });
            });
        }
 

        if($user_id){
            $data->where('client_id', $user_id);
            $resultsDinar=$data->sum('dinar');
            $resultsDollar=$data->sum('total'); 
            $resultsTotalS=$data->sum('total_s'); 
            $resultsProfit=$data->sum('profit'); 
            $resultsPaid=$data->sum('paid'); 
            $totalCars = $data->count();
        }
        $data =$data->orderBy('no', 'DESC')->paginate($limit)->toArray();
        $data['resultsDinar'] = $resultsDinar;
        $data['resultsDollar'] = $resultsDollar;
        $data['totalCars']  =$totalCars;
        $data['resultsProfit'] = $resultsProfit;
        $data['resultsPaid']  =$resultsPaid;
        $data['resultsTotalS']  =$resultsTotalS;
 

        return Response::json($data, 200);
    }
    public function getIndexCarSearch()
    {
        $owner_id=Auth::user()->owner_id;

        $term = $_GET['q']??'';
        $data =  Car::with('contract')->with('exitcar')->with('client')->where('owner_id',$owner_id)->orwhere('car_number', 'LIKE','%'.$term.'%')->orwhere('vin', 'LIKE','%'.$term.'%')->orwhere('car_type', 'LIKE','%'.$term.'%')->orWhereHas('client', function ($query) use ($term) {
            $query->where('name', 'LIKE', '%' . $term . '%');
        });
        $data =$data->orderBy('no', 'DESC')->paginate(100);
        return Response::json($data, 200);
    }
    public function addToBox()
    {
        $user_id = $_GET['user_id']??0;
        $desc=trans('text.addToBox').' '.($_GET['amount']??0).'$'.' || '.$_GET['note']??'';
        $this->accountingController->increaseWallet(($_GET['amount']??0), $desc,$this->mainAccount->where('owner_id',$owner_id)->first()->id,$user_id,'App\Models\User',$user_id);
        return Response::json('ok', 200);    
    }
    public function withDrawFromBox()
    {
        $user_id = $_GET['user_id']??0;
        $desc=trans('text.withDrawFromBox').' '.($_GET['amount']??'').'$'.' || '.$_GET['note']??'';
        $this->accountingController->decreaseWallet(($_GET['amount']??0), $desc,$this->mainAccount->where('owner_id',$owner_id)->first()->id,$user_id,'App\Models\User',$user_id);
        
        return Response::json('ok', 200);    
    }

    public function DelCar(Request $request){
        $owner_id=Auth::user()->owner_id;

        $car=Car::with('client')->find($request->id);
        $desc=' مرتج حذف سيارة'.$car->total;
        $wallet = Wallet::where('user_id',$car->client_id)->first();
        $this->accountingController->increaseWallet($car->total, $desc,$this->mainAccount->where('owner_id',$owner_id)->first()->id,$car->id,'App\Models\Car');
        if($car->results == 0 && $car->total_s!=0){
            $trans = $this->accountingController->decreaseWallet($car->total_s , $desc,$car->client->id,$car->id,'App\Models\Car');
        }
        if($car->results == 1){
            $trans = $this->accountingController->decreaseWallet($car->total_s-$car->paid , $desc,$car->client->id,$car->id,'App\Models\Car');
            }
        $car->delete();
        DB::statement('SET @row_number = 0');
        DB::table('car')
            ->whereNull('deleted_at') // Apply soft delete constraint
            ->orderBy('id') // Assuming 'id' is the primary key column
            ->update(['no' => DB::raw('(@row_number:=@row_number + 1)')]);
        return Response::json('delete is done', 200);
    }
    
}
