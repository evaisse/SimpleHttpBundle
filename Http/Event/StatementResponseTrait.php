<?php


namespace evaisse\SimpleHttpBundle\Http\Event;


use evaisse\SimpleHttpBundle\Http\Response;

trait StatementResponseTrait
{
    /** @var Response */
    protected $response = null;

    /**
     * @return Response|null
     */
    public function getResponse(): ?Response
    {
        return $this->response;
    }
}