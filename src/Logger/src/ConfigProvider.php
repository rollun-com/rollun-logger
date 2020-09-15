<?php
/**
 * @copyright Copyright © 2014 Rollun LC (http://rollun.com/)
 * @license   LICENSE.md New BSD License
 */

namespace rollun\logger;

use Psr\Log\LoggerInterface;
use rollun\logger\Filter\TurboSmsFilter;
use rollun\logger\Formatter\ContextToString;
use rollun\logger\Formatter\FluentdFormatter;
use rollun\logger\Formatter\LogStashUdpFormatter;
use rollun\logger\Formatter\SlackFormatter;
use rollun\logger\Processor\ExceptionBacktrace;
use rollun\logger\Processor\Factory\LifeCycleTokenReferenceInjectorFactory;
use rollun\logger\Processor\IdMaker;
use rollun\logger\Processor\LifeCycleTokenInjector;
use rollun\logger\Prometheus\Collector;
use rollun\logger\Prometheus\PushGateway;
use rollun\logger\Writer\Factory\PrometheusFactory;
use rollun\logger\Writer\PrometheusWriter;
use rollun\logger\Writer\Slack;
use rollun\logger\Writer\Udp;
use rollun\logger\Writer\HttpAsyncMetric;
use rollun\logger\Formatter\Metric;
use Zend\Log\LoggerAbstractServiceFactory;
use Zend\Log\LoggerServiceFactory;
use Zend\Log\FilterPluginManagerFactory;
use Zend\Log\FormatterPluginManagerFactory;
use Zend\Log\ProcessorPluginManagerFactory;
use Zend\Log\Writer\Db;
use Zend\Log\Writer\Stream;
use Zend\Log\WriterPluginManagerFactory;
use Zend\Log\Logger;
use Zend\ServiceManager\Factory\InvokableFactory;

class ConfigProvider
{
    /**
     * Return default logger config
     */
    public function __invoke()
    {
        return [
            "dependencies"   => $this->getDependencies(),
            "log"            => $this->getLog(),
            'log_processors' => $this->getLogProcessors(),
            'log_formatters' => $this->getLogFormatters(),
            'log_writers'    => $this->getLogWriters(),
        ];
    }

    protected function getLogProcessors()
    {
        return [
            'factories' => [
                LifeCycleTokenInjector::class => LifeCycleTokenReferenceInjectorFactory::class,
                IdMaker::class                => InvokableFactory::class
            ],
        ];
    }

    protected function getLogFormatters()
    {
        return [
            'factories' => [
                ContextToString::class  => InvokableFactory::class,
                FluentdFormatter::class => InvokableFactory::class,
            ],
        ];
    }

    /**
     * @return array
     */
    protected function getLogWriters()
    {
        return [
            'factories' => [
                PrometheusWriter::class => PrometheusFactory::class,
            ],
        ];
    }

    /**
     * Return dependencies config
     *
     * @return array
     */
    public function getDependencies()
    {
        return [
            'abstract_factories' => [
                LoggerAbstractServiceFactory::class,
            ],
            'factories'          => [
                Logger::class         => LoggerServiceFactory::class,
                'LogFilterManager'    => FilterPluginManagerFactory::class,
                'LogFormatterManager' => FormatterPluginManagerFactory::class,
                'LogProcessorManager' => ProcessorPluginManagerFactory::class,
                'LogWriterManager'    => WriterPluginManagerFactory::class,
            ],
            'invokables'         => [
                PushGateway::class => PushGateway::class,
                Collector::class   => Collector::class
            ],
            'aliases'            => [],
        ];
    }

    /**
     * Return default config for logger.
     *
     * @return array
     */
    public function getLog()
    {
        return [
            LoggerInterface::class => [
                'writers'    => [
                    [
                        'name'    => Stream::class,
                        'options' => [
                            'stream'    => 'php://stdout',
                            'formatter' => FluentdFormatter::class
                        ],
                    ],
                    [
                        'name' => Udp::class,

                        'options' => [
                            'client'    => [
                                'host' => getenv('LOGSTASH_HOST'),
                                'port' => getenv('LOGSTASH_PORT'),
                            ],
                            'formatter' => new LogStashUdpFormatter(
                                getenv("LOGSTASH_INDEX"),
                                [
                                    'timestamp'              => 'timestamp',
                                    'message'                => 'message',
                                    'level'                  => 'level',
                                    'priority'               => 'priority',
                                    'context'                => 'context',
                                    'lifecycle_token'        => 'lifecycle_token',
                                    'parent_lifecycle_token' => 'parent_lifecycle_token',
                                    '_index_name'            => '_index_name'
                                ]
                            ),
                            'filters'   => [
                                [
                                    'name'    => 'priority',
                                    'options' => [
                                        'operator' => '<',
                                        'priority' => 4,
                                    ],
                                ],
                                [
                                    'name'    => 'regex',
                                    'options' => [
                                        'regex' => '/^((?!METRICS).)*$/'
                                    ],
                                ],
                            ],
                        ],
                    ],
                    [
                        'name'    => HttpAsyncMetric::class,
                        'options' => [
                            'url'       => getenv('METRIC_URL'),
                            'filters'   => [
                                [
                                    'name'    => 'priority',
                                    'options' => [
                                        'operator' => '>=',
                                        'priority' => 4, // we should send only warnings or notices
                                    ],
                                ],
                                [
                                    'name'    => 'priority',
                                    'options' => [
                                        'operator' => '<=',
                                        'priority' => 5, // we should send only warnings or notices
                                    ],
                                ],
                                [
                                    'name'    => 'regex',
                                    'options' => [
                                        'regex' => '/^METRICS$/'
                                    ],
                                ],
                            ],
                            'formatter' => Metric::class,
                        ],
                    ],
                    [
                        PrometheusFactory::COLLECTOR => Collector::class, // не обязательный параметр.
                        PrometheusFactory::JOB_NAME  => 'logger_job',  // не обязательный параметр.
                        'name'                       => PrometheusWriter::class,
                        'options'                    => [
                            PrometheusFactory::TYPE => PrometheusFactory::TYPE_GAUGE,
                            'filters'               => [
                                [
                                    'name'    => 'regex',
                                    'options' => [
                                        'regex' => '/^METRICS_GAUGE$/'
                                    ],
                                ],
                                [
                                    'name'    => 'priority',
                                    'options' => [
                                        'operator' => '>=',
                                        'priority' => 4, // we should send only warnings or notices
                                    ],
                                ],
                                [
                                    'name'    => 'priority',
                                    'options' => [
                                        'operator' => '<=',
                                        'priority' => 5, // we should send only warnings or notices
                                    ],
                                ],
                            ]
                        ],
                    ],
                    [
                        'name'    => PrometheusWriter::class,
                        'options' => [
                            PrometheusFactory::TYPE => PrometheusFactory::TYPE_COUNTER,
                            'filters'               => [
                                [
                                    'name'    => 'regex',
                                    'options' => [
                                        'regex' => '/^METRICS_COUNTER$/'
                                    ],
                                ],
                                [
                                    'name'    => 'priority',
                                    'options' => [
                                        'operator' => '>=',
                                        'priority' => 4, // we should send only warnings or notices
                                    ],
                                ],
                                [
                                    'name'    => 'priority',
                                    'options' => [
                                        'operator' => '<=',
                                        'priority' => 5, // we should send only warnings or notices
                                    ],
                                ],
                            ]
                        ],
                    ],
                    [
                        'name'    => Slack::class,
                        'options' => [
                            'token'     => getenv('SLACK_TOKEN'),
                            'channel'   => getenv('SLACK_CHANNEL'),
                            'filters'   => [
                                [
                                    'name'    => 'regex',
                                    'options' => [
                                        'regex' => '/^((?!METRICS).)*$/'
                                    ],
                                ],
                                [
                                    'name'    => 'priority',
                                    'options' => [
                                        'operator' => '<',
                                        'priority' => 4,
                                    ],
                                ],
                            ],
                            'formatter' => SlackFormatter::class,
                        ],
                    ],
                    [
                        'name' => Db::class,
                        'options' => [
                            'db' => 'db',
//                            'db' => 'sms_db', // todo: if at db.global.php added 'adapter' use it here ('sms_db')
                            'table' => 'saasebay',
                            'column' => [
                                'message' => 'message',
                                'context' => [
                                    'sign' => 'sign',
                                    'number' => 'number',
                                ],
                            ],
                            'filters' => [
                                [
                                    'name' => 'priority',
                                    'options' => [
                                        'operator' => getenv('APP_DEBUG') == 'true' ? '<=' : '<',
                                        'priority' => 7,
                                    ],
                                ],
                                [
                                    'name' => TurboSmsFilter::class,
                                    'options' => [
                                        'ttl' => '24h'
                                    ]
                                ],
                                [
                                    'name'    => 'regex',
                                    'options' => [
                                        'regex' => '/^SMS_ALERT/'
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
                'processors' => [
                    [
                        'name' => IdMaker::class,
                    ],
                    [
                        'name' => ExceptionBacktrace::class
                    ],
                    [
                        'name' => LifeCycleTokenInjector::class,
                    ],
                ],
            ],
        ];
    }
}
