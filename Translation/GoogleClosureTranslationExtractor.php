<?php

namespace JMS\GoogleClosureBundle\Translation;

use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Extractor\ExtractorInterface;

class GoogleClosureTranslationExtractor implements ExtractorInterface
{
    private $prefix = '';

    public function extract($directory, MessageCatalogue $catalogue)
    {
        foreach (Finder::create()->name('*.js')->in($directory)->files() as $file) {
            $content = json_decode(file_get_contents($file), true);

            if (null === $content || !is_array($content) || !isset($content['define'])) {
                continue;
            }

            foreach ($content['define'] as $k => $id) {
                if (!preg_match('/(^|\.)MSG_[^.]+$/', $k)) {
                    continue;
                }

                $catalogue->set($this->prefix.$id, $id, 'messages');
            }
        }
    }

    public function setPrefix($prefix)
    {
        $this->prefix = $prefix;
    }
}