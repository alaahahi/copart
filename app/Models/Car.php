<?php

namespace App\Models;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Car extends Model
{
    use HasFactory;
    protected $table = 'car';
    protected $fillable = [
        'id',
        'no',
        'user_id',
        'created_at',
        'updated_at',
        'note',
        'client_id',
        'results',
        'car_type',
        'vin',
        'car_number',
        'dinar',
        'dolar_price',
        'dolar_custom',
        'checkout',
        'shipping_dolar',
        'coc_dolar',
        'total',
        'paid',
        'profit',
        'date',
        'car_color',
        'year',
        'expenses',
        'dinar_s',
        'dolar_price_s',
        'dolar_custom_s',
        'checkout_s',
        'shipping_dolar_s',
        'coc_dolar_s',
        'total_s',
        'discount',
        'expenses_s',
        'is_exit',
        'owner_id',
        'contract_id',
        'year_date',
        'car_have_expenses'
    ];
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    public function company()
    {
        return $this->belongsTo(Company::class, 'company_id');
    }
    public function carmodel()
    {
        return $this->belongsTo(CarModel::class,'model_id');
    }
    public function name()
    {
        return $this->belongsTo(Name::class);
    }
    public function color()
    {
        return $this->belongsTo(Color::class);
    }
    public function Client()
    {
        return $this->belongsTo(User::class);
    }
    public function transactions()
    {
        return $this->morphMany(Transactions::class, 'morphed');
    }
    public function contract()
    {
        // Define a one-to-one relationship with the Car model
        return $this->hasOne(Contract::class, 'car_id', 'id');
    }
    public function exitcar()
    {
        // Define a one-to-one relationship with the Car model
        return $this->hasOne(ExitCar::class, 'car_id', 'id');
    }
    public function CarImages()
    {
        return $this->hasMany(ContractImg::class, 'car_id');
    }
    protected $appends = ['image_url'];

    /**
     * Get Added Image Attribute URL.
     *
     * @return string|null
     */
    public function getImageUrlAttribute(): array
    {

        $images = json_decode($this->image);

        if (!is_array($images)) {
            return [];
        }
    
        $imageUrls = [];
    
        foreach ($images as $image) {
            $imageUrl = url('') . "/storage/car/" . $image;
            if (Str::contains($imageUrl, '/car/car/')) {
                $imageUrl = str_replace('/car/car/', '/car/', $imageUrl);
            }
            
            $imageUrls[] = $imageUrl;
        }
    
        return $imageUrls;
    }

    public function carexpenses()
    {
        return $this->hasMany(CarExpenses::class, 'car_id', 'id');
    }

    public function getSumAmountDollarAttribute()
    {
        // Calculate and return the sum of amountDollar for all related expenses
        return $this->carexpenses->sum('amountDollar');
    }

    public function getSumAmountDinarAttribute()
    {
        // Calculate and return the sum of amountDinar for all related expenses
        return $this->carexpenses->sum('amountDinar');
    }
  }