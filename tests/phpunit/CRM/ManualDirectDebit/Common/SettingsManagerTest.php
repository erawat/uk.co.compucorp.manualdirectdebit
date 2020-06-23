<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;
use Civi\Test;
use Civi;

/**
 * Runs tests on SettingsManager.
 *
 * @group headless
 */
class CRM_ManualDirectDebit_Common_SettingsManagerTest extends PHPUnit_Framework_TestCase implements HeadlessInterface, TransactionalInterface {

  /**
   * {@inheritdoc}
   */
  public function setUpHeadless() {
    return Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function testGetManualDirectDebitSettings() {
    //Flush CiviCRM settings to activate CiviCRM settings that defined in the extensions.
    //\Civi::service('settings_manager')->flush();
    $settingsManager = new CRM_ManualDirectDebit_Common_SettingsManager();
    $settingsManager->getManualDirectDebitSettings();
  }

}
