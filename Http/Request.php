<?php

namespace evaisse\SimpleHttpBundle\Http;

use Symfony\Component\HttpFoundation\Request as HttpRequest;

class Request extends HttpRequest
{
    /**
     * @param bool $asResource
     * @return resource|string
     */
    public function getContent($asResource = false)
    {
        if ($asResource) {
            $resource = fopen("php://temp/" . uniqid(), "w+");
            fwrite($resource, $this->content);
            return $resource;
        } else {
            return $this->content;
        }
    }


    /**
     * String request content, i.e. a json string for a json payload
     * @param string $content request content
     */
    public function setContent($content)
    {
        $this->content = $content;
        if (is_string($content)) {
            $this->headers->set('Content-Length', strlen($this->content));
        }
    }


}