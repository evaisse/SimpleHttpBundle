<?php

namespace evaisse\SimpleHttpBundle\Twig;

use Symfony\Component\BrowserKit\Exception\JsonException;
use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Extension\AbstractExtension;
use Twig\Loader\LoaderInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

class Extension extends AbstractExtension
{
    public function __construct(protected LoaderInterface $loader)
    {
    }

    public function getName(): string
    {
        return 'simple_http_extension';
    }

    public function getFilters(): array
    {
        $safe = array('is_safe' => array('html'));

        return [
            new TwigFilter('simple_http_beautify', array($this, 'format'), $safe),
            new TwigFilter('simple_http_format_http_code', array($this, 'formatHttpCode'), $safe),
            new TwigFilter('simple_http_format_http_code_as_badge', array($this, 'formatHttpCodeAsSfBadge'), $safe),
            new TwigFilter('simple_http_md5', 'md5'),
            new TwigFilter('simple_http_include_asset', array($this, 'assetInclude'), $safe),
            new TwigFilter('simple_http_format_ms', array($this, 'formatMilliseconds')),
            new TwigFilter('simple_http_format_num', array($this, 'numberFormat')),
            new TwigFilter('simple_json_decode', 'json_decode'),
        ];
    }

    public function getFunctions(): array
    {
        $safe = array('is_safe' => array('html'));

        return [
            new TwigFunction('simple_http_decode_body', array($this, 'decodeBody'), $safe),
        ];
    }

    public function numberFormat(int|float $number, int $decimals = 0): string
    {
        static $locale;

        $locale = $locale ?: localeconv();

        return number_format($number, $decimals, $locale['decimal_point'], $locale['thousands_sep']);
    }

    public function formatMilliseconds(float|int $ms): string
    {
        if ($ms >= 1) {
            return $this->numberFormat($ms, 1) .  ' s';
        }

        return $this->numberFormat($ms * 1000) . " ms";
    }

    public function assetInclude(string $file): string
    {
        try {
            return $this->loader->getSourceContext($file)->getCode();
        } catch (LoaderError) {
            return '';
        }
    }

    /**
     * @param int|array $codeOrResponse response data or just an http code
     * @return string
     */
    public function formatHttpCode(int|array $codeOrResponse): string
    {
        $d = $this->fetchInfosFromCodeOrResponse($codeOrResponse);
        return '<span class="http-status badge ' . $d['level'] . ' ' .
            ($d['fromCache'] ? 'http-cache-hit' : '') . '"><abbr title="' .
            htmlentities($d['text']) . '">' . $d['code'] . ($d['fromCache'] ? ' <small>+cached</small>' : '') .
            '</abbr></span>';
    }


    /**
     * @param int|array $codeOrResponse response data or just an http code
     * @return string
     */
    public function formatHttpCodeAsSfBadge(int|array $codeOrResponse): string
    {
        $d = $this->fetchInfosFromCodeOrResponse($codeOrResponse);
        return '<span class="http-status sf-toolbar-status sf-toolbar-status-' . $d['color'] . ' ' .
            ($d['fromCache'] ? 'http-cache-hit' : '') . '">'
            . '<abbr style="border:none" title="' . htmlentities($d['text']) . '">' . $d['code'] .
            ($d['fromCache'] ? '<small>+cache</small>' : '') . '</abbr>'
            . '</span>';
    }

    public function format(string $code, string $contentType): string
    {
        $class = array('hljs');
        if (str_contains($contentType, 'application/json')) {
            $class[] = 'json';
            $code = @$this->formatJson($code);
        } elseif (str_contains($contentType, 'application/xml')) {
            $class[] = 'xml';
            $code = @$this->formatXml($code);
        } else {
            $class[] = 'html';
        }

        return '<pre class="' . implode(' ', $class) . '">' . htmlentities($code) . '</pre>';
    }

    public function formatXml(string $xml): string
    {
        $domxml = new \DOMDocument('1.0');
        $domxml->preserveWhiteSpace = false;
        $domxml->formatOutput = true;
        $domxml->loadXML($xml);

        return $domxml->saveXML();
    }

    public function formatJson(string $json): string
    {
        $result = '';
        $level = 0;
        $in_quotes = false;
        $in_escape = false;
        $ends_line_level = null;
        $json_length = strlen($json);

        for ($i = 0; $i < $json_length; $i++) {
            $char = $json[$i];
            $new_line_level = null;
            $post = "";
            if ($ends_line_level !== null) {
                $new_line_level = $ends_line_level;
                $ends_line_level = null;
            }
            if ($in_escape) {
                $in_escape = false;
            } elseif ($char === '"') {
                $in_quotes = !$in_quotes;
            } elseif (!$in_quotes) {
                switch ($char) {
                    case '}':
                    case ']':
                        $level--;
                        $ends_line_level = null;
                        $new_line_level = $level;
                        break;
                    case '{':
                    case '[':
                        $level++;
                        // intentional no break
                    case ',':
                        $ends_line_level = $level;
                        break;

                    case ':':
                        $post = " ";
                        break;

                    case " ":
                    case "\t":
                    case "\n":
                    case "\r":
                        $char = "";
                        $ends_line_level = $new_line_level;
                        $new_line_level = null;
                        break;
                }
            } elseif ($char === '\\') {
                $in_escape = true;
            }
            if ($new_line_level !== null) {
                $result .= "\n" . str_repeat("    ", $new_line_level);
            }

            $result .= $char . $post;
        }

        return $result;
    }


    /**
     * @param int|array $codeOrResponse response data or just an http code
     * @return array infos "fromCache", "text", "color"
     */
    protected function fetchInfosFromCodeOrResponse(int|array $codeOrResponse): array
    {
        $fromCache = false;
        if (is_array($codeOrResponse)) {
            $response = $codeOrResponse;
            $code = array_key_exists('statusCode', $response) ? $response['statusCode'] : 'N/A';
            $fromCache = !empty($response['fromHttpCache']);
        } else {
            $code = $codeOrResponse;
        }

        if ($code === 'N/A' || $code >= 500) {
            $cls = "red";
            $level = "error";
        } elseif ($code >= 400) {
            $cls = "yellow";
            $level = "warning";
        } elseif ($code >= 300) {
            $cls = 'blue';
            $level = 'info';
        } elseif ($code >= 200) {
            $cls = "green";
            $level = "success";
        } else {
            $cls = 'default';
            $level = 'default';
        }

        $statusText = $code === 'N/A' ? 'N/A' : Response::$statusTexts[$code] ?? 'N/A';

        return [
            'fromCache' => $fromCache,
            'text'      => $statusText,
            'color'     => $cls,
            'code'      => $code,
            'level'     => $level,
        ];
    }

    /**
     * @param array $response
     * @return array
     */
    public function decodeBody(array $response): array
    {
        if (array_key_exists('headers', $response)) {
            foreach ($response['headers'] as $h) {
                if (preg_match('/Content-type:/i', $h, $m)) {
                    return [
                        'mime' => 'application/json',
                        'data' => @json_decode($response['body']),
                    ];
                }
            }
        }

        return [];
    }
}
