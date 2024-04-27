<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Profile;
use App\Models\UserType;
use App\Models\Wallet;
use App\Models\Results;
use App\Models\Company;
use App\Models\Transactions;
use App\Models\SystemConfig;
use App\Models\Name;
use App\Models\Car;
use App\Models\ExitCar;
use App\Models\CarModel;
use App\Models\Contract;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use App\Models\Massage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;






class OnlineContractsController extends Controller
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
    $this->onlineContracts= User::with('wallet')->where('type_id', $this->userAccount)->where('email','online-contracts');
    $this->onlineContractsDinar= User::with('wallet')->where('type_id', $this->userAccount)->where('email','online-contracts-dinar');
    $this->debtOnlineContracts= User::with('wallet')->where('type_id', $this->userAccount)->where('email','online-contracts-debt');
    $this->debtOnlineContractsDinar= User::with('wallet')->where('type_id', $this->userAccount)->where('email','online-contracts-debit-dinar');
    $this->howler= User::with('wallet')->where('type_id', $this->userAccount)->where('email','howler')->first();
    $this->shippingCoc= User::with('wallet')->where('type_id', $this->userAccount)->where('email','shipping-coc')->first();
    $this->border= User::with('wallet')->where('type_id', $this->userAccount)->where('email','border')->first();
    $this->iran= User::with('wallet')->where('type_id', $this->userAccount)->where('email','iran')->first();
    $this->dubai= User::with('wallet')->where('type_id', $this->userAccount)->where('email','dubai')->first();
    $this->mainBox= User::with('wallet')->where('type_id', $this->userAccount)->where('email','mainBox@account.com');
    $this->currentDate = Carbon::now()->format('Y-m-d');

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */

    public function online_contracts()
    {   
        $owner_id=Auth::user()->owner_id;

        $car = Car::all()->where('owner_id',$owner_id);
    
        $allCars = $car->count();
        $client = User::where('type_id', $this->userClient)->where('owner_id',$owner_id)->get();

        return Inertia::render('OnlineContracts', ['client'=>$client,'mainAccount'=>$this->mainAccount->where('owner_id',$owner_id)->first()->wallet->balance,'allCars'=>$allCars ]);   

    }
    public function addCarContracts(Request $request){
        $owner_id=Auth::user()->owner_id;

        $price=$request->price;
        $price_dinar=$request->price_dinar;
        $paid=$request->paid;
        $paid_dinar=$request->paid_dinar;
        $phone=$request->phone;
        $note=$request->note;
        $car_id=$request->car_id;
        $car = Car::find($car_id);
        $data = ['user_id'=>$car->client_id,'car_id'=>$car->id,"price"=>$price,"price_dinar"=>$price_dinar,'paid'=>$paid,'paid_dinar'=>$paid_dinar,'note'=>$note,'created'=>Carbon::now()->format('Y-m-d'),'owner_id'=>$owner_id];
        $contract=Contract::create($data);
        $car->update(['contract_id'=>$contract->id]);
        if($contract){
            $desc="انشاء عقد بقيمة ".$price." وتم دفع مبلغ".$paid.' '.$car->car_type.' رقم الشانص'.$car->vin.' '.$note;
            $descD="انشاء عقد بقيمة ".$price_dinar." وتم دفع مبلغ".$paid_dinar.' '.$car->car_type.' رقم الشانص'.$car->vin.' '.$note;
            $descDebit="دين من عقد السيارة ".$car->car_type.' '.' '.$car->car_type.' '.$car->car_type.' رقم الشانص'.$car->vin.' '.$note;
            if($paid){
                $tranDollar=$this->accountingController->increaseWallet($paid,$desc,$this->mainBox->where('owner_id',$owner_id)->first()->id,$this->mainBox->where('owner_id',$owner_id)->first()->id,'App\Models\Car',0,0,'$');
                $this->accountingController->increaseWallet($paid, $desc,$this->onlineContracts->where('owner_id',$owner_id)->first()->id,$car->id,'App\Models\Car',0,0,'$',0,$tranDollar->id);
            }

            if($paid_dinar){
                $tranDinar=$this->accountingController->increaseWallet($paid_dinar,$descD,$this->mainBox->where('owner_id',$owner_id)->first()->id,$this->mainBox->where('owner_id',$owner_id)->first()->id,'App\Models\Car',0,0,'IQD');
                $this->accountingController->increaseWallet($paid_dinar, $descD,$this->onlineContractsDinar->where('owner_id',$owner_id)->first()->id,$car->id,'App\Models\Car',0,0,'IQD',0,$tranDinar->id);
            }
        

            if($price-$paid > 0){
                $this->accountingController->increaseWallet($price-$paid, $descDebit,$this->debtOnlineContracts->where('owner_id',$owner_id)->first()->id,$car->id,'App\Models\Car',0,0,'$',0);
            }
            if($price_dinar-$paid_dinar > 0){
            $this->accountingController->increaseWallet($price_dinar-$paid_dinar, $descDebit,$this->debtOnlineContractsDinar->where('owner_id',$owner_id)->first()->id,$car->id,'App\Models\Car',0,0,'IQD',0);
            }
            if(($price-$paid == 0)&&($price_dinar-$paid_dinar == 0)){
                $dataExitCar=['car_id'=>$car->id,'user_id'=>$car->client_id, 'created'=>$this->currentDate, 'phone' =>$phone, 'note' =>$note,'owner_id'=>$owner_id];
                $exitCar = ExitCar::create($dataExitCar);
                $car->update(['is_exit'=>$exitCar->id]);

            }
        }
        return Response::json('ok', 200);    

    }
    public function makeCarExit(Request $request){
        $owner_id=Auth::user()->owner_id;
        $car_id=$request->car_id;
        $created=$request->created ?? $this->currentDate;
        $phone=$request->phone ?? 0;
        $note=$request->note ?? '';
        $car = Car::find($car_id);
        $data=['car_id'=>$car_id,'user_id'=>$car->client_id, 'created'=>$created, 'phone' =>$phone, 'note' =>$note,'owner_id'=>$owner_id];
        $exitCar = ExitCar::create($data);
        $car->update(['is_exit'=>$exitCar->id]);
        return $exitCar;
    }

    public function editCarContracts(Request $request){
        $owner_id=Auth::user()->owner_id;
        $paid=$request->paid;
        $paid_dinar=$request->paid_dinar;
        $note=$request->notePayment;
        $car_id=$request->car_id;
        $car = Car::find($car_id);
        $phone=$request->phone ?? 0;

        $contract=Contract::find($car->contract->id);
        $descDebit="دين من عقد السيارة ".$car->car_type.' '.$car->car_type.' رقم الشانص'.$car->vin.' '.$note;
        if($paid){
            $contract->increment('paid',$paid);
            $tran=$this->accountingController->increaseWallet($paid,$descDebit,$this->mainBox->where('owner_id',$owner_id)->first()->id,$this->mainBox->where('owner_id',$owner_id)->first()->id,'App\Models\Car',0,0,'$');

            $this->accountingController->increaseWallet($paid, $descDebit,$this->onlineContracts->where('owner_id',$owner_id)->first()->id,$car->id,'App\Models\Car',0,0,'$',0,$tran->id);
            $this->accountingController->decreaseWallet($paid, $descDebit,$this->debtOnlineContracts->where('owner_id',$owner_id)->first()->id,$car->id,'App\Models\Car',0,0,'$',0,$tran->id);
        }
        if($paid_dinar)
        {
            $contract->increment('paid_dinar',$paid_dinar);
            $tran=$this->accountingController->increaseWallet($paid_dinar,$descDebit,$this->mainBox->where('owner_id',$owner_id)->first()->id,$this->mainBox->where('owner_id',$owner_id)->first()->id,'App\Models\Car',0,0,'IQD');
            $this->accountingController->increaseWallet($paid_dinar, $descDebit,$this->onlineContractsDinar->where('owner_id',$owner_id)->first()->id,$car->id,'App\Models\Car',0,0,'IQD',0,$tran->id);
            $this->accountingController->decreaseWallet($paid_dinar, $descDebit,$this->debtOnlineContractsDinar->where('owner_id',$owner_id)->first()->id,$car->id,'App\Models\Car',0,0,'IQD',0,$tran->id);
        }
       

        if(( $contract->price-$paid == 0)&&($contract->price_dinar-$paid_dinar == 0)){
            $dataExitCar=['car_id'=>$car->id,'user_id'=>$car->client_id, 'created'=>$this->currentDate, 'phone' =>$phone, 'note' =>$note,'owner_id'=>$owner_id];
            $exitCar = ExitCar::create($dataExitCar);
            $car->update(['is_exit'=>$exitCar->id]);

        }
        return Response::json('ok', 200);    

    }

    public function removeContract(Request $request){
        $owner_id=Auth::user()->owner_id;

        $car = Car::find($request->id);

        $contract=Contract::find($request->contract_id);
        $descDebit="مرتجع حذف من عقد السيارة ".$car->car_type.' '.$car->car_type.' رقم الشانص'.$car->vin;

        if($contract->paid){
            $tran=$this->accountingController->decreaseWallet($contract->paid,$descDebit,$this->mainBox->where('owner_id',$owner_id)->first()->id,$this->mainBox->where('owner_id',$owner_id)->first()->id,'App\Models\Car',0,0,'$');

            $this->accountingController->decreaseWallet($contract->paid, $descDebit,$this->onlineContracts->where('owner_id',$owner_id)->first()->id,$car->id,'App\Models\Car',0,0,'$',0,$tran->id);

        }
        if($contract->paid_dinar){
            $tran=$this->accountingController->decreaseWallet($contract->paid_dinar,$descDebit,$this->mainBox->where('owner_id',$owner_id)->first()->id,$this->mainBox->where('owner_id',$owner_id)->first()->id,'App\Models\Car',0,0,'IQD');

            $this->accountingController->decreaseWallet($contract->paid_dinar, $descDebit,$this->onlineContracts->where('owner_id',$owner_id)->first()->id,$car->id,'App\Models\Car',0,0,'IQD',0,$tran->id);

        }

         $contract->delete();
        return Response::json('ok', 200);    

    }
    
    }