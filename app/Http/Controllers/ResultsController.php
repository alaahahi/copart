<?php
   
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Car;
use App\Models\UserType;
use App\Models\Results;
use App\Models\DoctorResults;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use App\Models\Massage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ResultsController extends Controller
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
        return Inertia::render('FormRegistration/Index', ['url'=>$this->url]);
    }
    public function show ()
    {
        return Inertia::render('Users/Index', ['url'=>$this->url]);
    }
    public function getIndex()
    {
        $user = Auth::user();
        $data = Profile::where('user_id',$user->id)->paginate(10);
        return Response::json($data, 200);
    }
    public function create()
    {
        return Inertia::render('results', ['url'=>$this->url]);
    }
    
    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function store(Request $request)
    {
        $user_id = Auth::user();     
        if($this->userSeles!=$user_id->type_id && $this->userAdmin!=$user_id->type_id){
            return back()->with('message', 'المعذرة لا تملك صلاحيات القيام بالعملية المطلوبة',['show'=>true]);
        }
        Validator::make($request->all(), [
        'husband_b'=> 'required|string|max:255',
        'husband_hb'=> 'required|string|max:255',
        'husband_mcv'=> 'required|string|max:255',
        'husband_mch'=> 'required|string|max:255',
        'husband_hemoglobin_f'=> 'required|string|max:255',
        'husband_hemoglobin_a2'=> 'required|string|max:255',
        'husband_hbs'=> 'required|string|max:255',
        'husband_hcv'=> 'required|string|max:255',
        'husband_hiv'=> 'required|string|max:255',
        'husband_syphilis'=> 'required|string|max:255',
        'wife_b'=> 'required|string|max:255',
        'wife_hb'=> 'required|string|max:255',
        'wife_mcv'=> 'required|string|max:255',
        'wife_mch'=> 'required|string|max:255',
        'wife_hemoglobin_f'=> 'required|string|max:255',
        'wife_hemoglobin_a2'=> 'required|string|max:255',
        'wife_hbs'=> 'required|string|max:255',
        'wife_hcv'=> 'required|string|max:255',
        'wife_hiv'=> 'required|string|max:255',
        'wife_syphilis'=> 'required|string|max:255',
        'profile_id'=> 'required|string|max:255'])->validate();
        $user = Results::create([
                    'husband_b' =>$request->husband_b,
                    'husband_hb' => $request->husband_hb,
                    'husband_mcv' => $request->husband_mcv,
                    'husband_mch' => $request->husband_mch,
                    'husband_hemoglobin_a' => $request->husband_hemoglobin_a,
                    'husband_hemoglobin_f' => $request->husband_hemoglobin_f,
                    'husband_hemoglobin_a2' => $request->husband_hemoglobin_a2,
                    'husband_hbs'=> $request->husband_hbs,
                    'husband_hcv'=> $request->husband_hcv,
                    'husband_hiv'=> $request->husband_hiv,
                    'husband_tb'=> $request->husband_tb,
                    'husband_syphilis'=> $request->husband_syphilis,
                    'husband_tpha'=> $request->husband_tpha,
                    'wife_b' =>$request->wife_b,
                    'wife_hb' => $request->wife_hb,
                    'wife_mcv' => $request->wife_mcv,
                    'wife_mch' => $request->wife_mch,
                    'wife_hemoglobin_a' => $request->wife_hemoglobin_a,
                    'wife_hemoglobin_f' => $request->wife_hemoglobin_f,
                    'wife_hemoglobin_a2' => $request->wife_hemoglobin_a2,
                    'wife_hbs'=> $request->wife_hbs,
                    'wife_hcv'=> $request->wife_hcv,
                    'wife_hiv'=> $request->wife_hiv,
                    'wife_tb'=> $request->wife_tb,
                    'wife_syphilis'=> $request->wife_syphilis,
                    'wife_tpha'=> $request->wife_tpha,
                    'user_id'=> $user_id->id,
                    'profile_id'=>  $request->profile_id,
                     ]);
                    Profile::where('id',$request->profile_id)->update(['user_add_lab'=>$user_id->id,'results'=>1]);
        return Inertia::render('FormRegistration/Index', ['url'=>$this->url])->with('success', 'شكراّ,تمت العملية بنجاح');
    }
    public function storeEdit(Request $request,$id)
    {

        $user_id = Auth::user();     
        if($this->userSeles!=$user_id->type_id && $this->userAdmin!=$user_id->type_id){
            return back()->with('message', 'المعذرة لا تملك صلاحيات القيام بالعملية المطلوبة',['show'=>true]);
        }
        Validator::make($request->all(), [
        'husband_b'=> 'required|string|max:255',
        'husband_hb'=> 'required|string|max:255',
        'husband_mcv'=> 'required|string|max:255',
        'husband_mch'=> 'required|string|max:255',
        'husband_hemoglobin_f'=> 'required|string|max:255',
        'husband_hemoglobin_a2'=> 'required|string|max:255',
        'husband_hbs'=> 'required|string|max:255',
        'husband_hcv'=> 'required|string|max:255',
        'husband_hiv'=> 'required|string|max:255',
        'husband_syphilis'=> 'required|string|max:255',
        'wife_b'=> 'required|string|max:255',
        'wife_hb'=> 'required|string|max:255',
        'wife_mcv'=> 'required|string|max:255',
        'wife_mch'=> 'required|string|max:255',
        'wife_hemoglobin_f'=> 'required|string|max:255',
        'wife_hemoglobin_a2'=> 'required|string|max:255',
        'wife_hbs'=> 'required|string|max:255',
        'wife_hcv'=> 'required|string|max:255',
        'wife_hiv'=> 'required|string|max:255',
        'wife_syphilis'=> 'required|string|max:255',
            ])->validate();
       $s=Results::where('id',$id)->update([
                    'husband_b' =>$request->husband_b,
                    'husband_hb' => $request->husband_hb,
                    'husband_mcv' => $request->husband_mcv,
                    'husband_mch' => $request->husband_mch,
                    'husband_hemoglobin_a' => $request->husband_hemoglobin_a,
                    'husband_hemoglobin_f' => $request->husband_hemoglobin_f,
                    'husband_hemoglobin_a2' => $request->husband_hemoglobin_a2,
                    'husband_hbs'=> $request->husband_hbs,
                    'husband_hcv'=> $request->husband_hcv,
                    'husband_hiv'=> $request->husband_hiv,
                    'husband_tb'=> $request->husband_tb,
                    'husband_syphilis'=> $request->husband_syphilis,
                    'husband_tpha'=> $request->husband_tpha,
                    'wife_b' =>$request->wife_b,
                    'wife_hb' => $request->wife_hb,
                    'wife_mcv' => $request->wife_mcv,
                    'wife_mch' => $request->wife_mch,
                    'wife_hemoglobin_a' => $request->wife_hemoglobin_a,
                    'wife_hemoglobin_f' => $request->wife_hemoglobin_f,
                    'wife_hemoglobin_a2' => $request->wife_hemoglobin_a2,
                    'wife_hbs'=> $request->wife_hbs,
                    'wife_hcv'=> $request->wife_hcv,
                    'wife_hiv'=> $request->wife_hiv,
                    'wife_tb'=> $request->wife_tb,
                    'wife_syphilis'=> $request->wife_syphilis,
                    'wife_tpha'=> $request->wife_tpha,

                     ]);
                    Profile::where('id',$request->profile_id)->update(['user_add_lab'=>$user_id->id,'results'=>1]);
                 
        return Inertia::render('FormRegistration/Index', ['url'=>$this->url])->with('success', 'شكراّ,تمت العملية بنجاح');
    }
    public function storeDoctor(Request $request)
    {
        $user_id = Auth::user();
 
        if($this->userDoctor!=$user_id->type_id && $this->userAdmin!=$user_id->type_id){
            return back()->with('message', 'المعذرة لا تملك صلاحيات القيام بالعملية المطلوبة',['show'=>true]);
        }
        $user = DoctorResults::create([
                    'husband_talasyma'=> $request->husband_talasyma,
                    'husband_faqar'=> $request->husband_faqar,
                    'husband_himofilya'=> $request->husband_himofilya,
                    'husband_al'=> $request->husband_al,
                    'husband_dam'=> $request->husband_dam,
                    'husband_note'=> $request->husband_note,
                    'husband_results'=> $request->husband_results,
                    'wife_talasyma'=> $request->wife_talasyma,
                    'wife_faqar'=> $request->wife_faqar,
                    'wife_himofilya'=> $request->wife_himofilya,
                    'wife_al'=> $request->wife_al,
                    'wife_dam'=> $request->wife_dam,
                    'wife_note'=> $request->wife_note,
                    'wife_results'=> $request->wife_results,
                    'user_id'=> $user_id->id,
                    'profile_id'=>  $request->profile_id,
                     ]);
                     if($request->status){
                        Profile::where('id',$request->profile_id)->update(['user_doctor'=>$user_id->id,'user_rejected'=>null,'user_accepted'=>$user_id->id,'results'=>3]);
                     }
                     else{
                        Profile::where('id',$request->profile_id)->update(['user_doctor'=>$user_id->id,'user_rejected'=>$user_id->id,'user_accepted'=>null,'results'=>2]);
                     }

        return Inertia::render('FormRegistration/Index', ['url'=>$this->url])->with('success', 'شكراّ,تمت العملية بنجاح');
    }
    public function storeDoctorEdit(Request $request,$id)
    {
        $user_id = Auth::user();
        if($this->userDoctor!=$user_id->type_id && $this->userAdmin!=$user_id->type_id){
            return back()->with('message', 'المعذرة لا تملك صلاحيات القيام بالعملية المطلوبة',['show'=>true]);
        }
        $user = DoctorResults::where('id',$id)->update([
                    'husband_talasyma'=> $request->husband_talasyma,
                    'husband_faqar'=> $request->husband_faqar,
                    'husband_himofilya'=> $request->husband_himofilya,
                    'husband_al'=> $request->husband_al,
                    'husband_dam'=> $request->husband_dam,
                    'husband_note'=> $request->husband_note,
                    'husband_results'=> $request->husband_results,
                    'wife_talasyma'=> $request->wife_talasyma,
                    'wife_faqar'=> $request->wife_faqar,
                    'wife_himofilya'=> $request->wife_himofilya,
                    'wife_al'=> $request->wife_al,
                    'wife_dam'=> $request->wife_dam,
                    'wife_note'=> $request->wife_note,
                    'wife_results'=> $request->wife_results,

                    'user_id'=> $user_id->id,
                     ]);

        return Inertia::render('FormRegistrationCompleted', ['url'=>$this->url])->with('success', 'شكراّ,تمت العملية بنجاح');
    }
}