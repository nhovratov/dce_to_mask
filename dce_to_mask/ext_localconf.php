<?php

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/install']['update']['dceToMask']
    = \Hov\DceToMask\Upgrade\DceToMaskUpgradeWizard::class;
