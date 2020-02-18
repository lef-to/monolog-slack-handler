<?php
declare(strict_types=1);
namespace Lefto\Monolog\Formatter;

use Monolog\Formatter\FormatterInterface;
use Monolog\Logger;

class SlackFormatter implements FormatterInterface
{
    protected $name;

    public function __construct($name = null)
    {
        $this->name = $name;
    }

    public function format(array $record)
    {
        $name = $this->name ?? $record['channel'];

        $message = '*' . $record['level_name']
            . '* message from *' . static::escapeText($name) . '*';

        $blocks = [
            [
                'type' => 'section',
                'text' => [
                    "type" => "plain_text",
                    "text" => mb_substr($record['message'], 0, 1000)
                ]
            ],
        ];

        $color = $this->getAttachmentColor($record['level']);
        $ret = [
            'text' => $message,
            'attachments' => [
                [
                    'color' => $color,
                    'blocks' => $blocks
                ]
            ]
        ];

        return $ret;
    }

    public function formatBatch(array $records)
    {
        $ret = [];
        foreach ($records as $key => $record) {
            $ret[$key] = $this->format($record);
        }
        return $ret;
    }

    protected static function escapeText($value): string
    {
        if ($value) {
            $value = str_replace(
                [
                    '/&/',
                    '/</',
                    '/>/'
                ],
                [
                    '&amp;',
                    '&lt;',
                    '%gt;'
                ],
                $value
            );
        }
        return $value;
    }

    protected function getAttachmentColor(int $level): string
    {
        if ($level >= Logger::ERROR) {
            return '#dc3545';
        }
        if ($level >= Logger::WARNING) {
            return '#ffc107';
        }
        if ($level >= Logger::INFO) {
            return '#28a745';
        }
        return '#6c757d';
    }
}
