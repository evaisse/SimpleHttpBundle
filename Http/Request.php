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
    }

}