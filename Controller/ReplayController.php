<?php
/**
 * Created by PhpStorm.
 * User: sroussel
 * Date: 03/02/2016
 * Time: 16:30
 */

namespace evaisse\SimpleHttpBundle\Controller;

use evaisse\SimpleHttpBundle\DataCollector\ProfilerDataCollector;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class ReplayController
 * @package evaisse\SimpleHttpBundle\Controller
 */
class ReplayController extends Controller
{

    /**
     * @Route("/_profiler/simple_http/replay", name="simple_http.replay_request")
     *
     * @Template("SimpleHttpBundle:Collector/partials:response.html.twig")
     * @param Request $request
     * @return array
     */
    public function replayRequestAction(Request $request)
    {
        $header = $body = false;
        $params = array();
        $content = $request->getContent();
        $response = false;
        if (!empty($content))
        {
            $params = json_decode($content, true); // 2nd param to get as array

            if (isset($params['uri'])) {
                $ch = curl_init($params['uri']);

                curl_setopt($ch,CURLOPT_HTTPHEADER, array_values($params['headers']));

                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_VERBOSE, 1);
                curl_setopt($ch, CURLOPT_HEADER, 1);

                $response = curl_exec($ch);
                $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $headers = substr($response, 0, $header_size);
                $body = substr($response, $header_size);
                curl_close($ch);

                $headerParsed = [];
                foreach(explode("\r\n", $headers) as $header) {
                    $header = explode(':', $header, 2);
                    if (isset($header[0]) && isset($header[1])) {
                        $headerParsed[trim($header[0])] = trim($header[1]);
                    }
                }

                $response = new Response($body, 200, $headerParsed);

                $dataCollector = new ProfilerDataCollector();
                $response = $dataCollector->fetchResponseInfos($response);
            }

        }

        return ['content' => $params, 'response' => $response];
    }
}