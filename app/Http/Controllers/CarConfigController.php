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
use App\Models\CarModel;
use App\Models\Color;
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





class CarConfigController extends Controller
{
    public function __construct(){
        $this->url = env('FRONTEND_URL');
        $this->userAdmin =  UserType::where('name', 'admin')->first()->id;
        $this->userClient =  UserType::where('name', 'client')->first()->id;
        $this->userAccount =  UserType::where('name', 'account')->first()->id;
         
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function index()
    {   
       $company = Company::all();
       return Inertia::render('CarConfig/Index', ['url'=>$this->url]);
          
    }
    public function showCar()
    {   
        $car_id =$_GET['car_id'] ?? 0;
        $data =  Car::with('carmodel')->with('name')->with('user')->with('color')->with('company')->with('client')->with('transactions')->where('id',$car_id)->first();
        return Response::json($data, 200);    
    }
    public function companyEdit($id)
    {
        $data = Profile::where('id',$id)->first();
        return Inertia::render('CarConfig/Edit', ['url'=>$this->url,'data'=> $data]);
    }
    
     
    public function saved()
    {
        try {
            $authUser = auth()?->user();
            if($authUser){
                $users = User::where('type_id', $this->userSeles)->get();
                return Inertia::render('FormRegistrationSaved', ['url'=>$this->url,'users'=>$users]);
            }
            else {
                return Inertia::render('Auth/Login');
            }
        } catch (\Throwable $th) {
            return Inertia::render('Auth/Login');
        }

    }
    public function court()
    {
        try {
            $authUser = auth()?->user();
            if($authUser){
            $users = User::where('type_id', $this->userSeles)->get();
            return Inertia::render('FormRegistrationCourt', ['url'=>$this->url,'users'=>$users]);
            }
            else {
                return Inertia::render('Auth/Login');
            }
        } catch (\Throwable $th) {
            return Inertia::render('Auth/Login');
        }

    }
    public function completed()
    {
        try {
            $authUser = auth()?->user();
            if($authUser){
                $users = User::where('type_id', $this->userSeles)->get();
                return Inertia::render('FormRegistrationCompleted', ['url'=>$this->url,'users'=>$users]);
            }
            else {
                return Inertia::render('Auth/Login');
            }
        } catch (\Throwable $th) {
            return Inertia::render('Auth/Login');
        }


    }
    public function show ()
    {
        try {
            $authUser = auth()?->user();
            if($authUser){
                return Inertia::render('Users/Index', ['url'=>$this->url]);
            }
            else {
                return Inertia::render('Auth/Login');
            }
        } catch (\Throwable $th) {
            return Inertia::render('Auth/Login');
        }

    }
    public function getIndex()
    {
        $data = Company::paginate(10);
        return Response::json($data, 200);
    }
    public function getIndexName()
    {
        $company_id = $_GET['company_id'] ?? 0;
        if($company_id)
        {
            $data = Name::with('company')->where('company_id',$company_id)->paginate(10);
            return Response::json($data, 200);

        }

        $data = Name::with('company')->paginate(10);
        return Response::json($data, 200);
    }
    public function getIndexModel()
    {
        $data = CarModel::paginate(10);
        return Response::json($data, 200);
    }
    public function getIndexColor()
    {
        $data = Color::paginate(10);
        return Response::json($data, 200);
    }
    public function getIndexSaved()
    {
        $data = Profile::with('user')->orderBy('no', 'DESC')->paginate(10);
        return Response::json($data, 200);
    }
    public function getIndexAccountsSelas()
    { 
        $user_id = $_GET['user_id'] ?? 0;
        $sales = User::with('wallet')->where('id', $user_id)->first();
        $transactions = Transactions ::where('wallet_id', $sales?->wallet?->id);

        $data = $transactions->paginate(10);
        $profile_count = Profile::where('user_id', $sales?->id)->where('results',1)->count();
        // Additional logic to retrieve sales data
        $salesData = [
            'totalAmount' =>  $transactions->sum('amount'),
            'count' => $profile_count,
            'total_sales' => $data?->total(),
            'current_page' => $data?->currentPage(),
            'per_page' => $data?->perPage(),
            'last_page' => $data?->lastPage(),
            'data' => $data?->items(),
            'sales'=>$sales,
            'date'=> Carbon::now()->format('Y-m-d')
        ];
        return Response::json($salesData, 200);
    }
    public function getIndexCompleted()
    {

        $user_id = $_GET['user_id'] ?? 0;
        if($user_id){
            $data = Profile::with('user')->where('user_id',$user_id)->where('results',0)->orderBy('no', 'DESC')->paginate(10);
        }else{
            $data = Profile::with('user')->orderBy('no', 'DESC')->where('results',0)->paginate(10);
        }
        return Response::json($data, 200);
    }
    public function companyDel($id)
    {
        Company::find($id)->delete();
        return Response::json('ok', 200);
    }
    public function delName($id)
    {
        Name::find($id)->delete();
        return Response::json('ok', 200);
    }
    public function delModel($id)
    {
        CarModel::find($id)->delete();
        return Response::json('ok', 200);
    }
    public function delColor($id)
    {
        Color::find($id)->delete();
        return Response::json('ok', 200);
    }
    public function create()
    {
        return Inertia::render('CarConfig/Add', ['url'=>$this->url]);
    }
    
    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        Validator::make($request->all(), [
                    'name' => 'required|string|max:255',
                    'nameEn' => 'string|max:255',
                     ])->validate();
                $user = Company::create([
                    'name' => $request->name,
                    'name_en'=> $request->nameEn,
                     ]);
        $company = Company::all();
        return Inertia::render('CarConfig/Index', ['url'=>$this->url]);
    }
    public function storeName(Request $request)
    {
    Name::updateOrCreate(['id' => $_GET['id']],[
                    'company_id' =>$_GET['company_id'],
                    'name'=> $_GET['name'],
                    'name_en'=>$_GET['name_en'],
                     ]);
       return Response::json('ok', 200);    
    }
    public function storeCarModel(Request $request)
    {
                    CarModel::updateOrCreate(['id' => $_GET['id']],[
                    'name'=> $_GET['name'],
                     ]);
       return Response::json('ok', 200);    
    }
    public function storeColor(Request $request)
    {
                    Color::updateOrCreate(['id' => $_GET['id']],[
                    'name'=> $_GET['name'],
                    'name_en'=>$_GET['name_en'],
                     ]);
       return Response::json('ok', 200);    
    }

    public function storeEdit(Request $request,$id)
    {
        $user = Auth::user();
        Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:255',
            'phone_number' => 'required|string|max:255',
            'invoice_number' => 'required|string|max:255',
                     ])->validate();
                Profile::where('id',$id)->update([
                    'card_number'=> $request->card_number,
                    'name' => $request->name,
                    'birthdate' => $request->birthdate,
                    'certification' => $request->certification,
                    'job' => $request->job,
                    'address' => $request->address,
                    'phone_number' => $request->phone_number,
                    'invoice_number' => $request->invoice_number,
                    'relatives' => $request->relatives,
                    'family_name'=> $request->family_name,
                     ]);
            
        return Inertia::render('CarConfig/Index', ['url'=>$this->url]);
    }
    public function labResults($id){
        $profile=Profile::where('id',$id)->first();
        
        return Inertia::render('CarConfig/AddlabResults', ['url'=>$this->url,'profile_id'=>$id,'profile'=>$profile]);
    }
    public function labResultsEdit($id){
        $profile=Profile::where('id',$id)->first();
        $data = Results::where('profile_id',$id)->latest()->first();
        return Inertia::render('CarConfig/EditlabResults', ['url'=>$this->url,'profile_id'=>$id,'profile'=>$profile,'data'=>$data]);
    }
    
    public function doctorResults($id){
        $profiles=Profile::where('id',$id)->first();
        $profile = Results::where('profile_id',$id)->latest()->first();
        return Inertia::render('CarConfig/AddDoctorResults', ['url'=>$this->url,'is_doctor'=>true,'profile'=>$profile ,'profile_id'=>$id,'profiles'=>$profiles]);
    }
    public function doctorResultsEdit($id){
        $profiles=Profile::where('id',$id)->first();
        $profile = Results::where('profile_id',$id)->latest()->first();
        $data = DoctorResults::where('profile_id',$id)->latest()->first();
        return Inertia::render('CarConfig/EditDoctorResults', ['url'=>$this->url,'is_doctor'=>true,'profile'=>$profile ,'profile_id'=>$id,'profiles'=>$profiles,'data'=>$data]);
    }
    

    public function Authorization($request){
        $token = substr($request->header('Authorization') ,7);
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

        public function document($id)
    {
        $config=SystemConfig::first();
        $profile=Profile::where('id',$id)->first();
        $results = Results::where('profile_id',$id)->latest()->first();
        $resultsDoctor = DoctorResults::where('profile_id',$id)->latest()->first();
        $url=$this->url;
      
       

       
    }
    public function showfile($id)
    {
        $config=SystemConfig::first();
        $profile=Profile::where('id',$id)->first();
        $results = Results::where('profile_id',$id)->latest()->first();
        $resultsDoctor = DoctorResults::where('profile_id',$id)->latest()->first();
        $url=$this->url;
        return view('show',compact('profile','results','resultsDoctor','url','config'));  
    }

    public function sentToCourt($id)
    {
        Profile::where('id',$id)->update(['results'=>4]);
        return back()->with('success', 'شكراّ,تمت العملية بنجاح');
    }


    public function getProfiles(Request $request)
    {
        $term = $request->get('q');
        $data = Profile::with('user')->orwhere('name', 'LIKE','%'.$term.'%')->orwhere('card_number', 'LIKE','%'.$term.'%')->orwhere('invoice_number',$term)->paginate(10);
        return response()->json($data); 

    }
    
    public function getProfilesSaved(Request $request)
    {
        $term = $request->get('q');
        $data = Profile::with('user')->where('name', 'LIKE','%'.$term.'%')->orwhere('card_number', 'LIKE','%'.$term.'%')->orwhere('invoice_number',$term)->paginate(10);
        return response()->json($data);
    }

    public function getProfilesCompleted(Request $request)
    {
        $term = $request->get('q');
        $data = Profile::with('user')->where('name', 'LIKE','%'.$term.'%')->where('results',3)->orwhere('card_number', 'LIKE','%'.$term.'%')->where('results',3)->orwhere('invoice_number',$term)->where('results',3)->paginate(10);
        return response()->json($data); 
    }

    public function check_vin (Request $request){
        $car_vin = $request->get('car_vin');
        $car = Car::where('vin',$car_vin)->first();
        if($car){
            return response()->json(true); 
        }else{
            return response()->json(false); 

        }

    }
    }