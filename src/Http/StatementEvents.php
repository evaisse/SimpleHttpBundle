<?php
/**
 * Created by PhpStorm.
 * User: evaisse
 * Date: 19/06/15
 * Time: 10:53
 */

namespace evaisse\SimpleHttpBundle\Http;


class StatementEvents
{

    const PREPARE = "simple_http.statement_events.prepare";
    const SUCCESS = "simple_http.statement_events.success";
    const ERROR = "simple_http.statement_events.error";
    const FINISH = "simple_http.statement_events.finish";

}


