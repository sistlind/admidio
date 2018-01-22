<?php
/**
 ***********************************************************************************************
 * Handle update of Admidio database to a new version
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 *
 * Parameters:
 *
 * mode = 1 : (Default) Check update status and show dialog with status
 *        2 : Perform update
 *        3 : Show result of update
 ***********************************************************************************************
 */

// embed config and constants file
$configPath    = __DIR__ . '/../../adm_my_files/config.php';
$configPathOld = __DIR__ . '/../../config.php';
if (is_file($configPath))
{
    require_once($configPath);
}
elseif (is_file($configPathOld))
{
    // config file at destination of version 2.0 exists -> copy config file to new destination
    if (!@copy($configPathOld, $configPath))
    {
        exit('<div style="color: #cc0000;">Error: The file <strong>config.php</strong> could not be copied to the folder <strong>adm_my_files</strong>.
            Please check if this folder has the necessary write rights. If it\'s not possible to set this right then copy the
            config.php from the Admidio main folder to adm_my_files with your FTP program.</div>');
    }
    require_once($configPath);
}
else
{
    // no config file exists -> go to installation
    header('Location: installation.php');
    exit();
}

$rootPath = substr(__FILE__, 0, strpos(__FILE__, DIRECTORY_SEPARATOR . 'adm_program'));
require_once($rootPath . '/adm_program/system/bootstrap.php');
require_once(ADMIDIO_PATH . '/adm_program/installation/install_functions.php');
require_once(ADMIDIO_PATH . '/adm_program/installation/update_functions.php');

// Initialize and check the parameters

define('THEME_URL', 'layout');
$getMode = admFuncVariableIsValid($_GET, 'mode', 'int', array('defaultValue' => 1));
$message = '';

// connect to database
try
{
    $gDb = new Database($gDbType, $g_adm_srv, $g_adm_port, $g_adm_db, $g_adm_usr, $g_adm_pw);
}
catch (AdmException $e)
{
    showNotice(
        $gL10n->get('SYS_DATABASE_NO_LOGIN', array($e->getText())),
        safeUrl(ADMIDIO_URL . '/adm_program/installation/installation.php', array('step' => 'connect_database')),
        $gL10n->get('SYS_BACK'),
        'layout/back.png'
    );
    // => EXIT
}

// now check if a valid installation exists.
$sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS;
$pdoStatement = $gDb->queryPrepared($sql, array(), false);

if (!$pdoStatement || $pdoStatement->rowCount() === 0)
{
    // no valid installation exists -> show installation wizard
    admRedirect(ADMIDIO_URL . '/adm_program/installation/installation.php');
    // => EXIT
}

// create an organization object of the current organization
$gCurrentOrganization = new Organization($gDb, $g_organization);

if ((int) $gCurrentOrganization->getValue('org_id') === 0)
{
    // Organization was not found
    exit('<div style="color: #cc0000;">Error: The organization of the config.php could not be found in the database!</div>');
}

// read organization specific parameters from adm_preferences
$gSettingsManager =& $gCurrentOrganization->getSettingsManager();

// create language and language data object to handle translations
if (!$gSettingsManager->has('system_language'))
{
    $gSettingsManager->set('system_language', 'de');
}
$gLanguageData = new LanguageData($gSettingsManager->getString('system_language'));
$gL10n = new Language($gLanguageData);

// config.php exists at wrong place
if (is_file(ADMIDIO_PATH . '/config.php') && is_file(ADMIDIO_PATH . FOLDER_DATA . '/config.php'))
{
    // try to delete the config file at the old place otherwise show notice to user
    if (!@unlink(ADMIDIO_PATH . '/config.php'))
    {
        showNotice(
            $gL10n->get('INS_DELETE_CONFIG_FILE', array(ADMIDIO_URL)),
            ADMIDIO_URL . '/adm_program/installation/index.php',
            $gL10n->get('SYS_OVERVIEW'),
            'layout/application_view_list.png'
        );
        // => EXIT
    }
}

// check database version
$message = checkDatabaseVersion($gDb);

if ($message !== '')
{
    showNotice(
        $message,
        ADMIDIO_URL . '/adm_program/index.php',
        $gL10n->get('SYS_OVERVIEW'),
        'layout/application_view_list.png'
    );
    // => EXIT
}

// read current version of Admidio database
$installedDbVersion     = '';
$installedDbBetaVersion = '';
$maxUpdateStep          = 0;
$currentUpdateStep      = 0;

$sql = 'SELECT 1 FROM ' . TBL_COMPONENTS;
if (!$gDb->queryPrepared($sql, array(), false))
{
    // in Admidio version 2 the database version was stored in preferences table
    if ($gSettingsManager->has('db_version'))
    {
        $installedDbVersion     = $gSettingsManager->getString('db_version');
        $installedDbBetaVersion = $gSettingsManager->getInt('db_version_beta');
    }
}
else
{
    // read system component
    $componentUpdateHandle = new ComponentUpdate($gDb);
    $componentUpdateHandle->readDataByColumns(array('com_type' => 'SYSTEM', 'com_name_intern' => 'CORE'));

    if ($componentUpdateHandle->getValue('com_id') > 0)
    {
        $installedDbVersion     = $componentUpdateHandle->getValue('com_version');
        $installedDbBetaVersion = (int) $componentUpdateHandle->getValue('com_beta');
        $currentUpdateStep      = (int) $componentUpdateHandle->getValue('com_update_step');
        $maxUpdateStep          = $componentUpdateHandle->getMaxUpdateStep();
    }
}

// if a beta was installed then create the version string with Beta version
if ($installedDbBetaVersion > 0)
{
    $installedDbVersion = $installedDbVersion . ' Beta ' . $installedDbBetaVersion;
}

// if database version is not set then show notice
if ($installedDbVersion === '')
{
    $message = '
        <div class="alert alert-danger alert-small" role="alert">
            <span class="glyphicon glyphicon-exclamation-sign"></span>
            <strong>' . $gL10n->get('INS_UPDATE_NOT_POSSIBLE') . '</strong>
        </div>
        <p>' . $gL10n->get('INS_NO_INSTALLED_VERSION_FOUND', array(ADMIDIO_VERSION_TEXT)) . '</p>';

    showNotice(
        $message,
        ADMIDIO_URL . '/adm_program/index.php',
        $gL10n->get('SYS_OVERVIEW'),
        'layout/application_view_list.png',
        true
    );
    // => EXIT
}

if ($getMode === 1)
{
    $gLogger->info('UPDATE: Show update start-view');

    // if database version is smaller then source version -> update
    // if database version is equal to source but beta has a difference -> update
    if (version_compare($installedDbVersion, ADMIDIO_VERSION_TEXT, '<')
    || (version_compare($installedDbVersion, ADMIDIO_VERSION_TEXT, '==') && $maxUpdateStep > $currentUpdateStep))
    {
        // create a page with the notice that the installation must be configured on the next pages
        $form = new HtmlFormInstallation('update_login_form', safeUrl(ADMIDIO_URL . '/adm_program/installation/update.php', array('mode' => 2)));
        $form->setUpdateModus();
        $form->setFormDescription('<h3>' . $gL10n->get('INS_DATABASE_NEEDS_UPDATED_VERSION', array($installedDbVersion, ADMIDIO_VERSION_TEXT)) . '</h3>');

        if (!isset($gLoginForUpdate) || $gLoginForUpdate == 1)
        {
            $form->addDescription($gL10n->get('INS_ADMINISTRATOR_LOGIN_DESC'));
            $form->addInput(
                'login_name', $gL10n->get('SYS_USERNAME'), '',
                array('maxLength' => 35, 'property' => HtmlForm::FIELD_REQUIRED, 'class' => 'form-control-small')
            );
            // TODO Future: 'minLength' => PASSWORD_MIN_LENGTH
            $form->addInput(
                'password', $gL10n->get('SYS_PASSWORD'), '',
                array('type' => 'password', 'property' => HtmlForm::FIELD_REQUIRED, 'class' => 'form-control-small')
            );
        }

        // if this is a beta version then show a warning message
        if (ADMIDIO_VERSION_BETA > 0)
        {
            $gLogger->notice('UPDATE: This is a BETA release!');

            $form->addHtml('
                <div class="alert alert-warning alert-small" role="alert">
                    <span class="glyphicon glyphicon-warning-sign"></span>
                    ' . $gL10n->get('INS_WARNING_BETA_VERSION') . '
                </div>');
        }
        $form->addSubmitButton(
            'next_page', $gL10n->get('INS_UPDATE_DATABASE'),
            array('icon' => 'layout/database_in.png', 'onClickText' => $gL10n->get('INS_DATABASE_IS_UPDATED'))
        );
        echo $form->show();
    }
    // if versions are equal > no update
    elseif (version_compare($installedDbVersion, ADMIDIO_VERSION_TEXT, '==') && $maxUpdateStep === $currentUpdateStep)
    {
        $message = '
            <div class="alert alert-success form-alert">
                <span class="glyphicon glyphicon-ok"></span>
                <strong>' . $gL10n->get('INS_DATABASE_IS_UP_TO_DATE') . '</strong>
            </div>
            <p>' . $gL10n->get('INS_DATABASE_DOESNOT_NEED_UPDATED') . '</p>';

        showNotice(
            $message,
            ADMIDIO_URL . '/adm_program/index.php',
            $gL10n->get('SYS_OVERVIEW'),
            'layout/application_view_list.png',
            true
        );
        // => EXIT
    }
    // if source version smaller then database -> show error
    else
    {
        $message = '
            <div class="alert alert-danger form-alert">
                <span class="glyphicon glyphicon-exclamation-sign"></span>
                <strong>' . $gL10n->get('SYS_ERROR') . '</strong>
                <p>' .
                    $gL10n->get(
                        'SYS_FILESYSTEM_VERSION_INVALID', array($installedDbVersion,
                        ADMIDIO_VERSION_TEXT, '<a href="' . ADMIDIO_HOMEPAGE . 'download.php">', '</a>')
                    ) . '
                </p>
            </div>';

        showNotice(
            $message,
            ADMIDIO_URL . '/adm_program/index.php',
            $gL10n->get('SYS_OVERVIEW'),
            'layout/application_view_list.png',
            true
        );
        // => EXIT
    }
}
elseif ($getMode === 2)
{
    doAdmidioUpdate($installedDbVersion);

    // start php session and remove session object with all data, so that
    // all data will be read after the update
    try
    {
        Session::start(COOKIE_PREFIX);
    }
    catch (\RuntimeException $exception)
    {
        // TODO
    }
    unset($_SESSION['gCurrentSession']);

    // show notice that update was successful
    $form = new HtmlFormInstallation('installation-form', ADMIDIO_HOMEPAGE . 'donate.php');
    $form->setUpdateModus();
    $form->setFormDescription(
        $gL10n->get('INS_UPDATE_TO_VERSION_SUCCESSFUL', array(ADMIDIO_VERSION_TEXT)) . '<br /><br />' . $gL10n->get('INS_SUPPORT_FURTHER_DEVELOPMENT'),
        '<div class="alert alert-success form-alert">
            <span class="glyphicon glyphicon-ok"></span>
            <strong>'.$gL10n->get('INS_UPDATING_WAS_SUCCESSFUL').'</strong>
        </div>'
    );
    $form->openButtonGroup();
    $form->addSubmitButton('next_page', $gL10n->get('SYS_DONATE'), array('icon' => 'layout/money.png'));
    $form->addButton(
        'main_page', $gL10n->get('SYS_LATER'),
        array('icon' => 'layout/application_view_list.png', 'link' => ADMIDIO_URL . '/adm_program/index.php')
    );
    $form->closeButtonGroup();
    echo $form->show();
}
