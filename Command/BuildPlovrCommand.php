<?php

/*
 * Copyright 2011 Johannes M. Schmitt <schmittjoh@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace JMS\GoogleClosureBundle\Command;

use Symfony\Component\Routing\RequestContext;
use JMS\GoogleClosureBundle\Exception\RuntimeException;
use Symfony\Component\Console\Output\Output;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Starts the Plovr server.
 *
 * @see http://plovr.com/docs.html
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class BuildPlovrCommand extends BaseCommand
{
    protected function configure()
    {
        $this
            ->setName('plovr:build')
            ->setDescription('Builds a Javascript app using the Plovr server')
            ->addArgument('config', InputArgument::REQUIRED, 'The configuration file to use.')
        ;

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $javaBin = $this->locateJavaBin($input);
        $plovrJar = $this->locatePlovrJar($input);
        $config = $this->loadPlovrConfig($input->getArgument('config'));
        $translator = $this->getContainer()->get('translator');
        $router = $this->getContainer()->get('router');

        if (!isset($config['output-file'])) {
            throw new RuntimeException('You must specify "output-file" in your plovr configuration file.');
        }
        if (!is_string($config['output-file'])) {
            throw new RuntimeException('"output-file" must be a string.');
        }
        $outputFile = $this->normalizePath($config['output-file']);
        unset($config['output-file']);

        if (!isset($config['locales'])) {
            $locales = array('en');
        } elseif (!is_array($config['locales'])) {
            throw new RuntimeException('"locales" must be an array of strings.');
        } else {
            $locales = $config['locales'];
            unset($config['locales']);
        }

        $localeSpecificVariableMap = isset($config['variable-map-output-file']) && false !== strpos($config['variable-map-input-file'], '$locale');
        $localeSpecificPropertyMap = isset($config['property-map-output-file']) && false !== strpos($config['property-map-output-file'], '$locale');
        $variableMapInputPath = isset($config['variable-map-input-file']) ? $this->normalizePath($config['variable-map-input-file']) : null;
        $propertyMapInputPath = isset($config['property-map-input-file']) ? $this->normalizePath($config['property-map-input-file']) : null;

        foreach ($locales as $locale) {
            $localeOutputFile = str_replace('$locale', $locale, $outputFile);
            $dir = dirname($localeOutputFile);
            if (!file_exists($dir)) {
                $output->writeln(sprintf('Creating output directory "%s"...', $dir));
                @mkdir($dir, 0777, true);
            }

            if (!is_writable($dir)) {
                throw new RuntimeException(sprintf('Output path "%s" is not writable.', $dir));
            }

            $localeConfig = $config;
            $localeConfig['define']['goog.LOCALE'] = $locale;

            // set translations (any constants which start with MSG_ are considered for translation)
            foreach ($localeConfig['define'] as $k => $v) {
                if (!preg_match('/\.(MSG|ROUTE)_[A-Z_0-9]+$/', $k, $match)) {
                    continue;
                }

                if ('MSG' === $match[1]) {
                    $localeConfig['define'][$k] = $translator->trans($v, array(), 'messages', $locale);
                } elseif ('ROUTE' === $match[1]) {
                    $requestContext = new RequestContext();
                    $requestContext->setParameter('_locale', $locale);
                    $router->setContext($requestContext);

                    $localeConfig['define'][$k] = $router->generate($v);
                }
            }

            if (null !== $variableMapInputPath) {
                $localeConfig['variable-map-input-file'] = $variableMapInputPath;
            }
            if (null !== $propertyMapInputPath) {
                $localeConfig['property-map-input-file'] = $propertyMapInputPath;
            }

            if (isset($localeConfig['variable-map-output-file'])) {
                $localeConfig['variable-map-output-path'] = str_replace('$locale', $locale, $localeConfig['variable-map-output-file']);

                if (!$localeSpecificVariableMap) {
                    $variableMapInputPath = $localeConfig['variable-map-output-file'];
                }
            }
            if (isset($localeConfig['property-map-output-file'])) {
                $localeConfig['property-map-output-file'] = str_replace('$locale', $locale, $localeConfig['property-map-output-file']);

                if (!$localeSpecificPropertyMap) {
                    $propertyMapInputPath = $localeConfig['property-map-output-file'];
                }
            }

            $path = $this->writeTempConfig($localeConfig);
            $this->runJar($output, $javaBin, $plovrJar, 'build '.escapeshellarg($path).' > '.escapeshellarg($localeOutputFile));
            unlink($path);
        }
    }
}
