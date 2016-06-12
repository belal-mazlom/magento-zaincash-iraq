<?php
/**
 * Created by PhpStorm.
 * User: belalmazlom
 * Date: 6/8/16
 * Time: 12:44 PM
 */

class Shopgo_ZainIraq_Helper_Data extends Mage_Core_Helper_Abstract
{

    /**
     * Log file name
     *
     * @var string
     */
    protected $_logFile = 'ZainIraq.log';

    public static function log($message, $level = null, $file = '', $forceLog = false)
    {
        if(Mage::getIsDeveloperMode()) {
            Mage::log($message, $level, $file, $forceLog);
        }
    }
}