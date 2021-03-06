<?php

require_once dirname(__FILE__) . '/TestBase.php';

/**
 * @author  Jason M. Felice <jason.m.felice@gmail.com>
 * @package Turba
 * @subpackage UnitTests
 */
class Turba_ListTest extends Turba_TestBase {

    function setUp()
    {
        parent::setUp();
        $this->setUpDatabase();
    }

    function test_sort_should_sort_according_to_passed_parameters()
    {
        $this->assertSortsList(array($this, 'sortList'));
    }

    function sortList($order)
    {
        $list = $this->getList();
        $list->sort($order);
        return $list;
    }

}
