<?php

namespace tests\units;

class PluginFieldsContainer extends \FieldsDbTestCase {

   public function testGetTypeName() {
      $this->string(\PluginFieldsContainer::getTypeName())->isIdenticalTo('Block');
   }

   public function testRawSearchOptions() {
      $container = new \PluginFieldsContainer();
      $this
         ->given($this->newTestedInstance)
         ->then
            ->array($this->testedInstance->rawSearchOptions())
               ->hasSize(8);
   }

   public function testNewContainer() {
      $container = new \PluginFieldsContainer();

      $data = [
         'label'     => '_container_label1',
         'type'      => 'tab',
         'is_active' => '1',
         'itemtypes' => ["Computer", "User"]
      ];

      $newid = $container->add($data);
      $this->integer($newid)->isGreaterThan(0);

      $this->boolean(class_exists('PluginFieldsComputercontainerlabelOne'))->isTrue();
   }

   public function testGetTypes() {
      $expected = [
         'tab'    => 'Add tab',
         'dom'    => 'Insertion in the form (before save button)',
         'domtab' => 'Insertion in the form of a specific tab (before save button)'
      ];

      $this->array(\PluginFieldsContainer::getTypes())->isIdenticalTo($expected);
   }

   public function testShowFormSubtype() {
      $subtypes = [
         // TODO: didn't work anymore, as we are in a type 'Item_OperatingSystem' (was before Computer)
         // @see PluginFieldsContainer::getSubtypes()
         /*\Computer::getType() => "/<select name='subtype' id='[^']*'[^>]*>" .
                                 "<option value='Computer\\\$1'>Operating system<\/option>" .
                                 "<\/select>/",*/
         \Ticket::getType()   => "/<select name='subtype' id='[^']*'[^>]*>" .
                                 "<option value='Ticket\\\$2'>Solution<\/option>" .
                                 "<\/select>/",
         \Problem::getType()  => "/<select name='subtype' id='[^']*'[^>]*>" .
                                 "<option value='Problem\\\$2'>Solution<\/option>" .
                                 "<\/select>/",
         \Change::getType()   => "/<select name='subtype' id='[^']*'[^>]*>" .
                                 "<option value='Change\\\$1'>Analysis<\/option>" .
                                 "<option value='Change\\\$2'>Solution<\/option>" .
                                 "<option value='Change\\\$3'>Plans<\/option>" .
                                 "<\/select>/",
         \Entity::getType()   => "/<select name='subtype' id='[^']*'[^>]*>" .
                                 "<option value='Entity\\\$2'>Address<\/option>" .
                                 "<option value='Entity\\\$3'>Advanced information<\/option>" .
                                 "<option value='Entity\\\$4'>Notifications<\/option>" .
                                 "<option value='Entity\\\$5'>Assistance<\/option>" .
                                 "<option value='Entity\\\$6'>Assets<\/option>" .
                                 "<\/select>/",
         \Software::getType() => "/<script type='text\/javascript'>jQuery\('#tab_tr'\)\.hide\(\);<\/script>/"
      ];

      foreach ($subtypes as $type => $pattern) {
         $form = \PluginFieldsContainer::showFormSubtype(
            [
               'type'      => 'domtab',
               'itemtype'  => $type
            ]
         );

         $this->integer(preg_match($pattern, $form))->isIdenticalTo(1);
      }
   }
}
