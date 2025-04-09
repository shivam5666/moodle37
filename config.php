<?php  // Moodle configuration file

unset($CFG);
global $CFG;
$CFG = new stdClass();

$CFG->dbtype    = 'mysqli';
$CFG->dblibrary = 'native';
//$CFG->dbhost    = 'db-mysql-codexeducation-do-user-9717736-0.b.db.ondigitalocean.com';
$CFG->dbhost    = '10.122.0.3';
$CFG->dbname    = 'demo';
$CFG->dbuser    = 'root'; //'root';
$CFG->dbpass    = 'yR*TGqa3hFFv6H'; //'codexeducation';
$CFG->prefix    = 'mdl_';
$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => '3306',
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_general_ci',
);

$CFG->wwwroot   = 'https://codexeducation.in/demo';
$CFG->dataroot  = '/mnt/volume_blr1_01/www/appdata/demomoodledata';
$CFG->admin     = 'admin';

$CFG->directorypermissions = 0777;
#$CFG-> dbsessions=1; 

// Force a debugging mode regardless the settings in the site administration
#@error_reporting(E_ALL | E_STRICT);   // NOT FOR PRODUCTION SERVERS!
#@ini_set('display_errors', '1');         // NOT FOR PRODUCTION SERVERS!
#$CFG->debug = (E_ALL | E_STRICT);   // === DEBUG_DEVELOPER - NOT FOR PRODUCTION SERVERS!
#$CFG->debugdisplay = 1;              // NOT FOR PRODUCTION SERVERS!

require_once(__DIR__ . '/lib/setup.php');

// There is no php closing tag in this file,
// it is intentional because it prevents trailing whitespace problems!
