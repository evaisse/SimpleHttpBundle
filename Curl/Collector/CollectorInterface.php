<?php

namespace evaisse\SimpleHttpBundle\Curl\Collector;

interface CollectorInterface {
    function collect();
    function retrieve();
}