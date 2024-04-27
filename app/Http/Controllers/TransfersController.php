<?php

namespace App\Http\Controllers;
use App\Http\Controllers\AccountingController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Http;
use App\Models\Transfers;
use App\Models\User;
use App\Models\Car;
use App\Models\Company;
use App\Models\Name;
use App\Models\CarModel;
use App\Models\Color;
use App\Models\Wallet;
use App\Models\UserType;
use App\Models\ExpensesType;
use Illuminate\Support\Facades\DB;
use App\Models\Transactions;
use App\Models\Expenses;
use Illuminate\Support\Facades\Auth;



use Carbon\Carbon;

use Inertia\Inertia;

class TransfersController extends Controller
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
    $this->onlineContracts= User::with('wallet')->where('type_id', $this->userAccount)->where('email','online-contracts');
    $this->onlineContractsDinar= User::with('wallet')->where('type_id', $this->userAccount)->where('email','online-contracts-dinar');
    $this->debtOnlineContracts= User::with('wallet')->where('type_id', $this->userAccount)->where('email','online-contracts-debt');
    $this->debtOnlineContractsDinar= User::with('wallet')->where('type_id', $this->userAccount)->where('email','online-contracts-debit-dinar');
    $this->howler= User::with('wallet')->where('type_id', $this->userAccount)->where('email','howler');
    $this->shippingCoc= User::with('wallet')->where('type_id', $this->userAccount)->where('email','shipping-coc');
    $this->border= User::with('wallet')->where('type_id', $this->userAccount)->where('email','border');
    $this->iran= User::with('wallet')->where('type_id', $this->userAccount)->where('email','iran');
    $this->dubai= User::with('wallet')->where('type_id', $this->userAccount)->where('email','dubai');
    $this->mainBox= User::with('wallet')->where('type_id', $this->userAccount)->where('email','mainBox@account.com');
    }

    public function index(Request $request)
    {
        $transfers = Transfers::get();
        return Response::json($transfers, 200);    
    }

    public function addTransfers()
    {
        $owner_id=Auth::user()->owner_id;
        $maxNo = Transfers::max('no') ?? 0;
        $no = $maxNo + 1;
        $tran=Transfers::create([
            'no'=>$no,
            'user_id' =>Auth::user()->id,
            'stauts'=>'قيد التسليم',
            'sender_id' =>$this->mainBox->where('owner_id',$owner_id)->first()->id,
            'amount'=> $_GET['amount'] ??'',
            'sender_note'=>$_GET['sender_note'] ?? '',
             ]);
        return Response::json('ok', 200);    
    }
    public function confirmTransfers(Request $request){
        $owner_id=Auth::user()->owner_id;
        $transfer_fee=$request->inputValue??0;
        $receiver_note=$request->receiver;
        $transfer=Transfers::find($request->id);
        if($transfer){
            $transfer->update(['fee'=>$transfer_fee,'receiver_id'=>$this->mainBox->where('owner_id',$owner_id)->first()->id,'receiver_note'=>$receiver_note, 'stauts'=>'تم الأستلام',]);
            $desc=' تحويل من فرع كركوك مبلغ '.$transfer->amount.' '.$transfer->sender_note.' '.$transfer->receiver_note.' '.'أجور التحويل '.$transfer->fee.' المبلغ الصافي '.$transfer->amount-$transfer->fee.' دولار ';
            $this->accountingController->decreaseWallet($transfer->amount,$desc,$transfer->sender_id,$transfer->sender_id,'App\Models\User');
            $this->accountingController->increaseWallet($transfer->amount-$transfer->fee,$desc,$transfer->receiver_id,$transfer->receiver_id,'App\Models\User');
            return Response::json($transfer, 200);    
        }else{
            return Response::json('transfer not found', 405);    
        }
    }
    public function cancelTransfers(Request $request){
            $transfer_id=$request->id;
            $transfer=Transfers::find($transfer_id);
            $transfer->delete();
            return Response::json('delete done', 200);    
    }
}
