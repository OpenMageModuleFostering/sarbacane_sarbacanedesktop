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
class Sarbacane_Sarbacanedesktop_Adminhtml_SarbacanedesktopController extends Mage_Adminhtml_Controller_Action
{

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('newsletter/sarbacanedesktop');
    }

    public function indexAction()
    {
        $general_configuration = Mage::helper('sarbacanedesktop')->getConfiguration('nb_configured');
        $displayed_step = 1;
        if ($general_configuration == 1) {
            $displayed_step = 2;
        } else if ($general_configuration == 2 || $general_configuration == 3) {
            $displayed_step = 3;
        }
        if (Mage::app()->getRequest()->getParam('submit_is_user')) {
            $this->saveSdIsUser();
            $general_configuration = Mage::helper('sarbacanedesktop')->getConfiguration('nb_configured');
            $displayed_step = 2;
        } else if (Mage::app()->getRequest()->getParam('submit_configuration')) {
            $this->saveListConfiguration();
            if (Mage::helper('sarbacanedesktop')->getConfiguration('sd_token') == '') {
                $this->saveTokenParameterConfiguration();
            }
            $general_configuration = Mage::helper('sarbacanedesktop')->getConfiguration('nb_configured');
            $displayed_step = 3;
        } else if (Mage::app()->getRequest()->getParam('submit_parameter_key')) {
            $this->saveTokenParameterConfiguration();
        }
        $this->loadLayout();
        $block = $this->getLayout()->getBlock('sarbacanedesktop');
        $block->setSdFormKey($this->getSdFormKey());
        $block->setKeyForSynchronisation($this->getKeyForSynchronisation());
        $block->setCheckFailed(Mage::helper('sarbacanedesktop')->checkFailed());
        $block->setListConfiguration(Mage::helper('sarbacanedesktop')->getListConfiguration('array'));
        $block->setGeneralConfiguration($general_configuration);
        $block->setSdIsUser(Mage::helper('sarbacanedesktop')->getConfiguration('sd_is_user'));
        $block->setDisplayedStep($displayed_step);
        $block->setShopsArray(Mage::helper('sarbacanedesktop')->getShopsArray());
        $this->_title($this->__('Sarbacane Desktop'));
        $this->_setActiveMenu('newsletter');
        $this->renderLayout();
    }

    private function saveSdIsUser()
    {
        if (Mage::app()->getRequest()->getParam('sd_is_user')) {
            $sd_is_user = Mage::app()->getRequest()->getParam('sd_is_user');
            Mage::helper('sarbacanedesktop')->updateConfiguration('sd_is_user', $sd_is_user);
        }
    }

    private function saveTokenParameterConfiguration()
    {
        $resource = Mage::getSingleton('core/resource');
        $db_write = $resource->getConnection('core_write');
        $sd_updates = $resource->getTableName('sd_updates');
        $sd_users = $resource->getTableName('sd_users');
        Mage::helper('sarbacanedesktop')->updateConfiguration('sd_nb_failed', '');
        $rq_sql = 'TRUNCATE `' . $sd_updates . '`';
        $db_write->query($rq_sql);
        $rq_sql = 'TRUNCATE `' . $sd_users . '`';
        $db_write->query($rq_sql);
        $token_parameter = rand(100000, 999999) . time() . '-';
        $characters = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';
        $nb_characters = strlen($characters);
        for ($i = 0; $i < 30; $i++) {
            $token_parameter .= substr($characters, mt_rand(0, $nb_characters - 1), 1);
        }
        Mage::helper('sarbacanedesktop')->updateConfiguration('sd_token', $token_parameter);
    }

    private function getKeyForSynchronisation()
    {
        return str_rot13('sarbacanedesktop?stk=' . Mage::helper('sarbacanedesktop')->getToken());
    }

    private function saveListConfiguration()
    {
        $shops = '';
        $stores_id = array();
        if (Mage::app()->getRequest()->getParam('store_id')) {
            $stores_id = Mage::app()->getRequest()->getParam('store_id');
        }
        if (is_array($stores_id)) {
            $sd_list_array = Mage::helper('sarbacanedesktop')->getListConfiguration('array');
            foreach ($sd_list_array as $sd_list) {
                if ( ! in_array($sd_list, $stores_id)) {
                    $store_id = Mage::helper('sarbacanedesktop')->getIdShopFromList($sd_list);
                    $list_type = Mage::helper('sarbacanedesktop')->getListTypeFromList($sd_list);
                    $this->deleteListData($list_type, $store_id);
                }
            }
            $shops = implode(',', $stores_id);
        }
        Mage::helper('sarbacanedesktop')->updateConfiguration('sd_list', $shops);
    }

    private function deleteListData($list_type, $store_id)
    {
        $resource = Mage::getSingleton('core/resource');
        $db_write = $resource->getConnection('core_write');
        $sd_updates = $resource->getTableName('sd_updates');
        $sd_users = $resource->getTableName('sd_users');
        $rq_sql = '
        DELETE
        FROM `' . $sd_updates . '`
        WHERE `store_id` = ' . (int)$store_id . '
        AND `list_type` = ' . $db_write->quote($list_type);
        $db_write->query($rq_sql);
        $rq_sql = '
        DELETE
        FROM `' . $sd_users . '`
        WHERE `list_id` = ' . $db_write->quote($store_id . $list_type);
        $db_write->query($rq_sql);
    }

    private function getSdFormKey()
    {
        return substr(Mage::helper('core')->encrypt('SarbacaneDesktopForm'), 0, 15);
    }

}
