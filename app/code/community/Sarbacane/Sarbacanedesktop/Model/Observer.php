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
class Sarbacane_Sarbacanedesktop_Model_Observer
{

    public function customerSave(Varien_Event_Observer $observer)
    {
        try {
            if (Mage::app()->getRequest()->getActionName() != 'saveOrder') {
                $old_data = $observer->getCustomer()->getOrigData();
                $new_data = $observer->getCustomer()->getData();
                if (isset($old_data['email']) && isset($new_data['email']) && isset($old_data['store_id']) && isset($new_data['store_id']) && isset($old_data['website_id']) && isset($new_data['website_id'])) {
                    $old_website_id = $old_data['website_id'];
                    $new_website_id = $new_data['website_id'];
                    if ($old_website_id != 0 || $new_website_id != 0) {
                        $old_email = $old_data['email'];
                        $new_email = $new_data['email'];
                        $old_store_id = $old_data['store_id'];
                        $new_store_id = $new_data['store_id'];
                        if ($old_email != $new_email || $old_store_id != $new_store_id) {
                            $now = gmdate('Y-m-d H:i:s');
                            $this->userInsertSdUpdatesIfNecessary($old_email, $old_store_id, 'C', 'U', $now);
                        }
                    }
                }
            }
        }
        catch(Exception $e) {}
    }

    public function customerDelete(Varien_Event_Observer $observer)
    {
        try {
            $data = $observer->getCustomer()->getData();
            if (isset($data['email']) && isset($data['entity_id']) && isset($data['store_id']) && isset($data['website_id'])) {
                $website_id = $data['website_id'];
                if ($website_id != 0) {
                    $email = $data['email'];
                    $store_id = $data['store_id'];
                    $now = gmdate('Y-m-d H:i:s');
                    $this->userInsertSdUpdatesIfNecessary($email, $store_id, 'C', 'U', $now);
                }
            }
        }
        catch(Exception $e) {}
    }

    public function newsletterSubscriberSave(Varien_Event_Observer $observer)
    {
        try {
            $action_name = Mage::app()->getRequest()->getActionName();
            if ($action_name != 'saveOrder') {
                $data = $observer->getSubscriber()->getData();
                if (isset($data['subscriber_email']) && isset($data['store_id']) && isset($data['subscriber_status'])) {
                    $new_email = $data['subscriber_email'];
                    $new_store_id = $data['store_id'];
                    $new_status = $data['subscriber_status'];
                    if ($new_status == '1' || $new_status == '3') {
                        $now = gmdate('Y-m-d H:i:s');
                        $mass_unsubscribe_subscribers = false;
                        if ($action_name == 'massUnsubscribe') {
                            if (Mage::app()->getRequest()->getParam('subscriber')) {
                                $mass_unsubscribe_subscribers = true;
                            }
                        }
                        if ($mass_unsubscribe_subscribers) {
                             $this->userInsertSdUpdatesIfNecessary($new_email, $new_store_id, 'N', 'U', $now);
                        } else {
                            if (isset($data['subscriber_id'])) {
                                $resource = Mage::getSingleton('core/resource');
                                $db_read = $resource->getConnection('core_read');
                                $newsletter_subscriber = $resource->getTableName('newsletter_subscriber');
                                $rq_sql = '
                                SELECT `subscriber_email`, `store_id`, `subscriber_status`
                                FROM `' . $newsletter_subscriber . '`
                                WHERE `subscriber_id` = ' . (int)$data['subscriber_id'];
                                $rq = $db_read->query($rq_sql);
                                while ($r = $rq->fetch()) {
                                    $old_email = $r['subscriber_email'];
                                    $old_store_id = $r['store_id'];
                                    $old_status = $r['subscriber_status'];
                                    if ($old_email != $new_email || $old_store_id != $new_store_id) {
                                        if ($old_status == '1') {
                                            $this->userInsertSdUpdatesIfNecessary($old_email, $old_store_id, 'N', 'U', $now);
                                        }
                                    } else {
                                        if ($old_status == '1' && $new_status == '3') {
                                            $this->userInsertSdUpdatesIfNecessary($new_email, $new_store_id, 'N', 'U', $now);
                                        }
                                    }
                                }
                            }
                            if ($new_status == '1') {
                                $this->userInsertSdUpdatesIfNecessary($new_email, $new_store_id, 'N', 'S', $now);
                            }
                        }
                    }
                }
            }
        }
        catch(Exception $e) {}
    }

    public function newsletterSubscriberDelete(Varien_Event_Observer $observer)
    {
        try {
            $data = $observer->getSubscriber()->getData();
            if (isset($data['subscriber_email']) && isset($data['store_id']) && isset($data['subscriber_status'])) {
                $subscriber_email = $data['subscriber_email'];
                $store_id = $data['store_id'];
                $subscriber_status = $data['subscriber_status'];
                if ($subscriber_status == '1') {
                    $now = gmdate('Y-m-d H:i:s');
                    $this->userInsertSdUpdatesIfNecessary($subscriber_email, $store_id, 'N', 'U', $now);
                }
            }
        }
        catch(Exception $e) {}
    }

    private function userInsertSdUpdatesIfNecessary($email, $store_id, $list_type, $action, $now)
    {
        if (Mage::helper('sarbacanedesktop')->checkNeedUserInsertSdUpdates($store_id, $list_type)) {
            $resource = Mage::getSingleton('core/resource');
            $db_write = $resource->getConnection('core_write');
            $sd_updates = $resource->getTableName('sd_updates');
            $rq_sql = '
            INSERT INTO `' . $sd_updates . '` VALUES
            (' . $db_write->quote($email) . ', ' . (int)$store_id . ', ' . $db_write->quote($list_type) . ', ' . $db_write->quote($action) . ', ' . $db_write->quote($now) . ')
            ON DUPLICATE KEY UPDATE
            `action` = VALUES(`action`),
            `update_date` = VALUES(`update_date`)';
            $db_write->query($rq_sql);
        }
    }

}
