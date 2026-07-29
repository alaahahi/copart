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
use App\Services\LedgerService;
use App\Services\SystemWalletService;
use App\Services\TransactionPaymentService;
use App\Services\VaultService;
use App\Models\Vault;
use App\Models\LedgerAccount;
use Illuminate\Support\Facades\Schema;
use App\Services\WhatsAppQueueService;
use App\Http\Requests\RestoreTransactionRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;


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
        $boxes = User::where('owner_id',$owner_id)->where('email', 'mainBox@account.com')->get();
        $this->accounting->loadAccounts(Auth::user()->owner_id);
        $this->attachLedgerBalancesToUsers($boxes, (int) $owner_id);
        
        // قاصات النظام من جدول vaults — ليست تجاراً بعلم show_in_dashboard
        $flaggedWallets = app(VaultService::class)->accountingShortcutUsers((int) $owner_id);
        $this->attachLedgerBalancesToUsers($flaggedWallets, (int) $owner_id);

        // اختصارات مصاريف/عمولات = حسابات COA (ليست قاصات نقدية)
        $ledger = app(LedgerService::class);
        $ledger->ensureSystemAccounts((int) $owner_id);
        $expenseShortcuts = $ledger->listExpenseCommissionAccounts((int) $owner_id);
        $expenseParentId = LedgerAccount::query()
            ->where('owner_id', (int) $owner_id)
            ->where('code', LedgerService::CODE_EXPENSE)
            ->where('is_active', true)
            ->value('id');

        // إسناد السحب إلى قاصة → قائمة القاصات فقط (legacy_user_id للتوافق مع API)
        $vaultService = app(VaultService::class);
        $mainBoxId = (int) ($boxes->first()?->id ?? 0);
        $walletUsers = $vaultService->listForOwner((int) $owner_id)
            ->filter(function ($vault) use ($mainBoxId) {
                $legacyId = (int) ($vault->legacy_user_id ?? 0);

                return $legacyId > 0 && $legacyId !== $mainBoxId;
            })
            ->map(fn ($vault) => (object) [
                'id' => (int) $vault->legacy_user_id,
                'name' => $vault->name,
                'vault_id' => (int) $vault->id,
                'vault_type' => $vault->type,
                'is_vault' => true,
            ])
            ->values();

        return Inertia::render('Accounting/Index', [
            'boxes'=>$boxes,
            'accounts'=>$this->accounting->mainAccount(),
            'flaggedWallets'=>$flaggedWallets,
            'walletUsers'=>$walletUsers,
            'expenseShortcuts'=>$expenseShortcuts,
            'suggestExpenseCode'=>$ledger->suggestExpenseAccountCode((int) $owner_id, 'expense'),
            'suggestCommissionCode'=>$ledger->suggestExpenseAccountCode((int) $owner_id, 'commission'),
            'expenseParentId'=>$expenseParentId !== null ? (int) $expenseParentId : null,
        ]);
    }
    public function wallet(Request $request)
    {  
        $id= $request->id;
        $owner_id=Auth::user()->owner_id;
        $boxes = User::where('owner_id',$owner_id)->where('id',$id)->first();
        $this->accounting->loadAccounts(Auth::user()->owner_id);
        if ($boxes) {
            $this->attachLedgerBalancesToUsers(collect([$boxes]), (int) $owner_id);
        }

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

    /**
     * Attach a computed `money_account` (id/code/name/name_ar/type) to each transaction,
     * derived from the double-entry journal already posted for it (or its parent leg).
     * Never invents fields — purely a read-time projection over existing ledger data,
     * so the UI can show which real account (cash box / قاصة / client AR) the movement hit.
     */
    private function attachMoneyAccounts($transactions)
    {
        $ledger = app(LedgerService::class);
        foreach ($transactions as $transaction) {
            $account = $ledger->resolveMoneyAccount($transaction);
            $transaction->setAttribute('money_account', $account ? [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'name_ar' => $account->name_ar,
                'type' => $account->type,
            ] : null);
        }

        return $transactions;
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
     $user = User::where('id', $user_id)->first();
     if (! $user || (int) $user->owner_id !== (int) $owner_id) {
         return response()->json(['message' => 'الحساب غير موجود'], 404);
     }

     // رصيد/حركات المحاسبة = من vault_id + الدفتر (بدون جدول wallets).
     $transactions = $this->transactionsQueryForUser($user)
         ->with(['TransactionsImages', 'morphed'])
         ->orderBy('id', 'desc');
     if ($from && $to) {
         $transactions = $transactions->whereBetween('created', [$from, $to]);
     }
     if ($q) {
         $transactions = $transactions->where(function ($query) use ($q) {
             $query->where('id', $q)
                 ->orWhere('description', 'LIKE', '%'.$q.'%');
         });
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
     if ($type == 'wallet') {
         $allTransactions = $transactions
             ->whereIn('type', ['inUser', 'outUser', 'inUserAmanah', 'outUserAmanah'])
             ->paginate(1000);
     } elseif ($type == 'printExcel') {
         $allTransactions = $transactions->paginate(1000);
     } else {
         $allTransactions = $transactions->paginate(100);
     }

     // أرصدة العرض من دليل الحسابات (قاصة نقدية أو ذمم زبون).
     try {
         $ledger = app(LedgerService::class);
         $user->setAttribute('balance', $ledger->walletLedgerAccount((int) $owner_id, (int) $user->id, '$')->balance('$'));
         $user->setAttribute('balance_dinar', $ledger->walletLedgerAccount((int) $owner_id, (int) $user->id, 'IQD')->balance('IQD'));
     } catch (\Throwable $e) {
         $user->setAttribute('balance', 0);
         $user->setAttribute('balance_dinar', 0);
     }
     // التأكد من تحميل المرفقات (TransactionsImages) في كل الحالات بما فيها عند الفلترة بالتاريخ
     $allTransactions->getCollection()->load(['TransactionsImages', 'journalEntry.lines.account', 'parent.journalEntry.lines.account']);
     $this->attachMoneyAccounts($allTransactions->getCollection());
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
     if ($request->get('group_by_driver') && $user) {
         $walletTrans = $this->transactionsQueryForUser($user)
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
      
        return Excel::download(new ExportAccount($from,$to,(int) $user->id), $from.' '.$to.'.xlsx');

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
        $owner_id = Auth::user()->owner_id;
        $this->accounting->loadAccounts($owner_id);

        $note = $request->note ?? $request->amountNote ?? '';
        $amountDollar = $request->amountDollar ?? 0;
        $amountDinar = $request->amountDinar ?? 0;
        if (!$amountDollar && !$amountDinar) {
            return Response::json(['message' => 'المبلغ مطلوب', 'errors' => ['amount' => ['المبلغ مطلوب']]], 422);
        }

        // Expense always from receipts/mainBox cash: Dr Expense / Cr Cash — no mirror child on target vault.
        $vaults = app(\App\Services\VaultService::class);
        try {
            $cashUserId = $vaults->receiptsCashUserId((int) $owner_id);
        } catch (\Throwable $e) {
            $cashUserId = (int) app(SystemWalletService::class)->requireMainBox((int) $owner_id)->id;
        }
        $cashUser = User::find($cashUserId);
        if (!$cashUser || (int) $cashUser->owner_id !== (int) $owner_id) {
            return Response::json([
                'message' => 'حساب النقد غير موجود',
                'errors' => ['id' => ['حساب النقد غير موجود']],
            ], 422);
        }

        $label = $cashUser->name;
        if ($request->id && (int) $request->id !== (int) $cashUserId) {
            $named = User::find($request->id);
            if ($named) {
                $label = $named->name;
            }
        }

        $desc = 'وصل سحب مباشر قاسه '.$label.' '.$note;
        $date = $request->date ?: $this->currentDate;
        $tag = $request->input('tag') ? trim($request->input('tag')) : null;
        $transaction = null;

        if ($amountDollar) {
            $transaction = $this->debtWallet($amountDollar, $desc, $cashUserId, $cashUserId, 'App\Models\User', 0, 0, '$', $date, 0, 'outUserBox');
            if (!$transaction) {
                return Response::json(['message' => 'تعذر تسجيل السحب من الصندوق'], 500);
            }
            if ($tag && \Illuminate\Support\Facades\Schema::hasColumn('transactions', 'tag')) {
                $transaction->forceFill(['tag' => $tag])->save();
            }
        }
        if ($amountDinar) {
            $transaction = $this->debtWallet($amountDinar, $desc, $cashUserId, $cashUserId, 'App\Models\User', 0, 0, 'IQD', $date, 0, 'outUserBox');
            if (!$transaction) {
                return Response::json(['message' => 'تعذر تسجيل السحب من الصندوق'], 500);
            }
            if ($tag && \Illuminate\Support\Facades\Schema::hasColumn('transactions', 'tag')) {
                $transaction->forceFill(['tag' => $tag])->save();
            }
        }

        return Response::json($transaction ?? $request, 200);
      }
     public function salesDebtUserAmanah(Request $request)
     {
        $owner_id = Auth::user()->owner_id;
        $this->accounting->loadAccounts($owner_id);

        $note = $request->note ?? $request->amountNote ?? '';
        $amountDollar = $request->amountDollar ?? 0;
        $amountDinar = $request->amountDinar ?? 0;
        if (!$amountDollar && !$amountDinar) {
            return Response::json(['message' => 'المبلغ مطلوب', 'errors' => ['amount' => ['المبلغ مطلوب']]], 422);
        }

        $vaults = app(\App\Services\VaultService::class);
        try {
            $cashUserId = $vaults->receiptsCashUserId((int) $owner_id);
        } catch (\Throwable $e) {
            $cashUserId = (int) app(SystemWalletService::class)->requireMainBox((int) $owner_id)->id;
        }
        $cashUser = User::find($cashUserId);
        if (!$cashUser || (int) $cashUser->owner_id !== (int) $owner_id) {
            return Response::json([
                'message' => 'حساب النقد غير موجود',
                'errors' => ['id' => ['حساب النقد غير موجود']],
            ], 422);
        }

        $label = $cashUser->name;
        if ($request->id && (int) $request->id !== (int) $cashUserId) {
            $named = User::find($request->id);
            if ($named) {
                $label = $named->name;
            }
        }

        $desc = 'وصل سحب أمانة قاسه '.$label.' '.$note;
        $date = $request->date ?: $this->currentDate;
        $transaction = null;
        $vaultId = $vaults->resolveVaultIdForLegacyUser($cashUserId);
        $walletId = $vaults->resolveWalletIdForLegacyUser($cashUserId);

        if ($amountDollar) {
            $payload = [
                'type' => 'outUserAmanah',
                'description' => $desc,
                'amount' => $amountDollar,
                'is_pay' => 1,
                'morphed_id' => $cashUserId,
                'morphed_type' => 'App\Models\User',
                'user_added' => 0,
                'created' => $date,
                'discount' => 0,
                'currency' => '$',
                'parent_id' => 0,
            ];
            if ($walletId) {
                $payload['wallet_id'] = $walletId;
            }
            if ($vaultId && \Illuminate\Support\Facades\Schema::hasColumn('transactions', 'vault_id')) {
                $payload['vault_id'] = $vaultId;
            }
            $transaction = Transactions::create($payload);
        }
        if ($amountDinar) {
            $payload = [
                'type' => 'outUserAmanah',
                'description' => $desc,
                'amount' => $amountDinar,
                'is_pay' => 1,
                'morphed_id' => $cashUserId,
                'morphed_type' => 'App\Models\User',
                'user_added' => 0,
                'created' => $date,
                'discount' => 0,
                'currency' => 'IQD',
                'parent_id' => 0,
            ];
            if ($walletId) {
                $payload['wallet_id'] = $walletId;
            }
            if ($vaultId && \Illuminate\Support\Facades\Schema::hasColumn('transactions', 'vault_id')) {
                $payload['vault_id'] = $vaultId;
            }
            $transaction = Transactions::create($payload);
        }

        return Response::json($transaction ?? $request, 200);
  
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
        $owner_id = Auth::user()->owner_id;
        $this->accounting->loadAccounts($owner_id);
        $note = $request->amountNote ?? $request->note ?? '';
        $amountDollar = $request->amountDollar ?? 0;
        $amountDinar = $request->amountDinar ?? 0;
        if (!$amountDollar && !$amountDinar) {
            return Response::json(['message' => 'المبلغ مطلوب', 'errors' => ['amount' => ['المبلغ مطلوب']]], 422);
        }

        // Receipt always on receipts/mainBox cash: Dr Cash / Cr Revenue — no mirror child on target vault.
        $vaults = app(\App\Services\VaultService::class);
        try {
            $cashUserId = $vaults->receiptsCashUserId((int) $owner_id);
        } catch (\Throwable $e) {
            $cashUserId = (int) app(SystemWalletService::class)->requireMainBox((int) $owner_id)->id;
        }
        $cashUser = User::find($cashUserId);
        if (!$cashUser || (int) $cashUser->owner_id !== (int) $owner_id) {
            return Response::json([
                'message' => 'حساب النقد غير موجود',
                'errors' => ['id' => ['حساب النقد غير موجود']],
            ], 422);
        }

        $label = $cashUser->name;
        if ($request->id && (int) $request->id !== (int) $cashUserId) {
            $named = User::find($request->id);
            if ($named) {
                $label = $named->name;
            }
        }

        $desc = 'وصل قبض مباشر قاسه '.$label.' '.$note;
        $date = $request->date ?: $this->currentDate;
        $tag = $request->input('tag') ? trim($request->input('tag')) : null;
        $transaction = null;

        if ($amountDollar) {
            $transaction = $this->increaseWallet($amountDollar, $desc, $cashUserId, $cashUserId, 'App\Models\User', 0, 0, '$', $date, 0, 'inUserBox', []);
            if (!$transaction) {
                return Response::json(['message' => 'تعذر تسجيل الإيداع في الصندوق'], 500);
            }
            if ($tag && \Illuminate\Support\Facades\Schema::hasColumn('transactions', 'tag')) {
                $transaction->forceFill(['tag' => $tag])->save();
            }
        }
        if ($amountDinar) {
            $transaction = $this->increaseWallet($amountDinar, $desc, $cashUserId, $cashUserId, 'App\Models\User', 0, 0, 'IQD', $date, 0, 'inUserBox', []);
            if (!$transaction) {
                return Response::json(['message' => 'تعذر تسجيل الإيداع في الصندوق'], 500);
            }
            if ($tag && \Illuminate\Support\Facades\Schema::hasColumn('transactions', 'tag')) {
                $transaction->forceFill(['tag' => $tag])->save();
            }
        }

        return Response::json($transaction, 200);

        }
       public function receiptArrivedUserAmanah(Request $request)
       {
        $owner_id = Auth::user()->owner_id;
        $this->accounting->loadAccounts($owner_id);
        $note = $request->amountNote ?? $request->note ?? '';
        $amountDollar = $request->amountDollar ?? 0;
        $amountDinar = $request->amountDinar ?? 0;
        if (!$amountDollar && !$amountDinar) {
            return Response::json(['message' => 'المبلغ مطلوب', 'errors' => ['amount' => ['المبلغ مطلوب']]], 422);
        }

        $vaults = app(\App\Services\VaultService::class);
        try {
            $cashUserId = $vaults->receiptsCashUserId((int) $owner_id);
        } catch (\Throwable $e) {
            $cashUserId = (int) app(SystemWalletService::class)->requireMainBox((int) $owner_id)->id;
        }
        $cashUser = User::find($cashUserId);
        if (!$cashUser || (int) $cashUser->owner_id !== (int) $owner_id) {
            return Response::json([
                'message' => 'حساب النقد غير موجود',
                'errors' => ['id' => ['حساب النقد غير موجود']],
            ], 422);
        }

        $label = $cashUser->name;
        if ($request->id && (int) $request->id !== (int) $cashUserId) {
            $named = User::find($request->id);
            if ($named) {
                $label = $named->name;
            }
        }

        $desc = 'وصل قبض أمانة قاسه '.$label.' '.$note;
        $date = $request->date ?: $this->currentDate;
        $transaction = null;
        $tag = $request->input('tag') ? trim($request->input('tag')) : null;
        $vaultId = $vaults->resolveVaultIdForLegacyUser($cashUserId);
        $walletId = $vaults->resolveWalletIdForLegacyUser($cashUserId);

        if($amountDollar){
            $payload = [
                'type' => 'inUserAmanah',
                'description' => $desc,
                'amount' => $amountDollar,
                'is_pay' => 1,
                'morphed_id' => $cashUserId,
                'morphed_type' => 'App\Models\User',
                'user_added' => 0,
                'created' => $date,
                'discount' => 0,
                'currency' => '$',
                'parent_id' => 0,
                'details' => [],
                'tag' => $tag,
            ];
            if ($walletId) {
                $payload['wallet_id'] = $walletId;
            }
            if ($vaultId && \Illuminate\Support\Facades\Schema::hasColumn('transactions', 'vault_id')) {
                $payload['vault_id'] = $vaultId;
            }
            $transaction = Transactions::create($payload);
        }
        if($amountDinar){
            $payload = [
                'type' => 'inUserAmanah',
                'description' => $desc,
                'amount' => $amountDinar,
                'is_pay' => 1,
                'morphed_id' => $cashUserId,
                'morphed_type' => 'App\Models\User',
                'user_added' => 0,
                'created' => $date,
                'discount' => 0,
                'currency' => 'IQD',
                'parent_id' => 0,
                'details' => [],
                'tag' => $tag,
            ];
            if ($walletId) {
                $payload['wallet_id'] = $walletId;
            }
            if ($vaultId && \Illuminate\Support\Facades\Schema::hasColumn('transactions', 'vault_id')) {
                $payload['vault_id'] = $vaultId;
            }
            $transaction = Transactions::create($payload);
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
        // Clients/Show only: include soft-deleted payments for restore UI.
        $includeTrashed = (int) ($_GET['include_trashed'] ?? 0) === 1;
        $client = User::where('id', $user_id)->first();
        $contract_total = 0;
        $contract_total_debit_Dollar = 0;
        $contract_total_debit_Dinar = 0;

        if (!$client) {
            return Response::json(['message' => 'المستخدم غير موجود'], 404);
        }

        if($from && $to ){
            $transactions = $this->transactionsQueryForUser($client)->whereBetween('created', [$from, $to]);
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
            $transactions = $this->transactionsQueryForUser($client);
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
        if ($includeTrashed) {
            $transactions->withTrashed();
        }
        // مجموع الدفعات بالدولار (type=out, is_pay=1, currency=$) - للمطابقة مع cars_paid
        // Always exclude soft-deleted rows from totals even when include_trashed is on.
        $payments_sum_dollar = (clone $transactions)
            ->whereNull('deleted_at')
            ->where('type', 'out')
            ->where('is_pay', 1)
            ->where('currency', '$')
            ->where('amount', '<', 0)
            ->sum('amount');
        $activeTotalAmount = (clone $transactions)->whereNull('deleted_at')->sum('amount');

        // Same as Car::clientRemainingBalanceSqlSubquery — uses wallet payments, NOT car.paid,
        // so توزيع السيارة (AddPayFromBalanceCar) does not change this figure.
        // payments_sum_dollar is a negative sum of out/is_pay amounts.
        $client_balance = round((float) $cars_sum - (float) $cars_discount + (float) $payments_sum_dollar, 2);

        //$data = $transactions->paginate(10);
 

        if($print==1){
            if($showComplatedCars==1){
                $printCars = (clone $cars)->where('results', '!=', '2')->get();
                $clientData = [
                    'totalAmount' =>   $activeTotalAmount,
                    'data' => $printCars,
                    'client'=>$client,
                    'car_total'=>$printCars->count(),
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
                    'client_balance'=>$client_balance,
                    'transactions'=>$this->attachMoneyAccounts($transactions->get()),
                    'date'=> Carbon::now()->format('Y-m-d')
                ];
            }else{
                $clientData = [
                    'totalAmount' =>   $activeTotalAmount,
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
                    'client_balance'=>$client_balance,
                    'transactions'=>$this->attachMoneyAccounts($transactions->get()),
                    'date'=> Carbon::now()->format('Y-m-d')
                ];
            }

            // Empty system_config table used to make print crash (null ArrayAccess in show.blade.php).
            $config = $this->resolveSystemConfig();

            if($printExcel){
                return Excel::download(new ExportInfo($user_id,$showComplatedCars), ($client->name ?? 'client').'.xlsx');
            }else{
                return view('show',compact('clientData','config'));
            }


         }

         if($print==6){
            $config = $this->resolveSystemConfig();
            $car = (clone $cars)->where('id', $car_id)->first();
            if (!$car) {
                abort(404, 'السيارة غير موجودة');
            }
            $clientData = [
                'totalAmount' =>   $activeTotalAmount,
                'data' => collect([$car]),
                'client'=>$client,
                'car_total'=>1,
                'car_total_unpaid'=>$car_total_unpaid,
                'car_total_complete'=>$car_total_complete,
                'car_total_uncomplete'=>$car_total_uncomplete,
                'contract_total'=>$contract_total,
                'exit_car_total'=>$exit_car_total,
                'contract_total_debit_Dollar'=>$contract_total_debit_Dollar,
                'contract_total_debit_Dinar'=>$contract_total_debit_Dinar,
                'cars_sum'=> $car->total_s,
                'cars_paid'=> $car->paid,
                'cars_discount'=>$car->discount,
                'cars_need_paid'=>$car->total_s - $car->paid - $car->discount,
                'payments_sum_dollar'=>$payments_sum_dollar,
                'client_balance'=>$client_balance,
                'transactions'=>$this->attachMoneyAccounts($transactions->get()),
                'date'=> Carbon::now()->format('Y-m-d'),
                'print'=> 6
            ];
            return view('show',compact('clientData','config'));
         }

                 // Additional logic to retrieve client data
        $clientData = [
            'totalAmount' =>   $activeTotalAmount,
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
            'client_balance'=>$client_balance,
            'transactions'=>$this->attachMoneyAccounts($transactions->get()),
            'date'=> Carbon::now()->format('Y-m-d')
        ];

         if($print==2){
            $config = $this->resolveSystemConfig();
            $transaction = Transactions ::find($transactions_id);

            return $this->renderVoucher('receipt', 'receipt', $config, compact('clientData','config','transactions_id','owner_id','transaction'));
         }
   
         
         if($print==3){
            $config = $this->resolveSystemConfig();
            $transaction = Transactions ::find($transactions_id);
            return $this->renderVoucher('receiptPayment', 'payment', $config, compact('clientData','config','transactions_id','transaction','owner_id'));
         }
         if($print==4){
            $config = $this->resolveSystemConfig();
            return view('receiptPaymentTotal',compact('clientData','config','transactions_id'));
         }
         if($print==5){
            $config = $this->resolveSystemConfig();
    
            return view('receiptExpensesTotal',compact('clientData','config','transactions_id'));
         }

        return Response::json($clientData, 200);
    }

    /**
     * Print blades index config as an array; null SystemConfig::first() caused HTTP 500.
     */
    protected function resolveSystemConfig(): SystemConfig
    {
        return SystemConfig::query()->first() ?? new SystemConfig([
            'first_title_ar' => (string) config('app.name', ''),
            'second_title_ar' => '',
            'third_title_ar' => '',
            'first_title_kr' => '',
            'second_title_kr' => '',
            'third_title_kr' => '',
        ]);
    }
    public function paySelse(Request $request,$id)
    {

        $this->accounting->loadAccounts(Auth::user()->owner_id);
        try {
            DB::beginTransaction();
            // Perform your database operations with Eloquent
            $user = User::find($id);
            if (! $user) {
                DB::rollBack();
                return Response::json(['message' => 'user not found'], 404);
            }
            $transactions = $this->transactionsQueryForUser($user)->where('is_pay', 0);
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

        // مجموع المدفوع والخصم بعد هذه الدفعة، لعرض "المتبقي" في وصل القبض
        // الحساب المحاسبي (increment على $car) يبقى كما هو أدناه، هذه فقط قيم للعرض في الوصل.
        $paidUpTotal = (float) $car->paid + (float) $amount;
        $discountTotal = (float) $car->discount + (float) $discount;
        $restTotal = round((float) $car->total_s - $paidUpTotal - $discountTotal, 2);

        // رقم اللوت (نفس عمود car_number الحالي في جدول السيارات) مرتبط بوصل الدفعة
        // ليتم عرضه في الوصل فقط عندما تكون الدفعة على سيارة محددة (وليس دفعة عامة للزبون).
        $details = [
            'car_id' => $car->id,
            'car_number' => (string) $car->car_number,
            'vin' => $car->vin,
            'total_amount' => $car->total_s,
            'paid' => (int) $amount,
            'discount' => (int) $discount,
            'lot' => (string) $car->car_number,
            'paid_up' => $paidUpTotal,
            'rest' => $restTotal,
        ];

        $desc=trans('text.addPayment').' '.$amount.' '.$car->car_type.' رقم الشانص'.' '.$car->vin.' '.$note;
        // Parent posts ONE journal: Dr receipts-vault cash / Cr AR. Child legs are UI mirrors only.
        $vaults = app(VaultService::class);
        try {
            $vaults->ensureMainBoxVault((int) $owner_id);
            $receiptsUserId = $vaults->receiptsCashUserId((int) $owner_id);
        } catch (\Throwable $e) {
            $receiptsUserId = (int) app(SystemWalletService::class)->requireMainBox((int) $owner_id)->id;
        }

        $tran = $this->increaseWallet($amount, $desc, $receiptsUserId, $car->client_id, 'App\Models\User', 0, $discount ?? 0, '$', 0, 0, 'in', $details);
        if (!$tran || ! is_object($tran)) {
            return Response::json(['message' => 'فشل تسجيل الدفعة — قاصة الاستلام غير متاحة'], 500);
        }

        // Optional الخزينة (main@account.com) mirror — never assume it exists after vaults migration.
        $mirrorAccountId = (int) ($this->accounting->mainAccount()?->id ?? 0);
        if ($mirrorAccountId > 0) {
            $this->increaseWallet($amount, $desc, $mirrorAccountId, $car_id, 'App\Models\Car', 1, $discount ?? 0, '$', $this->currentDate, $tran->id, 'in', $details);
        }

        $transaction = $this->decreaseWallet($amount + $discount, $desc, $car->client_id, $car_id, 'App\Models\Car', 1, $discount ?? 0, '$', $this->currentDate, $tran->id, 'out', $details);

        if ($discount ?? 0) {
            $car->increment('discount', $discount);
            $car = $car->fresh() ?? $car;
        }

        // JSON reference + paid cache (journals remain the accounting source of truth).
        app(\App\Services\CarPaymentAllocationService::class)->append($car, [
            'source' => \App\Services\CarPaymentAllocationService::SOURCE_DIRECT,
            'transaction_id' => (int) ($transaction->id ?? $tran->id),
            'amount' => (float) $amount,
            'discount' => (float) ($discount ?? 0),
            'currency' => '$',
            'note' => $note !== '' ? (string) $note : null,
        ]);

        try {
            $client = User::find($car->client_id);
            if ($client && $tran) {
                app(WhatsAppQueueService::class)->notifyPayment(
                    $client,
                    (float) $amount,
                    '$',
                    (int) $tran->id,
                    (string) ($car->vin ?: $car->car_number)
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('WA payment_received hook failed', [
                'car_id' => $car_id,
                'message' => $e->getMessage(),
            ]);
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

            // Parent posts ONE journal against قاصة استلام دفعات الزبائن.
            $ownerId = (int) Auth::user()->owner_id;
            $vaults = app(VaultService::class);
            try {
                $vaults->ensureMainBoxVault($ownerId);
                $receiptsUserId = $vaults->receiptsCashUserId($ownerId);
            } catch (\Throwable $e) {
                $receiptsUserId = (int) app(SystemWalletService::class)->requireMainBox($ownerId)->id;
            }

            $tran = $this->increaseWallet($amount_o, $desc, $receiptsUserId, $client_id, 'App\Models\User', 0, $discount, '$');
            if (!$tran || ! is_object($tran)) {
                return Response::json(['message' => 'فشل تسجيل الدفعة — قاصة الاستلام غير متاحة'], 500);
            }

            // Optional الخزينة mirror — skip when mainAccount was never seeded / soft-deleted.
            $mirrorAccountId = (int) ($this->accounting->mainAccount()?->id ?? 0);
            if ($mirrorAccountId > 0) {
                $this->increaseWallet($amount_o, $desc, $mirrorAccountId, $client_id, 'App\Models\User', 1, $discount, '$', $this->currentDate, $tran->id);
            }

            $transaction = $this->decreaseWallet((int) $amount_o + (int) $discount, $desc, $client_id, $client_id, 'App\Models\User', 1, $discount, '$', $this->currentDate, $tran->id);

            try {
                $client = User::find($client_id);
                if ($client && $tran) {
                    app(WhatsAppQueueService::class)->notifyPayment(
                        $client,
                        (float) $amount_o,
                        '$',
                        (int) $tran->id,
                        (string) $note
                    );
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('WA payment_received (total) hook failed', [
                    'client_id' => $client_id,
                    'message' => $e->getMessage(),
                ]);
            }

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

        // No new journal — parent client payment already posted; JSON is operational reference only.
        $car = app(\App\Services\CarPaymentAllocationService::class)->append($car, [
            'source' => \App\Services\CarPaymentAllocationService::SOURCE_FROM_BALANCE,
            'transaction_id' => null,
            'amount' => $toApply,
            'discount' => 0,
            'currency' => '$',
            'note' => 'توزيع من رصيد الزبون',
        ]);

        return Response::json($car, 200);
    }

    public function DelPayFromBalanceCar (Request $request){
        $car = Car::findOrFail($request->id);
        // Clears paid cache + allocation trail (return to undistributed client balance).
        $car = app(\App\Services\CarPaymentAllocationService::class)->clear($car);

        return Response::json($car, 200);
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
        $ownerId = (int) Auth::user()->owner_id;
        $this->accounting->loadAccounts($ownerId);
        $userId = (int) $request->userId;
        $user = User::where('id', $userId)->where('owner_id', $ownerId)->first();
        if (! $user) {
            return Response::json(['message' => 'user not found'], 404);
        }

        $ledger = app(LedgerService::class);
        // Ledger is source of truth for the wallet cache. Never trust the SPA's
        // "currentBalance" — comparing it used to return 201 on every client
        // page visit and spam "تم تصحيح الرصيد" while rewriting wallets.
        $ledger->syncWalletFromLedger($ownerId, $userId);
        $systemBalance = $ledger->clientBalance($ownerId, $userId, '$');

        return Response::json([
            'balance' => $systemBalance,
            'message' => 'synced',
        ], 200);
    }

    public function updateTransactionDescription(Request $request)
    {
        $this->accounting->loadAccounts(Auth::user()->owner_id);

        $validated = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
            'description' => ['required', 'string', 'max:1000'],
        ]);

        $transaction = Transactions::find($validated['transaction_id']);

        if (!$transaction) {
            return Response::json(['message' => 'لم يتم العثور على الحركة المطلوبة'], 404);
        }

        $txUserId = $this->resolveTxUserId($transaction);
        $walletUser = $txUserId ? User::find($txUserId) : null;

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
     * Convert a main-box withdrawal (debt/out) into a cash-box expense assignment.
     */
    public function assignTransactionToWallet(Request $request)
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'integer', 'exists:transactions,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $this->accounting->loadAccounts(Auth::user()->owner_id);
        $mainBox = $this->accounting->mainBox();
        $vaults = app(\App\Services\VaultService::class);
        $mainBoxVaultId = $vaults->resolveVaultIdForLegacyUser((int) $mainBox->id);
        $mainBoxWalletId = $vaults->resolveWalletIdForLegacyUser((int) $mainBox->id);

        if (!$mainBoxVaultId && !$mainBoxWalletId) {
            return Response::json(['message' => 'لم يتم العثور على صندوق المحاسبة'], 422);
        }

        $transaction = Transactions::find($validated['transaction_id']);

        $isMainBoxTx = false;
        if ($mainBoxVaultId && (int) ($transaction->vault_id ?? 0) === (int) $mainBoxVaultId) {
            $isMainBoxTx = true;
        } elseif ($mainBoxWalletId && (int) ($transaction->wallet_id ?? 0) === (int) $mainBoxWalletId) {
            $isMainBoxTx = true;
        }

        if (!$transaction || ! $isMainBoxTx) {
            return Response::json(['message' => 'هذه الحركة ليست من صندوق المحاسبة'], 422);
        }

        if (!in_array($transaction->type, ['debt', 'out'], true)) {
            return Response::json(['message' => 'يمكن تحويل حركات السحب من الصندوق فقط'], 422);
        }

        if ((int) ($transaction->parent_id ?? 0) > 0) {
            return Response::json(['message' => 'لا يمكن تحويل حركة مرتبطة بحركة أخرى'], 422);
        }

        if (Transactions::where('parent_id', $transaction->id)->where('type', 'outUser')->exists()) {
            return Response::json(['message' => 'الحركة مرتبطة مسبقاً بقاصة'], 422);
        }

        $targetUser = User::query()
            ->where('id', $validated['user_id'])
            ->where('owner_id', Auth::user()->owner_id)
            ->first();

        if (!$targetUser) {
            return Response::json(['message' => 'القاصة المحددة غير موجودة'], 404);
        }

        // Target must be an active cash vault, never a trader.
        if (Schema::hasTable('vaults')) {
            $isVault = Vault::query()
                ->forOwner((int) Auth::user()->owner_id)
                ->where('legacy_user_id', (int) $targetUser->id)
                ->active()
                ->cashBoxes()
                ->exists();
            if (! $isVault) {
                return Response::json(['message' => 'الهدف يجب أن يكون قاصة نقدية — اختر من قائمة القاصات'], 422);
            }
        }

        if ((int) $targetUser->id === (int) $mainBox->id) {
            return Response::json(['message' => 'لا يمكن إسناد الحركة إلى الصندوق نفسه'], 422);
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
            $description = 'وصل سحب مباشر'.' '.'قاصة'.' '.$targetUser->name.($noteSuffix !== '' ? ' '.$noteSuffix : '');
            $morphedId = $targetUser->id;
            $childDetails = $existingDetails;
        }

        $originalCreatedAt = $transaction->created_at;
        $originalCreated = $transaction->created;
        $currentDate = $this->currentDate;

        DB::transaction(function () use ($transaction, $description, $morphedId, $childDetails) {
            // Keep single cash-box expense leg — do not mirror onto target vault.
            $transaction->type = 'outUserBox';
            $transaction->morphed_id = $morphedId;
            $transaction->morphed_type = User::class;
            $transaction->description = $description;
            if (!empty($childDetails)) {
                $transaction->details = $childDetails;
            }
            $transaction->save();
        });

        return Response::json([
            'message' => 'تم إسناد الحركة إلى القاصة بنجاح',
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

        $transaction = Transactions::find($validated['transaction_id']);

        if (!$transaction) {
            return Response::json(['message' => 'لم يتم العثور على الحركة المطلوبة'], 404);
        }

        $txUserId = $this->resolveTxUserId($transaction);
        $walletUser = $txUserId ? User::find($txUserId) : null;

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
        if (\Illuminate\Support\Facades\Schema::hasTable('company_treasury_entries')) {
            \App\Models\CompanyTreasuryEntry::where('owner_id', Auth::user()->owner_id)
                ->where('tag', $name)
                ->update(['tag' => null]);
        }
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
        $user = User::find($user_id);
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
        $mainBoxId = (int) $this->accounting->mainBox()->id;
        if ($amountDollar) {
            $transactiond = $this->debtWallet($amountDollar, $desc, $mainBoxId, $user_id, 'App\Models\User', 0, 0, '$', $date, 0, 'outUserBox');
            $transaction = Transactions::create($this->transactionAttrsForUser($mainBoxId, [
                'type' => 'outUser',
                'description' => $desc,
                'amount' => $amountDollar * -1,
                'is_pay' => 1,
                'morphed_id' => $user_id,
                'morphed_type' => 'App\Models\User',
                'user_added' => 0,
                'created' => $date,
                'discount' => 0,
                'currency' => '$',
                'parent_id' => $transactiond->id,
                'details' => $details,
            ]));
        }
        if ($amountDinar) {
            $transactionq = $this->debtWallet($amountDinar, $desc, $mainBoxId, $user_id, 'App\Models\User', 0, 0, 'IQD', $date, 0, 'outUserBox');
            $transaction = Transactions::create($this->transactionAttrsForUser($mainBoxId, [
                'type' => 'outUser',
                'description' => $desc,
                'amount' => $amountDinar * -1,
                'is_pay' => 1,
                'morphed_id' => $user_id,
                'morphed_type' => 'App\Models\User',
                'user_added' => 0,
                'created' => $date,
                'discount' => 0,
                'currency' => 'IQD',
                'parent_id' => $transactionq->id,
                'details' => $details,
            ]));
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
        $loanTran = Transactions::find($request->parent_id);
        if (!$loanTran || $loanTran->type !== 'outUser') {
            return Response::json(['message' => 'حركة القرض غير موجودة'], 404);
        }
        $details = is_array($loanTran->details) ? $loanTran->details : [];
        if (empty($details['loan'])) {
            return Response::json(['message' => 'هذه الحركة ليست قرضاً'], 400);
        }
        $txUserId = $this->resolveTxUserId($loanTran);
        // Loan rows are stored on mainBox vault with morphed client; prefer morph party.
        if ((int) ($loanTran->morphed_id ?? 0) > 0
            && in_array((string) $loanTran->morphed_type, [User::class, 'App\\Models\\User', 'App\Models\User'], true)) {
            $txUserId = (int) $loanTran->morphed_id;
        }
        $walletUser = $txUserId ? User::find($txUserId) : null;
        if (!$walletUser || $walletUser->owner_id !== Auth::user()->owner_id) {
            return Response::json(['message' => 'غير مصرح'], 403);
        }
        $user_id = $walletUser->id;
        $amountDollar = $request->amountDollar ?? 0;
        $amountDinar = $request->amountDinar ?? 0;
        if (!$amountDollar && !$amountDinar) {
            return Response::json(['errors' => ['amount' => ['المبلغ مطلوب']]], 422);
        }
        $date = $request->date ?: $this->currentDate;
        $driver_name = $details['driver_name'] ?? 'سائق';
        $desc = 'دفعة إرجاع قرض - ' . $driver_name;
        $transaction = null;
        $mainBoxId = (int) $this->accounting->mainBox()->id;
        if ($amountDollar) {
            $transactiond = $this->increaseWallet($amountDollar, $desc, $mainBoxId, $user_id, 'App\Models\User', 0, 0, '$', $date, 0, 'inUserBox', []);
            $transaction = Transactions::create($this->transactionAttrsForUser($mainBoxId, [
                'type' => 'inUser',
                'description' => $desc,
                'amount' => $amountDollar,
                'is_pay' => 1,
                'morphed_id' => $user_id,
                'morphed_type' => 'App\Models\User',
                'user_added' => 0,
                'created' => $date,
                'discount' => 0,
                'currency' => '$',
                'parent_id' => $loanTran->id,
                'details' => ['driver_name' => $driver_name],
            ]));
        }
        if ($amountDinar) {
            $transactionq = $this->increaseWallet($amountDinar, $desc, $mainBoxId, $user_id, 'App\Models\User', 0, 0, 'IQD', $date, 0, 'inUserBox', []);
            $transaction = Transactions::create($this->transactionAttrsForUser($mainBoxId, [
                'type' => 'inUser',
                'description' => $desc,
                'amount' => $amountDinar,
                'is_pay' => 1,
                'morphed_id' => $user_id,
                'morphed_type' => 'App\Models\User',
                'user_added' => 0,
                'created' => $date,
                'discount' => 0,
                'currency' => 'IQD',
                'parent_id' => $loanTran->id,
                'details' => ['driver_name' => $driver_name],
            ]));
        }
        return Response::json(['message' => 'تم تسجيل دفعة الإرجاع', 'transaction' => $transaction], 201);
    }

    public function increaseWallet(int $amount,$desc,$user_id,$morphed_id='',$morphed_type='',$is_pay=0,$discount=0,$currency='$',$created=0,$parent_id=0,$type='in',$details=[],$owner_id=null) 
    {
        $ownerId = $owner_id ?? Auth::user()->owner_id;
        $this->accounting->loadAccounts($ownerId);
        if(!$amount){
            return 0;
        }
        if($created==0){
            $created=$this->currentDate;
        }
        $user=  User::find($user_id);
        if(!$user){
            return null;
        }

        return DB::transaction(function () use ($amount, $desc, $user_id, $morphed_id, $morphed_type, $is_pay, $discount, $currency, $created, $parent_id, $type, $details, $ownerId, $user) {
            $transactionDetils = $this->transactionAttrsForUser((int) $user_id, [
                'type' => $type,
                'description'=>$desc,
                'amount'=>$amount,
                'is_pay'=>$is_pay,
                'morphed_id'=>$morphed_id,
                'morphed_type'=>$morphed_type,
                'user_added'=>0,
                'created'=>$created,
                'discount'=>$discount??0,
                'currency'=>$currency,
                'parent_id'=>$parent_id,
                'details'=>$details,
            ]);
            $transaction = Transactions::create($transactionDetils);

            // Child / mirror legs do not post journals — the root cash-box (or party) leg owns the entry.
            if ((int) $parent_id > 0) {
                return $transaction;
            }

            $ledger = app(LedgerService::class);
            $currencyNorm = $currency === 'IQD' ? 'IQD' : '$';
            $absAmount = abs((float) $amount);
            $disc = abs((float) ($discount ?? 0));

            $receiptsCashUserId = null;
            try {
                $receiptsCashUserId = app(\App\Services\VaultService::class)->receiptsCashUserId((int) $ownerId);
            } catch (\Throwable $e) {
                $receiptsCashUserId = null;
            }
            $isReceiptsVault = $receiptsCashUserId && (int) $user_id === (int) $receiptsCashUserId;
            $isClientPaymentOnCashBox = $type === 'in'
                && $this->isClientMorphed($morphed_id, $morphed_type)
                && (
                    $isReceiptsVault
                    || $ledger->walletPostingKind((int) $ownerId, (int) $user_id) === 'cash_box'
                );

            if ($isClientPaymentOnCashBox) {
                $journal = $ledger->postClientPayment(
                    (int) $ownerId,
                    (int) $morphed_id,
                    $absAmount,
                    $currencyNorm,
                    (string) $desc,
                    $transaction,
                    $disc,
                    (int) $user_id
                );
            } else {
                $journal = $ledger->postWalletIncrease(
                    (int) $ownerId,
                    (int) $user_id,
                    $absAmount,
                    $currencyNorm,
                    (string) $desc,
                    $transaction
                );
            }

            if (\Illuminate\Support\Facades\Schema::hasColumn('transactions', 'journal_entry_id')) {
                $transaction->forceFill(['journal_entry_id' => $journal->id])->save();
            }
            $ledger->syncWalletFromLedger((int) $ownerId, (int) $user_id);
            if ($isClientPaymentOnCashBox) {
                $ledger->syncWalletFromLedger((int) $ownerId, (int) $morphed_id);
            }

            return $transaction;
        });
    }

    public function decreaseWallet(int $amount,$desc,$user_id,$morphed_id=0,$morphed_type='',$is_pay=0,$discount=0,$currency='$',$created=0,$parent_id=0,$type='out',$details=[],$owner_id=null) 
    {
        $ownerId = $owner_id ?? Auth::user()->owner_id;
        $this->accounting->loadAccounts($ownerId);
        if(!$amount){
            return 0;
        }
        if($created==0){
            $created=$this->currentDate;
        }

        $user=  User::find($user_id);
        if(!$user){
            return null;
        }

        return DB::transaction(function () use ($amount, $desc, $user_id, $morphed_id, $morphed_type, $is_pay, $discount, $currency, $created, $parent_id, $type, $details, $ownerId, $user) {
            $transactionDetils = $this->transactionAttrsForUser((int) $user_id, [
                'type' => $type,
                'description'=>$desc,
                'amount'=>$amount*-1,
                'is_pay'=>$is_pay,
                'morphed_id'=>$morphed_id,
                'morphed_type'=>$morphed_type,
                'user_added'=>0,
                'created'=>$created,
                'discount'=>$discount??0,
                'currency'=>$currency,
                'parent_id'=>$parent_id,
                'details'=>$details,
            ]);
            $transaction =Transactions::create($transactionDetils);

            if ((int) $parent_id > 0) {
                return $transaction;
            }

            $ledger = app(LedgerService::class);
            $journal = $ledger->postWalletDecrease(
                (int) $ownerId,
                (int) $user_id,
                abs((float) $amount),
                $currency === 'IQD' ? 'IQD' : '$',
                (string) $desc,
                $transaction
            );
            if (\Illuminate\Support\Facades\Schema::hasColumn('transactions', 'journal_entry_id')) {
                $transaction->forceFill(['journal_entry_id' => $journal->id])->save();
            }
            $ledger->syncWalletFromLedger((int) $ownerId, (int) $user_id);

            return $transaction;
        });
    }
    public function debtWallet(int $amount,$desc,$user_id,$morphed_id=0,$morphed_type='',$is_pay=0,$discount=0,$currency='$',$created=0,$parent_id=0,$type='debt')  
    {
        $ownerId = Auth::user()->owner_id;
        $this->accounting->loadAccounts($ownerId);
        if(!$amount){
            return 0;
        }
        if($created==0){
            $created=$this->currentDate ;
        }
        $user=  User::find($user_id);
        if(!$user){
            return null;
        }

        return DB::transaction(function () use ($amount, $desc, $user_id, $morphed_id, $morphed_type, $is_pay, $discount, $currency, $created, $parent_id, $type, $ownerId, $user) {
            $transactionDetils = $this->transactionAttrsForUser((int) $user_id, [
                'type' => $type,
                'description'=>$desc,
                'amount'=>$amount*-1,
                'is_pay'=>$is_pay,
                'morphed_id'=>$morphed_id,
                'morphed_type'=>$morphed_type,
                'user_added'=>0,
                'created'=>$created,
                'discount'=>$discount??0,
                'currency'=>$currency,
                'parent_id'=>$parent_id,
            ]);
            $transaction = Transactions::create($transactionDetils);

            if ((int) $parent_id > 0) {
                return $transaction;
            }

            $ledger = app(LedgerService::class);
            $journal = $ledger->postWalletDecrease(
                (int) $ownerId,
                (int) $user_id,
                abs((float) $amount),
                $currency === 'IQD' ? 'IQD' : '$',
                (string) $desc,
                $transaction
            );
            if (\Illuminate\Support\Facades\Schema::hasColumn('transactions', 'journal_entry_id')) {
                $transaction->forceFill(['journal_entry_id' => $journal->id])->save();
            }
            $ledger->syncWalletFromLedger((int) $ownerId, (int) $user_id);

            return $transaction;
        });
    }

    /**
     * Build transaction attributes with vault_id and optional legacy wallet_id.
     * Does not create Wallet rows.
     *
     * @param  array<string, mixed>  $base
     * @return array<string, mixed>
     */
    protected function transactionAttrsForUser(int $userId, array $base): array
    {
        $vaults = app(\App\Services\VaultService::class);
        $vaultId = $vaults->resolveVaultIdForLegacyUser($userId);
        $walletId = $vaults->resolveWalletIdForLegacyUser($userId);

        if ($vaultId && \Illuminate\Support\Facades\Schema::hasColumn('transactions', 'vault_id')) {
            $base['vault_id'] = $vaultId;
        }
        if ($walletId) {
            $base['wallet_id'] = $walletId;
        } elseif (\Illuminate\Support\Facades\Schema::hasColumn('transactions', 'wallet_id')) {
            // Nullable after Phase 2 migration; omit if column still NOT NULL and no wallet.
            $base['wallet_id'] = null;
        }

        return $base;
    }

    /**
     * Whether morphed_* points at a client (trader) user — used to route cash-box
     * receipts as Cash/AR instead of Cash/Revenue.
     */
    protected function isClientMorphed($morphedId, $morphedType): bool
    {
        if (!$morphedId) {
            return false;
        }
        $isUser = $morphedType === User::class
            || $morphedType === 'App\\Models\\User'
            || $morphedType === 'App\Models\User';
        if (!$isUser) {
            return false;
        }

        $clientTypeId = (int) (\Illuminate\Support\Facades\Cache::get('user_type_client')
            ?? \App\Models\UserType::where('name', 'client')->value('id'));
        if (!$clientTypeId) {
            return false;
        }

        return User::where('id', (int) $morphedId)->where('type_id', $clientTypeId)->exists();
    }
 
    public function delTransactions(Request $request)
    {
        $this->accounting->loadAccounts(Auth::user()->owner_id);
        $owner_id = Auth::user()->owner_id;
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
            $ledger = app(LedgerService::class);
            $walletUserId = $this->resolveTxUserId($originalTransaction);
            if ($ledger->voidJournalForTransaction($originalTransaction, 'حذف أمانة #' . $originalTransaction->id)) {
                if ($walletUserId) {
                    $ledger->syncWalletFromLedger((int) $owner_id, (int) $walletUserId);
                }
            } else {
                $this->legacyReverseWalletMovement($originalTransaction);
            }
            $originalTransaction->delete();
            Log::info('Transaction deleted', ['transaction_id' => $transaction_id, 'by' => Auth::id()]);
            return response()->json(['message' => 'deleted'], 200);
        }

        try {
            $deleted = app(TransactionPaymentService::class)->softDeleteTransactionTree(
                (int) $transaction_id,
                (int) $owner_id,
                fn (Transactions $t) => $this->legacyReverseWalletMovement($t)
            );
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'Transaction not found'], 404);
        }

        return response()->json(['message' => $deleted->values()], 200);
    }

    /**
     * Restore a soft-deleted payment (transaction + journals + car.paid allocation).
     */
    public function restoreTransactions(RestoreTransactionRequest $request)
    {
        $this->accounting->loadAccounts(Auth::user()->owner_id);
        $ownerId = (int) Auth::user()->owner_id;
        $transactionId = (int) $request->validated()['id'];

        try {
            $restored = app(TransactionPaymentService::class)
                ->restoreTransaction($transactionId, $ownerId);
        } catch (ModelNotFoundException $e) {
            return response()->json(['message' => 'الحركة المحذوفة غير موجودة'], 404);
        }

        return response()->json([
            'message' => 'restored',
            'transaction' => $restored,
        ], 200);
    }

    /**
     * When deleting a payment that was applied to a car, undo car.paid / discount.
     * @deprecated Prefer TransactionPaymentService::reverseCarPaymentAllocation
     */
    protected function reverseCarPaymentAllocation(Transactions $transaction): void
    {
        app(TransactionPaymentService::class)->reverseCarPaymentAllocation($transaction);
    }

    /**
     * Reverse wallet effect for transactions created before ledger linking.
     */
    protected function legacyReverseWalletMovement(Transactions $transaction): void
    {
        if (! Schema::hasTable('wallets') || ! $transaction->wallet_id) {
            return;
        }

        $wallet = DB::table('wallets')->where('id', $transaction->wallet_id)->first();
        if (! $wallet) {
            return;
        }

        $col = $transaction->currency === 'IQD' ? 'balance_dinar' : 'balance';
        DB::table('wallets')->where('id', $transaction->wallet_id)->update([
            $col => (float) ($wallet->{$col} ?? 0) - (float) $transaction->amount,
            'updated_at' => now(),
        ]);
    }

    /**
     * Attach ledger balance / balance_dinar on user models for Accounting UI.
     *
     * @param  \Illuminate\Support\Collection<int, User>|iterable<User>  $users
     */
    protected function attachLedgerBalancesToUsers($users, int $ownerId): void
    {
        $ledger = app(LedgerService::class);
        foreach ($users as $user) {
            if (! $user || ! isset($user->id)) {
                continue;
            }
            try {
                $user->setAttribute('balance', $ledger->walletLedgerAccount($ownerId, (int) $user->id, '$')->balance('$'));
                $user->setAttribute('balance_dinar', $ledger->walletLedgerAccount($ownerId, (int) $user->id, 'IQD')->balance('IQD'));
            } catch (\Throwable $e) {
                $user->setAttribute('balance', 0);
                $user->setAttribute('balance_dinar', 0);
            }
        }
    }

    /**
     * Resolve user id for a transaction via vault_id / wallets / morph.
     */
    protected function resolveTxUserId(Transactions $transaction): ?int
    {
        if ($transaction->vault_id && Schema::hasTable('vaults')) {
            $legacy = Vault::query()->where('id', (int) $transaction->vault_id)->value('legacy_user_id');
            if ($legacy) {
                return (int) $legacy;
            }
        }
        if ($transaction->wallet_id && Schema::hasTable('wallets')) {
            $uid = DB::table('wallets')->where('id', $transaction->wallet_id)->value('user_id');
            if ($uid) {
                return (int) $uid;
            }
        }
        if ((int) ($transaction->morphed_id ?? 0) > 0
            && in_array((string) ($transaction->morphed_type ?? ''), [User::class, 'App\\Models\\User', 'App\Models\User'], true)) {
            return (int) $transaction->morphed_id;
        }

        return null;
    }

    /**
     * Query transactions belonging to a user (vault, legacy wallet, or morph).
     */
    protected function transactionsQueryForUser(User $user)
    {
        $vaultId = app(\App\Services\VaultService::class)->resolveVaultIdForLegacyUser((int) $user->id);
        $walletId = app(\App\Services\VaultService::class)->resolveWalletIdForLegacyUser((int) $user->id);
        $carIds = Car::where('client_id', $user->id)->pluck('id');

        return Transactions::with(['journalEntry.lines.account', 'parent.journalEntry.lines.account'])
            ->where(function ($q) use ($user, $vaultId, $walletId, $carIds) {
                if ($vaultId) {
                    $q->orWhere('vault_id', $vaultId);
                }
                if ($walletId) {
                    $q->orWhere('wallet_id', $walletId);
                }
                $q->orWhere(function ($inner) use ($user) {
                    $inner->whereIn('morphed_type', [User::class, 'App\\Models\\User', 'App\Models\User'])
                        ->where('morphed_id', $user->id);
                });
                if ($carIds->isNotEmpty()) {
                    $q->orWhere(function ($inner) use ($carIds) {
                        $inner->whereIn('morphed_type', [Car::class, 'App\\Models\\Car', 'App\Models\Car'])
                            ->whereIn('morphed_id', $carIds);
                    });
                }
            });
    }

    public function receiveCard(Request $request)
    {
        $authUser = auth()->user();
        $profile_id = $_GET['id'] ?? 0;
        $profile = Profile::find($profile_id);
        $user = User::find($profile->user_id);
        $percentage = $user->percentage ?? 0;

        if (! Schema::hasTable('wallets')) {
            try {
                DB::beginTransaction();
                $profile->update(['results' => 1, 'user_accepted' => $authUser->id]);
                $this->increaseWallet($percentage, ' نسبة على البطاقة رقم ' . $profile?->card_number, $user->id);
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                Log::warning('receiveCard failed', ['error' => $e->getMessage()]);
            }

            return Response::json(0, 200);
        }

        $wallet = DB::table('wallets')->where('user_id', $profile->user_id)->first();
        $old_card = $wallet->card ?? 0;

        try {
            DB::beginTransaction();
            $profile->update(['results' => 1, 'user_accepted' => $authUser->id]);
            $this->increaseWallet($percentage, ' نسبة على البطاقة رقم ' . $profile?->card_number, $user->id);
            if ($wallet) {
                DB::table('wallets')->where('id', $wallet->id)->update([
                    'card' => max(0, (int) $old_card - 1),
                    'updated_at' => now(),
                ]);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::warning('receiveCard failed', ['error' => $e->getMessage()]);
        }

        return Response::json($wallet->balance ?? 0, 200);
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