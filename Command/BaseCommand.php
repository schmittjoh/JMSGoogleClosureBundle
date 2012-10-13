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

use Symfony\Component\Process\Process;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use JMS\GoogleClosureBundle\Exception\RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

/**
 * BaseCommand.
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
abstract class BaseCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->addOption('java-bin')
            ->addOption('plovr-jar')
        ;
    }

    protected function writeTempConfig(array $config)
    {
        $tempFile = tempnam(sys_get_temp_dir(), 'config');
        file_put_contents($tempFile, json_encode($config));

        return $tempFile;
    }

    /**
     * @return array
     */
    protected function loadPlovrConfig($file)
    {
        $file = $this->locatePlovrConfig($file);
        $config = json_decode(file_get_contents($file), true);

        if (null === $config) {
            throw new RuntimeException(sprintf('Invalid plovr configuration "%s".', $file));
        }

        $config = $this->normalizeConfig($config);

        return $config;
    }

    /**
     * Processes the plover config and generates a list of configuration files
     * which can be safely passed to the plovr command line.
     * 
     * @param array $files Files 
     * @return array
     */
    protected function processPlovrConfig($configs)
    {
        $configPaths = array();

        foreach ($configs as $inputConfig) {
            $config = $this->loadPlovrConfig($inputConfig);
            $configPaths[] = $this->writeTempConfig($config);
        }

        return $configPaths;
    }

    /**
     * Cleans up the config files gnerated in the running of the command
     * 
     * @param array $files Files to cleanup
     */
    protected function cleanupPlovrConfig($files)
    {
        foreach ($files as $file) {
            unlink($file);
        }
    }

    protected function locateJavaBin(InputInterface $input)
    {
        $javaBin = $input->getOption('java-bin');
        if (empty($javaBin)) {
            $javaBin = $this->detectJavaBin();
        }

        if (!file_exists($javaBin) || !is_executable($javaBin)) {
            throw new RuntimeException(sprintf('Java binary "%s" does not exist, or cannot be executed.', $javaBin));
        }

        return realpath($javaBin);
    }

    protected function locatePlovrJar(InputInterface $input)
    {
        $plovrJar = $input->getOption('plovr-jar');
        if (empty($plovrJar)) {
            $plovrJar = $this->getContainer()->getParameter('jms.google_closure.plovr.jar_path');
        }

        if (!file_exists($plovrJar) || !is_readable($plovrJar)) {
            throw new RuntimeException(sprintf('The plovr jar "%s" does not exist, or is not readable.', $plovrJar));
        }

        return realpath($plovrJar);
    }

    protected function normalizeConfig(array $config)
    {
        if (isset($config['paths'])) {
            $config['paths'] = $this->normalizePaths($config['paths']);
        }

        if (isset($config['inputs'])) {
            $config['inputs'] = $this->normalizePaths($config['inputs']);
        }

        if (isset($config['externs'])) {
            $config['externs'] = $this->normalizePaths($config['externs']);
        }

        if (isset($config['define'])) {
            $config['define'] = $this->getContainer()->getParameterBag()->resolveValue($config['define']);
        }

        if (isset($config['locales'])) {
            $config['locales'] = $this->getContainer()->getParameterBag()->resolveValue($config['locales']);
        }

        if (isset($config['variable-map-output-file'])) {
            $config['variable-map-output-file'] = $this->normalizePath($config['variable-map-output-file']);

            $dir = dirname($config['variable-map-output-file']);
            if (!file_exists($dir)) {
                if (false === @mkdir($dir, 0777, true)) {
                    throw new \RuntimeException(sprintf('Could not create variable map output directory "%s".', $dir));
                }
            }
        }
        if (isset($config['property-map-output-file'])) {
            $config['property-map-output-file'] = $this->normalizePath($config['property-map-output-file']);

            $dir = dirname($config['property-map-output-file']);
            if (!file_exists($dir)) {
                if (false === @mkdir($dir, 0777, true)) {
                    throw new \RuntimeException(sprintf('Could not create property map output directory "%s".', $dir));
                }
            }
        }

        return $config;
    }

    protected function normalizePaths($input)
    {
        $input = (array) $input;
        foreach ($input as $k => $v) {
            $input[$k] = $this->normalizePath($v);
        }

        return $input;
    }

    protected function normalizePath($str)
    {
        if ('@' === $str[0]) {
            list($bundle, $subPath) = explode('/', $str, 2);

            $path = $this->getContainer()->get('kernel')->getBundle(substr($bundle, 1))->getPath();
            $path .= '/'.$subPath;
        } else {
            $path = $str;
        }

        // replace parameters
        $container = $this->getContainer();
        $path = preg_replace_callback('/%((?:[^%]|%%)*)%/', function($match) use ($container) {
            return $container->getParameter($match[1]);
        }, $path);

        return $path;
    }

    /**
     * @param string      $javaBin
     * @param string      $jarPath
     * @param string|null $args
     */
    protected function runJar(OutputInterface $output, $javaBin, $jarPath, $args = null)
    {
        $cmd = escapeshellarg($javaBin).' -jar '.escapeshellarg($jarPath).(empty($args)?'':' '.$args);

        // fixes a seemingly broken C implementation of the proc_open command
        // see: http://coding.derkeiler.com/Archive/PHP/comp.lang.php/2005-01/1724.html
        if (stripos(PHP_OS, 'win') === 0) {
            $cmd = 'cmd /C "'.$cmd.'"';
        }

        $output->writeln(sprintf('Executing "%s"...', $cmd));
        $proc = new Process($cmd);
        $h = proc_open($cmd, $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
            1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
            2 => array("pipe", "w") // stderr is a file to write to
        ), $pipes);

        if ( ! is_resource($h)) {
            throw new \RuntimeException(sprintf('Could not launch process "%s".', $cmd));
        }

        fclose($pipes[0]);

        while (!feof($pipes[2])) {
            $output->writeln('Error: '.fgets($pipes[2]));
        }
        fclose($pipes[2]);

        while (!feof($pipes[1])) {
            $output->writeln(fgets($pipes[1]));
        }
        fclose($pipes[1]);
    }

    /**
     * @return string
     */
    private function locatePlovrConfig($inputStr)
    {
        if ('@' === $inputStr[0]) {
            $bundle = substr($inputStr, 1, ($pos = strpos($inputStr, '/'))-1);
            $path = $this->getContainer()->get('kernel')->getBundle($bundle)->getPath();

            $subPath = substr($inputStr, $pos);
            if (0 !== strpos($subPath, '/Resources/')) {
                $subPath = '/Resources/config/plovr'.$subPath;
            }

            $path .= $subPath;
        } else {
            $path = $inputStr;
        }

        if (!file_exists($path) || !is_readable($path)) {
            throw new RuntimeException(sprintf('Plovr configuration file "%s" does not exist, or is not readable.', $path));
        }

        return $path;
    }

    private function detectJavaBin()
    {
        $path = getenv('PATH') ? getenv('PATH') : getenv('Path');
        $suffixes = DIRECTORY_SEPARATOR == '\\' ? (getenv('PATHEXT') ? explode(PATH_SEPARATOR, getenv('PATHEXT')) : array('.exe', '.bat', '.cmd', '.com')) : array('');
        foreach (array('java') as $cli) {
            foreach ($suffixes as $suffix) {
                foreach (explode(PATH_SEPARATOR, $path) as $dir) {
                    if (is_file($file = $dir.DIRECTORY_SEPARATOR.$cli.$suffix) && is_executable($file)) {
                        return $file;
                    }
                }
            }
        }

        throw new RuntimeException('Unable to find Java executable.');
    }
}
