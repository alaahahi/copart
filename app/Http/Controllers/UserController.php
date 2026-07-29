<?php
   
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
 use App\Models\Car;
use App\Models\SystemConfig;

use App\Models\UserType;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\StoreClientRequest;
use App\Http\Requests\UpdateClientRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use App\Models\Massage;
use Carbon\Carbon;
use App\Models\Transactions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Services\ClientAccountService;
use App\Services\LedgerService;
use App\Services\SystemWalletService;
use App\Services\VaultService;
use App\Services\AccountingCacheService;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function __construct(
        protected ClientAccountService $clientAccounts
    ) {
         $this->url = env('FRONTEND_URL');
         $this->userAdmin =  UserType::where('name', 'admin')->first()->id;
         $this->selesKirkuk =  UserType::where('name', 'selesKirkuk')->first()->id ?? 0;
         $this->userAccount =  UserType::where('name', 'account')->first()->id;
         $this->car_expenses =  UserType::where('name', 'car_expenses')->first()->id ??0;
         $this->userClient =  UserType::where('name', 'client')->first()->id;

    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function index()
    {
        return Inertia::render('Users/Index');
    }

    public function clients()
    {
        return Inertia::render('Clients/Index', ['url'=>$this->url]);
    }
    public function showClients($id)
    {
        $owner_id=Auth::user()->owner_id;
        $q = request()->query('q');
        // Real traders only — hide system / commission vaults (عمولة …) from account switcher
        $clientsQuery = User::query()
            ->where('owner_id', $owner_id)
            ->where('type_id', $this->userClient);
        SystemWalletService::scopeExcludeSystemVaults($clientsQuery);
        $clients = $clientsQuery->orderBy('name')->get();
        $ledger = app(LedgerService::class);
        foreach ($clients as $c) {
            try {
                $c->setAttribute('balance', $ledger->walletLedgerAccount((int) $owner_id, (int) $c->id, '$')->balance('$'));
            } catch (\Throwable $e) {
                $c->setAttribute('balance', 0);
            }
        }
        $client= user::find($id);
        $auctions = \App\Models\Auction::where('owner_id', $owner_id)->orderBy('name')->get(['id', 'name']);
        return Inertia::render('Clients/Show', ['url'=>$this->url,'client'=>$client,'clients'=>$clients,'client_id'=>$id,'q'=>$q,'auctions'=>$auctions]);
    }
    public function show ()
    {
        return Inertia::render('Users/Index', ['url'=>$this->url]);
    }
    public function getIndex()
    {
        $data = User::with('userType:id,name','wallet')->whereIn('type_id', [$this->selesKirkuk,$this->car_expenses])->paginate(10);
        return Response::json($data, 200);
    }
    public function getIndexClients()
    {

        $q = request()->input('q', '');
        $from = request()->input('from', 0);
        $to = request()->input('to', 0);
        $owner_id = Auth::user()->owner_id;
        $userClient = $this->userClient ?? 0;
        $userAccount = $this->userAccount ?? 0;
        $page = request()->input('page', '');
        $print = request()->input('print', 0);
        $excludeZero = request()->input('exclude_zero', 0);

        // Tab filter keys (Clients Index)
        // traders          → التجار (clients excluding system vaults)
        // traders_qasa     → تجار بعرض محاسبة (CLIENT_HAS_QASA_FLAG, not system vault)
        // system_qasa      → قاصات النظام (vaults table)
        // show_in_dashboard→ legacy alias for traders_qasa
        $isTradersQasa = in_array($q, ['traders_qasa', 'show_in_dashboard'], true);
        $isSystemQasa = $q === 'system_qasa';
        $isTraders = $q === 'traders';
        $useQasaBalance = $isTradersQasa || $isSystemQasa;

        // قاصات النظام → dedicated vaults table (not fake trader users)
        if ($isSystemQasa) {
            if ((int) $page > 1 && (int) $print !== 1) {
                return response()->json(['data' => []], 200);
            }

            $vaultRows = app(VaultService::class)->systemQasaClientRows((int) $owner_id);

            if ((int) $print === 1) {
                $config = SystemConfig::first();
                $data = $vaultRows->toArray();

                return view('reportClients', compact('data', 'config', 'owner_id'));
            }

            return response()->json(['data' => $vaultRows->values()], 200);
        }

        $query = DB::table('users')
            ->select(
                'users.id',
                'users.name',
                'users.phone',
                'users.email',
                'users.type_id',
                'users.created_at',
                'users.show_in_dashboard'
            )
            ->selectSub(function ($subquery) {
                $subquery->selectRaw('COUNT(id)')
                    ->from('car')
                    ->whereColumn('car.client_id', 'users.id')
                    ->whereNull('car.deleted_at');
            }, 'car_count')
            ->selectSub(function ($subquery) {
                $subquery->selectRaw('COUNT(id)')
                    ->from('car')
                    ->whereColumn('car.client_id', 'users.id')
                    ->where('car.results', 2)
                    ->whereNull('car.deleted_at');
            }, 'car_count_completed')
            ->selectSub(function ($subquery) {
                $subquery->selectRaw('COUNT(id)')
                    ->from('car')
                    ->whereColumn('car.client_id', 'users.id')
                    ->where('car.total_s', 0)
                    ->whereNull('car.deleted_at');
            }, 'car_total_un_pay')
            // تجار بقاصة → رصيد الدفتر؛ الباقي → متبقي السيارات
            ->selectSub(
                $useQasaBalance
                    ? LedgerService::clientBalanceSqlSubquery((int) $owner_id, '$')
                    : Car::clientRemainingBalanceSqlSubquery(),
                'balance'
            )
            ->where('users.owner_id', $owner_id)
            ->whereNull('users.deleted_at')
            ->orderByDesc('balance');

        // Merchant lists (all / traders / traders_qasa): never mix in system/commission vaults
        $query->where('users.type_id', $userClient);
        SystemWalletService::scopeExcludeSystemVaults($query);

        if ($isTradersQasa) {
            // تجار بعرض محاسبة فقط (ليس قاصة نظام — القاصة في جدول vaults)
            $query->where('users.show_in_dashboard', true);
        }

        // Free-text / category filters (not used by special tab keys)
        if ($q && ! in_array($q, ['debit', 'box_movement', 'traders', 'traders_qasa', 'system_qasa', 'show_in_dashboard'], true)) {
            $query->leftJoin('car', 'users.id', '=', 'car.client_id')
                ->where(function ($subQuery) use ($q) {
                    $subQuery->where('users.name', 'like', '%' . $q . '%')
                        ->orWhere('users.phone', 'like', '%' . $q . '%')
                        ->orWhere(function ($carQuery) use ($q) {
                            $carQuery->where('car.vin', 'like', '%' . $q . '%')
                                ->orWhere('car.car_number', 'like', '%' . $q . '%');
                        });
                });
            $query->groupBy(
                'users.id',
                'users.name',
                'users.phone',
                'users.email',
                'users.type_id',
                'users.created_at',
                'users.show_in_dashboard'
            );
        }

        if ($q === 'box_movement') {
            $query->whereExists(function ($subQuery) use ($from, $to) {
                $subQuery->select(DB::raw(1))
                    ->from('transactions')
                    ->whereColumn('transactions.morphed_id', 'users.id')
                    ->where('transactions.morphed_type', 'App\\Models\\User')
                    ->whereIn('transactions.type', ['inUserBox', 'outUserBox'])
                    ->whereNull('transactions.deleted_at');

                if ($from && $to) {
                    $subQuery->whereBetween('transactions.created_at', [$from, $to]);
                }
            });
        }

        if ($from && $to && $q !== 'box_movement') {
            $query->whereBetween('users.created_at', [$from, $to]);
        }

        // SQLite rejects HAVING on non-aggregate queries — wrap + WHERE on balance.
        $applyBalanceFilter = fn ($builder, bool $excludeZeroFlag) => Car::filterClientsByBalance(
            $builder,
            $excludeZeroFlag ? 'neq' : 'gt'
        );

        if ($print == 1) {
            $config = SystemConfig::first();

            if ($q == 'debit' || (int) $excludeZero === 1) {
                $data = $applyBalanceFilter($query, (int) $excludeZero === 1)->get();
            } else {
                $data = $query->get();
            }
            $data = $data->toArray();

            return view('reportClients', compact('data', 'config', 'owner_id'));
        }

        $fullListKeys = ['debit', 'box_movement', 'traders_qasa', 'show_in_dashboard'];
        if (in_array($q, $fullListKeys, true)) {
            // page>1 returns [] so infinite-scroll clients list can stop after first fetch
            if ((int) $page === 1) {
                if ($q == 'debit') {
                    $data = $applyBalanceFilter($query, (int) $excludeZero === 1)->get();
                } else {
                    $data = $query->get();
                }

                $data = $this->attachClientDeleteFlags($data, (int) $userAccount);

                return response()->json(['data' => $data], 200);
            }

            return response()->json(['data' => []], 200);
        }

        $paginationLimit = 25;
        if ((int) $excludeZero === 1) {
            $data = $applyBalanceFilter($query, true)->paginate($paginationLimit);
        } else {
            $data = $query->paginate($paginationLimit);
        }

        $data->setCollection(
            $this->attachClientDeleteFlags($data->getCollection(), (int) $userAccount)
        );

        return response()->json($data, 200);
    }

    /**
     * Attach has_movements / can_delete so the UI can hide trash on vaults with history.
     * System vaults: deletable only with zero movements. Merchants: deletable only with zero cars.
     *
     * @param  \Illuminate\Support\Collection|array  $rows
     * @return \Illuminate\Support\Collection
     */
    protected function attachClientDeleteFlags($rows, int $accountTypeId)
    {
        $collection = collect($rows);
        $movementIds = array_fill_keys(
            SystemWalletService::idsWithMovements($collection->pluck('id')->all()),
            true
        );

        return $collection->map(function ($row) use ($movementIds, $accountTypeId) {
            $row = is_array($row) ? (object) $row : $row;
            $hasMovements = isset($movementIds[(int) $row->id]);
            $isSystemVault = SystemWalletService::isSystemVaultUser($row, $accountTypeId);

            $row->has_movements = $hasMovements;
            $row->can_delete = $isSystemVault
                ? ! $hasMovements
                : ((int) ($row->car_count ?? 0) === 0);

            return $row;
        })->values();
    }

    public function create()
    {
        $usersType = UserType::all();
        return Inertia::render('Users/Create',['usersType'=>$usersType]);
    }
    public function store(Request $request)
    {
        Validator::make($request->all(), [
            'name' => 'required|string|max:255|unique:users',
           ])->validate();
        $user = User::create([
                    'name' => $request->name,
                    'type_id' => $request->userType,
                    'email' => $request->email,
                    'created' =>Carbon::now()->format('Y-m-d'),
                    'password' => Hash::make($request->password),
                    'phone' => $request->phone
                ]);
     
        return Inertia::render('Users/Index', ['url'=>$this->url]);
    }
    public function clientsStore(StoreClientRequest $request)
    {
        $validated = $request->validated();
        $year_date = Carbon::now()->format('Y');

        $owner_id = Auth::user()->owner_id;
        //$userChief_id =User::where('type_id',  $this->userChief)->first()->id ?? 0 ;
        $user = User::create([
            'name' => $validated['name'],
            'type_id' => $this->userClient,
            'phone' => $validated['phone'] ?? null,
            'year_date' => $year_date,
            'owner_id' => $owner_id,
            'created' => Carbon::now()->format('Y-m-d'),
            // show_in_dashboard = عرض بالمحاسبة (AR shortcuts) — ليس قاصة نظام
            'show_in_dashboard' => $request->boolean('show_in_dashboard'),
        ]);

        // Always AR 1200-{id}; if show_in_dashboard also 1210/1220 — linked via party_id.
        // System vaults live in `vaults` table — never auto-created for traders.
        $this->clientAccounts->provisionForClient($user);

        return Response::json($user, 200);
    }
    public function clientsEdit(UpdateClientRequest $request)
    {
        $validated = $request->validated();
        $owner_id = Auth::user()->owner_id;

        $client = User::where('id', $validated['id'])
            ->where('owner_id', $owner_id)
            ->first();

        if (!$client) {
            return Response::json(['message' => 'التاجر غير موجود'], 404);
        }

        $client->update([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? null,
            // show_in_dashboard = عرض بالمحاسبة (LedgerService custody) — ليس قاصة نظام
            'show_in_dashboard' => $request->boolean('show_in_dashboard'),
        ]);

        // If عرض بالمحاسبة turned on: create missing custody accounts. Off: keep history (no delete).
        $this->clientAccounts->syncQasaFlag($client->fresh());

        return Response::json($client, 200);
    }

    public function toggleShowInDashboard(Request $request)
    {
        $validated = Validator::make($request->all(), [
            'client_id' => 'required|integer|exists:users,id',
            'show_in_dashboard' => 'required|boolean',
        ])->validate();

        $client = User::where('id', $validated['client_id'])
            ->where('owner_id', Auth::user()->owner_id)
            ->firstOrFail();

        $client->show_in_dashboard = $validated['show_in_dashboard'];
        $client->save();

        // عرض بالمحاسبة on → ensure 1210/1220 custody exist; off → do not delete ledger history
        $this->clientAccounts->syncQasaFlag($client);

        // show_in_dashboard = عرض بالمحاسبة للتاجر — قاصات النظام في جدول vaults
        return response()->json([
            'message' => 'تم تحديث عرض التاجر في صفحة المحاسبة',
            'show_in_dashboard' => (bool) $client->show_in_dashboard,
        ], 200);
    }
    public function delClient(Request $request)
    {
        $ownerId = (int) Auth::user()->owner_id;
        $client = User::with('wallet')
            ->where('id', $request->id)
            ->where('owner_id', $ownerId)
            ->first();

        if (! $client) {
            return response()->json(['message' => 'Client not found'], 404);
        }

        $isSystemVault = SystemWalletService::isSystemVaultUser($client, (int) $this->userAccount);

        // Defense in depth: never soft-delete a system vault that still has movements.
        if ($isSystemVault && SystemWalletService::vaultHasMovements($client)) {
            return response()->json([
                'message' => 'لا يمكن حذف القاصة لأنها تحتوي على حركات مالية',
            ], 422);
        }

        $snapshot = [
            'id' => $client->id,
            'name' => $client->name,
            'email' => $client->email,
            'type_id' => $client->type_id,
            'owner_id' => $client->owner_id,
            'is_system_vault' => $isSystemVault,
        ];

        DB::transaction(function () use ($client, $isSystemVault, $ownerId, $snapshot) {
            // Merchants: soft-delete related cars/transactions. System vaults keep ledger history.
            if (! $isSystemVault) {
                $vaultId = app(VaultService::class)->resolveVaultIdForLegacyUser((int) $client->id);
                Transactions::query()
                    ->where(function ($q) use ($client, $vaultId) {
                        if ($vaultId) {
                            $q->orWhere('vault_id', $vaultId);
                        }
                        $q->orWhere(function ($inner) use ($client) {
                            $inner->whereIn('morphed_type', [User::class, 'App\\Models\\User', 'App\Models\User'])
                                ->where('morphed_id', $client->id);
                        });
                    })
                    ->get()
                    ->each
                    ->delete();
                Car::where('client_id', $client->id)->get()->each->delete();
            }

            $client->delete();

            Log::info('User soft-deleted', array_merge($snapshot, [
                'deleted_by' => Auth::id(),
                'deleted_at' => now()->toDateTimeString(),
            ]));

            if ($isSystemVault) {
                app(AccountingCacheService::class)->forgetOwnerAccounts($ownerId);
            }
        });

        return response()->json([
            'message' => $isSystemVault
                ? 'تم حذف القاصة ولن تُعاد تلقائياً'
                : 'Client and related records deleted',
        ], 200);
    }
    public function getCoordinator(Request $request)
    {
        $user =User::where('type_id', $request->id);
        return Response::json(['status' => 200,'massage' => 'users found','data' => $user->get()],200);
    }
    
    
    /**
     * Write code on Method
     *
     * @return response()
     */
    public function edit(User $User)
    {
        $usersType = UserType::all();
        $user = User::find($User->id);
        return Inertia::render('Users/Edit', ['usersType'=>$usersType,'user'=>$user]);
    }
    
    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function update($id, Request $request)
    {
        $username = User::where('id', $id)->first()->email;
        switch ($username) {
            case $request->email:
                if ($request->password) {
                    $request->validate([
                        'name' => 'required|string|max:255',
                        'password' => [Rules\Password::defaults()],
                    ]);
                    $user = User::find($id)->update([
                        'name' => $request->name,
                        'password' => Hash::make($request->password),
                        'percentage' => $request->percentage
                    ]);
                } else {
                    $request->validate([
                        'name' => 'required|string|max:255',
                    ]);
                    $user = User::find($id)->update([
                        'name' => $request->name,
                        'percentage' => $request->percentage
                    ]);
                }
                break;
                
            default:
                if ($request->password) {
                    $request->validate([
                        'name' => 'required|string|max:255',
                        'email' => 'required|string|max:255|unique:users',
                    ]);
                    $user = User::find($id)->update([
                        'name' => $request->name,
                        'email' => $request->email,
                    ]);
                } else {
                    $request->validate([
                        'name' => 'required|string|max:255',
                        'email' => 'required|string|max:255|unique:users',
                        'password' => [Rules\Password::defaults()],
                    ]);
                    $user = User::find($id)->update([
                        'name' => $request->name,
                        'email' => $request->email,
                        'password' => Hash::make($request->password),
                    ]);
                }
                break;
        }
        
        return Inertia::render('Users/Index', ['url'=>$this->url]);

    }
    
    
    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function destroy($id)
    {
        $user = User::find($id);
        if ($user) {
            $snapshot = $user->only(['id', 'name', 'email', 'type_id', 'owner_id']);
            $user->delete();
            Log::info('User soft-deleted', array_merge($snapshot, [
                'deleted_by' => Auth::id(),
                'deleted_at' => now()->toDateTimeString(),
                'via' => 'destroy',
            ]));
            if (SystemWalletService::isSystemVaultUser((object) $snapshot, (int) $this->userAccount)) {
                app(AccountingCacheService::class)->forgetOwnerAccounts((int) ($snapshot['owner_id'] ?? 0));
            }
        }

        return Inertia::render('Users/Index', ['url' => $this->url]);
    }
    public function ban($id)
    {
        User::find($id)->update(['is_band' => 1]);
        return Inertia::render('Users/Index', ['url'=>$this->url]); 
    }
    public function unban($id)
    {
        User::find($id)->update(['is_band' => 0]);
        return Inertia::render('Users/Index', ['url'=>$this->url]); 
    }
    public function login(LoginRequest $request)
    {
        try {
             $request->authenticate();
             $user =User::where('email', $request->email)->first();
             $publickey_receiver =  User::find($user->parent_id)->public_key ?? 0;
             if( $user->device){
                $request->device = $user->device.' | '.$request->device;
             }
             $user->append(['token']);
             if(!$user->is_band){
                if( $user->type_id == $this->userChief){
                    if($request->public_key){
                        $user->update(['public_key' => $request->public_key,'device' =>  $request->device,'publickey_receiver'=> $publickey_receiver]);
                    }
                    return Response::json(['status' => 200,'massage' => 'user found','data' => $user,'token'=> Crypt::encryptString($user->first()->id)],200); 
                }else{
                    if($publickey_receiver){
                    if($request->public_key){
                        $user->update(['public_key' => $request->public_key,'device' => $request->device,'publickey_receiver'=> $publickey_receiver]);
                    }
                       return Response::json(['status' => 200,'massage' => 'user found','data' => $user,'token'=> Crypt::encryptString($user->first()->id)],200); 
                    }else
                    return Response::json(['status' => 407,'massage' => 'user found but publickey for parent notfound'],407); 

                }
             }
             else  return Response::json(['status' => 403,'massage' => 'user is band'],403);
            
             //else  return Response::json(['status' => 407,'massage' => 'user parent dont have public key'],407);
        } catch (\Throwable $th) {
              return   Response::json(['status' => 400,'massage' => 'user not found','error' =>  $th ],400);
        }
        
    }


  
 
    
    public function Authorization($request){
        $token = substr($request->header('Authorization') ,7);;
        try {
            $id = Crypt::decryptString($token) ;
        $authUser = User::where('id', $id) ? User::where('id', $id)->first() :0;
        if($authUser && !$authUser->is_band){
           return $authUser;
        }
        else
        return  Response::json(['status' => 401,'massage' => 'user not Authorize'],401);
        } catch (\Throwable $th) {
            return  Response::json(['status' => 401,'massage' => 'user not Authorize'],401);
        }
        }
    }