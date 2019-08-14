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

$installer = $this;
$installer->startSetup();

try{
    $installer->run("DROP TRIGGER IF EXISTS sd_newsletter_update;");
}
catch(Exception $e) {}
try{
    $installer->run("DROP TRIGGER IF EXISTS sd_newsletter_insert;");
}
catch(Exception $e) {}
try{
    $installer->run("DROP TRIGGER IF EXISTS sd_customer_delete;");
}
catch(Exception $e) {}

$installer->run("
CREATE TABLE IF NOT EXISTS `{$this->getTable('sarbacanedesktop_users')}` (
    `sd_type` varchar(20) NOT NULL,
    `sd_value` varchar(200) NOT NULL,
    PRIMARY KEY(`sd_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
");

$db_read = Mage::getSingleton('core/resource')->getConnection('core_read');
$sd_token = '';
$sd_list = '';
$sd_is_user = '';
$sd_nb_failed = '';
$sd_last_update = '';

$rq_sql = "
SELECT `sd_type`, `sd_value`
FROM `{$this->getTable('sarbacanedesktop_users')}`
WHERE `sd_type` IN ('sd_token', 'sd_list');";
$rq = $db_read->query($rq_sql);
while ($r = $rq->fetch()) {
    if ($r['sd_type'] == 'sd_token') {
        $sd_token = $r['sd_value'];
    } else if ($r['sd_type'] == 'sd_list') {
        $sd_list = $r['sd_value'];
    }
}

if ($sd_token != '' || $sd_list != '') {
    $sd_is_user = 'yes';
}

$installer->run("
DROP TABLE IF EXISTS `{$this->getTable('sarbacanedesktop')}`;

DROP TABLE IF EXISTS `{$this->getTable('sarbacanedesktop_users')}`;

DROP TABLE IF EXISTS `{$this->getTable('sd_updates')}`;

DROP TABLE IF EXISTS `{$this->getTable('sd_users')}`;

DROP TABLE IF EXISTS `{$this->getTable('sd_data')}`;

CREATE TABLE `{$this->getTable('sd_updates')}` (
    `email` varchar(50) NOT NULL,
    `store_id` int(11) unsigned NOT NULL,
    `list_type` varchar(10) NOT NULL,
    `action` varchar(5) NOT NULL,
    `update_date` datetime NOT NULL,
    PRIMARY KEY (`email`, `store_id`, `list_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `{$this->getTable('sd_users')}` (
    `sd_id` varchar(200) NOT NULL,
    `list_id` varchar(50) NOT NULL,
    `last_call_date` datetime NOT NULL,
    PRIMARY KEY(`sd_id`, `list_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE TABLE `{$this->getTable('sd_data')}` (
    `sd_type` varchar(20) NOT NULL,
    `sd_value` varchar(200) NOT NULL,
    PRIMARY KEY(`sd_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

INSERT INTO `{$this->getTable('sd_data')}`
(`sd_type`, `sd_value`)
VALUES
('sd_token', {$db_read->quote($sd_token)}),
('sd_list', {$db_read->quote($sd_list)}),
('sd_is_user', {$db_read->quote($sd_is_user)}),
('sd_nb_failed', {$db_read->quote($sd_nb_failed)}),
('sd_last_update', {$db_read->quote($sd_last_update)});
");

$installer->endSetup();
