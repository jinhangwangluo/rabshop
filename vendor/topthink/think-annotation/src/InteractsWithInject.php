<?php

namespace think\annotation;

use ReflectionObject;
use think\App;

/**
 * Trait InteractsWithInject
 * @package think\annotation\traits
 * @property App $app
 */
trait InteractsWithInject
{
    protected function autoInject()
    {
        if ($this->app->config->get('annotation.inject.enable', true)) {
            $this->app->resolving(function ($object, $app) {
                if ($this->isInjectClass(get_class($object))) {
                    $refObject = new ReflectionObject($object);
                    foreach ($refObject->getProperties() as $refProperty) {
                        if ($refProperty->isDefault() && !$refProperty->isStatic()) {
                            $attrs = $refProperty->getAttributes(Inject::class);
                            if (!empty($attrs)) {
                                if (!empty($attrs[0]->getArguments()[0])) {
                                    $type = $attrs[0]->getArguments()[0];
                                } elseif ($refProperty->getType() && !$refProperty->getType()->isBuiltin()) {
                                    $type = $refProperty->getType()->getName();
                                }

                                if (isset($type)) {
                                    $value = $app->make($type);
                                    // PHP >= 8.1 下 setAccessible 已无效且在 8.5 触发 deprecated
                                    // 直接赋值即可，避免运行时告警升级为异常
                                    $refProperty->setValue($object, $value);
                                }
                            }
                        }
                    }
                    if ($refObject->hasMethod('__injected')) {
                        $app->invokeMethod([$object, '__injected']);
                    }
                }
            });
        }
    }

    protected function isInjectClass($name)
    {
        $namespaces = ['app\\'] + $this->app->config->get('annotation.inject.namespaces', []);

        foreach ($namespaces as $namespace) {
            $namespace = rtrim($namespace, '\\') . '\\';

            if (0 === stripos(rtrim($name, '\\') . '\\', $namespace)) {
                return true;
            }
        }
    }
}
