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
class Sarbacane_Sarbacanedesktop_IndexController extends Mage_Core_Controller_Front_Action
{

    public function indexAction()
    {
        if (Mage::app()->getRequest()->getParam('stk') && Mage::app()->getRequest()->getParam('sdid')) {
            $sdid = Mage::app()->getRequest()->getParam('sdid');
            $stk = Mage::helper('sarbacanedesktop')->getToken();
            if (Mage::app()->getRequest()->getParam('stk') == $stk && strlen($stk) > 30 && ! Mage::helper('sarbacanedesktop')->checkFailed()) {
                if ($sdid != '' && Mage::helper('sarbacanedesktop')->getConfiguration('nb_configured') == 3) {
                    header('Content-type: text/plain; charset=utf-8');
                    $configuration = Mage::helper('sarbacanedesktop')->getConfiguration('all');
                    if ($configuration['sd_token'] != '' && $configuration['sd_is_user'] != '') {
                        $sd_list_array = Mage::helper('sarbacanedesktop')->getListConfiguration('array');
                        if (is_array($sd_list_array)) {
                            if (Mage::app()->getRequest()->getParam('list')) {
                                $list = Mage::app()->getRequest()->getParam('list');
                                $store_id = Mage::helper('sarbacanedesktop')->getIdShopFromList($list);
                                $list_type = Mage::helper('sarbacanedesktop')->getListTypeFromList($list);
                                $list_type_array = Mage::helper('sarbacanedesktop')->getListTypeArray();
                                if (in_array($list_type, $list_type_array)) {
                                    if (($list_type == 'N' && in_array($list . '0', $sd_list_array))
                                    || ($list_type == 'C' && (in_array($list . '0', $sd_list_array) || in_array($list . '1', $sd_list_array)))) {
                                        $now = gmdate('Y-m-d H:i:s');
                                        $this->processNewUnsubcribersAndSubscribers($list_type, $store_id, $sdid);
                                        $this->saveSdid($sdid, $list, $now);
                                        $this->clearHistory($list_type, $store_id);
                                    } else {
                                        header('HTTP/1.1 404 Not found');
                                        die('FAILED_ID');
                                    }
                                } else {
                                    header('HTTP/1.1 404 Not found');
                                    die('FAILED_ID');
                                }
                            } else {
                                if (Mage::app()->getRequest()->getParam('action') == 'reset') {
                                    Mage::helper('sarbacanedesktop')->deleteSdid($sdid);
                                } else {
                                    $this->setDataToUpdate();
                                    $this->getFormattedContentShops($sdid);
                                }
                            }
                        }
                    }
                }
            } else {
                $this->updateFailed();
                header("HTTP/1.1 403 Unauthorized");
                die('FAILED_SDTOKEN');
            }
            die;
        }
    }

    private function saveSdid($sd_id, $list, $now)
    {
        $resource = Mage::getSingleton('core/resource');
        $db_write = $resource->getConnection('core_write');
        $sd_users = $resource->getTableName('sd_users');
        $rq_sql = '
        INSERT INTO `' . $sd_users . '` VALUES
        (' . $db_write->quote($sd_id) . ', ' . $db_write->quote($list) . ', ' . $db_write->quote($now) . ')
        ON DUPLICATE KEY UPDATE
        `last_call_date` = VALUES(`last_call_date`)';
        $db_write->query($rq_sql);
    }

    private function updateFailed() {
        if ( ! Mage::helper('sarbacanedesktop')->checkFailed()) {
            Mage::helper('sarbacanedesktop')->updateConfiguration('sd_nb_failed', Mage::helper('sarbacanedesktop')->getConfiguration('sd_nb_failed') + 1);
        }
    }

    private function setDataToUpdate()
    {
        $resource = Mage::getSingleton('core/resource');
        $db_write = $resource->getConnection('core_write');
        $sd_updates = $resource->getTableName('sd_updates');
        $sales_flat_order = $resource->getTableName('sales_flat_order');
        $customer_entity = $resource->getTableName('customer_entity');
        $now = gmdate('Y-m-d H:i:s');
        if (count(Mage::helper('sarbacanedesktop')->getSynchronizedListsArray() > 0)) {
            $sd_last_update = Mage::helper('sarbacanedesktop')->getConfiguration('sd_last_update');
            if ($sd_last_update != '') {
                $sd_list_array = Mage::helper('sarbacanedesktop')->getListConfiguration('array');
                if (count($sd_list_array) > 0) {
                    $id_shops_customer_array = array();
                    foreach ($sd_list_array as $sd_list) {
                        $store_id = substr($sd_list, 0, -2);
                        if (strpos($sd_list, 'C') !== false) {
                            if (Mage::helper('sarbacanedesktop')->checkNeedUserInsertSdUpdates($store_id, 'C')) {
                                $id_shops_customer_array[] = (int)$store_id;
                            }
                        }
                    }
                    if (count($id_shops_customer_array) > 0) {
                        $id_shops_customer = implode(', ', $id_shops_customer_array);
                        for ($i = 0; $i < 2; $i++) {
                            if ($i == 0) {
                                $rq_sql_action = 'S';
                                $rq_sql_where = '`is_active` = 1';
                            } else {
                                $rq_sql_action = 'U';
                                $rq_sql_where = '`is_active` = 0';
                            }
                            $rq_sql = '
                            INSERT INTO `' . $sd_updates . '`
                            SELECT `email`, `store_id`, \'C\' AS `list_type`, \'' . $rq_sql_action . '\' AS `action`, ' . $db_write->quote($now) . ' AS `update_date`
                            FROM `' . $customer_entity . '`
                            WHERE `updated_at` >= ' . $db_write->quote($sd_last_update) . '
                            AND `updated_at` < ' . $db_write->quote($now) . '
                            AND `store_id` IN (' . $id_shops_customer . ')
                            AND `website_id` != 0
                            AND ' . $rq_sql_where . '
                            ON DUPLICATE KEY UPDATE
                            `action` = VALUES(`action`),
                            `update_date` = VALUES(`update_date`)';
                            $db_write->query($rq_sql);
                        }
                        $rq_sql = '
                        INSERT INTO `' . $sd_updates . '`
                        SELECT IFNULL(`c`.`email`, `sfo`.`customer_email`) AS `email`, `sfo`.`store_id`, \'C\' AS `list_type`, \'S\' AS `action`, ' . $db_write->quote($now) . ' AS `update_date`
                        FROM `' . $sales_flat_order . '` AS `sfo`
                        LEFT JOIN `' . $customer_entity . '` AS `c`
                            ON `c`.`entity_id` = `sfo`.`customer_id`
                            AND `c`.`store_id` = `sfo`.`store_id`
                            AND `c`.`website_id` != 0
                        WHERE `sfo`.`updated_at` >= ' . $db_write->quote($sd_last_update) . '
                        AND `sfo`.`updated_at` < ' . $db_write->quote($now) . '
                        AND `sfo`.`store_id` IN (' . $id_shops_customer . ')
                        ON DUPLICATE KEY UPDATE
                        `action` = VALUES(`action`),
                        `update_date` = VALUES(`update_date`)';
                        $db_write->query($rq_sql);
                    }
                }
            }
        }
        Mage::helper('sarbacanedesktop')->updateConfiguration('sd_last_update', $now);
    }

    private function processNewUnsubcribersAndSubscribers($list_type, $store_id, $sd_id)
    {
        $resource = Mage::getSingleton('core/resource');
        $db_read = $resource->getConnection('core_read');
        $sd_users = $resource->getTableName('sd_users');
        $rq_sql = '
        SELECT `last_call_date`
        FROM `' . $sd_users . '`
        WHERE `sd_id` = ' . $db_read->quote($sd_id) . '
        AND `list_id` = ' . $db_read->quote($store_id . $list_type);
        $last_call_date = $db_read->fetchOne($rq_sql);
        if ($last_call_date == null || $last_call_date == '') {
            $last_call_date = false;
        }
        $line = 'email;lastname;firstname';
        if ($list_type == 'C') {
            if ($this->checkIfListWithCustomerData('C', $store_id)) {
                $line .= ';date_first_order;date_last_order;amount_min_order;amount_max_order;amount_avg_order;nb_orders;amount_all_orders';
            }
        }
        $line .= ';action' . "\n";
        echo $line;
        $this->processNewUnsubscribers($list_type, $store_id, $last_call_date, 'display');
        $this->processNewSubscribers($list_type, $store_id, $last_call_date, 'display');
    }

    private function checkIfListWithCustomerData($list_type, $store_id)
    {
        $sd_list_array = Mage::helper('sarbacanedesktop')->getListConfiguration('array');
        if (in_array($store_id . $list_type . '1', $sd_list_array)) {
            return true;
        }
        return false;
    }

    private function getFormattedContentShops($sd_id)
    {
        $shops = Mage::helper('sarbacanedesktop')->getShopsArray();
        echo 'list_id;name;reset;is_updated;type;version' . "\n";
        $sd_list_array = Mage::helper('sarbacanedesktop')->getListConfiguration('array');
        foreach ($sd_list_array as $list) {
            foreach ($shops as $shop) {
                $store_id = Mage::helper('sarbacanedesktop')->getIdShopFromList($list);
                $list_type = Mage::helper('sarbacanedesktop')->getListTypeFromList($list);
                if ($shop['store_id'] == $store_id) {
                    $shop_list = $store_id . $list_type . ';' . $this->dQuote($shop['name']) . ';';
                    $shop_list .= $this->listStatus($store_id, $list_type, $sd_id) . ';';
                    $shop_list .= 'Magento;1.0.0.8' . "\n";//
                    echo $shop_list;
                }
            }
        }
    }

    private function listStatus($store_id, $list_type, $sd_id)
    {
        $resource = Mage::getSingleton('core/resource');
        $db_read = $resource->getConnection('core_read');
        $sd_users = $resource->getTableName('sd_users');
        $list_is_resetted = 'Y';
        $list_is_updated = 'N';
        $last_call_date = false;
        if ( ! isset($this->sd_used_lists)) {
            $rq_sql = '
            SELECT `sd_id`, `list_id`, `last_call_date`
            FROM `' . $sd_users . '`';
            $this->sd_used_lists = $db_read->fetchAll($rq_sql);
        }
        if (is_array($this->sd_used_lists)) {
            foreach ($this->sd_used_lists as $r) {
                if ($r['sd_id'] == $sd_id && $r['list_id'] == $store_id . $list_type) {
                    $list_is_resetted = 'N';
                    $last_call_date = $r['last_call_date'];
                    if ($last_call_date == null || $last_call_date == '') {
                        $last_call_date = false;
                    }
                    break;
                }
            }
        }
        if ($this->processNewUnsubscribers($list_type, $store_id, $last_call_date, 'is_updated') > 0) {
            $list_is_updated = 'Y';
        } else if ($this->processNewSubscribers($list_type, $store_id, $last_call_date, 'is_updated') > 0) {
            $list_is_updated = 'Y';
        }
        return $list_is_resetted . ';' . $list_is_updated;
    }

    private function dQuote($value)
    {
        $value = str_replace('"', '""', $value);
        if (strpos($value, ' ') !== false || strpos($value, ';') !== false) {
            $value = '"' . $value . '"';
        }
        return $value;
    }

    private function processNewSubscribers($list_type, $store_id, $last_call_date, $type_action = 'display')
    {
        $resource = Mage::getSingleton('core/resource');
        $db_read = $resource->getConnection('core_read');
        $sd_updates = $resource->getTableName('sd_updates');
        $newsletter_subscriber = $resource->getTableName('newsletter_subscriber');
        $sales_flat_order = $resource->getTableName('sales_flat_order');
        $customer_entity = $resource->getTableName('customer_entity');
        $customer_entity_varchar = $resource->getTableName('customer_entity_varchar');
        $attr_firstname = Mage::getModel('customer/customer')->getAttribute('firstname')->getAttributeId();
        $attr_lastname = Mage::getModel('customer/customer')->getAttribute('lastname')->getAttributeId();
        if ($list_type == 'N') {
            $rq_sql_select = '
            SELECT `ns`.`subscriber_email` AS `email`,
            IFNULL(`lastname`.`value`, SUBSTRING_INDEX(GROUP_CONCAT(`sfo`.`customer_lastname` ORDER BY `sfo`.`updated_at` DESC SEPARATOR \'||\'), \'||\', 1)) AS `lastname`,
            IFNULL(`firstname`.`value`, SUBSTRING_INDEX(GROUP_CONCAT(`sfo`.`customer_firstname` ORDER BY `sfo`.`updated_at` DESC SEPARATOR \'||\'), \'||\', 1)) AS `firstname`';
            $rq_sql_from = '
            LEFT JOIN `' . $customer_entity . '` AS `c`
                ON `c`.`email` = `ns`.`subscriber_email`
                AND `c`.`store_id` = `ns`.`store_id`
                AND `c`.`website_id` != 0
            LEFT JOIN `' . $customer_entity_varchar . '` AS `lastname`
                ON `lastname`.`entity_id` = `c`.`entity_id`
                AND `lastname`.`attribute_id` = ' . (int)$attr_lastname . '
            LEFT JOIN `' . $customer_entity_varchar . '` AS `firstname`
                ON `firstname`.`entity_id` = `c`.`entity_id`
                AND `firstname`.`attribute_id` = ' . (int)$attr_firstname . '
            LEFT JOIN `' . $sales_flat_order . '` AS `sfo`
                ON `sfo`.`customer_email` = `ns`.`subscriber_email`
                AND `sfo`.`store_id` = `ns`.`store_id`';
            $rq_sql_group_by = '
            GROUP BY `ns`.`subscriber_email`';
            if ( ! $last_call_date) {
                $rq_sql =
                $rq_sql_select . '
                FROM `' . $newsletter_subscriber . '` AS `ns`
                ' . $rq_sql_from . '
                WHERE `ns`.`subscriber_status` = 1
                AND `ns`.`store_id` = ' . (int)$store_id .
                $rq_sql_group_by;
            } else {
                $rq_sql =
                $rq_sql_select . '
                FROM `' . $sd_updates . '` AS `s`
                INNER JOIN `' . $newsletter_subscriber . '` AS `ns`
                    ON `ns`.`subscriber_email` = `s`.`email`
                    AND `ns`.`store_id` = `s`.`store_id`
                    AND `ns`.`subscriber_status` = 1
                ' . $rq_sql_from . '
                WHERE `s`.`update_date` >= ' . $db_read->quote($last_call_date) . '
                AND `s`.`list_type` = \'N\'
                AND `s`.`store_id` = ' . (int)$store_id .
                $rq_sql_group_by;
            }
        } else if ($list_type == 'C') {
            $add_customer_data = $this->checkIfListWithCustomerData('C', $store_id);
            if ($add_customer_data) {
                $rq_sql_select = '
                SELECT `t`.`email`, `t`.`lastname`, `t`.`firstname`,
                MIN(`t`.`date_first_order`) AS `date_first_order`, MAX(`t`.`date_last_order`) AS `date_last_order`,
                MIN(`t`.`amount_min_order`) AS `amount_min_order`, MAX(`t`.`amount_max_order`) AS `amount_max_order`,
                ROUND(SUM(`t`.`amount_all_orders`)/SUM(`t`.`nb_orders`), 6) AS `amount_avg_order`, SUM(`t`.`nb_orders`) AS `nb_orders`,
                SUM(`t`.`amount_all_orders`) AS `amount_all_orders`
                FROM
                (';
                $rq_sql_select_table_1 = '
                SELECT `c`.`email`, `lastname`.`value` AS `lastname`, `firstname`.`value` AS `firstname`,
                MIN(`sfo`.`created_at`) AS `date_first_order`, MAX(`sfo`.`created_at`) AS `date_last_order`,
                MIN(`sfo`.`base_grand_total`) AS `amount_min_order`, MAX(`sfo`.`base_grand_total`) AS `amount_max_order`,
                COUNT(`sfo`.`created_at`) AS `nb_orders`, SUM(`sfo`.`base_grand_total`) AS `amount_all_orders`';
                $rq_sql_select_table_2 = '
                SELECT `sfo`.`customer_email` AS `email`,
                SUBSTRING_INDEX(GROUP_CONCAT(`sfo`.`customer_lastname` ORDER BY `sfo`.`updated_at` DESC SEPARATOR \'||\'), \'||\', 1) AS `lastname`,
                SUBSTRING_INDEX(GROUP_CONCAT(`sfo`.`customer_firstname` ORDER BY `sfo`.`updated_at` DESC SEPARATOR \'||\'), \'||\', 1) AS `firstname`,
                MIN(`sfo`.`created_at`) AS `date_first_order`, MAX(`sfo`.`created_at`) AS `date_last_order`,
                MIN(`sfo`.`base_grand_total`) AS `amount_min_order`, MAX(`sfo`.`base_grand_total`) AS `amount_max_order`,
                COUNT(`sfo`.`created_at`) AS `nb_orders`, SUM(`sfo`.`base_grand_total`) AS `amount_all_orders`';
                $rq_sql_from = '
                INNER JOIN `' . $customer_entity_varchar . '` AS `lastname`
                    ON `lastname`.`entity_id` = `c`.`entity_id`
                    AND `lastname`.`attribute_id` = 7
                INNER JOIN `' . $customer_entity_varchar . '` AS `firstname`
                    ON `firstname`.`entity_id` = `c`.`entity_id`
                    AND `firstname`.`attribute_id` = 5
                LEFT JOIN `' . $sales_flat_order . '` AS `sfo`
                    ON `sfo`.`customer_id` = `c`.`entity_id`
                    AND `sfo`.`store_id` = `c`.`store_id`';
                $rq_sql_group_by = '
                ) AS `t`
                GROUP BY `t`.`email`';
            } else {
                $rq_sql_select = '
                SELECT `t`.`email`, `t`.`lastname`, `t`.`firstname`
                FROM
                (';
                $rq_sql_select_table_1 = '
                SELECT `c`.`email`, `lastname`.`value` AS `lastname`, `firstname`.`value` AS `firstname`';
                $rq_sql_select_table_2 = '
                SELECT `sfo`.`customer_email` AS `email`,
                SUBSTRING_INDEX(GROUP_CONCAT(`sfo`.`customer_lastname` ORDER BY `sfo`.`updated_at` DESC SEPARATOR \'||\'), \'||\', 1) AS `lastname`,
                SUBSTRING_INDEX(GROUP_CONCAT(`sfo`.`customer_firstname` ORDER BY `sfo`.`updated_at` DESC SEPARATOR \'||\'), \'||\', 1) AS `firstname`';
                $rq_sql_from = '
                INNER JOIN `' . $customer_entity_varchar . '` AS `lastname`
                    ON `lastname`.`entity_id` = `c`.`entity_id`
                    AND `lastname`.`attribute_id` = 7
                INNER JOIN `' . $customer_entity_varchar . '` AS `firstname`
                    ON `firstname`.`entity_id` = `c`.`entity_id`
                    AND `firstname`.`attribute_id` = 5';
                $rq_sql_group_by = '
                ) AS `t`
                GROUP BY `t`.`email`';
            }
            if ( ! $last_call_date) {
                $rq_sql =
                $rq_sql_select . '
                    (
                        ' . $rq_sql_select_table_1 . '
                        FROM `' . $customer_entity . '` AS `c`
                        ' . $rq_sql_from . '
                        WHERE `c`.`store_id` = ' . (int)$store_id . '
                        AND `c`.`website_id` != 0
                        GROUP BY `c`.`email`
                    )
                    UNION ALL
                    (
                        ' . $rq_sql_select_table_2 . '
                        FROM `' . $sales_flat_order . '` AS `sfo`
                        LEFT JOIN `' . $customer_entity . '` AS `c`
                            ON `c`.`entity_id` = `sfo`.`customer_id`
                            AND `c`.`store_id` = `sfo`.`store_id`
                            AND `c`.`website_id` != 0
                        WHERE `c`.`entity_id` IS NULL
                        AND `sfo`.`store_id` = ' . (int)$store_id . '
                        GROUP BY `sfo`.`customer_email`
                    )' .
                $rq_sql_group_by;
            } else {
                $rq_sql =
                $rq_sql_select . '
                    (
                        ' . $rq_sql_select_table_1 . '
                        FROM `' . $sd_updates . '` AS `s`
                        INNER JOIN `' . $customer_entity . '` AS `c`
                            ON `c`.`email` = `s`.`email`
                            AND `c`.`store_id` = `s`.`store_id`
                            AND `c`.`website_id` != 0
                        ' . $rq_sql_from . '
                        WHERE `s`.`store_id` = ' . (int)$store_id . '
                        AND `s`.`list_type` = \'C\'
                        AND `s`.`update_date` >= ' . $db_read->quote($last_call_date) . '
                        GROUP BY `c`.`email`
                    )
                    UNION ALL
                    (
                        ' . $rq_sql_select_table_2 . '
                        FROM `' . $sd_updates . '` AS `s`
                        INNER JOIN `' . $sales_flat_order . '` AS `sfo`
                            ON `sfo`.`customer_email` = `s`.`email`
                            AND `sfo`.`store_id` = `s`.`store_id`
                        LEFT JOIN `' . $customer_entity . '` AS `c`
                            ON `c`.`entity_id` = `sfo`.`customer_id`
                            AND `c`.`store_id` = `s`.`store_id`
                            AND `c`.`website_id` != 0
                        WHERE `c`.`entity_id` IS NULL
                        AND `s`.`store_id` = ' . (int)$store_id . '
                        AND `s`.`list_type` = \'C\'
                        AND `s`.`update_date` >= ' . $db_read->quote($last_call_date) . '
                        GROUP BY `sfo`.`customer_email`
                    )' .
                $rq_sql_group_by;
            }
        } else {
            if ($type_action == 'is_updated') {
                return 0;
            } else {
                return;
            }
        }
        $rq = $db_read->query($rq_sql);
        while ($r = $rq->fetch()) {
            if ($type_action == 'is_updated') {
                return 1;
            }
            $line = $this->dQuote($r['email']) . ';';
            $line .= $this->dQuote($r['lastname']) . ';' . $this->dQuote($r['firstname']);
            if ($list_type == 'C') {
                if ($add_customer_data) {
                    $line .= ';' . $this->dQuote($r ['date_first_order']) . ';' . $this->dQuote($r['date_last_order']);
                    $line .= ';' . (float)$r['amount_min_order'] . ';' . (float)$r['amount_max_order'] . ';' . (float)$r['amount_avg_order'];
                    $line .= ';' . $r['nb_orders'] . ';' . (float)$r['amount_all_orders'];
                }
            }
            $line .= ';S' . "\n";
            echo $line;
        }
    }

    private function processNewUnsubscribers($list_type, $store_id, $last_call_date, $type_action = 'display')
    {
        $resource = Mage::getSingleton('core/resource');
        $db_read = $resource->getConnection('core_read');
        $sd_updates = $resource->getTableName('sd_updates');
        if ($last_call_date !== false && ($list_type == 'N' || $list_type == 'C')) {
            $rq_sql = '
            SELECT `email`
            FROM `' . $sd_updates . '`
            WHERE `list_type` = ' . $db_read->quote($list_type) . '
            AND `store_id` = ' . (int)$store_id . '
            AND `action` = \'U\'
            AND `update_date` >= ' . $db_read->quote($last_call_date);
            $rq = $db_read->query($rq_sql);
            while ($r = $rq->fetch()) {
                if ($type_action == 'is_updated') {
                    return 1;
                }
                $line = $this->dQuote($r['email']) . ';;';
                if ($list_type == 'C') {
                    if ($this->checkIfListWithCustomerData('C', $store_id)) {
                        $line .= ';;;;;;;;';
                    }
                }
                $line .= ';U' . "\n";
                echo $line;
            }
        }
        else {
            if ($type_action == 'is_updated') {
                return 0;
            }
        }
    }

    private function clearHistory($list_type, $store_id)
    {
        $resource = Mage::getSingleton('core/resource');
        $db_write = $resource->getConnection('core_write');
        $sd_updates = $resource->getTableName('sd_updates');
        $sd_users = $resource->getTableName('sd_users');
        $rq_sql = '
        DELETE
        FROM `' . $sd_updates . '`
        WHERE `store_id` = ' . (int)$store_id . '
        AND `list_type` = ' . $db_write->quote($list_type) . '
        AND `update_date` <= (
            SELECT MIN(`last_call_date`)
            FROM `' . $sd_users . '`
            WHERE `list_id` = ' . $db_write->quote($store_id . $list_type) . '
        )';
        $db_write->query($rq_sql);
        $rq_sql = '
        DELETE
        FROM `' . $sd_updates . '`
        WHERE `update_date` <= (
            SELECT MIN(`last_call_date`)
            FROM `' . $sd_users . '`
        )';
        $db_write->query($rq_sql);
    }

}
