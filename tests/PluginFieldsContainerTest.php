<?php

use PHPUnit\Framework\TestCase;

class PluginFieldsContainerTest extends TestCase
{
    private array $createdContainers    = [];
    private array $createdTickets       = [];

    protected function setUp(): void
    {
        $_SESSION['glpiactive_entity'] = 0;
    }

    protected function tearDown(): void
    {
        $this->deleteAllContainers();

        // Delete all created tickets
        foreach ($this->createdTickets as $ticketId) {
            $ticket = new Ticket();
            $ticket->delete(['id' => $ticketId]);
        }
    }

    /**
     * * Delete all created containers and their associated fields and profiles
     */
    private function deleteAllContainers(): void
    {
        // Delete all created containers
        foreach ($this->createdContainers as $containerId) {
            $fieldsProfile      = new PluginFieldsProfile();
            $fieldsObj          = new PluginFieldsField();
            $fieldscontainer    = new PluginFieldsContainer();

            $fieldsProfile->deleteByCriteria(['plugin_fields_containers_id' => $containerId]);
            $fieldsObj->deleteByCriteria(['plugin_fields_containers_id' => $containerId]);
            $fieldscontainer->delete(['id' => $containerId]);
        }

        $this->createdContainers = [];
        $this->createdTickets = [];
    }

    /**
     * * Add container
     * @param array $input
     * @return false|int
     */
    private function addContainer(array $input): false|int
    {
        $container = new PluginFieldsContainer();
        $input['is_active'] = 1;
        $containerId = $container->add($input, [], false);
        if (is_int($containerId) && $containerId > 0) {
            $this->createdContainers[] = $containerId;
        }
        return $containerId;
    }

    /**
     * * Get container by ID
     * @param int $id
     * @return PluginFieldsContainer
     */
    private function getContainer(int $id): PluginFieldsContainer
    {
        $container = new PluginFieldsContainer();
        $container->getFromDB($id);
        return $container;
    }

    /**
     * * Add field to container
     * @param int $containerId
     * @param string $fieldName
     * @param string $type
     * @return false|int
     */
    private function addFieldToContainer(int $containerId, string $fieldName, string $type = 'text', bool $multiple = false): false|int
    {
        $field = new PluginFieldsField();
        $id = $field->add([
            'name' => $fieldName,
            'label' => ucfirst($fieldName),
            'type' => $type,
            'plugin_fields_containers_id' => $containerId,
            'ranking' => 1,
            'default_value' => '',
            'is_active' => 1,
            'is_readonly' => 0,
            'mandatory' => 1,
            'multiple' => $multiple ? 1 : 0,
            'allowed_values' => null,
        ]);

        $container = new PluginFieldsContainer();
        $container->getFromDB($containerId);
        $className = PluginFieldsContainer::getClassname('Ticket', $container->fields['name']);
        $className::addField($fieldName, $type, ['multiple' => $multiple]);

        return $id;
    }

    /**
     * * Add ticket
     * @param array $input
     * @return Ticket|false
     */
    private function addTicket(array $input): Ticket|false
    {
        $ticket = new Ticket();
        $ticketId = $ticket->add($input);
        if (is_int($ticketId)) {
            $this->createdTickets[] = $ticketId;
        } else {
            return false;
        }
        return $this->getTicket($ticketId);
    }

    /**
     * * Get ticket by ID
     * @param int $id
     * @return Ticket
     */
    private function getTicket(int $id): Ticket
    {
        $ticket = new Ticket();
        $ticket->getFromDB($id);
        return $ticket;
    }

    /**
     * * Test adding and reading a container
     */
    public function testAddAndReadContainer(): void
    {
        $containerOne = $this->addContainer([
            'name'    => 'testcontainerone',
            'label' => 'Test container 1',
            'itemtypes' => ['Computer', 'Ticket'],
            'type' => 'tab',
            'subtype' => null,
            'entities_id' => 0,
            'is_recursive' => 0,
        ]);
        $this->assertNotFalse($containerOne);
        $this->assertIsInt($containerOne);

        $containerOne = $this->getContainer($containerOne);

        $this->assertSame('Test container 1', $containerOne->fields['label']);
        $this->assertStringContainsString('Computer', $containerOne->fields['itemtypes']);
        $this->assertStringContainsString('Ticket', $containerOne->fields['itemtypes']);

        $this->deleteAllContainers();
    }

    /**
     * * Test find containers returns correct containers for given itemtype and entity
     */
    public function testFindContainersReturnsCorrectContainersForGivenItemtypeAndEntity()
    {
        // Container A should be found
        $idExactMatchContainer = $this->addContainer([
            'name'         => 'containerca',
            'label'        => 'Container CA',
            'itemtypes'    => ['Ticket'],
            'type'         => 'dom',
            'entities_id'  => 0,
            'is_recursive' => 0,
        ]);

        // Container B should be found (recursive and same entity)
        $idRecursiveParentContainer = $this->addContainer([
            'name'         => 'containercb',
            'label'        => 'Container CB',
            'itemtypes'    => ['Ticket'],
            'type'         => 'dom',
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        // Container C should not be found (wrong entity)
        $idWrongEntityContainer = $this->addContainer([
            'name'         => 'containercc',
            'label'        => 'Container CC',
            'itemtypes'    => ['Ticket'],
            'type'         => 'dom',
            'entities_id'  => 1,
            'is_recursive' => 0,
        ]);

        // Container D should not be found (recursive but wrong entity)
        $idRecursiveWrongEntityContainer = $this->addContainer([
            'name'         => 'containercd',
            'label'        => 'Container CD',
            'itemtypes'    => ['Ticket'],
            'type'         => 'dom',
            'entities_id'  => 1,
            'is_recursive' => 1,
        ]);

        // Container E should not be found (wrong itemtype)
        $idWrongItemtypeContainer = $this->addContainer([
            'name'         => 'containerce',
            'label'        => 'Container CE',
            'itemtypes'    => ['Computer'],
            'type'         => 'dom',
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        // Container F should be found (multiple itemtypes)
        $idMultipleItemtypesContainer = $this->addContainer([
            'name'         => 'containercf',
            'label'        => 'Container CF',
            'itemtypes'    => ['Computer', 'Ticket'],
            'type'         => 'dom',
            'entities_id'  => 0,
            'is_recursive' => 0,
        ]);

        $containersIds = PluginFieldsContainer::findContainers('Ticket', 'dom', '', 0);

        $this->assertContains($idExactMatchContainer, $containersIds, 'Container A should be found');
        $this->assertContains($idRecursiveParentContainer, $containersIds, 'Container B should be found (recursive and same entity)');
        $this->assertContains($idMultipleItemtypesContainer, $containersIds, 'Container F should be found (multiple itemtypes)');

        $this->assertNotContains($idWrongEntityContainer, $containersIds, 'Container C should not be found (wrong entity)');
        $this->assertNotContains($idRecursiveWrongEntityContainer, $containersIds, 'Container D should not be found (recursive but wrong entity)');
        $this->assertNotContains($idWrongItemtypeContainer, $containersIds, 'Container E should not be found (wrong itemtype)');

        $this->deleteAllContainers();
    }

    public function testFindContainersWithSubtypeInExpectedFormat(): void
    {
        // Container with a subtype (itemtype "Entity", type "domtab" and subtype "Entity$1") should be found
        $idValidSubtypeContainer = $this->addContainer([
            'name'         => 'containerentitytabone',
            'label'        => 'Container Entity Tab 1',
            'itemtypes'    => ['Entity'],
            'type'         => 'domtab',
            'subtype'      => 'Entity$1',
            'entities_id'  => 0,
            'is_recursive' => 0,
        ]);

        // Container with a another subtype (itemtype "Entity", type "domtab" and subtype "Entity$2") should not be found
        $idDifferentSubtypeContainer = $this->addContainer([
            'name'         => 'containerentitytabtwo',
            'label'        => 'Container Entity Tab 2',
            'itemtypes'    => ['Entity'],
            'type'         => 'domtab',
            'subtype'      => 'Entity$2',
            'entities_id'  => 0,
            'is_recursive' => 0,
        ]);

        // Same subtype but itemtype is "Ticket" => should not be found
        $idWrongItemtypeWithSubtype = $this->addContainer([
            'name'         => 'containerwrongitemtype',
            'label'        => 'Container Wrong itemType',
            'itemtypes'    => ['Ticket'],
            'type'         => 'domtab',
            'subtype'      => 'Entity$1',
            'entities_id'  => 0,
            'is_recursive' => 0,
        ]);

        // Same subtype and itemtype, but wrong type "dom" => should not be found
        $idWrongTypeWithValidSubtype = $this->addContainer([
            'name'         => 'containerwrongType',
            'label'        => 'Container Wrong Type',
            'itemtypes'    => ['Entity'],
            'type'         => 'dom',
            'subtype'      => 'Entity$1',
            'entities_id'  => 0,
            'is_recursive' => 0,
        ]);

        $containersIds = PluginFieldsContainer::findContainers('Entity', 'domtab', 'Entity$1', 0);

        $this->assertIsArray($containersIds);
        $this->assertNotEmpty($containersIds);

        $this->assertContains($idValidSubtypeContainer, $containersIds, 'Container with a subtype (itemtype "Entity", type "domtab" and subtype "Entity$1") should be found');

        $this->assertNotContains($idDifferentSubtypeContainer, $containersIds, 'Container with a another subtype (itemtype "Entity", type "domtab" and subtype "Entity$2") should not be found');
        $this->assertNotContains($idWrongItemtypeWithSubtype, $containersIds, 'Same subtype but itemtype is "Ticket" => should not be found');
        $this->assertNotContains($idWrongTypeWithValidSubtype, $containersIds, 'Same subtype and itemtype, but wrong type "dom" => should not be found');

        $this->deleteAllContainers();
    }

    public function testFindContainersConsidersRecursiveEntitiesFromChild(): void
    {
        // Container defined in parent entity (0) with recursion => should be visible from child
        $idContainerParentRecursive = $this->addContainer([
            'name'         => 'containerparentrecursive',
            'label'        => 'Container parent recursive',
            'itemtypes'    => ['Ticket'],
            'type'         => 'dom',
            'subtype'      => null,
            'entities_id'  => 0,
            'is_recursive' => 1,
        ]);

        // Container defined in parent entity (0) without recursion => should not be visible from child
        $idContainerParentNotRecursive = $this->addContainer([
            'name'         => 'containerparentnotrecursive',
            'label'        => 'Container parent not recursive',
            'itemtypes'    => ['Ticket'],
            'type'         => 'dom',
            'subtype'      => null,
            'entities_id'  => 0,
            'is_recursive' => 0,
        ]);

        $containersIds = PluginFieldsContainer::findContainers('Ticket', 'dom', '', 1);

        $this->assertIsArray($containersIds);
        $this->assertNotEmpty($containersIds);

        $this->assertContains($idContainerParentRecursive, $containersIds, 'Container defined in parent entity (0) with recursion => should be visible from child');
        $this->assertNotContains($idContainerParentNotRecursive, $containersIds, 'Container defined in parent entity (0) without recursion => should not be visible from child');
        $this->deleteAllContainers();
    }

    public function testParentCannotSeeChildRecursiveContainers(): void
    {
        // Container defined in child (1), recursive
        $idContainerChildRecursive = $this->addContainer([
            'name'         => 'containerchildrecursive',
            'label'        => 'Container child recursive',
            'itemtypes'    => ['Ticket'],
            'type'         => 'dom',
            'subtype'      => null,
            'entities_id'  => 1,
            'is_recursive' => 1,
        ]);

        $containersIds = PluginFieldsContainer::findContainers('Ticket', 'dom', '', 0);

        $this->assertNotContains($idContainerChildRecursive, $containersIds, 'Container defined in child (1), recursive, should not be visible from parent (0)');
        $this->deleteAllContainers();
    }

    public function testPreItemHandlesMultipleValidContainers(): void
    {
        $_SESSION['glpiactive_entity'] = 0;
        $_SESSION['glpiactiveprofile']['id'] = 4;

        $id1 = $this->addContainer([
            'name' => 'container1',
            'label' => 'Container 1',
            'itemtypes' => ['Ticket'],
            'type' => 'dom',
            'entities_id' => 0,
            'is_recursive' => 0,
        ]);
        $this->addFieldToContainer($id1, 'testfield1');

        $id2 = $this->addContainer([
            'name' => 'container2',
            'label' => 'Container 2',
            'itemtypes' => ['Ticket'],
            'type' => 'dom',
            'entities_id' => 0,
            'is_recursive' => 1,
        ]);
        $this->addFieldToContainer($id2, 'testfield2');

        $ticket = new Ticket();
        $ticket->input = [
            'name' => 'Test ticket',
            'content' => 'Test content',
            'entities_id' => 0,
            'plugin_fields_' . $id1 . '_testfield1' => 'foo',
            'plugin_fields_' . $id2 . '_testfield2' => 'bar',
            'status' => 1,
        ];

        $preItemReturn = PluginFieldsContainer::preItem($ticket);

        $this->assertTrue($preItemReturn);
        $this->assertArrayHasKey('_plugin_fields_data_multi', $ticket->input);
        $this->assertCount(2, $ticket->input['_plugin_fields_data_multi']);

        $data = $ticket->input['_plugin_fields_data_multi'];

        $this->assertSame('foo', $data[0]['testfield1']);
        $this->assertSame('bar', $data[1]['testfield2']);
        $this->deleteAllContainers();
    }

    public function testPostItemAddHandlesMultipleContainers(): void
    {
        $_SESSION['glpiactive_entity'] = 0;
        $_SESSION['glpiactiveprofile']['id'] = 4;
        $_REQUEST['massiveaction'] = false;

        // add containers + fields
        $containerId1 = $this->addContainer([
            'name' => 'containerpostitemaddone',
            'label' => 'Container postItemAdd 1',
            'itemtypes' => ['Ticket'],
            'type' => 'dom',
            'entities_id' => 0,
            'is_recursive' => 0,
        ]);
        $this->addFieldToContainer($containerId1, 'field1');

        $containerId2 = $this->addContainer([
            'name' => 'containerpostitemaddtwo',
            'label' => 'Container postItemAdd 2',
            'itemtypes' => ['Ticket'],
            'type' => 'dom',
            'entities_id' => 0,
            'is_recursive' => 0,
        ]);
        $this->addFieldToContainer($containerId2, 'field2');

        // add a ticket
        $ticket = $this->addTicket([
            'name'                                      => 'Test ticket postItemAdd',
            'content'                                   => 'test content',
            'entities_id'                               => 0,
        ]);

        // 3. Injecter les données comme si elles venaient du formulaire
        $ticket->input += [
            '_plugin_fields_data_multi' => [
                [
                    'plugin_fields_containers_id' => $containerId1,
                    'field1' => 'value1',
                ],
                [
                    'plugin_fields_containers_id' => $containerId2,
                    'field2' => 'value2',
                ],
            ],
        ];

        // check if postItemAdd return true
        $this->assertTrue(PluginFieldsContainer::postItemAdd($ticket));

        // check if the data is correctly saved in database
        $className1 = PluginFieldsContainer::getClassname('Ticket', 'containerpostitemaddone');
        $objClass1 = new $className1();
        $objClass1->getFromDBByCrit([
            'items_id' => $ticket->getID(),
            'plugin_fields_containers_id' => $containerId1,
        ]);
        $this->assertEquals('value1', $objClass1->fields['field1']);

        $className2 = PluginFieldsContainer::getClassname('Ticket', 'containerpostitemaddtwo');
        $objClass2 = new $className2();
        $objClass2->getFromDBByCrit([
            'items_id' => $ticket->getID(),
            'plugin_fields_containers_id' => $containerId2,
        ]);
        $this->assertEquals('value2', $objClass2->fields['field2']);
        $this->deleteAllContainers();
    }

    public function testPreItemUpdateHandlesMultipleContainers(): void
    {
        $_SESSION['glpiactive_entity'] = 0;
        $_SESSION['glpiactiveprofile']['id'] = 4;
        $_REQUEST['massiveaction'] = false;

        // add containers + fields
        $containerId1 = $this->addContainer([
            'name' => 'containerupdateone',
            'label' => 'Container update 1',
            'itemtypes' => ['Ticket'],
            'type' => 'dom',
            'entities_id' => 0,
            'is_recursive' => 0,
        ]);
        $this->addFieldToContainer($containerId1, 'field1');

        $containerId2 = $this->addContainer([
            'name' => 'containerupdatetwo',
            'label' => 'Container update 2',
            'itemtypes' => ['Ticket'],
            'type' => 'dom',
            'entities_id' => 0,
            'is_recursive' => 0,
        ]);
        $this->addFieldToContainer($containerId2, 'field2');

        // add a ticket
        $ticket = $this->addTicket([
            'name' => 'Ticket to update',
            'content' => 'test content',
            'entities_id' => 0,
        ]);

        $ticket->input['_plugin_fields_data_multi'] = [
            [
                'plugin_fields_containers_id' => $containerId1,
                'field1' => 'value1',
            ],
            [
                'plugin_fields_containers_id' => $containerId2,
                'field2' => 'value2',
            ],
        ];
        PluginFieldsContainer::postItemAdd($ticket);

        // update the fields
        $ticket->input += [
            'plugin_fields_' . $containerId1 . '_field1' => 'value1 updated',
            'plugin_fields_' . $containerId2 . '_field2' => 'value2 updated',
        ];
        PluginFieldsContainer::preItemUpdate($ticket);

        // check if the data is correctly updated in database
        $className1 = PluginFieldsContainer::getClassname('Ticket', 'containerupdateone');
        $valueField1 = (new $className1())->find(['items_id' => $ticket->getID(), 'plugin_fields_containers_id' => $containerId1]);
        $this->assertSame('value1 updated', current($valueField1)['field1']);

        $className2 = PluginFieldsContainer::getClassname('Ticket', 'containerupdatetwo');
        $valueField2 = (new $className2())->find(['items_id' => $ticket->getID(), 'plugin_fields_containers_id' => $containerId2]);
        $this->assertSame('value2 updated', current($valueField2)['field2']);
        $this->deleteAllContainers();
    }

    public function testPopulateDataWithPrefix(): void
    {
        $_SESSION['glpiactive_entity'] = 0;
        $_SESSION['glpiactiveprofile']['id'] = 4;

        // add a container with a field
        $containerId = $this->addContainer([
            'name' => 'containerpopulate',
            'label' => 'Container populate',
            'itemtypes' => ['Ticket'],
            'type' => 'dom',
            'entities_id' => 0,
            'is_recursive' => 0,
        ]);
        $this->addFieldToContainer($containerId, 'field1');

        // add a ticket with input data
        $ticket = new Ticket();
        $ticket->fields['id'] = 123;
        $ticket->fields['entities_id'] = 0;
        $ticket->input = [
            'plugin_fields_' . $containerId . '_field1' => 'hello world',
        ];

        // call populateData
        $data = PluginFieldsContainer::populateData($containerId, $ticket);

        // check fields are populated without prefix
        $this->assertIsArray($data);
        $this->assertSame(123, $data['items_id']);
        $this->assertSame('Ticket', $data['itemtype']);
        $this->assertSame(0, $data['entities_id']);
        $this->assertSame('hello world', $data['field1']);
        $this->deleteAllContainers();
    }

    public function testPopulateDataWithMultipleSelectionFields(): void
    {
        $_SESSION['glpiactive_entity'] = 0;
        $_SESSION['glpiactiveprofile']['id'] = 4;

        // add a container with a dropdown field that allows multiple selections
        $containerId = $this->addContainer([
            'name' => 'containermulti',
            'label' => 'Container Multi',
            'itemtypes' => ['Ticket'],
            'type' => 'dom',
            'entities_id' => 0,
            'is_recursive' => 0,
        ]);

        $fieldName = 'comboonemulti';
        $this->addFieldToContainer($containerId, $fieldName, 'dropdown', true);

        // add options to the dropdown field
        $dropdownClass = PluginFieldsDropdown::getClassname($fieldName);
        $dropdown = new $dropdownClass();
        $idOptA = $dropdown->add(['name' => 'Option A']);
        $idOptB = $dropdown->add(['name' => 'Option B']);

        // add a ticket with input data
        $ticket = new Ticket();
        $ticket->getEmpty();
        $ticket->fields['id'] = 1001;
        $ticket->fields['entities_id'] = 0;

        $ticket->input = [
            "plugin_fields_{$containerId}_{$fieldName}dropdowns_id" => [$idOptA, $idOptB],
            "_plugin_fields_{$containerId}_{$fieldName}dropdowns_id_defined" => true,
        ];

        // call populateData
        $data = PluginFieldsContainer::populateData($containerId, $ticket);
        $this->assertIsArray($data);

        $col = "plugin_fields_{$fieldName}dropdowns_id";
        $this->assertArrayHasKey($col, $data);
        $this->assertSame([$idOptA, $idOptB], $data[$col]);
        $this->deleteAllContainers();
    }

    public function testShowForTabDisplaysMultipleContainers(): void
    {
        $_SESSION['glpiactive_entity']          = 0;
        $_SESSION['glpiactiveprofile']['id']    = 4; // profil with right to read fields
        $_SERVER['REQUEST_URI']                 = '/front/ticket.form.php';
        $_SESSION['glpi_tabs']['ticket']        = 'ticket$main';

        // add two containers with fields
        $idContainer1 = $this->addContainer([
            'name'         => 'containeroneone',
            'label'        => 'Container 11',
            'itemtypes'    => ['Ticket'],
            'type'         => 'dom',
            'entities_id'  => 0,
            'is_recursive' => 0,
        ]);
        $this->addFieldToContainer($idContainer1, 'field1');

        $idContainer2 = $this->addContainer([
            'name'         => 'containertwotwo',
            'label'        => 'Container 22',
            'itemtypes'    => ['Ticket'],
            'type'         => 'dom',
            'entities_id'  => 0,
            'is_recursive' => 0,
        ]);
        $this->addFieldToContainer($idContainer2, 'field2');

        // add a ticket to test
        $ticket = $this->addTicket([
            'name'        => 'ShowForTab test',
            'content'     => 'dummy',
            'entities_id' => 0,
        ]);

        // capture the output of showForTab
        ob_start();
        PluginFieldsField::showForTab(['item' => $ticket, 'options' => []]);
        $html = ob_get_clean();

        // check if the HTML contains both containers
        $this->assertStringContainsString(
            'id=\'plugin_fields_container_'.$idContainer1.'\'',
            $html,
            "Container 1 (id=$idContainer1) not displayed"
        );
        $this->assertStringContainsString(
            'id=\'plugin_fields_container_'.$idContainer2.'\'',
            $html,
            "Container 2 (id=$idContainer2) not displayed"
        );
        $this->deleteAllContainers();
    }

    public function testShowForTabRightsAreEnforced(): void
    {
        $_SESSION['glpiactive_entity']      = 0;
        $_SERVER['REQUEST_URI']             = '/front/ticket.form.php';
        $_SESSION['glpi_tabs']['ticket']    = -1;

        // add a container with a field
        $containerId = $this->addContainer([
            'name'         => 'rightTest',
            'label'        => 'Container Right Test',
            'itemtypes'    => ['Ticket'],
            'type'         => 'dom',
            'entities_id'  => 0,
            'is_recursive' => 0,
        ]);
        $this->addFieldToContainer($containerId, 'visiblefield');

        // set profiles
        $profileWithRight  = 4;
        $profileNoRight    = -1;

        // add a ticket
        $ticket = new Ticket();
        $ticket->getEmpty();
        $ticket->fields['id']          = 42;
        $ticket->fields['entities_id'] = 0;

        // case 1 : profile with right
        $_SESSION['glpiactiveprofile']['id'] = $profileWithRight;

        ob_start();
        PluginFieldsField::showForTab(['item' => $ticket]);
        $htmlWithRight = trim(ob_get_clean());

        $this->assertNotSame(
            '',
            $htmlWithRight,
            'Container should be visible for a profile with right.'
        );
        // end case 1

        // case 2 : profile without right
        $_SESSION['glpiactiveprofile']['id'] = $profileNoRight;

        ob_start();
        PluginFieldsField::showForTab(['item' => $ticket]);
        $htmlNoRight = trim(ob_get_clean());

        $this->assertSame(
            '',
            $htmlNoRight,
            'Container should not be visible for a profile without right.'
        );
        // end case 2
        $this->deleteAllContainers();
    }
}
