<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use App\Http\Controllers\UserController;
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

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::resource('/users', UserController::class)->middleware(['auth', 'verified']);

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'config' => SystemConfig::first(),
        'canLogin' => Route::has('login'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::group(['middleware' => ['auth','verified']], function () {
    Route::get('/dashboard', function () {return Inertia::render('Dashboard');})->middleware(['auth', 'verified'])->name('dashboard');

    Route::get('dashboard',[DashboardController::class,'index'])->middleware(['auth', 'verified'])->name('dashboard');
    Route::get('sales',[DashboardController::class,'sales'])->name('sales');
    Route::get('purchases',[DashboardController::class,'purchases'])->name('purchases');

    Route::get('accounting',[AccountingController::class,'index'])->name('accounting');

    
    Route::get('getIndex',[UserController::class, 'getIndex'])->name("getIndex");
    Route::get('ban/{id}',[UserController::class, 'ban'])->name("ban");
    Route::get('sentToCourt/{id}',[FormRegistrationController::class, 'sentToCourt'])->name("sentToCourt");
    Route::get('clients',[UserController::class, 'clients'])->name('clients');
    Route::get('showClients/{id}',[UserController::class, 'showClients'])->name('showClients');

    
    Route::get('getIndexClients',[UserController::class, 'getIndexClients'])->name("getIndexClients");
    Route::get('getIndexClientsSearch',[UserController::class, 'getIndexClients'])->name("getIndexClientsSearch");

    

    Route::post('formRegistration',[FormRegistrationController::class, 'store'])->name('formRegistration');
    
    Route::post('formRegistrationstoreEdit/{id}',[FormRegistrationController::class, 'storeEdit'])->name('formRegistrationstoreEdit');
    
    
    Route::get('formRegistration',[FormRegistrationController::class, 'index'])->name('formRegistration');
    
    Route::get('formRegistrationEdit/{id}',[FormRegistrationController::class, 'formRegistrationEdit'])->name('formRegistrationEdit');
    
    
    Route::get('FormRegistrationSaved',[FormRegistrationController::class, 'saved'])->name('FormRegistrationSaved');
    Route::get('FormRegistrationCourt',[FormRegistrationController::class, 'court'])->name('FormRegistrationCourt');
    Route::get('FormRegistrationCompleted',[FormRegistrationController::class, 'completed'])->name('FormRegistrationCompleted');

    
    Route::get('getIndexFormRegistration',[FormRegistrationController::class, 'getIndex'])->name("getIndexFormRegistration");
    Route::get('getIndexFormRegistrationSaved',[FormRegistrationController::class, 'getIndexSaved'])->name("getIndexFormRegistrationSaved");
    Route::get('getIndexFormRegistrationCourt',[FormRegistrationController::class, 'getIndexCourt'])->name("getIndexFormRegistrationCourt");
    Route::get('getIndexFormRegistrationCompleted',[FormRegistrationController::class, 'getIndexCompleted'])->name("getIndexFormRegistrationCompleted");
    
    
    Route::get('labResults/{id}',[FormRegistrationController::class, 'labResults'])->name('labResults');
    Route::get('labResultsEdit/{id}',[FormRegistrationController::class, 'labResultsEdit'])->name('labResultsEdit');
    
    
    
    Route::get('doctorResults/{id}',[FormRegistrationController::class, 'doctorResults'])->name('doctorResults');
    Route::get('doctorResultsEdit/{id}',[FormRegistrationController::class, 'doctorResultsEdit'])->name('doctorResultsEdit');
    
    Route::post('results',[ResultsController::class, 'store'])->name('results');
    Route::post('resultsEdit/{id}',[ResultsController::class, 'storeEdit'])->name('resultsEdit');
    Route::post('resultsDoctor',[ResultsController::class, 'storeDoctor'])->name('resultsDoctor');
    Route::post('resultsDoctorEdit/{id}',[ResultsController::class, 'storeDoctorEdit'])->name('resultsDoctorEdit');
    Route::get('document/{id}', [FormRegistrationController::class, 'document'])->name('document');
    Route::get('show/{id}', [FormRegistrationController::class, 'showfile'])->name('show');
    
    
    Route::get('/livesearch', [FormRegistrationController::class, 'getProfiles'])->name('livesearch');
    Route::get('/livesearchSaved', [FormRegistrationController::class, 'getProfilesSaved'])->name('livesearchSaved');
    Route::get('/livesearchCompleted', [FormRegistrationController::class, 'getProfilesCompleted'])->name('livesearchCompleted');

    
    
    Route::get('/addUserCard/{card_id}/{card}/{user_id}', [UserController::class, 'addUserCard'])->name('addUserCard');
    
    Route::get('/receiveCard', [AccountingController::class, 'receiveCard'])->name('receiveCard');
    Route::get('/paySelse/{id}', [AccountingController::class, 'paySelse'])->name('paySelse');
    Route::get('addTransfers',[TransfersController::class, 'addTransfers'])->name('addTransfers');
    Route::get('transfers',[TransfersController::class, 'index'])->name('transfers');
    Route::get('getIndexAccountsSelas',[TransfersController::class, 'getIndexAccountsSelas'])->name('getIndexAccountsSelas');
    Route::get('carConfig',[CarConfigController::class, 'index'])->name('carConfig');
    Route::get('addCompany',[CarConfigController::class, 'create'])->name('addCompany');
    Route::post('addCompany',[CarConfigController::class, 'store']);
    Route::get('addName',[CarConfigController::class, 'storeName'])->name('addName');
    Route::get('addCarModel',[CarConfigController::class, 'storeCarModel'])->name('addCarModel');
    Route::get('addColor',[CarConfigController::class, 'storeColor'])->name('addColor');
    Route::get('addCar',[DashboardController::class, 'addCar'])->name('addCar');
    Route::get('payCar',[DashboardController::class, 'payCar'])->name('payCar');
    Route::get('getIndexCar',[DashboardController::class, 'getIndexCar'])->name('getIndexCar');
    Route::get('getIndexCarSearch',[DashboardController::class, 'getIndexCarSearch'])->name('getIndexCarSearch');

    Route::get('addExpenses',[DashboardController::class, 'addExpenses'])->name('addExpenses');
    Route::get('addPaymentCar',[DashboardController::class, 'addPaymentCar'])->name('addPaymentCar');

    
    Route::get('addToBox',[DashboardController::class, 'addToBox'])->name('addToBox');
    Route::get('withDrawFromBox',[DashboardController::class, 'withDrawFromBox'])->name('withDrawFromBox');


    
    
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


    Route::get('online_contracts',[OnlineContractsController::class, 'online_contracts'])->name('online_contracts');
    Route::get('car_expenses',[CarExpensesController::class, 'index'])->name('car_expenses');
    Route::get('car_contract',[CarContractController::class, 'index'])->name('car_contract');
    Route::get('contract_account',[CarContractController::class, 'contract_account'])->name('contract_account');
    Route::get('contract/{id?}', [CarContractController::class, 'contract'])->name('contract');
    Route::get('contract_print/{id}', [CarContractController::class, 'contract_print'])->name('contract_print');


    
    
    Route::get('dubai',[TransfersController::class, 'dubai'])->name('dubai');
    Route::get('iran',[TransfersController::class, 'iran'])->name('iran');
    Route::get('border',[TransfersController::class, 'border'])->name('border');
    Route::get('coc',[TransfersController::class, 'coc'])->name('coc');
    Route::get('howler',[TransfersController::class, 'howler'])->name('howler');
    Route::get('getIndexAccounting',[AccountingController::class, 'getIndexAccounting'])->name("getIndexAccounting");

    Route::get('annual_information',[AnnualController::class, 'index'])->name('annual_information');

    Route::get('wallet',[AccountingController::class, 'wallet'])->name("wallet");

    
 });



require __DIR__.'/auth.php';
