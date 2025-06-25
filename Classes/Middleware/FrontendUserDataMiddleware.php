<?php

declare(strict_types=1);

namespace OliverThiele\OtFormprefill\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Middleware that handles requests for pre-filling user data in a frontend environment.
 * It ensures that requests targeting a specific endpoint (`/prefill-user.json`) are
 * authenticated and returns user-specific data based on the site's configuration or a
 * default list of allowed fields.
 */
class FrontendUserDataMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ExtensionConfiguration $extensionConfiguration,
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $normalizedParams = $request->getAttribute('normalizedParams');
        if ($normalizedParams->getRequestUri() !== '/prefill-user.json') {
            return $handler->handle($request);
        }

        $frontendUserAuthentication = $request->getAttribute('frontend.user');
        if (!$frontendUserAuthentication instanceof FrontendUserAuthentication) {
            return new JsonResponse(
                ['error' => 'Authentication required'],
                403
            );
        }

        if ($frontendUserAuthentication->getUserId() <= 0) {
            return new JsonResponse(
                ['error' => 'No valid user session'],
                403
            );
        }

        /** @var Site|null $site */
        $site = $request->getAttribute('site');
        $siteConfiguration = $site?->getConfiguration();

        return new JsonResponse(
            $this->getUserData($frontendUserAuthentication, $siteConfiguration)
        );
    }

    /**
     * @param array<string, mixed> $siteConfiguration
     * @return array<string, string>
     */
    private function getUserData(
        FrontendUserAuthentication $frontendUserAuthentication,
        ?array $siteConfiguration
    ): array {
        $allowedFields = $siteConfiguration['otFormprefill']['allowedFields']
            ?? $this->getDefaultAllowedFields();

        return array_intersect_key(
            $frontendUserAuthentication->user,
            array_flip($allowedFields)
        );
    }

    /**
     * @return array<int, string>
     */
    private function getDefaultAllowedFields(): array
    {
        try {
            $extConf = $this->extensionConfiguration->get('ot_formprefill');
            return !empty($extConf['allowedFields'])
                ? explode(',', $extConf['allowedFields'])
                : [
                    'name',
                    'title',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'company',
                    'address',
                    'zip',
                    'city',
                    'country',
                    'telephone',
                    'fax',
                    'email',
                    'www',
                ];
        } catch (\Exception) {
            return [];
        }
    }
}
