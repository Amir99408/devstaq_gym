<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GymSetting extends Model
{
    protected $fillable = [
        'gym_name', 'tagline', 'about', 'address', 'phone', 'email',
        'logo_path', 'hero_image_path', 'favicon_path', 'facebook_url', 'instagram_url',
        'whatsapp', 'google_maps_embed_url', 'primary_color', 'secondary_color', 'meta_title', 'meta_description',
    ];

    public static function current(): self
    {
        return static::firstOrCreate([], ['gym_name' => 'My Gym']);
    }
}
