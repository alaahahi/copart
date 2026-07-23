<?php
   
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Wallet;
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
use App\Services\LedgerService;

class UserController extends Controller
{
    public function __construct(){
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
        $clients = User::with('wallet')->where('owner_id',$owner_id)->where('type_id', $this->userClient)->get();
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
        $page = request()->input('page', '');
        $print = request()->input('print', 0);
        $excludeZero = request()->input('exclude_zero', 0);


        $query = DB::table('users')
            ->select('users.id', 'users.name', 'users.phone', 'users.created_at', 'users.show_in_dashboard')
            ->selectSub(function ($subquery) use ($userClient) {
                $subquery->selectRaw('COUNT(id)')
                    ->from('car')
                    ->whereColumn('car.client_id', 'users.id')
                    ->whereNull('car.deleted_at');
            }, 'car_count')
            ->selectSub(function ($subquery) use ($userClient) {
                $subquery->selectRaw('COUNT(id)')
                    ->from('car')
                    ->whereColumn('car.client_id', 'users.id')
                    ->where('car.results', 2)
                    ->whereNull('car.deleted_at');
            }, 'car_count_completed')
            ->selectSub(function ($subquery) use ($userClient) {
                $subquery->selectRaw('COUNT(id)')
                    ->from('car')
                    ->whereColumn('car.client_id', 'users.id')
                    ->where('car.total_s', 0)
                    ->whereNull('car.deleted_at');
            }, 'car_total_un_pay')
            // قاسة / عرض بالمحاسبة → رصيد الدفتر؛ باقي الشاشات (الرئيسية، قائمة التجار) → متبقي السيارات
            ->selectSub(
                $q === 'show_in_dashboard'
                    ? LedgerService::clientBalanceSqlSubquery((int) $owner_id, '$')
                    : Car::clientRemainingBalanceSqlSubquery(),
                'balance'
            )
            ->where('users.owner_id', $owner_id)
            ->where('users.type_id', $userClient)

            ->orderBy('balance', 'desc');
    
            // Filter: clients flagged for accounting page (عرض بالمحاسبة / قاسة tab)
            if ($q === 'show_in_dashboard') {
                $query->where('users.show_in_dashboard', true);
            } elseif ($q && !in_array($q, ['debit', 'box_movement'], true)) {
                $query->leftJoin('car', 'users.id', '=', 'car.client_id')
                    ->where(function ($subQuery) use ($q) {
                        $subQuery->where('users.name', 'like', '%' . $q . '%')
                            ->orWhere('users.phone', 'like', '%' . $q . '%')
                            ->orWhere(function ($carQuery) use ($q) {
                                $carQuery->where('car.vin', 'like', '%' . $q . '%')
                                    ->orWhere('car.car_number', 'like', '%' . $q . '%');
                            });
                    });
                $query->groupBy('users.id', 'users.name', 'users.phone', 'users.created_at', 'users.show_in_dashboard');
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
        if($print==1)
        {
            $config=SystemConfig::first();

            if($q=='debit'){
                if ($excludeZero == 1) {
                    // عرض المدين والدائن فقط (balance != 0)
                    $data = $query->havingRaw('balance != 0')->get();
                } else {
                    // السلوك القديم: عرض المدين فقط (balance > 0)
                    $data = $query->havingRaw('balance > 0')->get();
                }
            }else{
                if ($excludeZero == 1) {
                    // استبعاد الرصيد = 0
                    $data = $query->havingRaw('balance != 0')->get();
                } else {
                    // السلوك القديم: عرض جميع العملاء
                    $data = $query->get();
                }
            }
            $data=$data->toArray();
            return view('reportClients',compact('data','config','owner_id'));

        }
        if (in_array($q, ['debit', 'box_movement', 'show_in_dashboard'], true)) {
            if ($page == 1) {
                if ($q == 'debit') {
                    if ($excludeZero == 1) {
                        $data = $query->havingRaw('balance != 0')->get();
                    } else {
                        $data = $query->havingRaw('balance > 0')->get();
                    }
                } else {
                    $data = $query->get();
                }
                return response()->json(['data' => $data], 200);
            } else {
                return response()->json(['data' => []], 200);
            }
        } else {
            $paginationLimit = 25;
            if ($excludeZero == 1) {
                // إذا كان exclude_zero=1، استبعاد الرصيد = 0
                $data = $query->havingRaw('balance != 0')->paginate($paginationLimit);
            } else {
                // السلوك القديم: عرض جميع العملاء
                $data = $query->paginate($paginationLimit);
            }
            return response()->json($data, 200);
        }
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
  
                Wallet::create(['user_id' => $user->id]);
     
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
            // عرض بالمحاسبة (قاسة) — hidden from accounting by default until explicitly enabled.
            'show_in_dashboard' => $request->boolean('show_in_dashboard'),
        ]);

        Wallet::create(['user_id' => $user->id]);

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
            // عرض بالمحاسبة (قاسة) — editable from the edit modal.
            'show_in_dashboard' => $request->boolean('show_in_dashboard'),
        ]);

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

        // show_in_dashboard controls visibility on Accounting page (عرض بالمحاسبة)
        return response()->json([
            'message' => 'تم تحديث عرض التاجر في صفحة المحاسبة',
            'show_in_dashboard' => (bool) $client->show_in_dashboard,
        ], 200);
    }
    public function delClient(Request $request)
    {
    // Find the client
    $client = User::with('wallet')->where('id', $request->id)->first();

    if ($client) {
        // Get related transactions
        $transactions = Transactions::where('wallet_id', $client->wallet->id)->get();

        // Get related cars
        $cars = Car::where('client_id', $client->id)->get();

        // Delete transactions
        $transactions->each(function ($transaction) {
            $transaction->delete();
        });

        // Delete cars
        $cars->each(function ($car) {
            $car->delete();
        });

        // Delete the client
        $client->delete();

        return response()->json(['message' => 'Client and related records deleted'], 200);
    }

    return response()->json(['message' => 'Client not found'], 404);
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
     
       // User::where('parent_id',$id)->update(['parent_id' =>null]);
        User::find($id)->delete();
     
        return Inertia::render('Users/Index', ['url'=>$this->url]); 
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