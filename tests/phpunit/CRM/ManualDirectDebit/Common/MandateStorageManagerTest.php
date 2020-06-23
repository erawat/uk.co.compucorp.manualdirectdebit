<?php

use Civi\Test\HeadlessInterface;
use Civi\Test\TransactionalInterface;
use Civi\Test;
use CRM_ManualDirectDebit_Common_MandateStorageManager as MandateStoreManager;

/**
 * Runs tests on MandateStorageManager.
 *
 * @group headless
 */
class CRM_ManualDirectDebit_Common_MandateStorageManagerTest extends PHPUnit_Framework_TestCase implements HeadlessInterface, TransactionalInterface {

  /**
   * @var int
   */
  private $contactId;

  /**
   * @var int
   */
  private $contributionId;

  /**
   * @var int
   */
  private $mandateId;

  /**
   * @var int
   */
  private $recurringContributionId;

  /**
   * {@inheritdoc}
   */
  public function setUpHeadless() {
    $this->contactId = civicrm_api3('Contact', 'create', [
      'first_name' => 'John',
      'last_name' => 'Doe',
      'contact_type' => 'Individual',
    ])['id'];

    $this->recurringContributionId = civicrm_api3('ContributionRecur', 'create', [
      'contact_id' => $this->contactId,
      'amount' => 100,
      'frequency_interval' => 1,
    ])['id'];

    $now = new DateTime();
    $this->contributionId =civicrm_api3('Contribution', 'create', [
      'financial_type_id' => "Member Dues",
      'receive_date' => $now->format('Y-m-d H:i:s'),
      'total_amount' => 100,
      'contact_id' => $this->contactId,
      'contribution_recur_id' => $this->recurringContributionId,
    ]);

    return Test::headless()
      ->installMe(__DIR__)
      ->apply();
  }

  public function testSaveDirectDebitMandate() {

    $defaultDDCode = civicrm_api3('OptionValue', 'get', [
      'sequential' => 1,
      'option_group_id' => 'direct_debit_codes',
      'label' => "0N",
    ])['values'][0]['value'];

    $now = new DateTime();
    $mandateValues = [
      'entity_id' => $this->contactId,
      'bank_name' => 'HSBC',
      'account_holder_name' => 'John Doe',
      'ac_number' => '12345678',
      'sort_code' => '40-11-00',
      'dd_code' => $defaultDDCode,
      'start_date' => $now->format('Y-m-d H:i:s'),
    ];

    $storageManager = new CRM_ManualDirectDebit_Common_MandateStorageManager();
    $mandate = $storageManager->saveDirectDebitMandate($this->contactId, $mandateValues);
    $this->assertNotNull($mandate->id);
    $this->assertEquals($mandate->bank_name, 'HSBC');
    $this->assertEquals($mandate->account_holder_name, 'John Doe');
    $this->assertEquals($mandate->ac_number, 'John Doe');
    $this->assertEquals($mandate->sort_code, 'John Doe');
    $this->assertEquals($mandate->dd_code, '0N');
    $this->assertNotNull($mandate->start_date);

    $this->mandateId = $mandate->id;

  }

  /**
   * Test assignContributionMandate function
   *
   */
  public function testAssignContributionMandate() {

    $storageManager = new CRM_ManualDirectDebit_Common_MandateStorageManager();
    $storageManager->assignContributionMandate($this->contributionId, $this->mandateId);

    $contributions =  civicrm_api3('Contribution', 'get', [
      'sequential' => 1,
      'custom_45' => $this->mandateId, //TODO Get custom field dynamically
    ]);

    $this->assertNotEmpty($contributions['values']);
  }

  /**
   * Test assignRecurringContributionMandate function
   *
   */
  public function testAssignRecurringContributionMandate() {

    $storageManager = new CRM_ManualDirectDebit_Common_MandateStorageManager();
    $storageManager->assignRecurringContributionMandate($this->recurringContributionId, $this->mandateId);


  }
}
