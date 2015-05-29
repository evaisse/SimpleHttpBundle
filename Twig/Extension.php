<?php

namespace evaisse\SimpleHttpBundle\Twig;


use Symfony\Component\HttpFoundation\Response;

class Extension extends \Twig_Extension
{
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('simple_http_beautify',
                    array($this, 'format'),
                    array('is_safe' => array('html'))
            ),
            new \Twig_SimpleFilter('simple_http_format_http_code',
                    array($this, 'formatHttpCode'), 
                    array('is_safe' => array('html'))
            ),
            new \Twig_SimpleFilter('simple_http_md5',
                    array($this, 'md5')
            ),
        );
    }

    public function md5($str)
    {
        return md5($str);
    }

    public function formatHttpCode($code)
    {
        if ($code >= 500) {
            $cls = "error";
        } else if ($code >= 400) {
            $cls = "warning";
        } else if ($code >= 300) {
            $cls = 'info';
        } else if ($code >= 200) {
            $cls = "success";
        } else {
            $cls = '';
        }
        $statusText = Response::$statusTexts[$code];
        return '<span class="badge ' . $cls . '"><abbr title="' . htmlentities($statusText) . '">' . $code . '</a></span>';
    }

    public function format($code, $contentType)
    {
        $class = array('hljs');
        if (strpos($contentType, 'application/json') !== false) {
            $class[] = 'json';
            $code = $this->formatJson($code);
        } else if (strpos($contentType, 'application/xml') !== false) {
            $class[] = 'xml';
            $code = $this->formatXml($code);
        } else {
            $class[] = 'html';
            $code = $code;
        }
        return '<pre class="' . join(' ', $class) . '">' . htmlentities($code) . '</pre>';
    }


    public function formatXml($xml)
    {
        $domxml = new DOMDocument('1.0');
        $domxml->preserveWhiteSpace = false;
        $domxml->formatOutput = true;
        $domxml->loadXML($xml);
        return $domxml->saveXML();
    }
    
    public function formatHtml($html)
    {
        return $html;
    }

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

    public function getName()
    {
        return 'simple_http_extension';
    }
}