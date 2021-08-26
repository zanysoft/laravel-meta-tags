<?php

namespace ZanySoft\LaravelMetaTags\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \ZanySoft\LaravelMetaTags\MetaTag set($key, $value = null, $attributes = [])
 * @method static \ZanySoft\LaravelMetaTags\MetaTag site($url)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag setSite($url)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag domain($url)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag setDomain($url)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag creator($value)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag setCreator($value)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag card($value)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag setCard($value)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag title($value)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag setTitle($value)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag description($value)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag setDescription($value)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag setUrl($value)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag url($value)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag locale($value)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag setLocale($value)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag video($value)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag setVideo($value)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag image(string $imageFile, array $attributes = null)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag setImage(string $imageFile, array $attributes = null)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag type(string $type)
 * @method static \ZanySoft\LaravelMetaTags\MetaTag setType(string $type)
 */
class MetaTag extends Facade
{
    /**
     * Name of the binding in the IoC container
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'metatag';
    }
}
