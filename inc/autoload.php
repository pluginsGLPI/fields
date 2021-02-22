<?php

class PluginFieldsAutoloader
{
   protected $paths = [];

   public function __construct($options = null) {
      if (null !== $options) {
         $this->setOptions($options);
      }
   }

   public function setOptions($options) {
      if (!is_array($options) && !($options instanceof \Traversable)) {
         throw new \InvalidArgumentException();
      }

      foreach ($options as $path) {
         if (!in_array($path, $this->paths)) {
            $this->paths[] = $path;
         }
      }
      return $this;
   }

   public function processClassname($classname) {
      preg_match("/Plugin([A-Z][a-z0-9]+)([A-Z]\w+)/", $classname, $matches);

      if (count($matches) < 3) {
         return false;
      } else {
         return $matches;
      }

   }

   public function autoload($classname) {
      $matches = $this->processClassname($classname);

      if ($matches !== false) {
         $plugin_name = strtolower($matches[1]);
         $class_name = strtolower($matches[2]);

         if ($plugin_name !== "fields") {
            return false;
         }

         $filename = implode(".", [
            $class_name,
            "class",
            "php"
         ]);

         foreach ($this->paths as $path) {
            $test = $path . DIRECTORY_SEPARATOR . $filename;
            if (file_exists($test)) {
               return include_once($test);
            }
         }
      }
      return false;
   }

   public function register() {
      spl_autoload_register([$this, 'autoload']);
   }
}

