<?php
namespace jemdev\dbrm\init;
use jemdev\dbrm\vue;

/**
 * @package     jemdev
 *
 * Ce code est fourni tel quel sans garantie.
 * Vous avez la liberté de l'utiliser et d'y apporter les modifications
 * que vous souhaitez. Vous devrez néanmoins respecter les termes
 * de la licence CeCILL dont le fichier est joint à cette librairie.
 * {@see http://www.cecill.info/licences/Licence_CeCILL_V2-fr.html}
 */
/**#@+
 * Définition des types de données manipulées en SQL
 */
defined('TYPE_VARCHAR')      || define('TYPE_VARCHAR',      'VARCHAR');
defined('TYPE_VARBINARY')    || define('TYPE_VARBINARY',    'VARBINARY');
defined('TYPE_CHAR')         || define('TYPE_CHAR',         'CHAR');
defined('TYPE_ENUM')         || define('TYPE_ENUM',         'ENUM');
defined('TYPE_BIGINT')       || define('TYPE_BIGINT',       'BIGINT');
defined('TYPE_INTEGER')      || define('TYPE_INTEGER',      'INT');
defined('TYPE_MEDIUMINT')    || define('TYPE_MEDIUMINT',    'MEDIUMINT');
defined('TYPE_TINYINT')      || define('TYPE_TINYINT',      'TINYINT');
defined('TYPE_SMALLINT')     || define('TYPE_SMALLINT',     'SMALLINT');
defined('TYPE_DECIMAL')      || define('TYPE_DECIMAL',      'DECIMAL');
defined('TYPE_FLOAT')        || define('TYPE_FLOAT',        'FLOAT');
defined('TYPE_DOUBLE')       || define('TYPE_DOUBLE',       'DOUBLE');
defined('TYPE_DATE')         || define('TYPE_DATE',         'DATE');
defined('TYPE_TIME')         || define('TYPE_TIME',         'TIME');
defined('TYPE_DATETIME')     || define('TYPE_DATETIME',     'DATETIME');
defined('TYPE_TIMESTAMP')    || define('TYPE_TIMESTAMP',    'TIMESTAMP');
defined('TYPE_BIT')          || define('TYPE_BIT',          'BIT');
defined('TYPE_BLOB')         || define('TYPE_BLOB',         'BLOB');
defined('TYPE_LONGBLOB')     || define('TYPE_LONGBLOB',     'LONGBLOB');
defined('TYPE_TEXT')         || define('TYPE_TEXT',         'TEXT');
defined('TYPE_LONGTEXT')     || define('TYPE_LONGTEXT',     'LONGTEXT');
defined('TYPE_MEDIUMTEXT')   || define('TYPE_MEDIUMTEXT',   'MEDIUMTEXT');
defined('TYPE_TINYTEXT')     || define('TYPE_TINYTEXT',     'TINYTEXT');
/**#@-*/
/**
 * Classe de génération d'un fichier de description de schéma.
 *
 * Utilisé par les classes jemdev\dbrm\xxx, ce fichier contient les
 * description detaillées des tables, relations et vue d'un ou
 * plusieurs schémas de données.
 *
 * Note importante : ce fichier est généré à partir des données
 * collectées dans INFORMATION_SCHEMA. Avec MySQL pour les moteurs
 * ne supportant pas la gestion de l'intégrité référentielle, les
 * clé étrangères ne sont pas identifiées, pas plus que les tables
 * qu'elles référencent. Il conviendra donc d'éditer le fichier
 * dbConf.php qui est généré et d'ajouter ces éléments manquant
 * dans la section «key => fk» de chaque table ou relation.
 *
 * Ces clés étrangères sont décrites de la manière suivante :
 * 'key' =>
 *     'pk' => array(),
 *     'fk' => array(
 *         'colonne_source' => array('table_referencee' => 'colonne_referencee')
 *     )
 * La colonne source étant la colonne de la table décrite.
 *
 * L'appel de cette classe et son utilisation sont des plus simples.
 * D'abord on crée un objet en passant les paramètres nécessaires, voir
 * les commentaires du constructeur.
 * Ensuite on appelle la méthode jemdev\dbrm\init\genereconf::genererConf() qui
 * attend deux paramètres :
 * - une instance de la classe dbVue
 * - le chemin vers le fichier à générer.
 *
 * À noter également, la connexion utilise PDO et il n'y a aucun ajustement
 * particulier à ajouter selon le SGBD visé.
 *
 * @author      Jean Molliné
 * @package     jemdev
 * @subpackage  dbrm
 * @todo        Ajustement pour récupérer les informations dans Oracle et vérifier
 *              les variantes avec les autres SGBDRs.
 *              La différence résidera dans les requêtes collectant les informations
 *              et non dans leur traitement.
 *  - Oracle n'implémente pas le SQL92 complètement et INFORMATION_SCHEMA n'existe pas
 *    il existe cependant une alternative. @see http://www.alberton.info/oracle_meta_info.html
 *    ou encore @see http://etutorials.org/SQL/SQL+Bible+Oracle/Part+V+Implementing+Security+Using+System+Catalogs/Chapter+13+The+System+Catalog+and+INFORMATION_SCHEMA/Oracle+9i+Data+Dictionary/
 *  - PostGreSQL n'a pas nonplus de base INFORMATION_SCHEMA mais des tables de catalogue système
 *    qui se dupliquent dans chaque base créée. @see http://docs.postgresql.fr/8.4/catalogs.html
 *  - Microsoft SQL-Server semble implémenter correctement la norme; @see http://msdn.microsoft.com/fr-fr/library/ms186778.aspx
 *    ou encore @see http://etutorials.org/SQL/SQL+Bible+Oracle/Part+V+Implementing+Security+Using+System+Catalogs/Chapter+13+The+System+Catalog+and+INFORMATION_SCHEMA/Microsoft+SQL+Server+2000+System+Catalog/
 *  - IBM Db2 semble l'implémenter également. @see http://etutorials.org/SQL/SQL+Bible+Oracle/Part+V+Implementing+Security+Using+System+Catalogs/Chapter+13+The+System+Catalog+and+INFORMATION_SCHEMA/IBM+DB2+UDB+8.1+System+Catalogs/
 *    cependant il y a également une seconde base système qui semble beaucoup plus complète : à vérifier et tester si possible.
 */
class genereconf
{
    private $_user;
    private $_mdp;
    private $_schemacible;
    private $_schemauser;
    private $_schemamdp;
    private $_schemauserdev;
    private $_schemamdpdev;
    private $_typeserveur;
    private $_metaschema = 'INFORMATION_SCHEMA';
    private $_hote;
    private $_port;
    private $_dns;
    private $_dbh;

    private $_type_pk   = 'PRI';
    private $_type_view = 'VIEW';

    private $_aTables   = array();
    private $_aRelation = array();
    private $_aVues     = array();

    private $_sPathExecute;
    private $_sPathVue;

    private $_aTypesDonnees = array();
    /**
     * Instance de la classe de vue qui sera utilisée pour
     * l'exécution des requêtes de recherche.
     *
     * @var Object
     */
    private $_oVue;
    /**
     *
     * @var jemdev\dbrm\init\getSchemaInfos
     */
    private $_oSchemaInfos;
    /**
     * Instance de jemdev\dbrm\selectTablesNames.
     * Classe servant à identifier les tables mise en oeuvre dans une requête SELECT.
     * @var jemdev\dbrm\selectTablesNames
     */
    private $_oSelectTablesName;

    /**
     * Constructeur
     *
     * @param String    $schema         Nom du schéma dont on veut la description
     * @param String    $schemauser     Nom d'utilisateur pouvant accéder à information_schema (mode production)
     * @param String    $schemamdp      Mot de passe de l'utilisateur (mode production)
     * @param String    $rootuser       Nom de l'utilisateur root qui doit se connecter à information_Schema
     * @param String    $rootmdp        Mot-de-passe de l'utilisateur root qui doit se connecter à information_Schema
     * @param String    $typeserveur    Type de serveur (MySQL, [Oracle ?], pgSql, etc...)
     * @param String    $host           Serveur où est situé information_schema
     * @param Int       $port           Port à utiliser pour la connexion au serveur où est information_schema
     * @param String    $schemauserdev  Nom d'utilisateur du schéma à décrire (mode développement)
     * @param String    $schemamdpdev   Mot de passe de l'utilisateur du schéma à décrire (mode développement)
     */
    public function __construct(
        $schema,
        $schemauser,
        $schemamdp,
        $rootuser         = 'root',
        $rootmdp          = '',
        $typeserveur      = 'mysql',
        $host             = 'localhost',
        $port             = null,
        $schemauserdev    = null,
        $schemamdpdev     = null
    )
    {
        $this->_user            = $rootuser;
        $this->_mdp             = $rootmdp;
        $this->_schemacible     = $schema;
        $this->_schemauser      = $schemauser;
        $this->_schemamdpdev    = (isset($schemamdpdev))  ? $schemamdpdev  : $schemamdp;
        $this->_schemauserdev   = (isset($schemauserdev)) ? $schemauserdev : $schemauser;
        $this->_schemamdp       = $schemamdp;
        $this->_typeserveur     = $typeserveur;
        $this->_hote            = $host;
        $this->_port            = (!is_null($port)) ? $port : '';
        $this->_setDns();
    }

    /**
     * Appel de génération du fichier de configuration
     *
     * @param   jemdev\dbrm\dbVue   $oVue           Instance de la classe de vues.
     * @param   String              $fichierCible   Chemin vers le fichier cible.
     * @return  Boolean                             Indique la réussite ou l'échec de la procédure.
     */
    public function genererConf(vue $oVue, $fichierCible)
    {
        switch ($this->_typeserveur)
        {
            case 'pgsql':
                $this->_oSchemaInfos = new getSchemaInfosPgsql($oVue, $this->_schemacible);
                break;
            case 'mysql':
            default:
                $this->_oSchemaInfos = new getSchemaInfosMysql($oVue, $this->_schemacible);
        }
        $this->_oVue = $oVue;
        $oVue->setTmpActivationCache(false);
        $dbConf = null;
        $configSchema = $this->_getConf();
        $ns = 0;
        if(file_exists( $fichierCible))
        {
            include_once($fichierCible);
            /**
             * Note : la variable $dbConf devra avoir été définie et initialisée dans le
             * fichier inclus.
             */
            if(isset($dbConf) && count($dbConf) > 0)
            {
                foreach($dbConf as $n => $confSchema)
                {
                    if($confSchema['schema']['name'] == $this->_schemacible)
                    {
                        $dbConf[$n] = $configSchema;
                        $ns = $n;
                        $aConf = $dbConf;
                        break;
                    }
                }
            }
            else
            {
                $aConf = array($ns => $configSchema);
            }
        }
        else
        {
            $aConf = array($ns => $configSchema);
        }
        /**
         * En-tête du fichier de configuration
         */
        $dategeneration = date('d/m/Y h:m:i');
        $sFichier = <<<CODE_PHP
<?php
/**
 * @package     jemdev
 *
 * Ce code est fourni tel quel sans garantie.
 * Vous avez la liberté de l'utiliser et d'y apporter les modifications
 * que vous souhaitez. Vous devrez néanmoins respecter les termes
 * de la licence CeCILL dont le fichier est joint à cette librairie.
 * {@see http://www.cecill.info/licences/Licence_CeCILL_V2-fr.html}
 *
 * Date de génération du fichier : {$dategeneration}
 */

/**
 * Définition des constantes sur les types de données
 */
defined('TYPE_INTEGER') || define('TYPE_INTEGER', 'INT');

CODE_PHP;
        foreach($this->_aTypesDonnees as $k => $type):
            $sType = strtoupper($k);
            $sVal  = strtoupper($type);
        $sFichier .= <<<CODE_PHP
defined('{$sType}') || define('{$k}', '$sVal');

CODE_PHP;
        endforeach;
        $sFichier .= <<<CODE_PHP
/**
 * Description détaillée des schémas
 */
\$dbConf = array(

CODE_PHP;
        foreach($aConf as $n => $conf):
            $sFichier .= <<<CODE_PHP
    {$n} => array(
        'schema' => array(
            'name'   => DB_APP_SCHEMA,
            'SGBD'   => DB_ROOT_TYPEDB,
            'server' => DB_APP_SERVER,
            'port'   => DB_ROOT_DBPORT,
            'user'   => DB_APP_USER,
            'mdp'    => DB_APP_PASSWORD,
            'pilote' => DB_ROOT_TYPEDB
        ),
        'tables' => array(

CODE_PHP;
            $nt = count($conf['tables']);
            $t = 0;
            foreach($conf['tables'] as $table => $aCols):
                $eot = $t < ($nt-1) ? ',' : null;
                $sFichier .= <<<CODE_PHP
            '{$table}' => array(
                'fields' => array(

CODE_PHP;
                $nf = count($aCols['fields']);
                $c = 0;
                foreach($aCols['fields'] as $col => $aDetails):
                    $eof = ($c < ($nf-1)) ? ',' : null;
                    $sFichier .= <<<CODE_PHP
                    '{$col}' => array(
                        'type'   => {$aDetails['type']},
                        'length' => {$aDetails['length']},
                        'null'   => {$aDetails['null']},

CODE_PHP;
                    if($aDetails['attr'] == 'null'):
                        $sFichier .= <<<CODE_PHP
                        'attr'   => null

CODE_PHP;
                    else:
                        $sFichier .= <<<CODE_PHP
                        'attr'   => array(

CODE_PHP;
                        $na = count($aDetails['attr']);
                        $ina = 1;
                        foreach($aDetails['attr'] as $d => $v):
                            $virg = $ina < $na ? "," : null;
                            $s = ((
                                $d == 'default' && (
                                    $aDetails['type'] == 'TYPE_INTEGER' ||
                                    $aDetails['type'] == 'TYPE_MEDIUMINT' ||
                                    $aDetails['type'] == 'TYPE_TINYINT' ||
                                    $aDetails['type'] == 'TYPE_SMALLINT' ||
                                    $aDetails['type'] == 'TYPE_DECIMAL' ||
                                    $aDetails['type'] == 'TYPE_FLOAT'
                                )
                            ) || $d == 'vals') ? $v : "'". $v ."'";
                            $sFichier .= <<<CODE_PHP
                            '{$d}' => {$s}{$virg}

CODE_PHP;
                            $ina++;
                        endforeach;
                        $sFichier .= <<<CODE_PHP
                        )

CODE_PHP;
                    endif;
                    $sFichier .= <<<CODE_PHP
                    ){$eof}

CODE_PHP;
                    $c++;
                endforeach;
                $sFichier .= <<<CODE_PHP
                ),
                'key' => array(

CODE_PHP;
                if(isset($aCols['keys']['pk'])):
                    $sFichier .= <<<CODE_PHP
                    'pk' => array(

CODE_PHP;
                    foreach($aCols['keys']['pk'] as $pk):
                        $sFichier .= <<<CODE_PHP
                        '{$pk}'

CODE_PHP;
                    endforeach;
                    $eopk = (isset($aCols['keys']['fk']) || isset($aCols['keys']['uk'])) ? ',' : null;
                    $sFichier .= <<<CODE_PHP
                    ){$eopk}

CODE_PHP;
                endif;
                if(isset($aCols['keys']['uk'])):
                    $sFichier .= <<<CODE_PHP
                    'uk' => array(

CODE_PHP;
                    foreach($aCols['keys']['uk'] as $uk):
                        $sFichier .= <<<CODE_PHP
                        '{$uk}',

CODE_PHP;
                    endforeach;
                    $eouk = (isset($aCols['keys']['fk'])) ? ',' : null;
                    $sFichier .= <<<CODE_PHP
                    ){$eouk}

CODE_PHP;
                endif;
                if(isset($aCols['keys']['fk'])):
                    $sFichier .= <<<CODE_PHP
                    'fk' => array(

CODE_PHP;
                    $nfk = count($aCols['keys']['fk']);
                    for($ifk = 0; $ifk < $nfk; $ifk++):
                        $afk = array_keys($aCols['keys']['fk'][$ifk]);
                        $k   = $afk[0];
                        $aft = array_keys($aCols['keys']['fk'][$ifk][$k]);
                        $ft  = $aft[0];
                        $fk  = $aCols['keys']['fk'][$ifk][$k][$ft];
                        $eoa = ($ifk < ($nfk -1)) ? ',' : null;
                        $sFichier .= <<<CODE_PHP
                        '{$k}' => array('{$ft}' => '{$fk}'){$eoa}

CODE_PHP;
                    endfor;
                    $sFichier .= <<<CODE_PHP
                    )

CODE_PHP;
                endif;
                $sFichier .= <<<CODE_PHP
                )
            ){$eot}

CODE_PHP;
                $t++;
            endforeach;
            $sFichier .= <<<CODE_PHP

CODE_PHP;
            $sFichier .= <<<CODE_PHP
        ),
        'relations' => array(

CODE_PHP;
            $nr = count($conf['relations']);
            $r = 0;
            foreach($conf['relations'] as $table => $aCols):
                $aFks = $this->_oSchemaInfos->getReferencesFK($table);
                $eor = ($r < ($nr-1)) ? ',' : null;
                $sFichier .= <<<CODE_PHP
            '{$table}' => array(
                'fields' => array(

CODE_PHP;
                $nf = count($aCols['fields']);
                $c = 0;
                foreach($aCols['fields'] as $col => $aDetails):
                    $eof = ($c < ($nf-1)) ? ',' : null;
                    $sFichier .= <<<CODE_PHP
                    '{$col}' => array(
                        'type'   => {$aDetails['type']},
                        'length' => {$aDetails['length']},
                        'null'   => {$aDetails['null']},

CODE_PHP;
                    if($aDetails['attr'] == 'null'):
                        $sFichier .= <<<CODE_PHP
                        'attr'   => null

CODE_PHP;
                    else:
                        $sFichier .= <<<CODE_PHP
                        'attr'   => array(

CODE_PHP;
                        foreach($aDetails['attr'] as $d => $v):
                            $s = (
                                $d == 'default' && (
                                $aDetails['type'] == 'TYPE_INTEGER' ||
                                $aDetails['type'] == 'TYPE_MEDIUMINT' ||
                                $aDetails['type'] == 'TYPE_TINYINT' ||
                                $aDetails['type'] == 'TYPE_SMALLINT' ||
                                $aDetails['type'] == 'TYPE_DECIMAL' ||
                                $aDetails['type'] == 'TYPE_FLOAT'
                            ) || $d == 'vals') ? $v : "'". $v ."'";
                            $sFichier .= <<<CODE_PHP
                            '{$d}' => {$s},

CODE_PHP;
                        endforeach;
                        $sFichier .= <<<CODE_PHP
                        )

CODE_PHP;
                    endif;
                    $sFichier .= <<<CODE_PHP
                    ){$eof}

CODE_PHP;
                    $c++;
                endforeach;
                $sFichier .= <<<CODE_PHP
                ),
                'key' => array(

CODE_PHP;
                if(isset($aCols['keys']['pk'])):
                    $sFichier .= <<<CODE_PHP
                    'pk' => array(

CODE_PHP;
                    foreach($aCols['keys']['pk'] as $pk):
                        $sFichier .= <<<CODE_PHP
                        '{$pk}',

CODE_PHP;
                    endforeach;
                    $eopk = (isset($aCols['keys']['fk'])) ? ',' : null;
                    $sFichier .= <<<CODE_PHP
                    ),

CODE_PHP;
                endif;
                $sFichier .= <<<CODE_PHP
                    'fk' => array(

CODE_PHP;
                $nfk = count($aFks);
                for($ifk = 0; $ifk < $nfk; $ifk++):
                    $eoa = ($ifk < ($nfk -1)) ? ',' : null;
                    $sFichier .= <<<CODE_PHP
                        '{$aFks[$ifk]['column_name']}' => array('{$aFks[$ifk]['referenced_table_name']}' => '{$aFks[$ifk]['referenced_column_name']}'){$eoa}

CODE_PHP;
                endfor;
                $sFichier .= <<<CODE_PHP
                    )

CODE_PHP;
                $sFichier .= <<<CODE_PHP
                )
            ){$eor}

CODE_PHP;
                $r++;
            endforeach;
            $sFichier .= <<<CODE_PHP

CODE_PHP;
            $sFichier .= <<<CODE_PHP
        ),
        'vues' => array(

CODE_PHP;
            $nv = count($conf['vues']);
            $w = 0;
            foreach($conf['vues'] as $table => $aCols):
                $aViewTables = $this->_oSchemaInfos->getViewTables($table);
                $eov = ($w < ($nv-1)) ? ',' : null;
                $sFichier .= <<<CODE_PHP
            '{$table}' => array(
                'fields' => array(

CODE_PHP;
                foreach($aCols['fields'] as $col => $aDetails):
                    $sFichier .= <<<CODE_PHP
                    '{$col}' => array(
                        'type'   => {$aDetails['type']},
                        'length' => {$aDetails['length']},
                        'null'   => {$aDetails['null']},

CODE_PHP;
                    if($aDetails['attr'] == 'null'):
                        $sFichier .= <<<CODE_PHP
                        'attr'   => null

CODE_PHP;
                    else:
                        $sFichier .= <<<CODE_PHP
                        'attr'   => array(

CODE_PHP;
                        foreach($aDetails['attr'] as $d => $v):
                            $s = (
                                $d == 'default' && (
                                $aDetails['type'] == 'TYPE_INTEGER' ||
                                $aDetails['type'] == 'TYPE_MEDIUMINT' ||
                                $aDetails['type'] == 'TYPE_TINYINT' ||
                                $aDetails['type'] == 'TYPE_SMALLINT' ||
                                $aDetails['type'] == 'TYPE_DECIMAL' ||
                                $aDetails['type'] == 'TYPE_FLOAT'
                            ) || $d == 'vals') ? $v : "'". $v ."'";
                            $sFichier .= <<<CODE_PHP
                            '{$d}' => {$s},

CODE_PHP;
                        endforeach;
                        $sFichier .= <<<CODE_PHP
                        )

CODE_PHP;
                    endif;
                    $sFichier .= <<<CODE_PHP
                    ),

CODE_PHP;
                endforeach;
                $sFichier .= <<<CODE_PHP
                ),
                'tables' => array(

CODE_PHP;
                $nvt = count($aViewTables);
                $n = 0;
                foreach($aViewTables as $t):
                    $evt = ($n < $nvt-1) ? ',' : null;
                    $sFichier .= <<<CODE_PHP
                    '{$t}'{$evt}

CODE_PHP;
                    $n++;
                endforeach;
                $sFichier .= <<<CODE_PHP
                )
            ){$eov}

CODE_PHP;
                $w++;
            endforeach;
            $sFichier .= <<<CODE_PHP

CODE_PHP;
            $sFichier .= <<<CODE_PHP
        )
    )

CODE_PHP;
        endforeach;
        /**
         * Fermeture du fichier de configuration
         */
        $sFichier .= <<<CODE_PHP
);
CODE_PHP;
        $retour = false;
        if(false !== ($f = fopen($fichierCible, 'w+')))
        {
            $ecriture = fwrite($f, $sFichier);
            fclose($f);
            $retour = (is_int($ecriture)) ? true : false;
            if(true === $retour)
            {
                $this->_compacterDbConf($fichierCible);
            }
        }
        if(defined('DBCACHE_ACTIF'))
        {
            $oVue->setTmpActivationCache(DBCACHE_ACTIF);
        }
        return $retour;
    }

    /**
     * Lance la procédure.
     * -1- Exécute les requête de récupération des informations;
     * -2- Écrit le fichier de configuration vers $fichierCible;
     *
     */
    private function _getConf()
    {
        $retour = false;
        /**
         * On initialise le tableau de configuration.
         */
        $aConf = array(
            'schema' => array(
                'name'   => $this->_schemacible,
                'SGBD'   => $this->_typeserveur,
                'server' => $this->_hote,
                'port'   => $this->_port,
                'user'   => $this->_schemauser,
                'mdp'    => $this->_schemamdp
            ),
            'tables' => array(),
            'relations' => array(),
            'vues' => array()
        );
        /**
         * On récupère les tables
         */
        $aTables    = $this->_oSchemaInfos->getTables();
        $nt = count($aTables);
        if($nt > 0)
        {
            $aConf = $this->_addInfosSection($aTables, $nt, $aConf, 'tables');
        }
        /**
         * On récupère les tables relationnelles
         */
        $aRelations = $this->_oSchemaInfos->getRelations();
        $nr = count($aRelations);
        if($nr > 0)
        {
            $aConf = $this->_addInfosSection($aRelations, $nr, $aConf, 'relations');
        }
        /**
         * On récupère les vues
         */
        $aVues      = $this->_oSchemaInfos->getVues();
        $nv = count($aVues);
        if($nv > 0)
        {
            $aConf = $this->_addInfosSection($aVues, $nv, $aConf, 'vues');
        }
        return $aConf;
    }

    /**
     * Définition de la chaîne de connexion pour PDO.
     */
    private function _setDns()
    {
        $this->_dns  = $this->_typeserveur;
        $this->_dns .= ':host=' . $this->_hote;
        $this->_dns .= ((!empty($this->_port)) ? (';port=' . $this->_port) : '');
        $this->_dns .= ';dbname=' . $this->_metaschema;
    }

    /**
     * Tentative d'établissement de la connexion
     *
     */
    private function _connect()
    {
        try
        {
            $this->_dbh = new \PDO($this->_dns, $this->_user, $this->_mdp);
        }
        catch (\PDOException $p)
        {
            $this->_aErreurs[] = array(
                'La connexion a échoué',
                'Message : '. $p->getMessage(),
                'Trace : '.$p->getTraceAsString()
            );
        }
        catch (\Exception $e)
        {
            $this->_aErreurs[] = array(
                'La connexion a échoué',
                'Message : '. $e->getMessage(),
                'Trace : '.$e->getTraceAsString()
            );
        }
        $this->_connect();
    }

    private function _addInfosSection($aTables, $nt, $aConf, $section = 'tables')
    {
        for($i = 0; $i < $nt; $i++)
        {
            $table = $aTables[$i]['table_name'];
            if($section != 'vues')
            {
                $aConf[$section][$table] = array(
                    'fields' => array(),
                    'keys' => array()
                );
            }
            else
            {
                $aConf[$section][$table] = array(
                    'fields' => array()
                );
            }
            $aColonnes = $this->_oSchemaInfos->getInfosColonnes($table);
            $aConstraints = $this->_oSchemaInfos->getConstraints();
            $nc = count($aColonnes);
            $aPk = array();
            $aUk = array();
            $aFk = array();
            for($c = 0; $c < $nc; $c++)
            {
                $aConf = $this->_addInfosColonnes($aConf, $table, $section, $aColonnes[$c]);
                if($section != 'vues')
                {
                    if($aColonnes[$c]['column_key'] == 'PRI' || $aColonnes[$c]['column_key'] == 'PRIMARY KEY')
                    {
                        $aPk[] = $aColonnes[$c]['column_name'];
                    }
                    if($aColonnes[$c]['column_key'] == 'UNI' || $aColonnes[$c]['column_key'] == 'UNIQUE')
                    {
                        $aUk[] = $aColonnes[$c]['column_name'];
                    }
                    if(($aColonnes[$c]['column_key'] == 'MUL' || $aColonnes[$c]['column_key'] == 'FOREIGN KEY' ) || $section == 'relations')
                    {
                        foreach($aConstraints as $constraint)
                        {
                            if($table == $constraint['table_name'] && $aColonnes[$c]['column_name'] == $constraint['column_name'] && !empty($constraint['referenced_column_name']))
                            {
                                $aFk[] = array(
                                    $constraint['column_name'] => array(
                                        $constraint['referenced_table_name'] => $constraint['referenced_column_name']
                                    )
                                );

                            }
                        }
                    }
                }
            }
            if(count($aPk) > 0)
            {
                $aConf = $this->_addPrimaryKeys($aConf, $table, $section, $aPk);
            }
            if(count($aUk) > 0)
            {
                $aConf = $this->_addUniqueKeys($aConf, $table, $section, $aUk);
            }
            if(count($aFk) > 0)
            {
                $aConf = $this->_addForeignKeys($aConf, $table, $section, $aFk);
            }
        }
        return $aConf;
    }

    private function _addInfosColonnes($aConf, $table, $section = 'tables', $aInfosColonne)
    {
        $col = $aInfosColonne['column_name'];
        $sType = ($aInfosColonne['data_type'] == 'int')
            ? 'TYPE_INTEGER'
            : 'TYPE_'. strtoupper($aInfosColonne['data_type']);
        if(!array_key_exists($sType, $this->_aTypesDonnees) && $aInfosColonne['data_type'] != 'int')
        {
            $this->_aTypesDonnees[$sType] = $aInfosColonne['data_type'];
        }
        $sPrec = (
            $sType == 'TYPE_INTEGER' ||
            $sType == 'TYPE_TINYINT' ||
            $sType == 'TYPE_SMALLINT' ||
            $sType == 'TYPE_MEDIUMINT' ||
            $sType == 'TYPE_BIGINT'
        )
            ? $aInfosColonne['numeric_precision']
            : (($sType == 'TYPE_VARCHAR' || $sType == 'TYPE_CHAR')
                ? $aInfosColonne['character_maximum_length']
                : (($sType == 'TYPE_DECIMAL' || $sType == 'TYPE_FLOAT')
                    ? $aInfosColonne['numeric_precision'] .".". $aInfosColonne['numeric_scale']
                    : 'null'
        ));
        $sNull = ($aInfosColonne['is_nullable'] == 'YES') ? 'true' : 'false';
        $aAttr = array();
        if(!is_null($aInfosColonne['extra']) && $aInfosColonne['extra'] != 'NULL' && !empty($aInfosColonne['extra']))
        {
            $aAttr['extra'] = $aInfosColonne['extra'];
        }
        if(!is_null($aInfosColonne['column_default']))
        {
            $aAttr['default'] = trim($aInfosColonne['column_default'], "'");
        }
        if($sType == 'TYPE_ENUM')
        {
            $detailsEnum = $aInfosColonne['column_type'];
            $masque_enum = "#^enum\(([^\)]+)\)#i";
            $sVals = preg_replace($masque_enum, "array($1)", $detailsEnum);
            $aAttr['vals'] = $sVals;
        }
        $attr = (count($aAttr) > 0) ? $aAttr : 'null';

        $aConf[$section][$table]['fields'][$col] = array(
            'type'   => $sType,
            'length' => $sPrec,
            'null'   => $sNull,
            'attr'   => $attr
        );

        return $aConf;
    }

    private function _addPrimaryKeys($aConf, $table, $section = 'tables', $aPks)
    {
        $aConf[$section][$table]['keys']['pk'] = $aPks;
        return $aConf;
    }

    private function _addUniqueKeys($aConf, $table, $section = 'tables', $aUks)
    {
        $aConf[$section][$table]['keys']['uk'] = $aUks;
        return $aConf;
    }

    private  function _addForeignKeys($aConf, $table, $section = 'tables', $aFks)
    {
        $aConf[$section][$table]['keys']['fk'] = $aFks;
        return $aConf;
    }

    private function _compacterDbConf($fichier)
    {
        $repertoire = realpath(dirname($fichier)) . DIRECTORY_SEPARATOR;
        $fichier = $repertoire ."dbConf.php";
        $cible   = $repertoire .'dbConf_compact.php';
        $fs = filesize($fichier);
        if(false !== ($f = fopen($fichier, 'r')))
        {
            if(false !== ($string = fread($f, $fs)))
            {
                if(false !== ($c = fopen($cible, "w")))
                {
                    /* 1 - Virer les commentaires */
                    $masqueComments = "#^(?:/|\s)\*.*#im";
                    $s1 = preg_replace($masqueComments, "", $string);
                    fclose($f);
                    /* 2 - Virer les retours de chariot et les espaces surnuméraires */
                    $masqueRetours = "#(?<!<\?php)\s+#im";
                    $s2 = preg_replace($masqueRetours, "", $s1);
                    /* 2 - Virer les virgules surnuméraires */
                    $masqueVirgules = "#,\)#im";
                    $s3 = preg_replace($masqueVirgules, ")", $s2);
                    if(false !== (fwrite($c, $s3)))
                    {
                        fclose($c);
                    }
                }
            }
        }
    }

}
