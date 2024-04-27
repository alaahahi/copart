<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Models\User;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UploadController;



use Illuminate\Foundation\Application;
use Inertia\Inertia;
use App\Http\Controllers\FormRegistrationController;
use App\Http\Controllers\ResultsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AccountingController;
use App\Http\Controllers\TransfersController;
use App\Http\Controllers\CarConfigController;
use App\Http\Controllers\OnlineContractsController;
use App\Http\Controllers\AnnualController;
use App\Http\Controllers\CarExpensesController;
use App\Http\Controllers\CarContractController;


use App\Models\SystemConfig;
Route::get('/clear-config-cache', function () {

    
    //return ;

    Artisan::call('config:cache');
    Artisan::call('config:clear');
    Artisan::call('route:clear');
    Artisan::call('view:clear');
    Artisan::call('view:clear');
    Artisan::call('optimize:clear');
    //Artisan::call('command:cache_most_visited');
    //$content_controller = new ContentEntityRepository();
    //$content_controller->log_visit_cache_job([]);
    return "Configuration cache file removed";
});
Route::middleware('auth:sanctum')->group(function () {
    Route::apiResource('upload', UploadController::class);
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {return $request->user();});
Route::get('/user/{id}', function (Request $request) { return  User::find($request->id)->massage;});
Route::get('/user/{id}',[UserController::class, 'getMassages']);
Route::get('/getUserMassages/{id}/{user}',[UserController::class, 'getUserMassages']);
Route::middleware('auth:api')->get('/user', function (Request $request) { return $request->user();});
Route::post('login',[UserController::class, 'login']);
Route::get('getcontact/{id}',[UserController::class, 'getcontact']);


Route::get('receiveCard',[UserController::class, 'receiveCard']);


Route::get('ackUserMassages/{sender}/{receiver}/{date}',[UserController::class, 'ackUserMassages']);

Route::get('getIndexClients',[UserController::class, 'getIndexClients'])->name("getIndexClients");
Route::get('getIndexClientsSearch',[UserController::class, 'getIndexClients'])->name("getIndexClientsSearch");


Route::get('getSaler',[UserController::class, 'getSaler']);

Route::post('clientsStore',[UserController::class, 'clientsStore'])->name('clientsStore');
Route::post('clientsEdit',[UserController::class, 'clientsEdit'])->name('clientsEdit');
Route::post('delClient',[UserController::class, 'delClient'])->name('delClient');


Route::post('addTransfers',[TransfersController::class, 'addTransfers'])->name('addTransfers');
Route::get('transfers',[TransfersController::class, 'index'])->name('transfers');
Route::post('confirmTransfers',[TransfersController::class, 'confirmTransfers'])->name('confirmTransfers');
Route::post('cancelTransfers',[TransfersController::class, 'cancelTransfers'])->name('cancelTransfers');


Route::get('getIndexAccountsSelas',[AccountingController::class, 'getIndexAccountsSelas'])->name('getIndexAccountsSelas');

Route::post('deleteTransactions',[AccountingController::class, 'delTransactions'])->name('deleteTransactions');


Route::get('carConfig',[CarConfigController::class, 'index'])->name('carConfig');
Route::get('addCompany',[CarConfigController::class, 'create'])->name('addCompany');
Route::post('addCompany',[CarConfigController::class, 'store']);
Route::get('addName',[CarConfigController::class, 'storeName'])->name('addName');
Route::get('addCarModel',[CarConfigController::class, 'storeCarModel'])->name('addCarModel');
Route::get('addColor',[CarConfigController::class, 'storeColor'])->name('addColor');
Route::post('addCar',[DashboardController::class, 'addCar'])->name('addCar');
Route::post('addCars',[DashboardController::class, 'addCars'])->name('addCars');

Route::post('addCarsAnnual',[AnnualController::class, 'addCarsAnnual'])->name('addCarsAnnual');


Route::post('updateCarsS',[DashboardController::class, 'updateCarsS'])->name('updateCarsS');
Route::post('updateCarsP',[DashboardController::class, 'updateCarsP'])->name('updateCarsP');

Route::get('/getcount', [DashboardController::class, 'getcount'])->name('getcount');



Route::post('payCar',[DashboardController::class, 'payCar'])->name('payCar');
Route::post('DelCar',[DashboardController::class, 'DelCar'])->name('DelCar');

Route::get('client',[DashboardController::class, 'client'])->name('client');
Route::get('getIndexCar',[DashboardController::class, 'getIndexCar'])->name('getIndexCar');
Route::get('getIndexCarSearch',[DashboardController::class, 'getIndexCarSearch'])->name('getIndexCarSearch');
Route::get('totalInfo',[DashboardController::class, 'totalInfo'])->name('totalInfo');
Route::get('getIndexCarAnnual',[AnnualController::class, 'getIndexCarAnnual'])->name('getIndexCarAnnual');

Route::post('carsAnnualUpload',[AnnualController::class, 'carsAnnualUpload'])->name('carsAnnualUpload');
Route::get('carsAnnualImageDel',[AnnualController::class, 'carsAnnualImageDel'])->name('carsAnnualImageDel');
Route::post('updateCarsAnnual',[AnnualController::class, 'updateCarsAnnual'])->name('updateCarsAnnual');
Route::post('delCarsAnnualr',[AnnualController::class, 'delCarsAnnualr'])->name('delCarsAnnualr');

Route::post('TransactionsUpload',[AccountingController::class, 'TransactionsUpload'])->name('TransactionsUpload');
Route::get('TransactionsImageDel',[AccountingController::class, 'TransactionsImageDel'])->name('TransactionsImageDel');

Route::get('getIndexExpenses',[DashboardController::class, 'getIndexExpenses'])->name('getIndexExpenses');
Route::get('showCar',[CarConfigController::class, 'showCar']);

Route::get('addExpenses',[DashboardController::class, 'addExpenses'])->name('addExpenses');
Route::get('addPaymentCar',[AccountingController::class, 'addPaymentCar'])->name('addPaymentCar');
Route::get('addCarContracts',[OnlineContractsController::class, 'addCarContracts'])->name('addCarContracts');
Route::get('editCarContracts',[OnlineContractsController::class, 'editCarContracts'])->name('editCarContracts');
Route::get('makeCarExit',[OnlineContractsController::class, 'makeCarExit'])->name('makeCarExit');
Route::get('unMakeCarExit',[OnlineContractsController::class, 'unMakeCarExit'])->name('unMakeCarExit');
Route::post('removeContract',[OnlineContractsController::class, 'removeContract'])->name('removeContract');


Route::get('addPaymentCarTotal',[AccountingController::class, 'addPaymentCarTotal'])->name('addPaymentCarTotal');


Route::get('addToBox',[DashboardController::class, 'addToBox'])->name('addToBox');
Route::get('withDrawFromBox',[DashboardController::class, 'withDrawFromBox'])->name('withDrawFromBox');

Route::get('check_vin',[CarConfigController::class, 'check_vin']);



Route::get('getIndexCompany',[CarConfigController::class, 'getIndex'])->name('getIndexCompany');
Route::get('getIndexName',[CarConfigController::class, 'getIndexName'])->name('getIndexName');
Route::get('getIndexModel',[CarConfigController::class, 'getIndexModel'])->name('getIndexModel');
Route::get('getIndexColor',[CarConfigController::class, 'getIndexColor'])->name('getIndexColor');


Route::get('companyEdit/{id}',[CarConfigController::class, 'companyEdit'])->name('companyEdit');
Route::get('delCompany/{id}',[CarConfigController::class, 'companyDel'])->name('delCompany');
Route::get('delName/{id}',[CarConfigController::class, 'delName'])->name('delName');
Route::get('delModel/{id}',[CarConfigController::class, 'delModel'])->name('delModel');
Route::get('delColor/{id}',[CarConfigController::class, 'delColor'])->name('delColor');
Route::get('companyStoreEdit',[CarConfigController::class, 'index'])->name('companyStoreEdit');
Route::post('companyStoreEdit',[CarConfigController::class, 'storeEdit'])->name('companyStoreEdit');


Route::post('salesDebt',[AccountingController::class, 'salesDebt'])->name('salesDebt');
Route::post('delTransactions',[AccountingController::class, 'delTransactions'])->name('delTransactions');
Route::post('receiptArrived',[AccountingController::class, 'receiptArrived'])->name('receiptArrived');
Route::post('receiptArrivedUser',[AccountingController::class, 'receiptArrivedUser'])->name('receiptArrivedUser');
Route::post('salesDebtUser',[AccountingController::class, 'salesDebtUser'])->name('salesDebtUser');


Route::post('GenExpenses',[AccountingController::class, 'GenExpenses'])->name('GenExpenses');
Route::get('getGenExpenses',[AccountingController::class, 'getGenExpenses'])->name('getGenExpenses');

Route::post('convertDollarDinar',[AccountingController::class, 'convertDollarDinar'])->name('convertDollarDinar');
Route::post('convertDinarDollar',[AccountingController::class, 'convertDinarDollar'])->name('convertDinarDollar');


Route::post('addCarFavorite',[CarExpensesController::class, 'addCarFavorite'])->name('addCarFavorite');
Route::post('confirmExpensesCar',[CarExpensesController::class, 'confirmExpensesCar'])->name('confirmExpensesCar');
Route::post('delExpensesCar',[CarExpensesController::class, 'delExpensesCar'])->name('delExpensesCar');
Route::post('confirmArchiveCar',[CarExpensesController::class, 'confirmArchiveCar'])->name('confirmArchiveCar');
Route::post('confirmArchiveCarBack',[CarExpensesController::class, 'confirmArchiveCarBack'])->name('confirmArchiveCarBack');
Route::post('confirmDelCarFav',[CarExpensesController::class, 'confirmDelCarFav'])->name('confirmDelCarFav');
Route::get('getIndexExpensesPrint',[CarExpensesController::class, 'getIndexExpensesPrint'])->name('getIndexExpensesPrint');

Route::post('addCarContract',[CarContractController::class, 'addCarContract'])->name('addCarContract');
Route::get('getIndexContractCar',[CarContractController::class, 'getIndexContractCar'])->name('getIndexContractCar');
Route::post('DelCarContract',[CarContractController::class, 'DelCarContract'])->name('DelCarContract');
Route::get('totalInfoContract',[CarContractController::class, 'totalInfoContract'])->name('totalInfoContract');
Route::get('getListTransactionsContract',[CarContractController::class, 'getListTransactionsContract'])->name('getListTransactionsContract');
Route::post('addToBoxContract',[CarContractController::class, 'addToBoxContract'])->name('addToBoxContract');
Route::post('delTransactionsContract',[CarContractController::class, 'delTransactionsContract'])->name('delTransactionsContract');
Route::post('convertDollarDinarContract',[CarContractController::class, 'convertDollarDinarContract'])->name('convertDollarDinarContract');
Route::post('convertDinarDollarContract',[CarContractController::class, 'convertDinarDollarContract'])->name('convertDinarDollarContract');
Route::post('DropFromBoxContract',[CarContractController::class, 'DropFromBoxContract'])->name('DropFromBoxContract');
Route::get('getIndexClientsContract',[CarContractController::class, 'getIndexClientsContract'])->name('getIndexClientsContract');
Route::get('contract_account_report',[CarContractController::class, 'contract_account_report'])->name('contract_account_report');




});
