<?php

namespace evaisse\SimpleHttpBundle\Controller;

use evaisse\SimpleHttpBundle\Service\Helper;
use evaisse\SimpleHttpBundle\Service\ReplayRequestResolver;
use evaisse\SimpleHttpBundle\Service\ReplayRequestSignature;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ReplayController
 * @package evaisse\SimpleHttpBundle\Controller
 */
class ReplayController
{
    public function __construct(
        protected Helper $serviceHelper,
        private ReplayRequestResolver $replayRequestResolver,
        private ReplayRequestSignature $replayRequestSignature,
        private bool $debug
    ) {
    }

    #[Route('/http-replay', name: 'simple_http.replay_request', methods: ['POST'])]
    public function replayRequestAction(Request $request): Response
    {
        if (!$this->debug || !$this->replayRequestResolver->isAvailable()) {
            throw new NotFoundHttpException('Replay is only available with the profiler enabled.');
        }

        $token = $request->request->getString('token');
        $signature = $request->request->getString('_token');
        $callIndex = filter_var(
            $request->request->get('callIndex'),
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0]]
        );

        if ('' === $token || false === $callIndex) {
            throw new BadRequestHttpException('Invalid replay request.');
        }

        if (!$this->replayRequestSignature->isValid($token, $callIndex, $signature)) {
            throw new AccessDeniedHttpException('Invalid replay token.');
        }

        $storedRequest = $this->replayRequestResolver->resolve($token, $callIndex);

        $service = $this->serviceHelper->prepare($storedRequest['method'], $storedRequest['uri']);
        $service->getRequest()->setContent($storedRequest['content']);

        foreach ($storedRequest['headers'] as $header) {
            if (fnmatch('*:*', $header)) {
                list($headerName, $headerValue) = explode(':', $header, 2);
                $headerName = trim($headerName);
                $headerValue = trim($headerValue);
                $service->getRequest()->headers->set($headerName, $headerValue);
            }
        }

        foreach ($storedRequest['cookies'] as $cookieName => $cookieValue) {
            $service->getRequest()->cookies->set($cookieName, $cookieValue);
        }

        $this->serviceHelper->execute([
            $service,
        ]);

        return new JsonResponse(['status' => 'ok']);
    }
}
