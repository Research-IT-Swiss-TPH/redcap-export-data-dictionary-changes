<?php

// Set the namespace defined in your config file
namespace STPH\exportDataDictionaryChanges;



// Declare your module class, which must extend AbstractExternalModule 
class exportDataDictionaryChanges extends \ExternalModules\AbstractExternalModule {

    private $moduleName = "Export Data Dictionary Changes";  

   /**
    * Constructs the class
    *
    */
    public function __construct()
    {        
        parent::__construct();
       // Other code to run when object is instantiated
    }

   /**
    * Hooks Export Data Dictionary Changes module to redcap_every_page_top
    *
    */
    public function redcap_every_page_top($project_id = null) {
        $this->renderModule();
    }

   /**
    * Renders the module
    *
    */
    private function renderModule() {
        
        $this->includeJavascript();
        
        
        $this->includeCSS();
        

        print '<p class="export-data-dictionary-changes">'.$this->helloFrom_exportDataDictionaryChanges().'<p>';

    }

    public function helloFrom_exportDataDictionaryChanges() {

        
        return $this->tt("hello_from").' '.$this->moduleName;
        

    }

    
   /**
    * Include JavaScript files
    *
    */
    private function includeJavascript() {
        ?>
        <script src="<?php print $this->getUrl('js/main.js'); ?>"></script>
        <script> 
            $(function() {
                $(document).ready(function(){
                    STPH_exportDataDictionaryChanges.init();
                })
            });
        </script>
        <?php
    }
    

    
   /**
    * Include Style files
    *
    */
    private function includeCSS() {
        ?>
        <link rel="stylesheet" href="<?= $this->getUrl('style.css')?>">
        <?php
    }
    
}