<?php
/**
 ***********************************************************************************************
 * Functions for update
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * checks if login is required and if so, if it is valid
 */
function checkLogin()
{
    global $gDb, $gL10n, $gProfileFields, $gLoginForUpdate;

    if (isset($gLoginForUpdate) && !$gLoginForUpdate)
    {
        return;
    }

    // get username and password
    $loginName = admFuncVariableIsValid($_POST, 'login_name', 'string', array('requireValue' => true, 'directOutput' => true));
    $password  = admFuncVariableIsValid($_POST, 'password',   'string', array('requireValue' => true, 'directOutput' => true));

    // Search for username
    $sql = 'SELECT usr_id
              FROM '.TBL_USERS.'
             WHERE UPPER(usr_login_name) = UPPER(?)';
    $userStatement = $gDb->queryPrepared($sql, array($loginName));

    if ($userStatement->rowCount() === 0)
    {
        $message = '
            <div class="alert alert-danger alert-small" role="alert">
                <span class="glyphicon glyphicon-exclamation-sign"></span>
                <strong>' . $gL10n->get('SYS_LOGIN_USERNAME_PASSWORD_INCORRECT') . '</strong>
            </div>';

        showNotice($message, 'update.php', $gL10n->get('SYS_BACK'), 'layout/back.png', true);
        // => EXIT
    }
    else
    {
        // create object with current user field structure und user object
        $gCurrentUser = new User($gDb, $gProfileFields, (int) $userStatement->fetchColumn());

        try
        {
            // check login data. If login failed an exception will be thrown.
            // Don't update the current session with user id and don't do a rehash of the password
            // because in former versions the password field was to small for the current hashes
            // and the update of this field will be done after this check.
            $gCurrentUser->checkLogin($password, false, false, false, true);
        }
        catch (AdmException $e)
        {
            $message = '
                <div class="alert alert-danger alert-small" role="alert">
                    <span class="glyphicon glyphicon-exclamation-sign"></span>
                    <strong>' . $e->getText() . '</strong>
                </div>';

            showNotice($message, 'update.php', $gL10n->get('SYS_BACK'), 'layout/back.png', true);
            // => EXIT
        }
    }
}

function updateOrgPreferences()
{
    global $gDb, $gPasswordHashAlgorithm;

    // erst einmal die evtl. neuen Orga-Einstellungen in DB schreiben
    require_once(__DIR__ . '/db_scripts/preferences.php');

    // calculate the best cost value for your server performance
    $benchmarkResults = PasswordHashing::costBenchmark(0.35, 'password', $gPasswordHashAlgorithm);
    $updateOrgPreferences = array('system_hashing_cost' => $benchmarkResults['cost']);

    $sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS;
    $orgaStatement = $gDb->queryPrepared($sql);

    while ($orgId = $orgaStatement->fetchColumn())
    {
        $organization = new Organization($gDb, $orgId);
        $settingsManager =& $organization->getSettingsManager();
        $settingsManager->setMulti($defaultOrgPreferences, false);
        $settingsManager->setMulti($updateOrgPreferences);
    }
}

/**
 * @param bool $enable
 */
function toggleForeignKeyChecks($enable)
{
    global $gDbType, $gDb;

    if ($gDbType === Database::PDO_ENGINE_MYSQL)
    {
        // disable foreign key checks for mysql, so tables can easily deleted
        $sql = 'SET foreign_key_checks = ' . (int) $enable;
        $gDb->queryPrepared($sql);
    }
}

/**
 * @param int $versionMain
 * @param int $versionMinor
 * @param int $versionPatch
 */
function doVersion2Update(&$versionMain, &$versionMinor, &$versionPatch)
{
    global $gLogger, $gDb, $gL10n, $g_tbl_praefix, $gSettingsManager, $gDbType;

    // nun in einer Schleife alle Update-Scripte fuer Version 2 einspielen
    while ($versionMain === 2)
    {
        // version 2 Admidio had sql and php files where the update statements where stored
        // these files must be executed

        // in der Schleife wird geschaut ob es Scripte fuer eine patch-version (3. Versionsstelle) gibt
        // patch-version 0 sollte immer vorhanden sein, die anderen in den meisten Faellen nicht
        for ($tmpPatchVersion = $versionPatch; $tmpPatchVersion <= 14; ++$tmpPatchVersion)
        {
            // output of the version number for better debugging
            $gLogger->notice('UPDATE: Start executing update steps to version '.$versionMain.'.'.$versionMinor.'.'.$tmpPatchVersion);

            $dbScriptsPath = ADMIDIO_PATH . '/adm_program/installation/db_scripts/';
            $version = $versionMain . '_' . $versionMinor . '_' . $tmpPatchVersion;
            $sqlFileName = 'upd_' . $version . '_db.sql';
            $phpFileName = 'upd_' . $version . '_conv.php';

            if (is_file($dbScriptsPath . $sqlFileName))
            {
                $sqlQueryResult = querySqlFile($gDb, $sqlFileName);

                if ($sqlQueryResult !== true)
                {
                    showNotice($sqlQueryResult, 'update.php', $gL10n->get('SYS_BACK'), 'layout/back.png', true);
                    // => EXIT
                }
            }

            $phpUpdateFile = $dbScriptsPath . $phpFileName;
            // check if an php update file exists and then execute the script
            if (is_file($phpUpdateFile))
            {
                require($phpUpdateFile);
            }

            $gLogger->notice('UPDATE: Finish executing update steps to version '.$versionMain.'.'.$versionMinor.'.'.$tmpPatchVersion);
        }

        // update minor verion number
        if ($versionMinor === 4) // we do not have more then 4 minor-versions with old updater
        {
            $versionMain = 3;
            $versionMinor = 0;
        }
        else
        {
            ++$versionMinor;
        }

        $versionPatch = 0;
    }
}

function doVersion3Update()
{
    global $gDb, $gL10n, $gCurrentOrganization, $gCurrentUser;

    $gProfileFields = new ProfileFields($gDb, (int) $gCurrentOrganization->getValue('org_id'));

    // set system user as current user, but this user only exists since version 3
    $sql = 'SELECT usr_id
              FROM ' . TBL_USERS . '
             WHERE usr_login_name = ?';
    $systemUserStatement = $gDb->queryPrepared($sql, array($gL10n->get('SYS_SYSTEM')));

    $gCurrentUser = new User($gDb, $gProfileFields, (int) $systemUserStatement->fetchColumn());

    // reread component because in version 3.0 the component will be created within the update
    $componentUpdateHandle = new ComponentUpdate($gDb);
    $componentUpdateHandle->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));
    $componentUpdateHandle->update(ADMIDIO_VERSION);
}

function createHtaccessIfNotExist()
{
    global $gLogger;

    if (is_file(ADMIDIO_PATH . FOLDER_DATA.'/.htaccess'))
    {
        return;
    }

    // create ".htaccess" file for folder "adm_my_files"
    $protection = new Htaccess(ADMIDIO_PATH . FOLDER_DATA);
    if (!$protection->protectFolder())
    {
        $gLogger->warning('htaccess file could not be created!');
    }
}

/**
 * @param string $installedDbVersion
 */
function doAdmidioUpdate($installedDbVersion)
{
    global $gLogger, $gDb;

    $gLogger->info('UPDATE: Execute update');

    checkLogin();

    // setzt die Ausfuehrungszeit des Scripts auf 5 Min., da hier teilweise sehr viel gemacht wird
    // allerdings darf hier keine Fehlermeldung wg. dem safe_mode kommen
    @set_time_limit(300);

    updateOrgPreferences();

    preg_match('/^(\d+)\.(\d+)\.(\d+)/', $installedDbVersion, $versionArray);
    $versionArray = array_map('intval', $versionArray);
    list(, $versionMain, $versionMinor, $versionPatch) = $versionArray;

    ++$versionPatch;

    // disable foreign key checks for mysql, so tables can easily deleted
    toggleForeignKeyChecks(false);

    // in version 2 we had an other update mechanism which will be handled here
    if ($versionMain === 2)
    {
        doVersion2Update($versionMain, $versionMinor, $versionPatch);
    }

    disableSoundexSearchIfPgSql($gDb);

    // since version 3 we do the update with xml files and a new class model
    if ($versionMain >= 3)
    {
        doVersion3Update();
    }

    // activate foreign key checks, so database is consistent
    toggleForeignKeyChecks(true);

    createHtaccessIfNotExist();

    // nach dem Update erst einmal bei Sessions das neue Einlesen des Organisations- und Userobjekts erzwingen
    $sql = 'UPDATE ' . TBL_SESSIONS . ' SET ses_renew = 1';
    $gDb->queryPrepared($sql);
}
