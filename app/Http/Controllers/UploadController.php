<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Massage;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Crypt;

class UploadController extends Controller
{
    public function __construct(){
        $this->url = env('FRONTEND_URL');

    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return Massage::all();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
    
        $image_path =  $request->file('image')  ? $this->url ."/data/".$request->file('image')->store('image')  : "";
        $voice_path =  $request->file('voice')  ? $this->url ."/data/".$request->file('voice')->store('voice')  : "";
        $text_path  =  $request->file('text')   ? $this->url ."/data/".$request->file('text')->store('text')    : "" ;
        $lat = Crypt::encryptString($request->lat);
        $lng = Crypt::encryptString($request->lng);
        $receiver_id	 = $request->receiver_id;
        $sender_id  =  $request->sender_id;
        $aes = $request->aes;
        $data = Massage::create([
            'image' => $image_path,
            'voice' => $voice_path,
            'text' =>   $text_path,
            'lat' =>          $lat,
            'lng' =>          $lng,
            'sender_id' => $sender_id,
            'receiver_id' => $receiver_id,
            'aes' =>  $aes,
        ]);
        return  $data;
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        return  Massage::findOrFail($id);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $Massage = Massage::findOrFail($id);
        $Massage->update($request->all());

        return $Massage;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $Massage = Massage::findOrFail($id);
        $Massage->delete();

        return 201;
        //
    }
}
