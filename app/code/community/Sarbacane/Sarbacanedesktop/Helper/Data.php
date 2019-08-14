<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category    Sarbacane
 * @package     Sarbacane_Sarbacanedesktop
 * @author      Sarbacane Software <contact@sarbacane.com>
 * @copyright   2015 Sarbacane Software
 * @license     http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */
class Sarbacane_Sarbacanedesktop_Helper_Data extends Mage_Core_Helper_Abstract
{

    public function getShopsArray()
    {
        $shops = Mage::app()->getStores();
        $shops_array = array();
        foreach ($shops as $shop) {
            $shops_array[] = array(
                'store_id' => $shop->store_id,
                'name' => $shop->name
            );
        }
        $shops_array[] = array(
            'store_id' => '0',
            'name' => $this->__('Other')
        );
        return $shops_array;
    }

    public function getIdShopFromList($list)
    {
        if (substr($list, -1) == 'N' || substr($list, -1) == 'C') {
            return substr($list, 0, -1);
        } else {
            return substr($list, 0, -2);
        }
    }

    public function getListTypeFromList($list)
    {
        if (substr($list, -1) == 'N' || substr($list, -1) == 'C') {
            return substr($list, -1);
        } else {
            return substr($list, -2, 1);
        }
    }

    public function getListTypeArray()
    {
        return array('N', 'C');
    }

    public function getListConfiguration($return = 'string')
    {
        $sd_list = $this->getConfiguration('sd_list');
        if ($return == 'string') {
            return $sd_list;
        } else {
            if (strlen($sd_list) != 0) {
                return explode(',', $sd_list);
            }
            return array();
        }
    }

    public function getToken()
    {
        $str = $this->getConfiguration('sd_token');
        $str = explode('-', $str);
        $token = '';
        foreach ($str as $key => $s) {
            if ($key == 0) {
                $token = $s . substr(Mage::helper('core')->encrypt('SecurityTokenForModule'), 0, 11) . $s;
                $token = md5($token);
            }else {
                $token .= $s;
            }
        }
        return $token;
    }

    public function getConfiguration($return = 'nb_configured')
    {
        $sd_configuration_loaded = false;
        if (isset($this->sd_configuration)) {
            if (isset($this->sd_configuration['sd_token']) && isset($this->sd_configuration['sd_list'])
            && isset($this->sd_configuration['sd_is_user']) && isset($this->sd_configuration['sd_last_update'])
            && isset($this->sd_configuration['sd_nb_failed'])) {
                $sd_configuration_loaded = true;
            }
        }
        if ( ! $sd_configuration_loaded) {
            $resource = Mage::getSingleton('core/resource');
            $db_read = $resource->getConnection('core_read');
            $sd_data = $resource->getTableName('sd_data');
            $sd_configuration = array(
                'sd_token' => '',
                'sd_list' => '',
                'sd_is_user' => '',
                'sd_last_update' => '',
                'sd_nb_failed' => 0
            );
            $rq_sql = '
            SELECT *
            FROM `' . $sd_data . '`
            WHERE `sd_type` IN (\'sd_token\', \'sd_list\', \'sd_is_user\', \'sd_last_update\', \'sd_nb_failed\')';
            $rq = $db_read->query($rq_sql);
            while ($r = $rq->fetch()) {
                $sd_configuration[$r['sd_type']] = $r['sd_value'];
            }
            $sd_configuration['sd_nb_failed'] = (int)$sd_configuration['sd_nb_failed'];
            $this->sd_configuration = $sd_configuration;
        }
        if ($return == 'sd_token' || $return == 'sd_list' || $return == 'sd_is_user' || $return == 'sd_last_update' || $return == 'sd_nb_failed') {
            return $this->sd_configuration[$return];
        } else {
            if ($return == 'all') {
                return $this->sd_configuration;
            } else {
                $nb_configured = 0;
                if ($this->sd_configuration['sd_token'] != '') {
                    $nb_configured = 3;
                } else {
                    if ($this->sd_configuration['sd_list'] != '') {
                        $nb_configured++;
                    }
                    if ($this->sd_configuration['sd_is_user'] != '') {
                        $nb_configured++;
                    }
                }
                return $nb_configured;
            }
        }
    }

    public function updateConfiguration($name, $value) {
        $resource = Mage::getSingleton('core/resource');
        $db_write = $resource->getConnection('core_write');
        $sd_data = $resource->getTableName('sd_data');
        $rq_sql = '
        UPDATE `' . $sd_data . '`
        SET `sd_value` = ' . $db_write->quote($value) . '
        WHERE `sd_type` = ' . $db_write->quote($name);
        $db_write->query($rq_sql);
        unset($this->sd_configuration);
    }

    public function deleteSdid($sd_id)
    {
        $resource = Mage::getSingleton('core/resource');
        $db_write = $resource->getConnection('core_write');
        $sd_users = $resource->getTableName('sd_users');
        $rq_sql = '
        DELETE
        FROM `' . $sd_users . '`
        WHERE `sd_id` = ' . $db_write->quote($sd_id);
        $db_write->query($rq_sql);
    }

    public function checkNeedUserInsertSdUpdates($store_id, $list_type)
    {
        $sd_list_array = $this->getListConfiguration('array');
        $sd_list_string = implode(',', $sd_list_array);
        if (strpos($sd_list_string, $list_type) === false) {
            return false;
        }
        if ($list_type == 'N') {
            $in_array = in_array($store_id . 'N0', $sd_list_array);
        } else {
            $in_array = in_array($store_id . 'C0', $sd_list_array) || in_array($store_id . 'C1', $sd_list_array);
        }
        if ($in_array) {
            $sd_synchronized_lists_array = $this->getSynchronizedListsArray();
            if (in_array($store_id . $list_type, $sd_synchronized_lists_array)) {
                return true;
            }
        }
        return false;
    }

    public function getSynchronizedListsArray() {
        if ( ! isset($this->sd_synchronized_lists_array)) {
            $resource = Mage::getSingleton('core/resource');
            $db_read = $resource->getConnection('core_read');
            $sd_users = $resource->getTableName('sd_users');
            $sd_synchronized_lists_array = array();
            $rq_sql = '
            SELECT `list_id`
            FROM `' . $sd_users . '`
            GROUP BY `list_id`';
            $rq = $db_read->query($rq_sql);
            while ($r = $rq->fetch()) {
                $sd_synchronized_lists_array[] = $r['list_id'];
            }
            $this->sd_synchronized_lists_array = $sd_synchronized_lists_array;
        }
        return $this->sd_synchronized_lists_array;
    }

   public function checkFailed() {
        if ($this->getConfiguration('sd_nb_failed') < 1000000) {
            return false;
        }
        return true;
    }

}
