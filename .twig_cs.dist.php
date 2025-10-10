<?php

declare(strict_types=1);

use FriendsOfTwig\Twigcs\Finder\TemplateFinder;
use FriendsOfTwig\Twigcs\Config\Config;
use Glpi\Tools\GlpiTwigRuleset;

$finder = TemplateFinder::create()
    ->in(__DIR__ . '/templates')
    ->name('*.html.twig')
    ->ignoreVCSIgnored(true);

return Config::create()
    ->setFinder($finder)
    ->setRuleSet(GlpiTwigRuleset::class)
;
