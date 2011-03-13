<?php

namespace JMS\GoogleClosureBundle\Command;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Bundle\FrameworkBundle\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

/**
 * Starts the Plovr server.
 *
 * @see http://plovr.com/docs.html
 *
 * @author Johannes M. Schmitt <schmittjoh@gmail.com>
 */
class StartPlovrCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('plovr:start')
            ->setDescription('Starts the Plovr server')
            ->addArgument('config', InputArgument::REQUIRED, 'The configuration file to use')
            ->addOption('java-bin')
            ->addOption('plovr-jar')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $javaBin = $input->getOption('java-bin');
        if (empty($javaBin)) {
            $javaBin = $this->detectJavaBin();
        }

        if (!file_exists($javaBin) || !is_executable($javaBin)) {
            throw new \RuntimeException(sprintf('"%s" does not exist, or cannot be executed.', $javaBin));
        }

        $plovrJar = $input->getOption('plovr-jar');
        if (empty($plovrJar)) {
            $plovrJar = realpath(__DIR__.'/../../../../vendor/plovr/plovr.jar');
        }

        if (!file_exists($plovrJar)) {
            throw new \RuntimeException(sprintf('The plovr jar "%s" does not exist.', $plovrJar));
        }

        $config = $input->getArgument('config');
//        if (!file_exists($config)) {
//            throw new \RuntimeException(sprintf('The config file "%s" does not exist.', $config));
//        }

        $cmd = escapeshellarg($javaBin).' -jar '.escapeshellarg($plovrJar).' serve '.escapeshellarg($config);

        // fixes a seemingly broken C implementation of the proc_open command
        // see: http://coding.derkeiler.com/Archive/PHP/comp.lang.php/2005-01/1724.html
        if (stripos(PHP_OS, 'win') === 0) {
            $cmd = 'cmd /C "'.$cmd.'"';
        }

        $output->writeln(sprintf('Executing "%s"...', $cmd));
        $h = proc_open($cmd, $descriptorspec = array(
            0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
            1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
            2 => array("pipe", "w") // stderr is a file to write to
        ), $pipes);

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

    private function detectJavaBin()
    {
        $path = getenv('PATH') ? getenv('PATH') : getenv('Path');
        $suffixes = DIRECTORY_SEPARATOR == '\\' ? (getenv('PATHEXT') ? explode(PATH_SEPARATOR, getenv('PATHEXT')) : array('.exe', '.bat', '.cmd', '.com')) : array('');
        foreach (array('java') as $cli)
        {
            foreach ($suffixes as $suffix)
            {
                foreach (explode(PATH_SEPARATOR, $path) as $dir)
                {
                    if (is_file($file = $dir.DIRECTORY_SEPARATOR.$cli.$suffix) && is_executable($file))
                    {
                        return $file;
                    }
                }
            }
        }

        throw new RuntimeException('Unable to find Java executable.');
    }
}
