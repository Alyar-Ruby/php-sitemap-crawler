<?php
namespace Olden\Speller;

use Mekras\Speller\Source\Source;

/**
 * This class uses for hack vendor Hunspell class,
 * because no other way to send it OPTION -p to use custom stop words
 *
 * Class Hunspell
 * @package Olden\Speller
 */
class Hunspell extends \Mekras\Speller\Hunspell\Hunspell
{
    private $customDictionaries;

    public function setCustomDictionaries(array $customDictionaries)
    {
        parent::setCustomDictionaries($customDictionaries);

        $this->customDictionaries = $customDictionaries;
    }

    protected function createArguments(Source $source, array $languages)
    {
        $args = parent::createArguments($source, $languages);

        if (!$this->customDictionaries) {
            return $args;
        }

        return array_merge($args, ['-p ' . implode(',', $this->customDictionaries)]);
    }
}
