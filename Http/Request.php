<?php

namespace evaisse\SimpleHttpBundle\Http;

use Symfony\Component\HttpFoundation\Request as HttpRequest;

class Request extends HttpRequest
{

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