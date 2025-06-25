<?php

declare(strict_types=1);

/**
 * Copyright notice
 * (c) 2025 Oliver Thiele <mail@oliver-thiele.de>, Web Development Oliver Thiele
 * All rights reserved
 * This script is part of the TYPO3 project. The TYPO3 project is
 * free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * The GNU General Public License can be found at
 * http://www.gnu.org/copyleft/gpl.html.
 * This script is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * This copyright notice MUST APPEAR in all copies of the script!
 */

namespace OliverThiele\OtFormprefill\ViewHelpers;

use Doctrine\DBAL\ParameterType;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\Exception\InvalidFileException;
use TYPO3\CMS\Core\Resource\Exception\InvalidPathException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Service\FlexFormService;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;
use TYPO3Fluid\Fluid\Core\ViewHelper\Exception;

/**
 * Class FormPrefillViewHelper
 *
 * Provides functionality for pre-filling forms based on field mappings and data sources.
 * Processes FlexForm configurations and builds mappings between form fields and Front-End (FE) user fields.
 * Generates a JavaScript mapping object for use in the front-end.
 */
class FormPrefillViewHelper extends AbstractViewHelper
{
    private const ERROR_NO_FORM = '<div class="alert alert-danger" role="alert"><b>ERROR:</b> No active form found on this page.</div>';
    private const ERROR_MULTIPLE_FORMS = '<div class="alert alert-danger" role="alert"><b>ERROR:</b> More than one active form found on this page. Please enter a unique identifier.</div>';
    private const ERROR_FORM_DEFINITION = '<div class="alert alert-danger" role="alert"><b>ERROR:</b> Form definition could not be found.</div>';

    protected $escapeOutput = false;

    public function __construct(
        private readonly FlexFormService $flexFormService,
        private readonly ResourceFactory $resourceFactory,
        private readonly SiteFinder $siteFinder,
    ) {}

    /**
     * Initialize arguments.
     *
     * @throws Exception
     */
    public function initializeArguments(): void
    {
        parent::initializeArguments();
    }

    public function render(): string
    {
        $templateVariables = $this->renderingContext->getVariableProvider();
        $contentData = $templateVariables->get('data');

        $formIdentifier = $this->resolveFormIdentifier(
            $contentData['pid'],
            $contentData['sys_language_uid'],
            $this->getFlexFormSettings($contentData['pi_flexform'])
        );

        if (str_starts_with($formIdentifier, '<div class="alert')) {
            return $formIdentifier;
        }

        $mapping = $this->buildFieldMapping(
            $this->getFlexFormSettings($contentData['pi_flexform'])['fieldMapping'] ?? '',
            $formIdentifier,
            $this->siteFinder->getSiteByPageId((int)$contentData['pid'])
        );

        return $this->generateJavaScript($formIdentifier, $mapping);
    }

    /**
     * @param int $pageUid
     * @param int $languageUid
     * @param array $settings
     * @return string
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function resolveFormIdentifier(int $pageUid, int $languageUid, array $settings): string
    {
        if (!empty($settings['formIdentifier'])) {
            return $settings['formIdentifier'];
        }

        $forms = $this->findFormsOnPage($pageUid, $languageUid);
        if (empty($forms)) {
            return self::ERROR_NO_FORM;
        }

        if (count($forms) > 1) {
            return self::ERROR_MULTIPLE_FORMS;
        }

        $identifier = $this->extractFormIdentifier($forms[0]);
        return $identifier ?? self::ERROR_FORM_DEFINITION;
    }

    /**
     * @param string $flexFormContent
     * @return array
     */
    private function getFlexFormSettings(string $flexFormContent): array
    {
        return $this->flexFormService->convertFlexFormContentToArray($flexFormContent)['settings'] ?? [];
    }

    /**
     * @param string $flexFormMapping
     * @param string $formIdentifier
     * @param Site|null $site
     * @return string[]
     */
    private function buildFieldMapping(
        string $flexFormMapping,
        string $formIdentifier,
        ?Site $site
    ): array {
        // Start with default mappings
        $defaultMapping = [
            'title' => 'title',
            'firstName' => 'first_name',
            'lastName' => 'last_name',
            'name' => 'name',
            'company' => 'company',
            'address' => 'address',
            'zip' => 'zip',
            'city' => 'city',
            'country' => 'country',
            'email' => 'email',
        ];

        // Get custom mappings from FlexForm
        $customMapping = $this->parseFlexFormMapping($flexFormMapping);

        // Merge with site configuration if available
        if ($site !== null) {
            $siteConfig = $site->getConfiguration();

            // Extract the base identifier without UID
            $baseIdentifier = preg_replace('/-\d+$/', '', $formIdentifier);

            // Start with generic mappings for the form type (without UID)
            if (!empty($siteConfig['otFormprefill']['formMappings'][$baseIdentifier])) {
                $genericMapping = $siteConfig['otFormprefill']['formMappings'][$baseIdentifier];
                $customMapping = array_merge($customMapping, $genericMapping);
            }

            // Then apply specific overrides if they exist (with UID)
            if (!empty($siteConfig['otFormprefill']['formMappings'][$formIdentifier])) {
                $specificMapping = $siteConfig['otFormprefill']['formMappings'][$formIdentifier];
                $customMapping = array_merge($customMapping, $specificMapping);
            }
        }
        // Custom mappings override default mappings
        return array_merge($defaultMapping, $customMapping);
    }

    /**
     * @return array<string, string>
     */
    private function parseFlexFormMapping(string $mappingString): array
    {
        $mapping = [];
        $lines = array_filter(explode("\n", $mappingString));

        foreach ($lines as $line) {
            $parts = array_map('trim', explode(':', $line));
            if (count($parts) === 2) {
                $mapping[$parts[0]] = $parts[1];
            }
        }

        return $mapping;
    }

    /**
     * @param int $pageUid
     * @param int $languageUid
     * @return array
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    private function findFormsOnPage(int $pageUid, int $languageUid): array
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');

        return $queryBuilder
            ->select('uid', 'CType', 'pi_flexform')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pageUid, ParameterType::INTEGER)
                ),
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($languageUid, ParameterType::INTEGER)
                ),
                $queryBuilder->expr()->or(
                    $queryBuilder->expr()->eq(
                        'CType',
                        $queryBuilder->createNamedParameter('form_formframework')
                    ),
                    $queryBuilder->expr()->eq(
                        'CType',
                        $queryBuilder->createNamedParameter('form_formframework_content')
                    )
                ),
                $queryBuilder->expr()->eq(
                    'hidden',
                    $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)
                ),
                $queryBuilder->expr()->eq(
                    'deleted',
                    $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)
                )
            )
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @param array $formData
     * @return string|null
     */
    private function extractFormIdentifier(array $formData): ?string
    {
        try {
            $formSettings = $this->getFlexFormSettings($formData['pi_flexform']);
            $persistenceIdentifier = $formSettings['persistenceIdentifier'] ?? '';

            if (str_starts_with($persistenceIdentifier, 'EXT:')) {
                $absoluteFilePath = GeneralUtility::getFileAbsFileName($persistenceIdentifier);
                if (!$absoluteFilePath || !file_exists($absoluteFilePath)) {
                    return null;
                }
                $yaml = file_get_contents($absoluteFilePath);
            } else {
                try {
                    $file = $this->resourceFactory->retrieveFileOrFolderObject($persistenceIdentifier);
                    $yaml = $file->getContents();
                } catch (InvalidPathException|InvalidFileException) {
                    return null;
                }
            }

            $formDefinition = Yaml::parse($yaml);
            return !empty($formDefinition['identifier'])
                ? $formDefinition['identifier'] . '-' . $formData['uid']
                : null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Generates a JavaScript snippet for form prefill mappings.
     *
     * @param string $formIdentifier The identifier of the form to be mapped.
     * @param array<string, string> $mapping The mapping of form fields and their respective values.
     * @return string The generated JavaScript snippet.
     * @throws \JsonException
     */
    private function generateJavaScript(string $formIdentifier, array $mapping): string
    {
        return sprintf(
            '<script>
            window.formPrefillMappings = {
                "%s": %s
            }
            </script>',
            $formIdentifier,
            json_encode($mapping, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );
    }
}
