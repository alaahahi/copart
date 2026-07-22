<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Profile;
use App\Models\UserType;
use App\Models\Transactions;
use App\Models\Results;
use App\Models\DoctorResults;
use App\Models\SystemConfig;
use App\Models\Wallet;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\Transfers;
use App\Models\Car;
use App\Models\ExpensesType;
use App\Models\Expenses;
use App\Models\TransactionsImages;
use App\Models\PaymentTag;
use App\Support\VoucherPrint;
use App\Helpers\UploadHelper;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\File;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\ImportInfo;
use App\Exports\ExportInfo;
use App\Exports\ExportAccount;
use App\Services\AccountingCacheService;
use Illuminate\Support\Facades\Auth;


class AccountingController extends Controller
{
    protected $accounting;  
    protected $url;
    protected $currentDate;
    

    public function __construct(AccountingCacheService $accounting){
        $this->accounting = $accounting;

         $this->url = env('FRONTEND_URL');
        $this->currentDate = Carbon::now()->format('Y-m-d');
    }

    public function TransactionsUpload(Request $request)
    {
        $transactionsId = $request->transactionsId;
        $path1 = public_path('uploads');
        $path2 = public_path('uploadsResized');
    
        // Create the directories if they don't exist
        if (!file_exists($path1)) {
            mkdir($path1, 0777, true);
        }
        if (!file_exists($path2)) {
            mkdir($path2, 0777, true);
        }
    
        $file = $request->file('image');
    
        // Generate a unique file name
        $name = uniqid();
    
        // Save the original image to the first directory
        $file->move($path1, $name);
    
        // Load the original image using Intervention Image
        $image = Image::make(public_path('uploads/' . $name));
    
        // Save the resized image to the second directory
        $image->resize(50, 50, function ($constraint) {
            $constraint->aspectRatio();
            $constraint->upsize();
        });
    
        $image->save(public_path('uploadsResized/' . $name));
        // Create a new record in the database
        $carImage = TransactionsImages::create([
            'name' => $name,
            'transactions_id' => $transactionsId,
        ]);

        return response()->json($carImage, 200);
    }
    public function TransactionsImageDel(Request $request){
        $name = $request->name;

        File::delete(public_path('uploads/'.$name));
        File::delete(public_path('uploadsResized/'.$name));

        TransactionsImages::where('name', $name)->delete();
       
        
        return Response::json('deleted is done', 200);

    }

    public function index()
    {  
        $owner_id=Auth::user()->owner_id;
        $boxes = User::with('wallet')->where('owner_id',$owner_id)->where('email', 'mainBox@account.com')->get();
        $this->accounting->loadAccounts(Auth::user()->owner_id);
        
        // جلب المحافظ المميزة للعرض في لوحة التحكم
        $flaggedWallets = User::with('wallet')
            ->where('owner_id', $owner_id)
            ->where('show_in_dashboard', true)
            ->whereHas('wallet')
            ->get();

        $clientTypeId = $this->accounting->userClient();
        $walletUsers = User::query()
            ->where('owner_id', $owner_id)
            ->where(function ($query) use ($clientTypeId) {
                $query->where(function ($base) {
                    $base->where('email', '!=', 'mainBox@account.com')
                        ->whereHas('wallet');
                });
                if ($clientTypeId) {
                    $query->orWhere(function ($traders) use ($clientTypeId) {
                        $traders->where('type_id', $clientTypeId)
                            ->whereExists(function ($subQuery) {
                                $subQuery->select(DB::raw(1))
                                    ->from('transactions')
                                    ->whereColumn('transactions.morphed_id', 'users.id')
                                    ->where('transactions.morphed_type', User::class)
                                    ->whereIn('transactions.type', ['inUserBox', 'outUserBox'])
                                    ->whereNull('transactions.deleted_at');
                            });
                    });
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Accounting/Index', [
            'boxes'=>$boxes,
            'accounts'=>$this->accounting->mainAccount(),
            'flaggedWallets'=>$flaggedWallets,
            'walletUsers'=>$walletUsers,
        ]);
    }
    public function wallet(Request $request)
    {  
        $id= $request->id;
        $owner_id=Auth::user()->owner_id;
        $boxes = User::with('wallet')->where('owner_id',$owner_id)->where('id',$id)->first();
        $this->accounting->loadAccounts(Auth::user()->owner_id);

        return Inertia::render('Accounting/Wallet', ['boxes'=>$boxes,'accounts'=>$this->accounting->mainAccount()]);
    }

    public function toggleWalletTags(Request $request)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'has_wallet_tags' => 'required|boolean',
        ]);
        $user = User::where('id', $validated['user_id'])
            ->where('owner_id', Auth::user()->owner_id)
            ->firstOrFail();
        $user->has_wallet_tags = $validated['has_wallet_tags'];
        $user->save();
        return Response::json([
            'message' => 'تم تحديث تفعيل إدارة التاغات',
            'has_wallet_tags' => (bool) $user->has_wallet_tags,
        ], 200);
    }

    public function getIndexAccounting(Request $request)
    {
     $owner_id=Auth::user()->owner_id;
     $user_id = $_GET['user_id'] ?? 0;
     $from =  $_GET['from'] ?? 0;
     $to =$_GET['to'] ?? 0;
     $print =$_GET['print'] ?? 0;
     $q= $_GET['q'] ?? 0;
     $type = $_GET['type'] ??'';
     $transactions_id = $_GET['transactions_id'] ?? 0;
     $owner_id = $owner_id ?? Auth::user()->owner_id;
     $user = User::with('wallet')->where('id',$user_id)->first();
     if($from && $to ){
         $transactions = Transactions ::with('TransactionsImages')->with('morphed')->where('wallet_id', $user->wallet->id)->orderBy('id','desc')->whereBetween('created', [$from, $to]);
     }else{
         $transactions = Transactions ::with('TransactionsImages')->with('morphed')->where('wallet_id', $user->wallet->id)->orderBy('id','desc');
     }
     if($q){
        $transactions = Transactions::with('TransactionsImages')->with('morphed')->where('wallet_id', $user->wallet->id)
        ->where(function ($query) use ($q) {
            $query->where('id', $q)
                  ->orWhere('description', 'LIKE', '%' . $q . '%');
        })
        ->orderBy('id', 'desc');
    }
     $tag_filter = $request->get('tag');
     if ($tag_filter !== null && $tag_filter !== '') {
         $transactions = $transactions->where('tag', $tag_filter);
     }
     $driver_q = $request->get('driver_name') ?: $request->get('q_driver');
     if ($driver_q !== null && $driver_q !== '') {
         $driverLike = '%' . $driver_q . '%';
         if (DB::connection()->getDriverName() === 'sqlite') {
             $transactions = $transactions->whereRaw("json_extract(details, '$.driver_name') LIKE ?", [$driverLike]);
         } else {
             $transactions = $transactions->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(details, '$.driver_name')) LIKE ?", [$driverLike]);
         }
     }
     $loans_only = $request->get('loans_only');
     if ($loans_only) {
         if (DB::connection()->getDriverName() === 'sqlite') {
             $transactions = $transactions->whereRaw("json_extract(details, '$.loan') = 1");
         } else {
             $transactions = $transactions->whereRaw("JSON_EXTRACT(details, '$.loan') = true");
         }
     }
     if($type=='wallet'){
        $allTransactions = $transactions
        ->whereIn('type', ['inUser', 'outUser', 'inUserAmanah', 'outUserAmanah'])
        ->where('wallet_id', $user->wallet->id)
        ->paginate(1000);
         }elseif($type=='printExcel'){
            $allTransactions = $transactions->paginate(1000);
        }
         else{
        $allTransactions = $transactions->paginate(100);
     }
     // التأكد من تحميل المرفقات (TransactionsImages) في كل الحالات بما فيها عند الفلترة بالتاريخ
     $allTransactions->getCollection()->load('TransactionsImages');
     $sumAllTransactions = $allTransactions->where('currency','$')->sum('amount');
     $sumDebitTransactions = $allTransactions->where('currency','$')->whereIn('type', ['debt','outUserBox'])->sum('amount');
     $sumInTransactions = $allTransactions->where('currency','$')->whereIn('type', ['in', 'inUserBox'])->sum('amount');
     $sumInTransactionsUser = $allTransactions->where('currency','$')->where('type', 'inUser')->sum('amount');
     $sumOutTransactionsUser = $allTransactions->where('currency','$')->where('type', 'outUser')->sum('amount');
     $sumInTransactionsUserAmanah = $allTransactions->where('currency','$')->where('type', 'inUserAmanah')->sum('amount');
     $sumOutTransactionsUserAmanah = $allTransactions->where('currency','$')->where('type', 'outUserAmanah')->sum('amount');

     $sumAllTransactionsDinar = $allTransactions->where('currency','IQD')->sum('amount');
     $sumDebitTransactionsDinar = $allTransactions->where('currency','IQD')->whereIn('type', ['debt','outUserBox'])->sum('amount');
     $sumInTransactionsDinar = $allTransactions->where('currency','IQD')->whereIn('type', ['in', 'inUserBox'])->sum('amount');
     $sumInTransactionsDinarUser = $allTransactions->where('currency','IQD')->where('type', 'inUser')->sum('amount');
     $sumOutTransactionsDinarUser = $allTransactions->where('currency','IQD')->where('type', 'outUser')->sum('amount');
     $sumInTransactionsDinarUserAmanah = $allTransactions->where('currency','IQD')->where('type', 'inUserAmanah')->sum('amount');
     $sumOutTransactionsDinarUserAmanah = $allTransactions->where('currency','IQD')->where('type', 'outUserAmanah')->sum('amount');

     
     // Additional logic to retrieve client data
     $data = [
         'user' => $user,
         'transactions' => $allTransactions,
         'sum_transactions' => $sumAllTransactions,
         'sum_transactions_debit' => $sumDebitTransactions,
         'sum_transactions_in' => $sumInTransactions,
         'sum_transactions_dinar' => $sumAllTransactionsDinar,
         'sum_transactions_debit_dinar' => $sumDebitTransactionsDinar,
         'sum_transactions_in_dinar' => $sumInTransactionsDinar,
         'sumInTransactionsUser' =>  $sumInTransactionsUser,
         'sumInTransactionsDinarUser' => $sumInTransactionsDinarUser,
         'sumOutTransactionsUser' =>  $sumOutTransactionsUser,
         'sumOutTransactionsDinarUser' => $sumOutTransactionsDinarUser,
         'sumInTransactionsUserAmanah' =>  $sumInTransactionsUserAmanah,
         'sumInTransactionsDinarUserAmanah' => $sumInTransactionsDinarUserAmanah,
         'sumOutTransactionsUserAmanah' =>  $sumOutTransactionsUserAmanah,
         'sumOutTransactionsDinarUserAmanah' => $sumOutTransactionsDinarUserAmanah
     ];
     if ($request->get('group_by_driver') && $user && $user->wallet) {
         $walletTrans = Transactions::where('wallet_id', $user->wallet->id)
             ->whereIn('type', ['inUser', 'outUser', 'inUserAmanah', 'outUserAmanah'])
             ->get();
         $data['drivers_summary'] = $walletTrans->groupBy(function ($t) {
             $d = $t->details;
             return (is_array($d) && !empty($d['driver_name'])) ? $d['driver_name'] : '—';
         })->map(function ($items, $driverName) {
             $in = $items->whereIn('type', ['inUser', 'inUserAmanah'])->sum('amount');
             $out = $items->whereIn('type', ['outUser', 'outUserAmanah'])->sum('amount');
             return ['driver_name' => $driverName, 'total_in' => round($in, 2), 'total_out' => round($out, 2), 'count' => $items->count()];
         })->values()->toArray();
     }
     if($print==1){
         $config=SystemConfig::first();
         return view('receiptPaymentTotal',compact('data','config'));
      }
      elseif($print==2){
         $config=SystemConfig::first();
         return $this->renderVoucher('receipt', 'receipt', $config, compact('data','config','transactions_id','owner_id'));
      }
      elseif($print==3){
         $config=SystemConfig::first();
         return $this->renderVoucher('receiptPayment', 'payment', $config, compact('data','config','transactions_id','owner_id'));
      }
      elseif($print==4){
         $config=SystemConfig::first();
 
         return view('receiptPaymentTotal',compact('data','config','transactions_id'));
      }
      elseif($print==5){
        $config=SystemConfig::first();

        return view('receiptBoxTotal',compact('data','config','transactions_id'));
     }
     elseif($print==6){
        $config=SystemConfig::first();
      
        return Excel::download(new ExportAccount($from,$to,$user->wallet->id), $from.' '.$to.'.xlsx');

        return view('receiptPaymentTotal',compact('data','config','transactions_id'));
     }
     elseif($print==7){
        $config=SystemConfig::first();
        // Filter only Amanah transactions - get collection from paginated result
        $amanahTransactions = collect($allTransactions->items())->whereIn('type', ['inUserAmanah', 'outUserAmanah'])->values();
        $data['transactions'] = $amanahTransactions;
        return view('receiptWalletTotal',compact('data','config'));
     }
     elseif($print==8){
        $config=SystemConfig::first();
        // Filter only Wallet transactions (excluding Amanah) - get collection from paginated result
        $walletTransactions = collect($allTransactions->items())->whereIn('type', ['inUser', 'outUser'])->values();
        $data['transactions'] = $walletTransactions;
        return view('receiptWalletTotal',compact('data','config'));
     }
     elseif($print==9){
        // طباعة وصل قبض للدفعات (inUser)
        $config=SystemConfig::first();
        $transaction = Transactions::find($transactions_id);
        $clientData = [
            'client' => $user
        ];
        return $this->renderVoucher('receiptWallet', 'receipt', $config, compact('clientData','config','transactions_id','owner_id','transaction'));
     }
     elseif($print==10){
        // طباعة وصل دفع للدفعات (outUser)
        $config=SystemConfig::first();
        $transaction = Transactions::find($transactions_id);
        $clientData = [
            'client' => $user
        ];
        return $this->renderVoucher('receiptWalletPayment', 'payment', $config, compact('clientData','config','transactions_id','owner_id','transaction'));
     }
     elseif($print==11){
        // طباعة وصل قبض للأمانات (inUserAmanah)
        $config=SystemConfig::first();
        $transaction = Transactions::find($transactions_id);
        $clientData = [
            'client' => $user
        ];
        return $this->renderVoucher('receiptWalletAmanah', 'receipt', $config, compact('clientData','config','transactions_id','owner_id','transaction'));
     }
     elseif($print==12){
        // طباعة وصل دفع للأمانات (outUserAmanah)
        $config=SystemConfig::first();
        $transaction = Transactions::find($transactions_id);
        $clientData = [
            'client' => $user
        ];
        return $this->renderVoucher('receiptWalletAmanahPayment', 'payment', $config, compact('clientData','config','transactions_id','owner_id','transaction'));
     }
     return response()->json($data); 
     }
     public function salesDebtUser(Request $request)
     {
        $this->accounting->loadAccounts(Auth::user()->owner_id);
      $owner_id=Auth::user()->owner_id;
      $note= $request->note??'';
      $amountDollar= $request->amountDollar??0;
      $amountDinar= $request->amountDinar??0;
      $user_id=$request->id;
      $user=  User::with('wallet')->find($user_id);
      $desc="وصل سحب مباشر"." ".' قاسه'.' '.$user->name.' '.$note;
      $date= $request->date??0;
      $details = array_filter([
          'cars_count' => $request->input('cars_count'),
          'cmr' => $request->input('cmr'),
          'driver_name' => $request->input('driver_name'),
          'entry_date' => $request->input('entry_date'),
      ], function ($v) { return $v !== null && $v !== ''; });
      $tag = $request->input('tag') ? trim($request->input('tag')) : null;
      if($amountDollar){
        $transactiond=$this->debtWallet($amountDollar,$desc,$this->accounting->mainBox()->id,$user_id,'App\Models\User',0,0,'$',$date,0,'outUserBox');
        $transactionDetilsd = ['type' => 'outUser','wallet_id'=>$user->wallet->id,'description'=>$desc,'amount'=>$amountDollar,'is_pay'=>1,'morphed_id'=>$user_id,'morphed_type'=>'App\Models\User','user_added'=>0,'created'=>$date,'discount'=>0,'currency'=>'$','parent_id'=>$transactiond->id,'details'=>$details,'tag'=>$tag];
        $transaction = Transactions::create($transactionDetilsd);
      }
      if($amountDinar)
      {
        $transactionq=$this->debtWallet($amountDinar,$desc,$this->accounting->mainBox()->id,$user_id,'App\Models\User',0,0,'IQD',$date,0,'outUserBox');
        $transactionDetilsq = ['type' => 'outUser','wallet_id'=>$user->wallet->id,'description'=>$desc,'amount'=>$amountDinar,'is_pay'=>1,'morphed_id'=>$user_id,'morphed_type'=>'App\Models\User','user_added'=>0,'created'=>$date,'discount'=>0,'currency'=>'IQD','parent_id'=>$transactionq->id,'details'=>$details,'tag'=>$tag];
        $transaction = Transactions::create($transactionDetilsq);
      }
      return Response::json($request, 200);
  
      }
     public function salesDebtUserAmanah(Request $request)
     {
        $this->accounting->loadAccounts(Auth::user()->owner_id);
      $owner_id=Auth::user()->owner_id;
      $note= $request->note??'';
      $amountDollar= $request->amountDollar??0;
      $amountDinar= $request->amountDinar??0;
      $user_id=$request->id;
      $user=  User::with('wallet')->find($user_id);
      $desc="وصل سحب أمانة"." ".' قاسه'.' '.$user->name.' '.$note;
      $date= $request->date??0;
      if($amountDollar){
        // الأمانة لا تؤثر على balance - balance فقط للسيارات
        $transactionDetilsd = ['type' => 'outUserAmanah','wallet_id'=>$user->wallet->id,'description'=>$desc,'amount'=>$amountDollar,'is_pay'=>1,'morphed_id'=>$user_id,'morphed_type'=>'App\Models\User','user_added'=>0,'created'=>$date,'discount'=>0,'currency'=>'$','parent_id'=>0];
        $transaction = Transactions::create($transactionDetilsd);
      }
      if($amountDinar)
      {
        // الأمانة لا تؤثر على balance - balance فقط للسيارات
        $transactionDetilsq = ['type' => 'outUserAmanah','wallet_id'=>$user->wallet->id,'description'=>$desc,'amount'=>$amountDinar,'is_pay'=>1,'morphed_id'=>$user_id,'morphed_type'=>'App\Models\User','user_added'=>0,'created'=>$date,'discount'=>0,'currency'=>'IQD','parent_id'=>0];
        $transaction = Transactions::create($transactionDetilsq);
      }
      return Response::json($request, 200);
  
      }
     public function salesDebt(Request $request)
     {
        $this->accounting->loadAccounts(Auth::user()->owner_id);
      $owner_id=Auth::user()->owner_id;
      $user_id= $request->user['id']??0;
      $note= $request->note??'';
      $amountDollar= $request->amountDollar??0;
      $amountDinar= $request->amountDinar??0;

      $desc=" سحب دفعة  ".' '.$note;
      $date= $request->date??0;
      if($amountDollar){
        $transaction=$this->debtWallet($amountDollar,$desc,$this->accounting->mainBox()->id,$this->accounting->mainBox()->id,'App\Models\User',0,0,'$',$date);
      }
      if($amountDinar)
      {
        $transaction=$this->debtWallet($amountDinar,$desc,$this->accounting->mainBox()->id,$this->accounting->mainBox()->id,'App\Models\User',0,0,'IQD',$date);

      }

  
      return Response::json($request, 200);
  
      }
      public function receiptArrived(Request $request)
      {
        $this->accounting->loadAccounts(Auth::user()->owner_id);
       $owner_id=Auth::user()->owner_id;
       $note= $request->amountNote??'';
       $amountDollar= $request->amountDollar??0;
       $amountDinar= $request->amountDinar??0;
       $desc="وصل قبض مباشر"." ".' '.$note;
       $date= $request->date??0;
       if($amountDollar){
        $transaction=$this->increaseWallet($amountDollar,$desc,$this->accounting->mainBox()->id,$this->accounting->mainBox()->id,'App\Models\User',0,0,'$',$date);
       }
       if($amountDinar){

        $transaction=$this->increaseWallet($amountDinar,$desc,$this->accounting->mainBox()->id,$this->accounting->mainBox()->id,'App\Models\User',0,0,'IQD',$date);
       }

       return Response::json($transaction, 200);
   
       }
       public function receiptArrivedUser(Request $request)
       {
        $this->accounting->loadAccounts(Auth::user()->owner_id);
        $owner_id=Auth::user()->owner_id;
        $note= $request->amountNote??'';
        $user_id=$request->id;

        $amountDollar= $request->amountDollar??0;
        $amountDinar= $request->amountDinar??0;
        $user=  User::with('wallet')->find($user_id);

        $desc="وصل قبض مباشر"." ".' قاسه'.' '.$user->name.' '.$note;
        $date= $request->date??0;

        $details = array_filter([
            'cars_count' => $request->input('cars_count'),
            'cmr' => $request->input('cmr'),
            'driver_name' => $request->input('driver_name'),
            'entry_date' => $request->input('entry_date'),
        ], function ($v) { return $v !== null && $v !== ''; });
        $tag = $request->input('tag') ? trim($request->input('tag')) : null;

        if($amountDollar){
            $transactiond=$this->increaseWallet($amountDollar,$desc,$this->accounting->mainBox()->id,$user_id,'App\Models\User',0,0,'$',$date,0,'inUserBox',$details);
            $transactionDetilsd = ['type' => 'inUser','wallet_id'=>$user->wallet->id,'description'=>$desc,'amount'=>$amountDollar,'is_pay'=>1,'morphed_id'=>$user_id,'morphed_type'=>'App\Models\User','user_added'=>0,'created'=>$date,'discount'=>0,'currency'=>'$','parent_id'=>$transactiond->id,'details'=>$details,'tag'=>$tag];
            $transaction = Transactions::create($transactionDetilsd);
        }
        if($amountDinar){
            $transactionq=$this->increaseWallet($amountDinar,$desc,$this->accounting->mainBox()->id,$user_id,'App\Models\User',0,0,'IQD',$date,0,'inUserBox',$details);
            $transactionDetilsq = ['type' => 'inUser','wallet_id'=>$user->wallet->id,'description'=>$desc,'amount'=>$amountDinar,'is_pay'=>1,'morphed_id'=>$user_id,'morphed_type'=>'App\Models\User','user_added'=>0,'created'=>$date,'discount'=>0,'currency'=>'IQD','parent_id'=>$transactionq->id,'details'=>$details,'tag'=>$tag];
            $transaction = Transactions::create($transactionDetilsq);

        }

        return Response::json($transaction, 200);

        }
       public function receiptArrivedUserAmanah(Request $request)
       {
        $this->accounting->loadAccounts(Auth::user()->owner_id);
        $owner_id=Auth::user()->owner_id;
        $note= $request->amountNote??'';
        $user_id=$request->id;

        $amountDollar= $request->amountDollar??0;
        $amountDinar= $request->amountDinar??0;
        $user=  User::with('wallet')->find($user_id);

        $desc="وصل قبض أمانة"." ".' قاسه'.' '.$user->name.' '.$note;
        $date= $request->date??0;

        $details = array_filter([
            'cars_count' => $request->input('cars_count'),
            'cmr' => $request->input('cmr'),
            'driver_name' => $request->input('driver_name'),
            'entry_date' => $request->input('entry_date'),
        ], function ($v) { return $v !== null && $v !== ''; });
        $tag = $request->input('tag') ? trim($request->input('tag')) : null;

        if($amountDollar){
            // الأمانة لا تؤثر على balance - balance فقط للسيارات
            $transactionDetilsd = ['type' => 'inUserAmanah','wallet_id'=>$user->wallet->id,'description'=>$desc,'amount'=>$amountDollar,'is_pay'=>1,'morphed_id'=>$user_id,'morphed_type'=>'App\Models\User','user_added'=>0,'created'=>$date,'discount'=>0,'currency'=>'$','parent_id'=>0,'details'=>$details,'tag'=>$tag];
            $transaction = Transactions::create($transactionDetilsd);
        }
        if($amountDinar){
            // الأمانة لا تؤثر على balance - balance فقط للسيارات
            $transactionDetilsq = ['type' => 'inUserAmanah','wallet_id'=>$user->wallet->id,'description'=>$desc,'amount'=>$amountDinar,'is_pay'=>1,'morphed_id'=>$user_id,'morphed_type'=>'App\Models\User','user_added'=>0,'created'=>$date,'discount'=>0,'currency'=>'IQD','parent_id'=>0,'details'=>$details,'tag'=>$tag];
            $transaction = Transactions::create($transactionDetilsq);
        }

        return Response::json($transaction, 200);

        }
    public function getIndexAccountsSelas()
    { 
        $this->accounting->loadAccounts(Auth::user()->owner_id);
        $owner_id=Auth::user()->owner_id;
        $user_id = $_GET['user_id'] ?? 0;
        $from =  $_GET['from'] ?? 0;
        $to =$_GET['to'] ?? 0;
        $print =$_GET['print'] ?? 0;
        $car_id = $_GET['car_id'] ?? 0;
        $printExcel=$_GET['printExcel'] ?? 0;

        $showComplatedCars=$_GET['showComplatedCars'] ?? 0;
        $tag = $_GET['tag'] ?? '';
        $transactions_id = $_GET['transactions_id'] ?? 0;
        $client = User::with('wallet')->where('id', $user_id)->first();
        $contract_total = 0;
        $contract_total_debit_Dollar = 0;
        $contract_total_debit_Dinar = 0;

        if($from && $to ){
            $transactions = Transactions::where('wallet_id', $client?->wallet?->id)->whereBetween('created', [$from, $to]);
            $cars = Car::with('CarImages')->where('client_id',$client->id)->whereBetween('date', [$from, $to]);
            $car_total = $cars->count();
            $car_total_unpaid =     Car::where('client_id',$client->id)->where('results',0)->whereBetween('date', [$from, $to])->count();
            $car_total_uncomplete = Car::where('client_id',$client->id)->where('results',1)->whereBetween('date', [$from, $to])->count();
            $car_total_complete =   Car::where('client_id',$client->id)->where('results',2)->whereBetween('date', [$from, $to])->count();
            $cars_discount=   Car::where('client_id',$client->id)->whereBetween('date', [$from, $to])->sum('discount');
            $cars_paid=   Car::where('client_id',$client->id)->whereBetween('date', [$from, $to])->sum('paid');
            $cars_sum=   Car::where('client_id',$client->id)->whereBetween('date', [$from, $to])->sum('total_s');
            $exit_car_total=   Car::where('client_id',$client->id)->whereBetween('date', [$from, $to])->where('is_exit','!=',0)->count();
            $cars_need_paid=$cars_sum-($cars_paid+$cars_discount);
        }else{
            $transactions = Transactions::where('wallet_id', $client?->wallet?->id);
            $cars =  Car::with('CarImages')->where('client_id',$client->id);
            $car_total = $cars->count();
            $car_total_unpaid =     Car::where('client_id',$client->id)->where('results',0)->count();
            $car_total_uncomplete = Car::where('client_id',$client->id)->where('results',1)->count();
            $car_total_complete =   Car::where('client_id',$client->id)->where('results',2)->count();
            $cars_discount=Car::where('client_id',$client->id)->sum('discount');
            $cars_paid=   Car::where('client_id',$client->id)->sum('paid');
            $cars_sum=   Car::where('client_id',$client->id)->sum('total_s');
            $exit_car_total=   Car::where('client_id',$client->id)->where('is_exit','!=',0)->count();
            $cars_need_paid=$cars_sum-($cars_paid+$cars_discount);
        }
        // مجموع الدفعات بالدولار (type=out, is_pay=1, currency=$) - للمطابقة مع cars_paid
        $payments_sum_dollar = (clone $transactions)
            ->where('type', 'out')
            ->where('is_pay', 1)
            ->where('currency', '$')
            ->where('amount', '<', 0)
            ->sum('amount');

        //$data = $transactions->paginate(10);
 

        if($print==1){
            if($showComplatedCars==1){
                $clientData = [
                    'totalAmount' =>   $transactions->sum('amount'),
                    'data' => $cars->where('results','!=','2')->get(),
                    'client'=>$client,
                    'car_total'=>$cars->where('results','!=','2')->count(),
                    'car_total_unpaid'=>$car_total_unpaid,
                    'car_total_complete'=>$car_total_complete,
                    'car_total_uncomplete'=>$car_total_uncomplete,
                    'contract_total'=>$contract_total,
                    'exit_car_total'=>$exit_car_total,
                    'contract_total_debit_Dollar'=>$contract_total_debit_Dollar,
                    'contract_total_debit_Dinar'=>$contract_total_debit_Dinar,
                    'cars_sum'=>$cars_sum,
                    'cars_paid'=>$cars_paid,
                    'cars_discount'=>$cars_discount,
                    'cars_need_paid'=>$cars_need_paid,
                    'transactions'=>$transactions->get(),
                    'date'=> Carbon::now()->format('Y-m-d')
                ];
            }else{
                $clientData = [
                    'totalAmount' =>   $transactions->sum('amount'),
                    'data' => $cars->get(),
                    'client'=>$client,
                    'car_total'=>$car_total,
                    'car_total_unpaid'=>$car_total_unpaid,
                    'car_total_complete'=>$car_total_complete,
                    'car_total_uncomplete'=>$car_total_uncomplete,
                    'contract_total'=>$contract_total,
                    'exit_car_total'=>$exit_car_total,
                    'contract_total_debit_Dollar'=>$contract_total_debit_Dollar,
                    'contract_total_debit_Dinar'=>$contract_total_debit_Dinar,
                    'cars_sum'=>$cars_sum,
                    'cars_paid'=>$cars_paid,
                    'cars_discount'=>$cars_discount,
                    'cars_need_paid'=>$cars_need_paid,
                    'transactions'=>$transactions->get(),
                    'date'=> Carbon::now()->format('Y-m-d')
                ];
            }

            $config=SystemConfig::first();

            if($printExcel){
                return Excel::download(new ExportInfo($user_id,$showComplatedCars), $client->name.'.xlsx');
            }else{
                return view('show',compact('clientData','config'));
            }


         }

         if($print==6){
            $config=SystemConfig::first();
            $clientData = [
                'totalAmount' =>   $transactions->sum('amount'),
                'data' => $cars->where('id',$car_id)->get(),
                'client'=>$client,
                'car_total'=>$cars->where('id',$car_id)->count(),
                'car_total_unpaid'=>$car_total_unpaid,
                'car_total_complete'=>$car_total_complete,
                'car_total_uncomplete'=>$car_total_uncomplete,
                'contract_total'=>$contract_total,
                'exit_car_total'=>$exit_car_total,
                'contract_total_debit_Dollar'=>$contract_total_debit_Dollar,
                'contract_total_debit_Dinar'=>$contract_total_debit_Dinar,
                'cars_sum'=> $cars->where('id',$car_id)->first()->total_s,
                'cars_paid'=> $cars->where('id',$car_id)->first()->paid,
                'cars_discount'=>$cars->where('id',$car_id)->first()->discount,
                'cars_need_paid'=>$cars->where('id',$car_id)->first()->total_s - $cars->where('id',$car_id)->first()->paid,
                'transactions'=>$transactions->get(),
                'date'=> Carbon::now()->format('Y-m-d'),
                'print'=> 6
            ];
            return view('show',compact('clientData','config'));
         }

                 // Additional logic to retrieve client data
        $clientData = [
            'totalAmount' =>   $transactions->sum('amount'),
            'data' => $cars->get(),
            'client'=>$client,
            'car_total'=>$car_total,
            'car_total_unpaid'=>$car_total_unpaid,
            'car_total_complete'=>$car_total_complete,
            'car_total_uncomplete'=>$car_total_uncomplete,
            'contract_total'=>$contract_total,
            'exit_car_total'=>$exit_car_total,
            'contract_total_debit_Dollar'=>$contract_total_debit_Dollar,
            'contract_total_debit_Dinar'=>$contract_total_debit_Dinar,
            'cars_sum'=>$cars_sum,
            'cars_paid'=>$cars_paid,
            'cars_discount'=>$cars_discount,
            'cars_need_paid'=>$cars_need_paid,
            'payments_sum_dollar'=>$payments_sum_dollar,
            'transactions'=>$transactions->get(),
            'date'=> Carbon::now()->format('Y-m-d')
        ];

         if($print==2){
            $config=SystemConfig::first();
            $transaction = Transactions ::find($transactions_id);

            return $this->renderVoucher('receipt', 'receipt', $config, compact('clientData','config','transactions_id','owner_id','transaction'));
         }
   
         
         if($print==3){
            $config=SystemConfig::first();
            $transaction = Transactions ::find($transactions_id);
            return $this->renderVoucher('receiptPayment', 'payment', $config, compact('clientData','config','transactions_id','transaction','owner_id'));
         }
         if($print==4){
            $config=SystemConfig::first();
            return view('receiptPaymentTotal',compact('clientData','config','transactions_id'));
         }
         if($print==5){
            $config=SystemConfig::first();
    
            return view('receiptExpensesTotal',compact('clientData','config','transactions_id'));
         }

        return Response::json($clientData, 200);
    }
    public function paySelse(Request $request,$id)
    {

        $this->accounting->loadAccounts(Auth::user()->owner_id);
        try {
            DB::beginTransaction();
            // Perform your database operations with Eloquent
            $user=  User::with('wallet')->find($id);
            $transactions =Transactions ::where('wallet_id', $user?->wallet?->id)->where('is_pay',0);
            $amount=$transactions->sum('amount');
            $transactions->update(['is_pay' => 1]);
            $profile_count = Profile::where('user_id', $user?->id)->where('results',1)->update(['results' => 2]);
            $this->decreaseWallet($amount*-1,' تسليم مبلغ '.$amount.' دينار عراقي ',$user->id);
            // If everything is successful, commit the transaction
            DB::commit();
            // Return a response or perform other actions
        } catch (\Exception $e) {
            // Something went wrong, rollback the transaction
            DB::rollBack();
            // Handle the exception or return an error response
        }
        return Response::json('ok', 200);

    }
    public function addPaymentCar()
    {
        $this->accounting->loadAccounts(Auth::user()->owner_id);
        $owner_id=Auth::user()->owner_id;
        $user_id = $_GET['user_id']??0;
        $car_id = $_GET['car_id']??0;
        $amount=$_GET['amount']??0;
        $discount = $_GET['discount']??0;
        $note = $_GET['note'] ?? '';
        $car = Car::find($car_id);
        $details = [[
            'car_id' => $car->id,
            'car_number' => (string)$car->car_number,
            'vin' => $car->vin,
            'total_amount' => $car->total_s,
            'paid' => (int)$amount,
            'discount' => (int)$discount
        ]];
 
        $wallet = Wallet::where('user_id',$car->client_id)->first();
        $desc=trans('text.addPayment').' '.$amount.' '.$car->car_type.' رقم الشانص'.' '.$car->vin.' '.$note;
        $tran=$this->increaseWallet($amount,$desc,$this->accounting->mainBox()->id,$car->client_id,'App\Models\User',0,0,'$',0,0,'in',$details);
        $this->increaseWallet($amount, $desc,$this->accounting->mainAccount()->id,$car_id,'App\Models\Car',1,$discount??0,'$',$this->currentDate,$tran->id,'in',$details);
        $transaction=$this->decreaseWallet($amount+$discount, $desc,$car->client_id,$car_id,'App\Models\Car',1,$discount??0,'$',$this->currentDate,$tran->id,'out',$details);

        $car->increment('paid',$amount);
        if($discount ?? 0){
            $car->increment('discount',$discount);
        }

        if((($car->paid)+($car->discount))-$car->total_s < 0 && $amount){
            $car->update(['results'=>1]);
        }
        return Response::json($transaction, 200);
    }
    public function addPaymentCarTotal()
    {
        $this->accounting->loadAccounts(Auth::user()->owner_id);
        $client_id  = $_GET['client_id']  ??0;
        $amount_o  = $_GET['amount']  ??0;
        $note = $_GET['note'] ?? '';
        $discount= $_GET['discount']  ??0;

        if ($discount) {
            $carLast = Car::where('client_id', $client_id)
                ->where('total_s', '!=', 0)
                ->whereIn('results', [0, 1])
                ->latest()
                ->first();
            if ($carLast) {
                $carLast->increment('discount', $discount);
            }
        }

        if ($amount_o) {
            $desc = trans('text.addPayment').' '.$amount_o.' '.$note;

            $tran = $this->increaseWallet($amount_o, $desc, $this->accounting->mainBox()->id, $client_id, 'App\Models\User', 0, 0, '$');

            $this->increaseWallet($amount_o, $desc, $this->accounting->mainAccount()->id, $client_id, 'App\Models\User', 1, $discount, '$', $this->currentDate, $tran->id);

            $transaction = $this->decreaseWallet((int) $amount_o + (int) $discount, $desc, $client_id, $client_id, 'App\Models\User', 1, $discount, '$', $this->currentDate, $tran->id);

            return Response::json($transaction, 200);
        }

        return Response::json('ok', 200);
    }

    public function AddPayFromBalanceCar (Request $request){

        $balance = (float) ($request->balance ?? 0);
        $car = Car::findOrFail($request->id);
        $shouldPaid = max(0, (float) $car->total_s - (float) $car->paid - (float) $car->discount);
        $toApply = min($balance, $shouldPaid);

        if ($toApply <= 0) {
            return Response::json($car, 200);
        }

        $newPaid = (float) $car->paid + $toApply;
        $updates = ['paid' => $newPaid];
        if ($newPaid + (float) $car->discount < (float) $car->total_s) {
            $updates['results'] = 1;
        }
        $car->update($updates);

        return Response::json($car->fresh(), 200);
    }

    public function DelPayFromBalanceCar (Request $request){
        $car_id = $request->id;
        $car = Car::find($car_id);
        $car->update(['paid' => 0 ,'results' =>0]);
        return Response::json($car, 200);    

    }
    
    public function getGenExpenses (Request $request){
        $year_date=Carbon::now()->format('Y');

        $expenses = Expenses::where('expenses_type_id',$request->expenses_type_id)->where('year_date',$year_date)->get();

        return Response::json($expenses, 200);    

    }
    public function GenExpenses (Request $request){
        $owner_id=Auth::user()->owner_id;
        $year_date=Carbon::now()->format('Y');
        $factor=$request->factor ?? 1;
        $amount=(($request->amount)/ $factor);
        $expenses_type_id = $request->expenses_type_id;
        $reason=$request->note ?? '';
        $desc='';
        if($expenses_type_id==1){
            $user_id=$this->accounting->howler()->id;
            $desc='مصاريف أربيل مبلغ '.' '.($request->amount).'بسعر صرف'.' '.$factor.' '.$reason;
        }
        if($expenses_type_id==2){
            $user_id=$this->accounting->dubai()->id;
            $desc='مصاريف دبي مبلغ '.' '.($request->amount).'بسعر صرف'.' '.$factor.' '.$reason;
        }
        if($expenses_type_id==3){
            $desc='مصاريف ايران مبلغ '.' '.($request->amount).'بسعر صرف'.' '.$factor.' '.$reason;
            $user_id=$this->accounting->iran()->id;
        }
        if($expenses_type_id==4){
            $desc='مصاريف الحدود مبلغ '.' '.($request->amount).'بسعر صرف'.' '.$factor.' '.$reason;
            $user_id=$this->accounting->border()->id;
        }
        if($expenses_type_id==5){
            $desc='مصاريف شهادة coc مبلغ '.' '.($request->amount).'بسعر صرف'.' '.$factor.' '.$reason;
            $user_id=$this->accounting->shippingCoc()->id;
        }
        $tran=$this->decreaseWallet($amount,$desc,$this->accounting->mainBox()->id,$this->accounting->mainBox()->id,'App\Models\User',0,0,'$');
        $transaction=$this->increaseWallet($amount, $desc,$user_id,$user_id,'App\Models\User',1,0,'$',$this->currentDate,$tran->id);
        $expenses = Expenses::create([
            'factor' => $factor,
            'amount' => ($request->amount)/ $factor ?? 0,
            'reason' => $reason,
            'year_date'=>$year_date,
            'expenses_type_id'=>$expenses_type_id,
            'transaction_id' =>  $transaction->id,
            'user_id' => $user_id
        ]);

        return Response::json($transaction, 200);    

    }
    public function convertDollarDinar(Request $request){
        $this->accounting->loadAccounts(Auth::user()->owner_id);
        $owner_id=Auth::user()->owner_id;
        $amountDollar =$request->amountDollar;
        $amountResultDinar =$request->amountResultDinar;
        $exchangeRate =$request->exchangeRate;
        $date=$request->date??0;
        $desc=' تحويل من الصندوق مبلغ بالدولار'.' '.($amountDollar).'  بسعر صرف '.' '.$exchangeRate.' المبلغ المضاف للصندوف بالدينار '.$amountResultDinar;
        if($amountDollar){
            $transactionDollar=$this->decreaseWallet($amountDollar,$desc,$this->accounting->mainBox()->id,$this->accounting->mainBox()->id,'App\Models\User',0,0,'$',$date);
          }
          if($amountResultDinar)
          {
            $transactionDinar=$this->increaseWallet($amountResultDinar,$desc,$this->accounting->mainBox()->id,$this->accounting->mainBox()->id,'App\Models\User',0,0,'IQD',$date);
          }
          
          $transactionDollar->update(['parent_id'=>$transactionDinar->id]);
          $transactionDinar->update(['parent_id'=>$transactionDollar->id]);
          return Response::json($transactionDinar, 200);    

    }
    public function convertDinarDollar(Request $request){
        $this->accounting->loadAccounts(Auth::user()->owner_id);
        $owner_id=Auth::user()->owner_id;
        $amountDinar =$request->amountDinar;
        $amountResultDollar =$request->amountResultDollar;
        $exchangeRate =$request->exchangeRate;
        $date=$request->date??0;
        $desc=' تحويل من الصندوق مبلغ بالدينار'.' '.($amountDinar).'  بسعر صرف '.' '.$exchangeRate.' المبلغ المضاف للصندوف بالدولار '.$amountResultDollar;
        if($amountResultDollar){
            $transactionDollar= $this->increaseWallet($amountResultDollar,$desc,$this->accounting->mainBox()->id,$this->accounting->mainBox()->id,'App\Models\User',0,0,'$',$date);
          }
          if($amountDinar)
          {
            $transactionDinar= $transaction=$this->decreaseWallet($amountDinar,$desc,$this->accounting->mainBox()->id,$this->accounting->mainBox()->id,'App\Models\User',0,0,'IQD',$date);
          }
          $transactionDollar->update(['parent_id'=>$transactionDinar->id]);
          $transactionDinar->update(['parent_id'=>$transactionDollar->id]);
          return Response::json($transactionDinar, 200);    

    }
    public function checkClientBalance(Request $request)
    {
        $this->accounting->loadAccounts(Auth::user()->owner_id);
        $userId= $request->userId;
        $currentBalance= $request->currentBalance;
        $user = User::with('wallet')->where('id',$userId)->first();
        $systemBalance=$user->wallet->balance;
        if($systemBalance==$currentBalance){
            return Response::json('balance is good', 200);
        }else{
            $wallet = Wallet::find($user->wallet->id);
            $wallet->update(['balance' => $currentBalance]);
            return Response::json($systemBalance,201);
        }
        return Response::json('balance is good',200);
    }

    public function updateTransactionDescription(Request $request)
    {
        $this->accounting->loadAccounts(Auth::user()->owner_id);

        $validated = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        $transaction = Transactions::with(['wallet.user'])->find($validated['transaction_id']);

        if (!$transaction) {
            return Response::json(['message' => 'لم يتم العثور على الحركة المطلوبة'], 404);
        }

        $walletUser = optional($transaction->wallet)->user;

        if (!$walletUser || $walletUser->owner_id !== Auth::user()->owner_id) {
            return Response::json(['message' => 'غير مصرح بتعديل هذه الحركة'], 403);
        }

        $description = trim($validated['description']);

        if ($description === '') {
            return Response::json([
                'errors' => [
                    'description' => ['الوصف مطلوب'],
                ],
            ], 422);
        }

        $transaction->description = $description;
        $transaction->save();

        return Response::json([
            'message' => 'تم تحديث الوصف بنجاح',
            'transaction' => [
                'id' => $transaction->id,
                'description' => $transaction->description,
            ],
        ], 200);
    }

    /**
     * Detect GenExpenses (البوكسات الخمسة) from description and return the expense account user id.
     */
    private function resolveGenExpenseAccountUserId(?string $description): ?int
    {
        if (!$description) {
            return null;
        }

        $account = null;
        if (preg_match('/مصاريف\s+أربيل/ui', $description)) {
            $account = $this->accounting->howler();
        } elseif (preg_match('/مصاريف\s+دبي/ui', $description)) {
            $account = $this->accounting->dubai();
        } elseif (preg_match('/مصاريف\s+ايران/ui', $description) || preg_match('/مصاريف\s+إيران/ui', $description)) {
            $account = $this->accounting->iran();
        } elseif (preg_match('/مصاريف\s+الحدود/ui', $description)) {
            $account = $this->accounting->border();
        } elseif (preg_match('/مصاريف\s+شهادة\s*coc/ui', $description)) {
            $account = $this->accounting->shippingCoc();
        }

        return $account?->id;
    }

    /**
     * Convert a main-box withdrawal (debt/out) into a wallet withdrawal (outUserBox + outUser).
     */
    public function assignTransactionToWallet(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $this->accounting->loadAccounts(Auth::user()->owner_id);
        $mainBox = $this->accounting->mainBox();
        $mainBoxWalletId = $mainBox->wallet->id ?? null;

        if (!$mainBoxWalletId) {
            return Response::json(['message' => 'لم يتم العثور على صندوق المحاسبة'], 422);
        }

        $transaction = Transactions::with('wallet.user')->find($validated['transaction_id']);

        if (!$transaction || (int) $transaction->wallet_id !== (int) $mainBoxWalletId) {
            return Response::json(['message' => 'هذه الحركة ليست من صندوق المحاسبة'], 422);
        }

        if (!in_array($transaction->type, ['debt', 'out'], true)) {
            return Response::json(['message' => 'يمكن تحويل حركات السحب من الصندوق فقط'], 422);
        }

        if ((int) ($transaction->parent_id ?? 0) > 0) {
            return Response::json(['message' => 'لا يمكن تحويل حركة مرتبطة بحركة أخرى'], 422);
        }

        if (Transactions::where('parent_id', $transaction->id)->where('type', 'outUser')->exists()) {
            return Response::json(['message' => 'الحركة مرتبطة مسبقاً بقاسة'], 422);
        }

        $targetUser = User::with('wallet')
            ->where('id', $validated['user_id'])
            ->where('owner_id', Auth::user()->owner_id)
            ->first();

        if (!$targetUser) {
            return Response::json(['message' => 'القاسة المحددة غير موجودة'], 404);
        }

        if ((int) $targetUser->id === (int) $mainBox->id) {
            return Response::json(['message' => 'لا يمكن إسناد الحركة إلى الصندوق نفسه'], 422);
        }

        if (!$targetUser->wallet) {
            Wallet::create(['user_id' => $targetUser->id, 'balance' => 0, 'balance_dinar' => 0]);
            $targetUser->load('wallet');
        }

        $amount = abs((float) $transaction->amount);
        if ($amount <= 0) {
            return Response::json(['message' => 'مبلغ الحركة غير صالح'], 422);
        }

        $originalDescription = trim($transaction->description ?? '');
        $genExpenseAccountUserId = $this->resolveGenExpenseAccountUserId($originalDescription);
        $existingDetails = is_array($transaction->details) ? $transaction->details : [];

        if ($genExpenseAccountUserId) {
            // البوكسات الخمسة: إبقاء وصف المصروف الأصلي وربط الحساب بصندوق المصروف (دبي/إيران/الحدود...)
            $description = $originalDescription;
            $morphedId = $genExpenseAccountUserId;
            $childDetails = array_merge($existingDetails, [
                'gen_expense_box' => true,
                'assigned_wallet_user_id' => $targetUser->id,
            ]);
        } else {
            $noteSuffix = $originalDescription;
            if (preg_match('/سحب\s+دفعة\s*(.*)/u', $noteSuffix, $matches)) {
                $noteSuffix = trim($matches[1]);
            }
            $description = 'وصل سحب مباشر'.' '.'قاسه'.' '.$targetUser->name.($noteSuffix !== '' ? ' '.$noteSuffix : '');
            $morphedId = $targetUser->id;
            $childDetails = $existingDetails;
        }

        $originalCreatedAt = $transaction->created_at;
        $originalCreated = $transaction->created;
        $currentDate = $this->currentDate;

        DB::transaction(function () use ($transaction, $targetUser, $description, $amount, $originalCreatedAt, $originalCreated, $morphedId, $childDetails, $currentDate) {
            $transaction->type = 'outUserBox';
            $transaction->morphed_id = $morphedId;
            $transaction->morphed_type = User::class;
            $transaction->description = $description;
            if (!empty($childDetails)) {
                $transaction->details = $childDetails;
            }
            $transaction->save();

            $child = Transactions::create([
                'type' => 'outUser',
                'wallet_id' => $targetUser->wallet->id,
                'description' => $description,
                'amount' => $amount,
                'is_pay' => $transaction->is_pay,
                'morphed_id' => $morphedId,
                'morphed_type' => User::class,
                'user_added' => 0,
                'created' => $originalCreated ?: ($originalCreatedAt ? Carbon::parse($originalCreatedAt)->format('Y-m-d') : $currentDate),
                'discount' => $transaction->discount ?? 0,
                'currency' => $transaction->currency,
                'parent_id' => $transaction->id,
                'details' => $childDetails ?: null,
                'tag' => $transaction->tag,
            ]);

            if ($originalCreatedAt) {
                $child->created_at = $originalCreatedAt;
                $child->updated_at = $originalCreatedAt;
                $child->save();
            }
        });

        return Response::json([
            'message' => 'تم إسناد الحركة إلى القاسة بنجاح',
            'transaction_id' => $transaction->id,
            'wallet_user_id' => $targetUser->id,
        ], 200);
    }

    /**
     * Update transaction: description, tag, and details (cars_count, cmr, driver_name, entry_date).
     */
    public function updateTransaction(Request $request)
    {
        $this->accounting->loadAccounts(Auth::user()->owner_id);

        $validated = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'tag' => ['nullable', 'string', 'max:255'],
            'details' => ['nullable', 'array'],
            'details.cars_count' => ['nullable'],
            'details.cmr' => ['nullable', 'string', 'max:255'],
            'details.driver_name' => ['nullable', 'string', 'max:255'],
            'details.entry_date' => ['nullable', 'string', 'max:50'],
        ]);

        $transaction = Transactions::with(['wallet.user'])->find($validated['transaction_id']);

        if (!$transaction) {
            return Response::json(['message' => 'لم يتم العثور على الحركة المطلوبة'], 404);
        }

        $walletUser = optional($transaction->wallet)->user;

        if (!$walletUser || $walletUser->owner_id !== Auth::user()->owner_id) {
            return Response::json(['message' => 'غير مصرح بتعديل هذه الحركة'], 403);
        }

        if (array_key_exists('description', $validated) && $validated['description'] !== null) {
            $description = trim($validated['description']);
            if ($description === '') {
                return Response::json([
                    'errors' => ['description' => ['الوصف مطلوب إذا تم إرساله']],
                ], 422);
            }
            $transaction->description = $description;
        }

        if (array_key_exists('tag', $validated)) {
            $transaction->tag = $validated['tag'] ? trim($validated['tag']) : null;
        }

        if (!empty($validated['details'])) {
            $current = is_array($transaction->details) ? $transaction->details : [];
            $allowed = ['cars_count', 'cmr', 'driver_name', 'entry_date', 'loan'];
            foreach ($allowed as $key) {
                if (array_key_exists($key, $validated['details'])) {
                    $current[$key] = $validated['details'][$key];
                }
            }
            $transaction->details = $current;
        }

        $transaction->save();

        return Response::json([
            'message' => 'تم تحديث الحركة بنجاح',
            'transaction' => [
                'id' => $transaction->id,
                'description' => $transaction->description,
                'tag' => $transaction->tag,
                'details' => $transaction->details,
            ],
        ], 200);
    }

    public function getPaymentTags(Request $request)
    {
        $owner_id = Auth::user()->owner_id;
        $tags = PaymentTag::where('owner_id', $owner_id)->orderBy('name')->get(['id', 'name']);
        return Response::json($tags, 200);
    }

    public function storePaymentTag(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);
        $owner_id = Auth::user()->owner_id;
        $tag = PaymentTag::create([
            'owner_id' => $owner_id,
            'name' => trim($validated['name']),
        ]);
        return Response::json($tag, 201);
    }

    public function deletePaymentTag(Request $request)
    {
        $id = $request->input('id');
        $tag = PaymentTag::find($id);
        if (!$tag || $tag->owner_id !== Auth::user()->owner_id) {
            return Response::json(['message' => 'غير مصرح'], 403);
        }
        $name = $tag->name;
        $tag->delete();
        Transactions::where('tag', $name)->update(['tag' => null]);
        \App\Models\CompanyTreasuryEntry::where('owner_id', Auth::user()->owner_id)
            ->where('tag', $name)
            ->update(['tag' => null]);
        return Response::json(['message' => 'تم حذف التاغ'], 200);
    }

    public function createDriverLoan(Request $request)
    {
        $this->accounting->loadAccounts(Auth::user()->owner_id);
        $request->validate([
            'id' => 'required|exists:users,id',
            'amountDollar' => 'nullable|numeric|min:0',
            'amountDinar' => 'nullable|numeric|min:0',
            'driver_name' => 'required|string|max:255',
            'date' => 'nullable|string',
            'note' => 'nullable|string|max:500',
            'cmr' => 'nullable|string|max:255',
        ]);
        $user_id = $request->id;
        $user = User::with('wallet')->find($user_id);
        if (!$user || $user->owner_id !== Auth::user()->owner_id) {
            return Response::json(['message' => 'غير مصرح'], 403);
        }
        $amountDollar = $request->amountDollar ?? 0;
        $amountDinar = $request->amountDinar ?? 0;
        if (!$amountDollar && !$amountDinar) {
            return Response::json(['errors' => ['amount' => ['المبلغ مطلوب']]], 422);
        }
        $date = $request->date ?: $this->currentDate;
        $driver_name = trim($request->driver_name);
        $details = [
            'loan' => true,
            'driver_name' => $driver_name,
            'entry_date' => $request->entry_date ?: $date,
            'cmr' => $request->cmr ? trim($request->cmr) : null,
        ];
        $note = $request->note ? trim($request->note) : '';
        $desc = 'قرض سائق - ' . $driver_name . ($note ? ' - ' . $note : '');
        $transaction = null;
        if ($amountDollar) {
            $transactiond = $this->debtWallet($amountDollar, $desc, $this->accounting->mainBox()->id, $user_id, 'App\Models\User', 0, 0, '$', $date, 0, 'outUserBox');
            $transaction = Transactions::create([
                'type' => 'outUser', 'wallet_id' => $user->wallet->id, 'description' => $desc, 'amount' => $amountDollar * -1,
                'is_pay' => 1, 'morphed_id' => $user_id, 'morphed_type' => 'App\Models\User', 'user_added' => 0, 'created' => $date,
                'discount' => 0, 'currency' => '$', 'parent_id' => $transactiond->id, 'details' => $details,
            ]);
        }
        if ($amountDinar) {
            $transactionq = $this->debtWallet($amountDinar, $desc, $this->accounting->mainBox()->id, $user_id, 'App\Models\User', 0, 0, 'IQD', $date, 0, 'outUserBox');
            $transaction = Transactions::create([
                'type' => 'outUser', 'wallet_id' => $user->wallet->id, 'description' => $desc, 'amount' => $amountDinar * -1,
                'is_pay' => 1, 'morphed_id' => $user_id, 'morphed_type' => 'App\Models\User', 'user_added' => 0, 'created' => $date,
                'discount' => 0, 'currency' => 'IQD', 'parent_id' => $transactionq->id, 'details' => $details,
            ]);
        }
        return Response::json(['message' => 'تم تسجيل القرض', 'transaction' => $transaction], 201);
    }

    public function createDriverLoanRepayment(Request $request)
    {
        $this->accounting->loadAccounts(Auth::user()->owner_id);
        $request->validate([
            'parent_id' => 'required|integer|exists:transactions,id',
            'amountDollar' => 'nullable|numeric|min:0',
            'amountDinar' => 'nullable|numeric|min:0',
            'date' => 'nullable|string',
        ]);
        $loanTran = Transactions::with('wallet.user')->find($request->parent_id);
        if (!$loanTran || $loanTran->type !== 'outUser') {
            return Response::json(['message' => 'حركة القرض غير موجودة'], 404);
        }
        $details = is_array($loanTran->details) ? $loanTran->details : [];
        if (empty($details['loan'])) {
            return Response::json(['message' => 'هذه الحركة ليست قرضاً'], 400);
        }
        $walletUser = $loanTran->wallet->user;
        if (!$walletUser || $walletUser->owner_id !== Auth::user()->owner_id) {
            return Response::json(['message' => 'غير مصرح'], 403);
        }
        $user_id = $walletUser->id;
        $user = User::with('wallet')->find($user_id);
        $amountDollar = $request->amountDollar ?? 0;
        $amountDinar = $request->amountDinar ?? 0;
        if (!$amountDollar && !$amountDinar) {
            return Response::json(['errors' => ['amount' => ['المبلغ مطلوب']]], 422);
        }
        $date = $request->date ?: $this->currentDate;
        $driver_name = $details['driver_name'] ?? 'سائق';
        $desc = 'دفعة إرجاع قرض - ' . $driver_name;
        $transaction = null;
        if ($amountDollar) {
            $transactiond = $this->increaseWallet($amountDollar, $desc, $this->accounting->mainBox()->id, $user_id, 'App\Models\User', 0, 0, '$', $date, 0, 'inUserBox', []);
            $transaction = Transactions::create([
                'type' => 'inUser', 'wallet_id' => $user->wallet->id, 'description' => $desc, 'amount' => $amountDollar,
                'is_pay' => 1, 'morphed_id' => $user_id, 'morphed_type' => 'App\Models\User', 'user_added' => 0, 'created' => $date,
                'discount' => 0, 'currency' => '$', 'parent_id' => $loanTran->id, 'details' => ['driver_name' => $driver_name],
            ]);
        }
        if ($amountDinar) {
            $transactionq = $this->increaseWallet($amountDinar, $desc, $this->accounting->mainBox()->id, $user_id, 'App\Models\User', 0, 0, 'IQD', $date, 0, 'inUserBox', []);
            $transaction = Transactions::create([
                'type' => 'inUser', 'wallet_id' => $user->wallet->id, 'description' => $desc, 'amount' => $amountDinar,
                'is_pay' => 1, 'morphed_id' => $user_id, 'morphed_type' => 'App\Models\User', 'user_added' => 0, 'created' => $date,
                'discount' => 0, 'currency' => 'IQD', 'parent_id' => $loanTran->id, 'details' => ['driver_name' => $driver_name],
            ]);
        }
        return Response::json(['message' => 'تم تسجيل دفعة الإرجاع', 'transaction' => $transaction], 201);
    }

    public function increaseWallet(int $amount,$desc,$user_id,$morphed_id='',$morphed_type='',$is_pay=0,$discount=0,$currency='$',$created=0,$parent_id=0,$type='in',$details=[],$owner_id=null) 
    {
        $ownerId = $owner_id ?? Auth::user()->owner_id;
        $this->accounting->loadAccounts($ownerId);
        if($amount){
            if($created==0){
                $created=$this->currentDate;
            }
            $user=  User::with('wallet')->find($user_id);
            if($id = $user->wallet->id){
            $transactionDetils = ['type' => $type,'wallet_id'=>$id,'description'=>$desc,'amount'=>$amount,'is_pay'=>$is_pay,'morphed_id'=>$morphed_id,'morphed_type'=>$morphed_type,'user_added'=>0,'created'=>$created,'discount'=>$discount??0,'currency'=>$currency,'parent_id'=>$parent_id,'details'=>$details];
            $transaction = Transactions::create($transactionDetils);
            $wallet = Wallet::find($id);
            if($currency=='IQD'){
                $wallet->increment('balance_dinar', $amount);
            }else{
                $wallet->increment('balance', $amount);
            }
            }
            if (is_null($wallet)) {
                return null;
            }
            // Finally return the updated wallet.
            return $transaction;
        }else{
            return 0 ;
        }

    }

    public function decreaseWallet(int $amount,$desc,$user_id,$morphed_id=0,$morphed_type='',$is_pay=0,$discount=0,$currency='$',$created=0,$parent_id=0,$type='out',$details=[],$owner_id=null) 
    {
        $ownerId = $owner_id ?? Auth::user()->owner_id;
        $this->accounting->loadAccounts($ownerId);
        if($amount){
        if($created==0){
            $created=$this->currentDate;
        }

        $user=  User::with('wallet')->find($user_id);
        if(!$user->wallet->id){
          Wallet::create(['user_id' => $user_id,'balance'=>0]);
        }
  
        if($id = $user->wallet->id){
        $wallet = Wallet::find($id);
        $transactionDetils = ['type' => $type,'wallet_id'=>$id,'description'=>$desc,'amount'=>$amount*-1,'is_pay'=>$is_pay,'morphed_id'=>$morphed_id,'morphed_type'=>$morphed_type,'user_added'=>0,'created'=>$created,'discount'=>$discount??0,'currency'=>$currency,'parent_id'=>$parent_id,'details'=>$details];
        $transaction =Transactions::create($transactionDetils);
        if($currency=='IQD'){
            $wallet->decrement('balance_dinar', $amount);
        }else{
            $wallet->decrement('balance', $amount);
        }

        }
        if (is_null($wallet)) {
            return null;
        }
        // Finally return the updated wallet.
        return $transaction;
        }else{
            return 0 ;
        }
    }
    public function debtWallet(int $amount,$desc,$user_id,$morphed_id=0,$morphed_type='',$is_pay=0,$discount=0,$currency='$',$created=0,$parent_id=0,$type='debt')  
    {
        $this->accounting->loadAccounts(Auth::user()->owner_id);
        if($created==0){
            $created=$this->currentDate ;
        }
        $user=  User::with('wallet')->find($user_id);
        if($id = $user->wallet->id){
        $wallet = Wallet::find($id);
        if($currency=='IQD'){
            $wallet->decrement('balance_dinar', $amount);
        }else{
            $wallet->decrement('balance', $amount);
        }
            $transactionDetils = ['type' => $type,'wallet_id'=>$id,'description'=>$desc,'amount'=>$amount*-1,'is_pay'=>$is_pay,'morphed_id'=>$morphed_id,'morphed_type'=>$morphed_type,'user_added'=>0,'created'=>$created,'discount'=>$discount??0,'currency'=>$currency,'parent_id'=>$parent_id];

            $Transactions =Transactions::create($transactionDetils);
         
        
        }
        if (is_null($wallet)) {
            return null;
        }
        // Finally return the updated wallet.
        return $Transactions;
    }
 
    public function delTransactions(Request $request)
    {
        $this->accounting->loadAccounts(Auth::user()->owner_id);
        $owner_id=Auth::user()->owner_id;
        $transaction_id = $request->id ?? 0;
        $originalTransaction = Transactions::with('TransactionsImages')->find($transaction_id);
        if (!$originalTransaction) {
          return response()->json(['message' => 'Transaction not found'], 404);
        }

        if (in_array($originalTransaction->type, ['inUserAmanah', 'outUserAmanah'], true)) {
            foreach ($originalTransaction->TransactionsImages as $transactionsImage) {
                File::delete(public_path('uploads/' . $transactionsImage->name));
                File::delete(public_path('uploadsResized/' . $transactionsImage->name));
                $transactionsImage->delete();
            }
            $originalTransaction->delete();
            return response()->json(['message' => 'deleted'], 200);
        }

        $wallet_id=$originalTransaction->wallet_id;
        $refundTransaction = 'مرتجع حذف حركة';
        $firstTransaction = null;

        $wallet=Wallet::find($wallet_id);
        if($originalTransaction->currency=='$'){
            if($originalTransaction->type=='inUserBox' || $originalTransaction->type=='outUserBox')
            {
                $wallet->decrement('balance', $originalTransaction->amount);
                $all=  Transactions::where('parent_id',$transaction_id)->get();
 
                $firstTransaction=Transactions::where('parent_id',$transaction_id)->first();
                 if ($all->isNotEmpty()) { // Check if there are records in the collection
                  foreach ($all as $transaction) {
                      if($transaction->currency=='$'){
                          $wallet_id = $transaction->wallet_id;
                           $wallet = Wallet::find($wallet_id);
                          $transaction->delete();
                      }
                      if($transaction->currency=='IQD'){
                          $wallet_id = $transaction->wallet_id;
                           $wallet = Wallet::find($wallet_id);
                          $transaction->delete();
                      }
                  }
              }
            }else{
                $wallet->decrement('balance', $originalTransaction->amount);
                $all=  Transactions::where('parent_id',$transaction_id)->get();
      
                $firstTransaction=Transactions::where('parent_id',$transaction_id)->first();
                if ($all->isNotEmpty()) { // Check if there are records in the collection
                  foreach ($all as $transaction) {
                      if($transaction->currency=='$'){
                          $wallet_id = $transaction->wallet_id;
                          $wallet = Wallet::find($wallet_id);
                          $wallet->decrement('balance', $transaction->amount);
                          $transaction->delete();
                      }
                      if($transaction->currency=='IQD'){
                          $wallet_id = $transaction->wallet_id;
                          $wallet = Wallet::find($wallet_id);
                          $wallet->decrement('balance_dinar', $transaction->amount);
                          $transaction->delete();
                      }
                  }
              }
            }

        }
        if($originalTransaction->currency=='IQD'){
            if($originalTransaction->type=='inUserBox'|| $originalTransaction->type=='outUserBox')
            {
                $wallet->decrement('balance_dinar', $originalTransaction->amount);
                $all=  Transactions::where('parent_id',$transaction_id)->get();
                $firstTransaction=Transactions::where('parent_id',$transaction_id)->first();
      
                if ($all->isNotEmpty()) { // Check if there are records in the collection
                  foreach ($all as $transaction) {
                      if($transaction->currency=='$'){
                          $wallet_id = $transaction->wallet_id;
                          $wallet = Wallet::find($wallet_id);
                          $transaction->delete();
                      }
                      if($transaction->currency=='IQD'){
                          $wallet_id = $transaction->wallet_id;
                          $wallet = Wallet::find($wallet_id);
                          $transaction->delete();
                      }
                  }
              }
            }else{
                $wallet->decrement('balance_dinar', $originalTransaction->amount);
                $all=  Transactions::where('parent_id',$transaction_id)->get();
                $firstTransaction=Transactions::where('parent_id',$transaction_id)->first();
      
                if ($all->isNotEmpty()) { // Check if there are records in the collection
                  foreach ($all as $transaction) {
                      if($transaction->currency=='$'){
                          $wallet_id = $transaction->wallet_id;
                          $wallet = Wallet::find($wallet_id);
                          $wallet->decrement('balance', $transaction->amount);
                          $transaction->delete();
                      }
                      if($transaction->currency=='IQD'){
                          $wallet_id = $transaction->wallet_id;
                          $wallet = Wallet::find($wallet_id);
                          $wallet->decrement('balance_dinar', $transaction->amount);
                          $transaction->delete();
                      }
                  }
              }
            }


        }
        $walletExpensesIds = [];
        if ($this->accounting->howler() && $this->accounting->howler()->wallet) {
            $walletExpensesIds[] = $this->accounting->howler()->wallet->id;
        }
        if ($this->accounting->shippingCoc() && $this->accounting->shippingCoc()->wallet) {
            $walletExpensesIds[] = $this->accounting->shippingCoc()->wallet->id;
        }
        if ($this->accounting->border() && $this->accounting->border()->wallet) {
            $walletExpensesIds[] = $this->accounting->border()->wallet->id;
        }
        if ($this->accounting->iran() && $this->accounting->iran()->wallet) {
            $walletExpensesIds[] = $this->accounting->iran()->wallet->id;
        }
        if ($this->accounting->dubai() && $this->accounting->dubai()->wallet) {
            $walletExpensesIds[] = $this->accounting->dubai()->wallet->id;
        }
        if ($firstTransaction && in_array($wallet_id, $walletExpensesIds)) {
            $expenses = Expenses::where('transaction_id',$firstTransaction->id);
            $expenses->delete();
        }
         
        if($originalTransaction){
            foreach ($originalTransaction->TransactionsImages as $transactionsImage) {
                // Delete the image file from the public directory
                File::delete(public_path('uploads/' . $transactionsImage->name));
                File::delete(public_path('uploadsResized/' . $transactionsImage->name));
    
                // Delete the image record from the database
                $transactionsImage->delete();
            }
        }
 
 
        $originalTransaction->delete();
    
        return response()->json(['message' => $all], 200);
    }

    public function receiveCard(Request $request)
    {
        $authUser = auth()->user();
        $profile_id = $_GET['id'] ?? 0;
        $profile = Profile::find($profile_id);
        $wallet = Wallet::where('user_id', $profile->user_id)->first();
        $user = User::find($profile->user_id);
        $old_card = $wallet->card;
        $old_balance = $wallet->balance;
        $card_price = $card->price ?? 0;
        $percentage = $user->percentage ?? 0;
        $new_balance = $old_balance + $percentage;

        try {
            DB::beginTransaction();
            $profile->update(['results' => 1, 'user_accepted' => $authUser->id]);
            $this->increaseWallet($percentage, ' نسبة على البطاقة رقم ' . $profile?->card_number, $user->id);
            $wallet->update(['card' => $old_card - 1, 'balance' => $new_balance]);
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
        }

        return Response::json($new_balance, 200);
    }

    protected function renderVoucher(string $defaultView, string $voucherType, $config, array $vars)
    {
        $transaction = $vars['transaction'] ?? null;
        if (!$transaction && !empty($vars['transactions_id'])) {
            $transaction = Transactions::find($vars['transactions_id']);
        }

        $client = $vars['clientData']['client']
            ?? ($vars['user'] ?? ($vars['data']['user'] ?? null));

        if (VoucherPrint::usesMklTemplate($config)) {
            return view('receiptVoucherMkl', array_merge(
                VoucherPrint::dataFromTransaction($transaction, $client, $voucherType),
                ['config' => $config ? $config->toArray() : []]
            ));
        }

        return view($defaultView, $vars);
    }
}