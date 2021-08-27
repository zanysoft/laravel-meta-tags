<?php

namespace ZanySoft\LaravelMetaTags;

use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Class MetaTag
 * @package ZanySoft\LaravelMetaTags
 */
class MetaTag
{
    /**
     * Instance of request
     *
     * @var \Illuminate\Http\Request
     */
    private $request;
    /**
     * @var array
     */
    private $config = [];
    /**
     * Locale default for app.
     *
     * @var string
     */
    private $defaultLocale = '';
    /**
     * @var array
     */
    private $metas = [];

    /**
     * @var array
     */
    private $links = [];
    /**
     * @var string
     */
    private $title;
    /**
     * @var boolean
     */
    private $validate = false;
    /**
     * OpenGraph elements
     *
     * @var array
     */
    private $og = [
        'title',
        'description',
        'site_name',
        'type',
        'image',
        'image:alt',
        'image:url',
        'image:secure_url',
        'image:type',
        'image:width',
        'image:height',
        'url',
        'determiner',
        'locale',
        'locale:alternate',
        'audio',
        'audio:secure_url',
        'audio:type',
        'video',
        'video:secure_url',
        'video:type',
        'video:width',
        'video:height',
        'gallery',
    ];

    /**
     * Twitter card elements
     *
     * @var array
     */
    private $twitter = [
        'card',
        'title',
        'description',
        'url',
        'site',
        'creator',
        'image',
        'image:src',
        'domain',
    ];

    /**
     * @param \Illuminate\Http\Request $request
     */
    public function __construct(Request $request, array $config = [], $defaultLocale = 'en')
    {
        $this->request = $request;
        $this->config = $config;
        $this->defaultLocale = $defaultLocale;

        // Is locales a callback
        if (is_callable($this->config['locales'])) {
            $this->setLocales(call_user_func($this->config['locales']));
        } else {
            $this->setLocales($this->config['locales']);
        }

        $this->validate = ($this->config['validate'] ?? false);

        // Set defaults
        $this->set('title', $this->config['title']);
        $this->set('url', $this->request->url());
    }

    /**
     * @param $key
     * @param null $default
     * @return array|\ArrayAccess|mixed
     */
    public function get($key, $default = null)
    {
        return Arr::get($this->metas, $key, $default);
    }

    /**
     * @param string $key
     * @param string $value
     * @param array $attributes
     * @return string
     */
    public function set($key, $value = null, array $attributes = [])
    {
        $value = $this->fix($value);

        $method = 'set' . Str::studly($key);

        if (method_exists($this, $method)) {
            return $this->$method($value, $attributes);
        }

        $this->metas[$key] = self::cut($value, $key);

        return $this;
    }

    /**
     * Set app support locales.
     *
     * @param array $locals
     */
    public function setLocale($local = '')
    {
        if (Str::contains($local, '_')) {
            list($local) = explode('_', $local);
        }

        if ($local && !in_array($local, $this->config['locales'])) {
            $this->config['locales'][] = $local;
        }

        return $this;
    }

    /**
     * Set app support locales.
     *
     * @param array $locals
     */
    public function setLocales(array $locals = [])
    {
        $this->config['locales'] = [];

        foreach ($locals as $local) {
            $this->setLocale($local);
        }

        return $this;
    }

    /**
     * @param string $value
     * @return string
     */
    public function setTitle($value, $attributes = [])
    {
        $title = $this->title;

        if ($title && $this->config['title_limit']) {
            $title = ' - ' . $title;
            $limit = $this->config['title_limit'] - strlen($title);
        } else {
            $limit = 'title';
        }

        $this->metas['title'] = self::cut($value, $limit) . $title;

        $this->attributes('title', $attributes);

        return $this;
    }

    /**
     * @param string $value
     * @return string
     */
    public function setCanonical($value)
    {
        $this->links['canonical'] = $value;

        return $this;
    }

    /**
     * @param string $value
     * @return string
     */
    public function setDescription($value)
    {
        $limit = 'description';

        $this->metas['description'] = self::cut($value, $limit);
        return $this;
    }

    /**
     * Adds an image tag.
     * If the URL is relative it's converted to an absolute one.
     * @param string $imageFile
     * @param array|null $attributes
     * @return $this
     * @throws \Exception
     */
    public function setImage(string $imageFile, array $attributes = []): self
    {
        if ($this->validate and !$imageFile) {
            throw new Exception("Open Graph: Invalid image URL (empty)");
        }

        if ($this->validate and !filter_var($imageFile, FILTER_VALIDATE_URL)) {
            throw new Exception("Open Graph: Invalid image URL '{$imageFile}'");
        }

        if ($this->validate and strpos($imageFile, '://') === false and function_exists('asset')) {
            $imageFile = asset($imageFile);
        }

        if ($imageFile) {
            $valid = ['secure_url', 'alt', 'url', 'src', 'type', 'width', 'height'];
            $key = 'image';
            if (isset($this->metas['image']) && $this->metas['image']) {
                $image[$key] = $imageFile;
                if (!empty($attributes)) {
                    foreach ($attributes as $name => $value) {
                        if ($this->validate && !in_array($name, $valid)) {
                            throw new Exception("MetaTags: Invalid attribute '{$name}' (unknown type)");
                        }
                        $image[$key . ':' . $name] = $this->convertDate($value);
                    }
                }
                $this->metas['gallery'][] = $image;
            } else {
                $this->metas[$key] = $imageFile;
                if (!empty($attributes)) {
                    $this->attributes($key, $attributes, $valid);
                }
            }
        }

        return $this;
    }


    /**
     * Adds attribute tags to the list of tags
     * @param string $tagName
     * @param array $attributes
     * @param array $valid
     * @return $this
     * @throws \Exception
     */
    private function attributes(string $tagName, array $attributes = [], array $valid = []): self
    {
        foreach ($attributes as $name => $value) {
            if ($this->validate and sizeof($valid) > 0) {
                if (!in_array($name, $valid)) {
                    throw new Exception("MetaTags: Invalid attribute '{$name}' (unknown type)");
                }
            }

            $value = $this->convertDate($value);

            $this->metas[$tagName . ':' . $name] = $value;
        }

        return $this;
    }

    /**
     * Create a tag based on the given key
     *
     * @param string $key
     * @param string $value
     * @return string
     */
    public function tag($key, $value = '')
    {
        if ($key == 'canonical') {
            $content = $value ? $value : (isset($this->links[$key]) ? $this->links[$key] : '');
            $tag = 'link';
        } else {
            $content = $value ? $value : (isset($this->metas[$key]) ? $this->metas[$key] : '');
            $tag = 'meta';
        }

        if (!$content) {
            return '';
        }

        $res = $this->createTag([
            'name' => $key,
            'property' => $key,
            'content' => $content,
        ], $tag);

        return trim($res, "\n");
    }

    /**
     * Create canonical tags
     *
     * @return string
     */
    public function canonical($url = null)
    {
        $url = $url ? $url : (isset($this->links['canonical']) ? $this->links['canonical'] : $this->request->url());

        $html = $this->createTag([
            'rel' => 'canonical',
            'href' => $url
        ], 'link');

        if (!in_array($this->defaultLocale, $this->config['locales'])) {
            $this->config['locales'][] = $this->defaultLocale;
        }

        if (count($this->config['locales']) > 1) {
            foreach ($this->config['locales'] as $value) {
                // Turn current URL into a localized URL
                // using the given lang code
                $localized_url = $this->localizedURL($value, $url);

                $html .= $this->createTag([
                    'rel' => 'alternate',
                    'href' => $localized_url,
                    'hreflang' => $value == $this->defaultLocale ? 'x-default' : $value,
                ], 'link');
            }
        }

        return $html;
    }

    /**
     * Create open graph tags
     *
     * @return string
     */
    public function openGraph()
    {
        $html = [
            'url' => $this->createTag([
                'property' => 'og:url',
                'content' => $this->request->url()
            ])
        ];

        foreach ($this->og as $tag) {
            // Get value for tag, default to dynamically set value
            $value = Arr::get($this->config['open_graph'], $tag, $this->get($tag));
            if (!empty($value)) {
                if ($tag == 'gallery' || is_array($value)) {
                    foreach ($value as $glKey => $image) {
                        foreach ($image as $key => $val) {
                            $html["$tag.$glKey.$key"] = $this->createTag([
                                'property' => "og:{$key}",
                                'content' => $val
                            ]);
                        }
                    }
                } else {

                    $html[$tag] = $this->createTag([
                        'property' => "og:{$tag}",
                        'content' => $value
                    ]);
                }
            }
        }

        $html = implode('', $html);

        /* $gallery = $this->get('gallery');
         if (is_array($gallery) && !empty($gallery)) {
             foreach ($gallery as $image) {
                 foreach ($image as $key => $val) {
                     $html .= $this->createTag([
                         'property' => "og:{$key}",
                         'content' => $val
                     ]);
                 }
             }
         }*/

        return $html;
    }

    /**
     * Create twitter card tags
     *
     * @return string
     */
    public function twitterCard()
    {
        $html = [];

        foreach ($this->twitter as $tag) {
            // Get value for tag, default to dynamically set value
            $value = Arr::get($this->config['twitter'], $tag, $this->get($tag));

            if ($value && !isset($html[$tag])) {
                $html[$tag] = $this->createTag([
                    'property' => "twitter:{$tag}",
                    'content' => $value,
                ]);
            }
        }

        $image = $this->get('image');
        $image_src = $this->get('image:src');

        if (!$image_src && $image) {
            $image_src = $image;
        }

        // Set image
        if (empty($html['image']) && $image) {
            $html['image'] = $this->createTag([
                'name' => "twitter:image",
                'content' => $image
            ]);
        }

        // Set image
        if (empty($html['image:src']) && $image_src) {
            $html['image:src'] = $this->createTag([
                'property' => "twitter:image:src",
                'content' => $image_src
            ]);
        }

        // Set domain
        if (empty($html['domain'])) {
            $html['domain'] = $this->createTag([
                'property' => "twitter:domain",
                'content' => $this->request->getHttpHost()
            ]);
        }

        return implode('', $html);
    }

    /**
     * @return string
     */
    public function renderAll()
    {
        $html = '';
        foreach ($this->metas as $key => $val) {
            if (!in_array($key, ['images', 'gallery', 'canonical', 'type'])) {
                if (!Str::contains($key, ':'))
                    $html .= $this->createTag([
                        'name' => $key,
                        'content' => $val,
                    ]);
            }
        }

        $html .= "\n\t";
        foreach ($this->links as $key => $val) {
            if (!in_array($key, ['images', 'canonical'])) {
                $html .= $this->createTag([
                    'name' => $key,
                    'content' => $val,
                ], 'link');
            }
        }

        $html .= $this->canonical();
        $html .= "\n\t";
        $html .= $this->twitterCard();
        $html .= "\n\t";
        $html .= $this->openGraph();

        return trim($html, "\n");
    }

    /**
     * Create meta tag from attributes
     *
     * @param array $values
     * @return string
     */
    private function createTag(array $values, $tag = 'meta')
    {
        $attributes = array_map(function ($key) use ($values) {
            $value = $this->fix($values[$key]);
            return "{$key}=\"{$value}\"";
        }, array_keys($values));

        $attributes = implode(' ', $attributes);

        return "\n\t<{$tag} {$attributes}>";
    }

    /**
     * @param string $text
     * @return string
     */
    private function fix($text)
    {
        $text = preg_replace('/(*UTF8)<[^>]+>/', ' ', $text, PREG_OFFSET_CAPTURE);
        $text = preg_replace('/(*UTF8)[\r\n\s]+/', ' ', $text, PREG_OFFSET_CAPTURE);

        return trim(str_replace('"', '&quot;', $text));
    }

    /**
     * @param string $text
     * @param string $key
     * @return string
     */
    private function cut($text, $key)
    {
        if (is_string($key) && isset($this->config[$key . '_limit'])) {
            $limit = $this->config[$key . '_limit'];
        } else if (is_integer($key)) {
            $limit = $key;
        } else {
            return $text;
        }

        $length = mb_strlen($text);

        if ($length <= (int)$limit) {
            return $text;
        }

        $text = mb_substr($text, 0, ($limit -= 3));

        if ($space = mb_strrpos($text, ' ')) {
            $text = mb_substr($text, 0, $space);
        }

        return $text . '...';
    }

    /**
     * Returns an URL adapted to locale
     *
     * @param string $locale
     * @return string
     */
    private function localizedURL($locale, $url = null)
    {
        // Default language doesn't get a special subdomain
        $locale = ($locale !== $this->defaultLocale) ? strtolower($locale) : '';

        if ($url) {
            $scheme = $parsed_url['scheme '] ?? 'http';
        } else {
            $scheme = $this->request->getScheme();
        }

        $uri = $this->getUri($url);
        $host = $this->getHost($url);

        // Get host
        $array = explode('.', $host);
        $host = (array_key_exists(count($array) - 2, $array) ? $array[count($array) - 2] : '') . '.' . $array[count($array) - 1];

        $locale_url_patron = $this->config['locale_url'];

        if (Str::contains($locale_url_patron, '?[uri]')) {
            $locale_url_patron = str_replace("?[uri]", '[uri]?', $locale_url_patron);
        }

        if ($locale) {
            if (Str::contains($locale_url_patron, '//[locale]')) {
                $locale .= '.';
            } elseif (Str::contains($locale_url_patron, '[host][locale]')) {
                if (!Str::endsWith($host, '/')) {
                    $locale = '/' . $locale;
                }
            } elseif (Str::contains($locale_url_patron, '?[locale]')) {
                if (Str::contains($uri, '?')) {
                    $locale = '&local=' . $locale;
                } else {
                    $locale = '?local=' . $locale;
                }
                $locale_url_patron = str_replace("?", '', $locale_url_patron);
            }
        }

        // Create URL from template
        $localized_url = str_replace(
            ['[scheme]', '[locale]', '[host]', '[uri]'],
            [$scheme, $locale, $host, $uri],
            $locale_url_patron
        );

        return url(rtrim($localized_url, '?&/'));
    }

    /**
     * @param null $url
     * @return string
     */
    private function getHost($url = null)
    {
        if ($url) {
            $parsed_host = parse_url($url, PHP_URL_HOST);
            $parsed_host = strtolower(preg_replace('/:\d+$/', '', trim($parsed_host)));
            $array = explode('.', $parsed_host);
        } else {
            $array = explode('.', $this->request->getHttpHost());

        }

        return (array_key_exists(count($array) - 2, $array) ? $array[count($array) - 2] : '') . '.' . $array[count($array) - 1];
    }

    /**
     * @param null $url
     * @return string
     */
    private function getUri($url = null)
    {
        if ($url) {
            $parsed_uri = '';
            $parsed_url = parse_url($url);

            if (isset($parsed_url['path'])) {
                $parsed_uri = $parsed_url['path'];
            }

            if (isset($parsed_url['query'])) {
                $parsed_uri .= '?' . $parsed_url['query'];
            }

            $uri = $parsed_uri;
        } else {
            $uri = $this->request->getPathInfo();
        }

        return '/' . trim($uri, '/');
    }

    /**
     * Remove all tags with the given name
     *
     * @param string $name
     * @return MetaTag
     */
    public function forget(string $name): self
    {
        foreach ($this->metas as $key => $tag) {
            if ($key == $name) {
                unset($this->metas[$key]);
            }
        }

        return $this;
    }

    /**
     * Remove all tags
     *
     * @return MetaTag
     */
    public function clear(): self
    {
        $this->metas = [];

        return $this;
    }

    /**
     * True if at least one tag with the given name exists.
     * It's possible that a tag has multiple values.
     *
     * @param string $name
     * @return bool
     */
    public function has(string $name): bool
    {
        foreach ($this->metas as $key => $tag) {
            if ($key == $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * Adds a type tag.
     * @param string $type
     * @return string
     */
    private function setType(string $type)
    {
        $types = [
            'music.song',
            'music.album',
            'music.playlist',
            'music.radio_station',
            'video.movie',
            'video.episode',
            'video.tv_show',
            'video.other',
            'article',
            'book',
            'profile',
            'website',
        ];

        if (!in_array($type, $types)) {
            throw new \Exception("Open Graph: Invalid type '{$type}' (unknown type)");
        }

        $this->metas['type'] = $type;

        return $this;
    }

    /**
     * Create Facebook app ID tag
     *
     * @return string
     */
    public function fbAppId()
    {
        return $this->createTag([
            'property' => 'fb:app_id',
            'content' => $this->get('fb:app_id'),
        ]);
    }

    /**
     * Converts a DateTime object to a string (ISO 8601)
     *
     * @param string|DateTime $date The date (string or DateTime)
     * @return string
     */
    protected function convertDate($date): string
    {
        if (is_a($date, 'DateTime')) {
            return (string)$date->format(DateTime::ISO8601);
        }

        return $date;
    }

    /**
     * Returns the last tag in the lists of tags with matching name
     *
     * @param string $name The name of the tag
     * @return string|null       Returns the tag object or null
     */
    public function lastTag(string $name)
    {
        $lastTag = null;

        foreach ($this->metas as $key => $tag) {
            if ($key == $name) {
                $lastTag = $tag;
            }
        }

        return $lastTag;
    }

    public function __call($method, $args)
    {
        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], $args);
        }

        $set_method = 'set' . ucfirst($method);
        $get_method = 'get' . ucfirst($method);

        if (method_exists($this, $set_method)) {
            return call_user_func_array([$this, $set_method], $args);
        } elseif (method_exists($this, $get_method)) {
            return call_user_func_array([$this, $get_method], $args);
        } else {
            if (substr($method, 0, strlen('set')) === (string)'set') {
                $method = strtolower(str_replace('set', '', $method));
                return call_user_func_array([$this, 'set'], array_merge([$method], $args));
            }

            if (substr($method, 0, strlen('get')) === (string)'get') {
                $method = strtolower(str_replace('get', '', $method));
                if (method_exists($this, $method)) {
                    return call_user_func_array([$this, $method], $args);
                } else {
                    return call_user_func_array([$this, 'get'], array_merge([$method], $args));
                }
            }

            return call_user_func_array([$this, 'set'], array_merge([$method], $args));
        }
    }
}
