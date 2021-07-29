<?php

// Set the namespace defined in your config file
namespace STPH\exportDataDictionaryChanges;



// Declare your module class, which must extend AbstractExternalModule 
class exportDataDictionaryChanges extends \ExternalModules\AbstractExternalModule {

    private $moduleName = "Export Data Dictionary Changes";
    private $settings = [];

    private $hasExport;
    private $isExportActive;

    private $hasExportDownloadForAutomatic;
    private $hasExportMailForAutomatic;

    private $hasExportDownloadForManual;
    private $hasExportMailForManual;


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
    * Hook into redcap_every_page_top
    *
    */
    public function redcap_every_page_top($project_id = null) {

        $this->setSettings();

        //  Check if Export Download or Export Mail are enabled
        if( $this->hasExport ) {

            //  Include basic Javascript
            $this->includeJavascriptBase();

            //  Include Switch for toggle Export active state before Submit
            //  Hook into "Submit Changes" Dialog
            if(strpos(PAGE, "Design/online_designer.php") !== false ) {
                $this->includeJavascriptSwitch();
            }
            
            //  Check if Export active state is true
            if($this->isExportActive) {

                //  Process Automatic Approvals
                //  Hook into "Automatic Changes" Dialog
                if(strpos(PAGE, "Design/online_designer.php") !== false && isset($_GET['msg']) && $_GET['msg'] == "autochangessaved" ) {
                    $this->processExportsForAutomatic();                
                }            
                
                //  Process Manual Approvals
                //  Hook into "Automatic Changes" Dialog
                if(strpos(PAGE, "Design/draft_mode_notified.php") !== false && $_GET["action"] == "approve" ) {
                    $this->processExportsForManual();
                }

            }
        }          
    }

   /**
    * Set module configuration settings
    * @since 1.0.0
    */
    private function setSettings(){

        $settingHasExportDownload = $this->getProjectSetting("has-export-download");
        $this->hasExportDownloadForAutomatic = ($settingHasExportDownload == 1 || $settingHasExportDownload == 2) ? true : false;
        $this->hasExportDownloadForManual = ( $settingHasExportDownload == 1 || $settingHasExportDownload == 3) ? true : false;

        $settingHasExportMail = $this->getProjectSetting("has-export-mail");
        $this->hasExportMailForAutomatic = ($settingHasExportMail == 1 || $settingHasExportMail == 2) ? true : false;
        $this->hasExportMailForManual = ($settingHasExportMail == 1 || $settingHasExportMail == 3) ? true : false;

        $this->hasExport = $settingHasExportDownload ||  $settingHasExportMail;
        $this->isExportActive = $this->getProjectSetting("is-export-active");
    }


   /**
    * Process Exports for Automatic Approvals
    * @since 1.0.0
    */
    private function processExportsForAutomatic() {
        //  Check if Export Download is enabled for Automatic Approvals
        if( $this->hasExportDownloadForAutomatic ) {                
            ?>
            <script> 
                $(function() {
                    $(document).ready(function(){
                        STPH_exportDataDictionaryChanges.initDownloadForAutomatic();
                    })
                });
            </script>            
            <?php
        }

        //  Check if Export Email is enabled for Automatic Approvals
        if( $this->hasExportMailForAutomatic ) {
            //$this->triggerCSVMail();
        }
    }

   /**
    * Process Exports for Manual Approvals
    * @since 1.0.0
    */    
    private function processExportsForManual() {    
        //  Check if Export Download is enabled for Manual Approvals
        if( $this->hasExportDownloadForManual ) {   
            ?>
            <script> 
                $(function() {
                    $(document).ready(function(){
                        STPH_exportDataDictionaryChanges.initDownloadForManual();
                    })
                });
            </script>   
            <?php
        }

        //  Check if Export Email is enabled for Manual Approvals
        if( $this->hasExportMailForManual ) {
            //$this->triggerCSVMail(true);
        }
    }
  
   /**
    * Include JavaScript files
    *
    */
    private function includeJavascriptBase() {
        ?>        
        <script src="<?php print $this->getUrl('js/main.js'); ?>"></script>
        <script> 
            $(function() {
                STPH_exportDataDictionaryChanges.requestHandler = "<?= $this->getUrl("requestHandler.php") ?>";
            });
        </script>
        <?php
    }

    /**
     * Includes Javascript for Auto Download Switch 
     * Hooked into "SUBMIT CHANGES FOR REVIEW?" Dialog (#confirm-review) 
     * 
     * @since 1.0.0
     */    
    private function includeJavascriptSwitch() {
        ?>        
        <script> 
            $(function() {
                $(document).ready(function(){
                    STPH_exportDataDictionaryChanges.initSwitch();
                })
            });
        </script>
        <?php        
    }

    /**
     * Includes Javascript for Export Download
     * Hooked into "SUBMIT CHANGES FOR REVIEW?" Dialog (#confirm-review) 
     * 
     * @since 1.0.0
     */    
    private function includeJavascriptExportAsDownload($isAutomaticApproval) {
        if($isAutomaticApproval): ?>        
            <script> 
                $(function() {
                    $(document).ready(function(){
                        STPH_exportDataDictionaryChanges.initDownloadForAutomatic();
                    })
                });
            </script>
        <?php else : ?>
            <script> 
                $(function() {
                    $(document).ready(function(){
                        STPH_exportDataDictionaryChanges.initDownloadForManual();
                    })
                });
            </script>            
        <?php
        endif;
    }

    /**
     * Gets module configuration setting "active-auto-download" and returns its value in json
     * Called from RequestHandler via Ajax
     * 
     * @since 1.0.0
     */
    public function getExportActiveState() {
        header('Content-Type: application/json; charset=UTF-8');
        $value = $this->getProjectSetting("is-export-active");
        echo json_encode(
            array( "state" => $value )
        );
    }

    /**
     * Toogles module configuration setting "active-auto-download" and returns its value in json
     * Called from RequestHandler via Ajax
     * 
     * @since 1.0.0
     */
    public function toggleExportActive($checked) {

        if($checked === "true") {
           $this->setProjectSetting("is-export-active", true);
        } else {
           $this->setProjectSetting("is-export-active", false);
        }

        $value = $this->getProjectSetting("is-export-active");
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(
            array( "state" => $value )
        );

    }    

    
}