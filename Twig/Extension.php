<?php

namespace evaisse\SimpleHttpBundle\Twig;

use Symfony\Component\HttpFoundation\Response;
use Twig\Error\LoaderError;
use Twig\Extension\ExtensionInterface;
use Twig\Loader\LoaderInterface;
use Twig\TwigFilter;
use Twig\TwigFunction;

class Extension implements ExtensionInterface
{
    public function __construct(protected LoaderInterface $loader)
    {
        $this->loader = $loader;
    }

    /**
     * @return string
     */
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
            new TwigFilter('simple_http_md5', array($this, 'md5')),
            new TwigFilter('simple_http_include_asset', array($this, 'assetInclude'), $safe),
            new TwigFilter('simple_http_format_ms', array($this, 'formatMilliseconds')),
            new TwigFilter('simple_http_format_num', array($this, 'numberFormat')),
        ];
    }


    public function getFunctions(): array
    {
        $safe = array('is_safe' => array('html'));

        return [
            new TwigFunction('simple_http_decode_body', array($this, 'decodeBody'), $safe),
        ];
    }

    /**
     * @param int|float $number
     * @param int       $decimals
     * @return string
     */
    public function numberFormat($number, $decimals = 0): string
    {
        static $locale;

        $locale = $locale ? $locale : localeconv();

        return number_format($number, $decimals, $locale['decimal_point'], $locale['thousands_sep']);
    }

    /**
     * @param $ms
     * @return string
     */
    public function formatMilliseconds($ms): string
    {
        if ($ms >= 1) {
            return $this->numberFormat($ms, 1) .  ' s';
        } else {
            return $this->numberFormat($ms * 1000) . " ms";
        }
    }

    /**
     * @param $str
     * @return string
     */
    public function md5($str): string
    {
        return md5($str);
    }

    /**
     * @param $file
     * @return string
     */
    public function assetInclude($file): string
    {
        try {
            return $this->loader->getSourceContext($file)->getCode();
        } catch (LoaderError $e) {
            return '';
        }
    }

    /**
     * @param int|array $codeOrResponse response data or just an http code
     * @return string
     */
    public function formatHttpCode($codeOrResponse): string
    {
        $d = $this->fetchInfosFromCodeOrResponse($codeOrResponse);
        return '<span class="http-status badge '.$d['level'].' '.($d['fromCache']?'http-cache-hit':'').'"><abbr title="' . htmlentities($d['text']) . '">'.$d['code'].($d['fromCache']?' <small>+cached</small>':'').'</abbr></span>';
    }


    /**
     * @param int|array $codeOrResponse response data or just an http code
     * @return string
     */
    public function formatHttpCodeAsSfBadge($codeOrResponse): string
    {
        $d = $this->fetchInfosFromCodeOrResponse($codeOrResponse);
        return '<span class="http-status sf-toolbar-status sf-toolbar-status-' . $d['color'].' '.($d['fromCache']?'http-cache-hit':'').'">'
              .'<abbr style="border:none" title="' . htmlentities($d['text']) . '">'.$d['code'].($d['fromCache']?'<small>+cache</small>':'').'</abbr>'
              .'</span>';
    }

    /**
     * @param $code
     * @param $contentType
     * @return string
     */
    public function format($code, $contentType)
    {
        $class = array('hljs');
        if (strpos($contentType, 'application/json') !== false) {
            $class[] = 'json';
            $code = @$this->formatJson($code);
        } else if (strpos($contentType, 'application/xml') !== false) {
            $class[] = 'xml';
            $code = @$this->formatXml($code);
        } else {
            $class[] = 'html';
        }

        return '<pre class="' . join(' ', $class) . '">' . htmlentities($code) . '</pre>';
    }

    /**
     * @param $xml
     * @return string
     */
    public function formatXml($xml)
    {
        $domxml = new \DOMDocument('1.0');
        $domxml->preserveWhiteSpace = false;
        $domxml->formatOutput = true;
        $domxml->loadXML($xml);
        return $domxml->saveXML();
    }

    /**
     * @param $html
     * @return mixed
     */
    public function formatHtml($html)
    {
        return $html;
    }

    /**
     * @param $json
     * @return string
     */
    public function formatJson($json)
    {
        $result = '';
        $level = 0;
        $in_quotes = false;
        $in_escape = false;
        $ends_line_level = NULL;
        $json_length = strlen($json);
        
        for ($i = 0; $i < $json_length; $i++) {
            $char = $json[$i];
            $new_line_level = NULL;
            $post = "";
            if ($ends_line_level !== NULL) {
                $new_line_level = $ends_line_level;
                $ends_line_level = NULL;
            }
            if ($in_escape) {
                $in_escape = false;
            } 
            else if ($char === '"') {
                $in_quotes = !$in_quotes;
            } 
            else if (!$in_quotes) {
                switch ($char) {
                    case '}':
                    case ']':
                        $level--;
                        $ends_line_level = NULL;
                        $new_line_level = $level;
                        break;

                    case '{':
                    case '[':
                        $level++;
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
                        $new_line_level = NULL;
                        break;
                }
            } 
            else if ($char === '\\') {
                $in_escape = true;
            }
            if ($new_line_level !== NULL) {
                $result.= "\n" . str_repeat("    ", $new_line_level);
            }
            $result.= $char . $post;
        }
        
        return $result;
    }


    /**
     * @param int|array $codeOrResponse response data or just an http code
     * @return array infos "fromCache", "text", "color"
     */
    protected function fetchInfosFromCodeOrResponse($codeOrResponse)
    {
        $fromCache = false;
        if (is_array($codeOrResponse)) {
            $response = $codeOrResponse;
            $code = array_key_exists('statusCode', $response)?$response['statusCode']:'N/A';
            $fromCache = !empty($response['fromHttpCache']);
        } else {
            $code = (int)$codeOrResponse;
        }

        if ($code >= 500) {
            $cls = "red";
            $level = "error";
        } else if ($code >= 400) {
            $cls = "yellow";
            $level = "warning";
        } else if ($code >= 300) {
            $cls = 'blue';
            $level = 'info';
        } else if ($code >= 200) {
            $cls = "green";
            $level = "success";
        } else {
            $cls = 'default';
            $level = 'default';
        }

        $statusText = Response::$statusTexts[$code];

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
    public function decodeBody(array $response)
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

    /**
     * @inheritDoc
     */
    public function getTokenParsers(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getNodeVisitors(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getTests(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function getOperators(): array
    {
        return [];
    }
}
