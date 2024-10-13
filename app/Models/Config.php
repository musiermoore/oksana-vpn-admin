<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Config extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
    ];

    public function user()
    {
        return $this->belongsTo(User::class)
            ->withTrashed();
    }

    public function getPathAttribute()
    {
        return storage_path('app/configs/' . $this->name . '.conf');
    }

    public function getAddressAttribute()
    {
        try {
            $content = file_get_contents($this->path);

            if (preg_match('/^Address\s*=\s*(.+)$/m', $content, $matches)) {
                // Return the address without any leading/trailing whitespace
                return trim($matches[1]);
            }

            return 'Файл не найден.';
        } catch (\Exception $exception) {
            return 'Ошибка при загрузке файла.';
        }
    }
}
