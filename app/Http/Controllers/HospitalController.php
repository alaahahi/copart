<?php
   
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Car;
use App\Models\UserType;
use App\Models\Appointment;
use App\Models\DoctorResults;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use App\Models\Massage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class HospitalController extends Controller
{
    public function __construct(){
         $this->url = env('FRONTEND_URL');
         $this->userAdmin =  UserType::where('name', 'admin')->first()->id;
         $this->userClient =  UserType::where('name', 'client')->first()->id;
         $this->userAccount =  UserType::where('name', 'account')->first()->id;

    }
    public function index()
    {
        $users = User::where('type_id',$this->userDoctor)->get();

        return Inertia::render('Hospital/Index', ['url'=>$this->url,'users'=>$users]);
    }
    public function show ()
    {
        return Inertia::render('Hospital/Index', ['url'=>$this->url]);
    }
    public function getIndex()
    {
        $user_id = $_GET['user_id'] ?? 0;
        if($user_id){
            $data = Appointment::with('user')->where('user_id',$user_id)->paginate(10);
        }else{
            $data = Appointment::with('user')->paginate(10);
        }
        return Response::json($data, 200);
    }
    public function create()
    {
        $userDoctor = User::where('type_id',$this->userDoctor)->get();
        return Inertia::render('Hospital/Add', ['url'=>$this->url,'userDoctor'=>$userDoctor]);
    }
    public function store(Request $request)
    {

        $userDoctor = User::where('type_id',$this->userDoctor)->get();
        $users = User::where('type_id',$this->userDoctor)->get();


        Validator::make($request->all(), [
        'user_id'=> 'required|int|max:50000',
        'card_id'=> 'required|int|max:50000',
       ])->validate();

        $profile = Profile::where('card_number',$request->card_id)->first();
        if($profile){
            $appointment = Appointment::create([
                'user_id' =>$request->user_id,
                'card_id' => $request->card_id,
                'start' => $this->convertToTimestamp($request->start),
                'end' => $this->convertToTimestamp($request->end),
                 ]);
                 $profile->update(['user_rejected'=>$appointment->id]);
                 return Inertia::render('Hospital/Index', ['url'=>$this->url,'users'=>$users])->with('success', 'شكراّ,تمت العملية بنجاح');
        }else
        return Inertia::render('Hospital/Add', ['url'=>$this->url,'userDoctor'=>$userDoctor])->with('success', 'رقم البطاقة غير صالح او لم يتم تسجيل البطاقة');
    }
    public function edit(Request $request,$id)
    {
        $userDoctor = User::where('type_id',$this->userDoctor)->get();
        $appointment=Appointment::find($id);
        return Inertia::render('Hospital/Edit', ['url'=>$this->url,'userDoctor'=>$userDoctor,'appointment'=>$appointment]);
    }
    public function livesearchAppointment(Request $request)
    {
        $term = $request->get('q');
        if($term){
            $data = Appointment::with('user')->where('card_id',$term)->paginate(10);
        }
        else{
            $data = Appointment::with('user')->paginate(10);

        }
        return response()->json($data); 

    }
    public function storeEdit(Request $request)
    {
        $userDoctor = User::where('type_id',$this->userDoctor)->get();
        $users = User::where('type_id',$this->userDoctor)->get();

        Validator::make($request->all(), [
        'user_id'=> 'required|int|max:50000',
        'card_id'=> 'required|int|max:50000',
       ])->validate();

        $profile = Profile::where('card_number',$request->card_id)->first();
        if($profile){
            $appointment = Appointment::find($request->id)->update([
                'user_id' =>$request->user_id,
                'card_id' => $request->card_id,
                'start' => $this->convertToTimestamp($request->start),
                'end' => $this->convertToTimestamp($request->end),
                'is_come' => 1,
                 ]);
                 return Inertia::render('Hospital/Index', ['url'=>$this->url,'users'=>$users])->with('success', 'شكراّ,تمت التعديل بنجاح');
        }else
        return Inertia::render('Hospital/Edit', ['url'=>$this->url,'userDoctor'=>$userDoctor])->with('success', 'رقم البطاقة غير صالح او لم يتم تسجيل البطاقة');
    }
    public function convertToTimestamp($datetime)
    {
        $carbon = Carbon::parse($datetime);
        $timestamp = $carbon;
        return $timestamp;
    }

    public function appointmentCancel()
    {
        $id = $_GET['id'] ?? 0;
        $appointment = Appointment::find($id)->update([
            'is_come' =>0,
             ]);
             return Response::json($appointment, 200);
            
    }
    public function appointmentCome()
    {
        $id = $_GET['id'] ?? 0;
        $appointment = Appointment::find($id)->update([
            'is_come' =>2,
             ]);
             return Response::json($appointment, 200);
    }
}