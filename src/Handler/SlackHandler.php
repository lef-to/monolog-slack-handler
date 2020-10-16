<?php
declare(strict_types=1);
namespace Lefto\Monolog\Handler;

use MonoLog\Logger;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Formatter\FormatterInterface;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Exception\ConnectException;
use Lefto\Monolog\Formatter\SlackFormatter;
use Exception;

class SlackHandler extends AbstractProcessingHandler
{
    /**
     * @var string
     */
    protected $url;

    /**
     * @var int
     */
    protected $retryCount;

    /**
     * @var callable|null
     */
    protected $retryDelay;

    /**
     * @var bool
     */
    protected $throwException;

    public function __construct(
        $url,
        $level = Logger::ERROR,
        bool $bubble = true,
        $retryCount = 0,
        callable $retryDelay = null,
        bool $throwException = false
    ) {
        parent::__construct($level, $bubble);
        $this->url = $url;
        $this->retryCount = $retryCount;
        $this->retryDelay = $retryDelay;
        $this->throwException  = $throwException;
    }

    protected function write(array $record): void
    {
        $message = $record['formatted'];

        try {
            $client = $this->createClient();
            $client->request('POST', $this->url, [ 'json' => $message ]);
        } catch (Exception $ex) {
            if ($this->throwException) {
                throw $ex;
            } else {
                error_log('Failed to send log to Slack: ' . $ex->getMessage());
            }
        }
    }

    protected function getDefaultFormatter(): FormatterInterface
    {
        return new SlackFormatter();
    }

    protected function createClient(): Client
    {
        $retryCount = $this->retryCount;
        if ($retryCount) {
            $stack = HandlerStack::create();
            $retryMiddleware = Middleware::retry(
                static function ($retries, $request, $response, $exception) use ($retryCount) {
                    if ($retryCount < $retries) {
                        return false;
                    }

                    if ($exception instanceof ConnectException) {
                        return true;
                    }

                    return false;
                },
                $this->retryDelay
            );

            $stack->push($retryMiddleware);
            return new Client([ 'handler' => $stack ]);
        }
        return new Client();
    }
}
