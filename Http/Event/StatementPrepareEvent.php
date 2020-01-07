<?php
namespace evaisse\SimpleHttpBundle\Http\Event;


class StatementPrepareEvent extends AbstractStatementEvent
{
    const KEY = "simple_http.statement_events.prepare";
}