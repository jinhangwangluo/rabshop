<?php
// +----------------------------------------------------------------------
// | Rabbit多商户商城系统
// +----------------------------------------------------------------------
// | Copyright (c) 2022~2026 https://www.rabshop.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed Rabbit多商户商城系统不是自由软件，未经许可不能去掉Rabbit相关版权
// +----------------------------------------------------------------------
// | Author: Rabbit Team <admin@rabshop.cn>
// +----------------------------------------------------------------------

namespace Rabbit\service\wechat\message;


use Rabbit\service\wechat\WechatException;
use EasyWeChat\Kernel\Support\Arr;
use think\helper\Str;

trait HasAttributes
{
    /**
     * @var array
     */
    protected array $attributes = [];

    /**
     * @var bool
     */
    protected bool $snakeable = true;

    /**
     * Set Attributes.
     *
     * @param array $attributes
     *
     * @return HasAttributes
     */
    public function setAttributes(array $attributes = []): self
    {
        $this->attributes = $attributes;

        return $this;
    }

    /**
     * Set attribute.
     *
     * @param string $attribute
     * @param string $value
     *
     * @return $this
     */
    public function setAttribute(string $attribute, string $value)
    {
        Arr::set($this->attributes, $attribute, $value);

        return $this;
    }

    /**
     * Get attribute.
     *
     * @param string $attribute
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function getAttribute(string $attribute, mixed $default = null)
    {
        return Arr::get($this->attributes, $attribute, $default);
    }

    /**
     * @param string $attribute
     *
     * @return bool
     */
    public function isRequired(string $attribute)
    {
        return in_array($attribute, $this->getRequired(), true);
    }

    /**
     * @return array|mixed
     */
    public function getRequired(): mixed
    {
        return property_exists($this, 'required') ? $this->required : [];
    }

    /**
     * Set attribute.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return $this
     */
    public function with(string $attribute, mixed $value)
    {
        $this->snakeable && $attribute = Str::snake($attribute);

        $this->setAttribute($attribute, $value);

        return $this;
    }

    /**
     * Override parent set() method.
     *
     * @param string $attribute
     * @param mixed $value
     *
     * @return $this
     */
    public function set(string $attribute, mixed $value)
    {
        $this->setAttribute($attribute, $value);

        return $this;
    }

    /**
     * Override parent get() method.
     *
     * @param string $attribute
     * @param mixed|null $default
     *
     * @return mixed
     */
    public function get(string $attribute, mixed $default = null)
    {
        return $this->getAttribute($attribute, $default);
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function has(string $key)
    {
        return Arr::has($this->attributes, $key);
    }

    /**
     * @param array $attributes
     *
     * @return $this
     */
    public function merge(array $attributes)
    {
        $this->attributes = array_merge($this->attributes, $attributes);

        return $this;
    }

    /**
     * @param array|string $keys
     *
     * @return array
     */
    public function only(array|string $keys)
    {
        return array_intersect_key($this->attributes, array_flip((array)$keys));
    }

    /**
     * Return all items.
     *
     * @return array
     *
     */
    public function all()
    {
        $this->checkRequiredAttributes();

        return $this->attributes;
    }

    /**
     * Magic call.
     *
     * @param string $method
     * @param array $args
     *
     * @return $this
     */
    public function __call($method, $args)
    {
        if (0 === stripos($method, 'with')) {
            return $this->with(substr($method, 4), array_shift($args));
        }

        throw new \BadMethodCallException(sprintf('Method "%s" does not exists.', $method));
    }

    /**
     * Magic get.
     *
     * @param string $property
     *
     * @return mixed
     */
    public function __get($property)
    {
        return $this->get($property);
    }

    /**
     * Magic set.
     *
     * @param string $property
     * @param mixed $value
     *
     * @return $this
     */
    public function __set($property, $value)
    {
        return $this->with($property, $value);
    }

    /**
     * Whether or not an data exists by key.
     *
     * @param string $key
     *
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->attributes[$key]);
    }

    /**
     * Check required attributes.
     *
     */
    protected function checkRequiredAttributes()
    {
        foreach ($this->getRequired() as $attribute) {
            if (is_null($this->get($attribute))) {
                throw new WechatException(sprintf('"%s" cannot be empty.', $attribute));
            }
        }
    }
}
