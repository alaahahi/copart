<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserType;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AccountingCacheService
{
    public function __construct()
    {
        $this->loadUserTypes();
     }

    protected function loadUserTypes()
    {
        if(Cache::get('user_type_admin')===null){
            Cache::rememberForever('user_type_admin', fn () => UserType::where('name', 'admin')->first()?->id);
            Cache::rememberForever('user_type_client', fn () => UserType::where('name', 'client')->first()?->id);
            Cache::rememberForever('user_type_account', fn () => UserType::where('name', 'account')->first()?->id);
            Cache::rememberForever('user_type_seles_kirkuk', fn () => UserType::where('name', 'selesKirkuk')->first()?->id);
            Cache::rememberForever('user_type_car_expenses', fn () => UserType::where('name', 'car_expenses')->first()?->id);
            Cache::rememberForever('user_type_internal_sales_client', fn () => UserType::where('name', 'internal_sales_client')->first()?->id);
            Cache::rememberForever('user_type_shipping_company', fn () => UserType::where('name', 'shipping_company')->first()?->id);
            Cache::rememberForever('user_type_shipping_trips_admin', fn () => UserType::where('name', 'shipping_trips_admin')->first()?->id);
        }
    
    }

    public function loadAccounts($ownerId)
    {
        $accountId = Cache::get('user_type_account');
        if(Cache::get('owner_id')===null){
            Cache::rememberForever('owner_id', fn () => $ownerId);
        }else{
        if(Cache::get('owner_id')!== $ownerId){
            Cache::forget('owner_id');
            Cache::rememberForever('owner_id', fn () => $ownerId);
        }
        }
          $accounts = [
            'main_account'      => 'main@account.com',
            'in_account'        => 'in@account.com',
            'out_account'       => 'out@account.com',
            'debt_account'      => 'debt@account.com',
            'transfers_account' => 'transfers@account.com',
            'out_supplier'      => 'supplier-out',
            'debt_supplier'     => 'supplier-debt',
            'howler'            => 'howler',
            'shipping_coc'      => 'shipping-coc',
            'border'            => 'border',
            'iran'              => 'iran',
            'dubai'             => 'dubai',
            'main_box'          => 'mainBox@account.com',
            'online_contracts' => 'online-contracts',
            'online_contracts_dinar' => 'online-contracts-dinar',
            'debt_online_contracts' => 'online-contracts-debt', 
            'debt_online_contracts_dinar' => 'online-contracts-debit-dinar',
            
        ];
  
        foreach ($accounts as $key => $email) {
        Cache::remember("account_{$ownerId}_$key", now()->addMinutes(60), function () use ($accountId, $ownerId, $email) {
                return User::with('wallet')
                    ->where('owner_id', $ownerId)
                    ->where('email', $email)
                    ->first();
            });
        }

        // حفظ owner_id الحالي لمقارنته لاحقًا
        Cache::put('account_owner_id', $ownerId);
    }

    public function userAdmin()  { return Cache::get('user_type_admin'); }
    public function userClient() { return Cache::get('user_type_client'); }
    public function userAccount(){ return Cache::get('user_type_account'); }
    public function userSelesKirkuk(){ return Cache::get('user_type_seles_kirkuk'); }
    public function userCarExpenses(){ return Cache::get('user_type_car_expenses'); }
    public function userInternalSalesClient(){ return Cache::get('user_type_internal_sales_client'); }
    public function userShippingCompany(){ return Cache::get('user_type_shipping_company'); }
    public function userShippingTripsAdmin(){ return Cache::get('user_type_shipping_trips_admin'); }


    public function getAccount($key)
    {
        $ownerId = Cache::get('owner_id');
        return Cache::get("account_{$ownerId}_$key");
    }

    // Shortcut methods
    public function mainAccount()      { return $this->getAccount('main_account'); }
    public function inAccount()        { return $this->getAccount('in_account'); }
    public function outAccount()       { return $this->getAccount('out_account'); }
    public function debtAccount()      { return $this->getAccount('debt_account'); }
    public function transfersAccount() { return $this->getAccount('transfers_account'); }
    public function outSupplier()      { return $this->getAccount('out_supplier'); }
    public function debtSupplier()     { return $this->getAccount('debt_supplier'); }
    public function howler()           { return $this->getAccount('howler'); }
    public function shippingCoc()      { return $this->getAccount('shipping_coc'); }
    public function border()           { return $this->getAccount('border'); }
    public function iran()             { return $this->getAccount('iran'); }
    public function dubai()            { return $this->getAccount('dubai'); }
    public function mainBox()          { return $this->getAccount('main_box'); }
    public function onlineContracts()  { return $this->getAccount('online_contracts'); }
    public function onlineContractsDinar()  { return $this->getAccount('online_contracts_dinar'); }
    public function debtOnlineContracts()  { return $this->getAccount('debt_online_contracts'); }
    public function debtOnlineContractsDinar()  { return $this->getAccount('debt_online_contracts_dinar'); }


    public function refresh()
    {
        Cache::forget('user_type_admin');
        Cache::forget('user_type_client');
        Cache::forget('user_type_account');
        Cache::forget('user_type_seles_kirkuk');
        Cache::forget('user_type_car_expenses');
        Cache::forget('user_type_internal_sales_client');
        Cache::forget('user_type_shipping_company');
        Cache::forget('user_type_shipping_trips_admin');


        $ownerId = Cache::get('owner_id');
 
        foreach ([
            'main_account', 'in_account', 'out_account', 'debt_account',
            'transfers_account', 'out_supplier', 'debt_supplier',
            'howler', 'shipping_coc', 'border', 'iran', 'dubai', 'main_box',
            'online_contracts', 'online_contracts_dinar', 'debt_online_contracts', 'debt_online_contracts_dinar'
        ] as $key) {
            Cache::forget("account_{$ownerId}_$key");
        }
         Cache::forget('account_owner_id');
         $this->loadUserTypes();
         return response()->json(['message' => ' 1 تم تحديث الكاش بنجاح']);
    }

    public function refreshIfNeeded()
    {
        $this->refresh();
         $currentOwnerId = Cache::get('owner_id');
        $cachedOwnerId = Cache::get('account_owner_id');
        if ($currentOwnerId !== $cachedOwnerId) {
            $this->refresh();
        }
    }
}
