<?php
namespace App\Http\Controllers;

use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\User;
use App\Models\Wallet;
use App\Models\UserType;
use App\Models\Contract;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use App\Models\Warehouse;
use App\Models\CarImages;
use App\Models\ContractImg;


use Intervention\Image\Facades\Image;
use File;


class AnnualController extends Controller
{
    public function __construct(){
        $this->url = env('FRONTEND_URL');
        $this->userClient =  UserType::where('name', 'client')->first()->id;
        $this->userClientAnnual =  UserType::where('name', 'clientAnnual')->first()->id;
        $this->currentDate = Carbon::now()->format('Y-m-d');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return Response
     */
    public function index()
    {  
        $clientAnnual = User::where('type_id', $this->userClientAnnual)->get();
        return Inertia::render('Annual/Index', ['clientAnnual'=>$clientAnnual]);
    }

    public function addCarsAnnual(Request $request)
    {
        
        $client_id =$request->client_id;
        if( $client_id==0){
            $client = new User;
            $client->name = $request->client_name;
            $client->phone = $request->client_phone;
            $client->created =$this->currentDate;
            $client->type_id = $this->userClientAnnual;
            $client->save();
            Wallet::create(['user_id' => $client->id,'balance'=>0]);
            $client_id=$client->id;
        } 
        $car=Warehouse::create([
            'note'=> $request->note??'',
            'car_owner'=> $request->car_owner,
            'car_type'=> $request->car_type,
            'car_number'=> $request->car_number,
            'year'=> $request->year,
            'car_color'=> $request->car_color,
            'date'=> $request->date ?? $this->currentDate,
            'client_id'=>$client_id,
             ]);

        return Response::json($car, 200);    

    }

    public function getIndexCarAnnual()
    {
        $user_id =$_GET['user_id'] ?? '';
        $q = $_GET['q']??'';
        $from =  $_GET['from'] ?? 0;
        $to =$_GET['to'] ?? 0;
        $limit =$_GET['limit'] ?? 0;
        $data =  Warehouse::with('client')->with('CarImages');
        $totalCars = $data->count();
        if($q){
            $data = $data->orwhere('car_number', 'LIKE','%'.$q.'%')->orwhere('car_type', 'LIKE','%'.$q.'%')->orWhereHas('client', function ($query) use ($q) {
                $query->where('name', 'LIKE', '%' . $q . '%');
            });
        }
 
        if($user_id){
            $data =    $data->where('client_id',  $user_id);

            $totalCars = $data->count();
        }
        $data =$data->paginate($limit)->toArray();

        $data['totalCars']  =$totalCars;


        return Response::json($data, 200);
    }
    public function carsAnnualUpload(Request $request)
    {
        $carId = $request->carId;
        $path1 = public_path('uploads');
        $path2 = public_path('uploadsResized');
        $img_type=$request->img_type??'';
    
        // Create the directories if they don't exist
        if (!file_exists($path1)) {
            mkdir($path1, 0777, true);
        }
        if (!file_exists($path2)) {
            mkdir($path2, 0777, true);
        }
    
        $file = $request->file('image');
    
        // Generate a unique file name
        $name = uniqid() . '_' . trim($file->getClientOriginalName());
    
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
        if($img_type){
        // Create a new record in the database
        $carImage = ContractImg::create([
            'name' => $name,
            'car_id' => $carId,
        ]);
        }else{
        // Create a new record in the database
        $carImage = CarImages::create([
            'name' => $name,
            'car_id' => $carId,
        ]);
    
        }

        return response()->json($carImage, 200);
    }
    public function carsAnnualImageDel(Request $request){
        $name = $request->name;
        $img_type=$request->img_type??'';

        File::delete(public_path('uploads/'.$name));
        File::delete(public_path('uploadsResized/'.$name));
        if($img_type){
            ContractImg::where('name', $name)->delete();
        }else{
            CarImages::where('name', $name)->delete();
        }
        
        return Response::json('deleted is done', 200);

    }
    public function updateCarsAnnual(Request $request){
        $client_id =$request->client_id;
        if(!$client_id){
            $client = new User;
            $client->name = $request->client_name;
            $client->phone = $request->client_phone;
            $client->created =$this->currentDate;
            $client->type_id = $this->userClientAnnual;
            $client->save();
            Wallet::create(['user_id' => $client->id,'balance'=>0]);
            $client_id=$client->id;
        } 
        $warehouse= Warehouse::find($request->id)->update(
        [
        'client_id'=>$client_id,
        'car_type'=>$request->car_type,
        'car_color'=>$request->car_color,
        'year'=>$request->year,
        'note'=>$request->note,
        'car_number'=>$request->car_number]);
        return Response::json($warehouse, 200);
    }
    public function delCarsAnnualr(Request $request){
        $warehouse = Warehouse::with('CarImages')->find($request->id);

        if ($warehouse) {
            foreach ($warehouse->CarImages as $carImage) {
                // Delete the image file from the public directory
                File::delete(public_path('uploads/' . $carImage->name));
                File::delete(public_path('uploadsResized/' . $carImage->name));

                // Delete the image record from the database
                $carImage->delete();
            }
        
            // After deleting all associated images, delete the warehouse
            $warehouse->delete();
            
            return response()->json(['message' => 'Warehouse and associated images deleted successfully'], 200);
        } else {
            return response()->json(['message' => 'Warehouse not found'], 404);
        }
    }
    public function check_vinAnnual(Request $request){
        $car_vin = $request->get('car_vin');
        $car = Warehouse::where('vin',$car_vin)->first();
        if($car){
            return response()->json(true); 
        }else{
            return response()->json(false); 

        }
    }
    }