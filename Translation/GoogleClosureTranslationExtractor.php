<?php

namespace JMS\GoogleClosureBundle\Translation;

use JMS\TranslationBundle\Model\FileSource;
use JMS\TranslationBundle\Model\Message;
use JMS\TranslationBundle\Model\MessageCatalogue;
use JMS\TranslationBundle\Translation\Extractor\FileVisitorInterface;

class GoogleClosureTranslationExtractor implements FileVisitorInterface
{
    public function visitFile(\SplFileInfo $file, MessageCatalogue $catalogue)
    {
        if ('.js' !== substr($file, -3)) {
            return;
        }

        $content = json_decode(file_get_contents($file), true);
        if (null === $content || !is_array($content) || !isset($content['define'])) {
            return;
        }

        foreach ($content['define'] as $k => $id) {
            if (!preg_match('/(^|\.)MSG_[^.]+$/', $k)) {
                continue;
            }

            $message = new Message($id);
            $message->addSource(new FileSource((string) $file));
            $catalogue->add($message);
        }
    }

    public function visitPhpFile(\SplFileInfo $file, MessageCatalogue $catalogue, array $ast) { }
    public function visitTwigFile(\SplFileInfo $file, MessageCatalogue $catalogue, \Twig_Node $node) { }
}
