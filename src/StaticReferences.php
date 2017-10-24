<?php

namespace AvtoDev\StaticReferencesLaravel;

use ReflectionClass;
use Illuminate\Support\Str;
use Illuminate\Cache\Repository as CacheRepository;
use AvtoDev\StaticReferencesLaravel\Traits\InstanceableTrait;
use AvtoDev\StaticReferencesLaravel\References\ReferenceInterface;
use AvtoDev\StaticReferencesLaravel\Exceptions\InvalidReferenceException;
use AvtoDev\StaticReferencesLaravel\PreferencesProviders\AutoCategoriesProvider;
use AvtoDev\StaticReferencesLaravel\PreferencesProviders\ReferenceProviderInterface;

/**
 * Class StaticReferences.
 */
class StaticReferences implements StaticReferencesInterface
{
    use InstanceableTrait;

    /**
     * Префикс для ключей кэша.
     *
     * @var string
     */
    const CACHE_KEY_PREFIX = 'reference_';

    /**
     * Стек инстансов **провайдеров** справочников.
     *
     * @var ReferenceProviderInterface[]|array
     */
    protected $providers = [];

    /**
     * Карта связей вида "%бинд_провайдера% => &%ссылка_на_инстанс_его_провайдера%".
     *
     * @var ReferenceProviderInterface[]|array
     */
    protected $binds_map = [];

    /**
     * Стек инстансов самих **справочников**, вида "%класс_провайдера_справочника% => %инстанс_провайдера_справочника%".
     *
     * @var ReferenceInterface[]|array
     */
    protected $references = [];

    /**
     * Конфигурация, используемая по-умолчанию.
     *
     * @var array
     */
    protected $config = [
        'cache'     => [
            'enabled'  => true,
            'lifetime' => 0,
            'store'    => 'auto',
        ],
        'providers' => [
            AutoCategoriesProvider::class,
        ],
    ];

    /**
     * StaticReferences constructor.
     *
     * @param array $config
     *
     * @throws InvalidReferenceException
     */
    public function __construct(array $config = [])
    {
        $this->config = array_replace_recursive($this->config, $config);

        $this->initProviders();
    }

    /**
     * {@inheritdoc}
     */
    public function __get($name)
    {
        return $this->make($name);
    }

    /**
     * {@inheritdoc}
     */
    public function &make($bind_name)
    {
        // Ищем среди биндов ссылку на провайдер искомого справочника
        if (is_string($bind_name) && isset($this->binds_map[$bind_name])) {
            /** @var ReferenceProviderInterface $provider */
            $provider_class = get_class($provider = $this->binds_map[$bind_name]);

            // Если в стеке инстансов справочников НЕ присутствует необходимый нам - то создаём его
            if (! isset($this->references[$provider_class])) {
                $cache_enabled = $this->config['cache']['enabled'] === true;
                $cache_key     = static::CACHE_KEY_PREFIX . Str::snake($provider_class);

                // Ищем инстанс справочника в кэше, то сразу его и извлекаем
                if ($cache_enabled && $this->getCacheRepository()->has($cache_key)) {
                    $this->references[$provider_class] = $this->getCacheRepository()->get($cache_key);
                } else {
                    // В противном случае - создаем его
                    $this->references[$provider_class] = ($instance = $provider->instance());

                    // И помещаем в кэш
                    if ($cache_enabled) {
                        if (($lifetime = (int) $this->config['cache']['lifetime']) > 0) {
                            $this->getCacheRepository()->put($cache_key, $instance, $lifetime);
                        } else {
                            $this->getCacheRepository()->forever($cache_key, $instance);
                        }
                    }
                }
            }

            return $this->references[$provider_class];
        }

        throw new InvalidReferenceException(sprintf('Invalid reference bind: "%s"', $bind_name));
    }

    /**
     * Производит инициализацию провайдеров справочников, не вызывая конструкторы самих справочников.
     *
     * @throws InvalidReferenceException
     *
     * @return void
     */
    protected function initProviders()
    {
        foreach ($this->config['providers'] as $reference_provider_class) {
            if (class_exists($reference_provider_class)) {
                $reflection = new ReflectionClass($reference_provider_class);

                if ($reflection->implementsInterface(ReferenceProviderInterface::class)) {
                    // Создаём инстанс провайдера справочника и сразу же пушим его в стек
                    /* @var ReferenceProviderInterface $provider */
                    array_push($this->providers, $provider = new $reference_provider_class());

                    // Автоматически в бинды добавляем имя класса его провайдера
                    $binds = array_unique(
                        array_merge(array_flatten((array) $provider->binds()), [$reference_provider_class])
                    );

                    // Обновляем карту связей-биндов
                    foreach ($binds as $bind_name) {
                        if (is_string($bind_name) && ! empty($bind_name)) {
                            $this->binds_map[$bind_name] = $provider;
                        }
                    }

                    // Вызываем boot-метод
                    $provider->boot($this);

                    // Если провайдер справочника не-deferred, то сразу же создаём его инстанс
                    if ($provider->isDeferred() !== true) {
                        $this->make($reference_provider_class);
                    }
                } else {
                    throw new InvalidReferenceException(sprintf(
                        'Reference provider class must implements "%s" interface',
                        ReferenceProviderInterface::class
                    ));
                }
            }
        }
    }

    /**
     * Возвращает инстанс кэша.
     *
     * @return CacheRepository
     */
    protected function getCacheRepository()
    {
        static $instance = null;

        if (! ($instance instanceof CacheRepository)) {
            $storage = ($name = $this->config['cache']['store']) === 'auto'
                ? config('cache.default')
                : $name;

            $instance = app()->make('cache')->store($storage);
        }

        return $instance;
    }
}
