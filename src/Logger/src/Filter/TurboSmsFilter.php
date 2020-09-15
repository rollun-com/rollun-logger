<?php


namespace rollun\logger\Filter;


use Psr\SimpleCache\InvalidArgumentException;
use Zend\Cache\Storage\StorageInterface;
use Zend\Cache\StorageFactory;
use Zend\Log\Filter\FilterInterface;

class TurboSmsFilter implements FilterInterface
{
    /** @var StorageInterface */
    private $cache;

    public function __construct($options)
    {
        //todo: 1) check if we have configured Redis adapter use it; 2) if not - use Filesystem with options
        $this->cache = StorageFactory::factory([
            'adapter' => [
                'name'    => 'filesystem',
                'options' => ['ttl' => 15 ], // todo: get this from configured options
            ],
            'plugins' => [
                'exception_handler' => ['throw_exceptions' => true],
            ],
        ]);
    }

    /**
     * @param array $event
     * @return bool|void
     * @throws InvalidArgumentException
     */
    public function filter(array $event): bool
    {
        $key = $this->getKey($event);
        if (!$this->cache->getItem($key)) {
            $this->cache->setItem($key, $event['id']);
            return true;
        }
        return false;
    }

    protected function getKey(array $event)
    {
        return sprintf('%s_%s', $event['level'], $event['context']['message']); //todo: maybe add $event['ID']????
    }
}
