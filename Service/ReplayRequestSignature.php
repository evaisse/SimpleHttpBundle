<?php

namespace evaisse\SimpleHttpBundle\Service;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class ReplayRequestSignature
{
    public function __construct(
        private string $secret,
        private ?CsrfTokenManagerInterface $csrfTokenManager = null
    ) {
    }

    public function generate(string $token, int $callIndex): string
    {
        $tokenId = $this->buildTokenId($token, $callIndex);

        if ($this->csrfTokenManager) {
            return $this->csrfTokenManager->getToken($tokenId)->getValue();
        }

        return hash_hmac('sha256', $tokenId, $this->secret);
    }

    public function isValid(string $token, int $callIndex, ?string $submittedToken): bool
    {
        if (!is_string($submittedToken) || '' === $submittedToken) {
            return false;
        }

        $tokenId = $this->buildTokenId($token, $callIndex);

        if ($this->csrfTokenManager) {
            return $this->csrfTokenManager->isTokenValid(new CsrfToken($tokenId, $submittedToken));
        }

        return hash_equals($this->generate($token, $callIndex), $submittedToken);
    }

    private function buildTokenId(string $token, int $callIndex): string
    {
        return 'simple_http.replay.' . $token . '.' . $callIndex;
    }
}
