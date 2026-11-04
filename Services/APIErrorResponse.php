<?php

declare(strict_types=1);

namespace Pumukit\ExternalAPIBundle\Services;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface;
use Symfony\Component\HttpFoundation\Response;

final class APIErrorResponse
{
    private const HEADERS = ['Content-Type' => 'text/xml'];

    public function __construct(private readonly LoggerInterface $logger) {}

    public function fromException(\Exception $exception): Response
    {
        if ($exception instanceof RequestExceptionInterface) {
            return new Response($exception->getMessage(), Response::HTTP_BAD_REQUEST, self::HEADERS);
        }

        $code = (int) $exception->getCode();
        if ($code >= 400 && $code < 600) {
            return new Response($exception->getMessage(), $code, self::HEADERS);
        }

        $this->logger->error('[external-api] '.$exception->getMessage(), ['exception' => $exception]);
        $message = $exception instanceof \InvalidArgumentException ? $exception->getMessage() : 'Internal Server Error';

        return new Response($message, Response::HTTP_INTERNAL_SERVER_ERROR, self::HEADERS);
    }
}
