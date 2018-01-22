<?php
/**
 ***********************************************************************************************
 * List of all modules and administration pages of Admidio
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

// if config file doesn't exists, than show installation dialog
if (!is_file(__DIR__ . '/../adm_my_files/config.php'))
{
    header('Location: installation/index.php');
    exit();
}

require_once(__DIR__ . '/system/common.php');

$headline = 'Admidio '.$gL10n->get('SYS_OVERVIEW');

// Navigation of the module starts here
$gNavigation->addStartUrl(CURRENT_URL, $headline);

// create html page object
$page = new HtmlPage($headline);

// main menu of the page
$mainMenu = $page->getMenu();

if($gValidLogin)
{
    // show link to own profile
    $mainMenu->addItem(
        'adm_menu_item_my_profile', ADMIDIO_URL . FOLDER_MODULES . '/profile/profile.php',
        $gL10n->get('PRO_MY_PROFILE'), 'profile.png'
    );
    // show logout link
    $mainMenu->addItem(
        'adm_menu_item_logout', ADMIDIO_URL . '/adm_program/system/logout.php',
        $gL10n->get('SYS_LOGOUT'), 'door_in.png'
    );
}
else
{
    // show login link
    $mainMenu->addItem(
        'adm_menu_item_login', ADMIDIO_URL . '/adm_program/system/login.php',
        $gL10n->get('SYS_LOGIN'), 'key.png'
    );

    if($gSettingsManager->getBool('registration_enable_module'))
    {
        // show registration link
        $mainMenu->addItem(
            'adm_menu_item_registration', ADMIDIO_URL . FOLDER_MODULES . '/registration/registration.php',
            $gL10n->get('SYS_REGISTRATION'), 'new_registrations.png'
        );
    }
}

// menu with links to all modules of Admidio
$moduleMenu = new Menu('index_modules', $gL10n->get('SYS_MODULES'));

if((int) $gSettingsManager->get('enable_announcements_module') === 1
|| ((int) $gSettingsManager->get('enable_announcements_module') === 2 && $gValidLogin))
{
    $moduleMenu->addItem(
        'announcements', ADMIDIO_URL . FOLDER_MODULES . '/announcements/announcements.php',
        $gL10n->get('ANN_ANNOUNCEMENTS'), '/icons/announcements_big.png', $gL10n->get('ANN_ANNOUNCEMENTS_DESC')
    );
}
if($gSettingsManager->getBool('enable_download_module'))
{
    $moduleMenu->addItem(
        'download', ADMIDIO_URL . FOLDER_MODULES . '/downloads/downloads.php',
        $gL10n->get('DOW_DOWNLOADS'), '/icons/download_big.png', $gL10n->get('DOW_DOWNLOADS_DESC')
    );
}
if($gSettingsManager->getBool('enable_mail_module') && !$gValidLogin)
{
    $moduleMenu->addItem(
        'email', ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php',
        $gL10n->get('SYS_EMAIL'), '/icons/email_big.png', $gL10n->get('MAI_EMAIL_DESC')
    );
}
if(($gSettingsManager->getBool('enable_pm_module') || $gSettingsManager->getBool('enable_mail_module')) && $gValidLogin)
{
    $unreadBadge = '';

    // get number of unread messages for user
    $message = new TableMessage($gDb);
    $unread = $message->countUnreadMessageRecords((int) $gCurrentUser->getValue('usr_id'));

    if($unread > 0)
    {
        $unreadBadge = '<span class="badge">' . $unread . '</span>';
    }

    $moduleMenu->addItem(
        'private message', ADMIDIO_URL . FOLDER_MODULES . '/messages/messages.php',
        $gL10n->get('SYS_MESSAGES') . $unreadBadge, '/icons/messages_big.png', $gL10n->get('MAI_EMAIL_DESC')
    );
}
if((int) $gSettingsManager->get('enable_photo_module') === 1
|| ((int) $gSettingsManager->get('enable_photo_module') === 2 && $gValidLogin))
{
    $moduleMenu->addItem(
        'photo', ADMIDIO_URL . FOLDER_MODULES . '/photos/photos.php',
        $gL10n->get('PHO_PHOTOS'), '/icons/photo_big.png', $gL10n->get('PHO_PHOTOS_DESC')
    );
}
if((int) $gSettingsManager->get('enable_guestbook_module') === 1
|| ((int) $gSettingsManager->get('enable_guestbook_module') === 2 && $gValidLogin))
{
    $moduleMenu->addItem(
        'guestbk', ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook.php',
        $gL10n->get('GBO_GUESTBOOK'), '/icons/guestbook_big.png', $gL10n->get('GBO_GUESTBOOK_DESC')
    );
}
if($gSettingsManager->getBool('lists_enable_module') && $gValidLogin)
{
    $moduleMenu->addItem(
        'lists', ADMIDIO_URL . FOLDER_MODULES . '/lists/lists.php',
        $gL10n->get('LST_LISTS'), '/icons/lists_big.png', $gL10n->get('LST_LISTS_DESC')
    );
    $moduleMenu->addSubItem(
        'lists', 'mylist', ADMIDIO_URL . FOLDER_MODULES . '/lists/mylist.php',
        $gL10n->get('LST_MY_LIST')
    );
    $moduleMenu->addSubItem(
        'lists', 'rolinac', safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/lists/lists.php', array('active_role' => 0)),
        $gL10n->get('ROL_INACTIV_ROLE')
    );
}
if((int) $gSettingsManager->get('enable_dates_module') === 1
|| ((int) $gSettingsManager->get('enable_dates_module') === 2 && $gValidLogin))
{
    $moduleMenu->addItem(
        'dates', ADMIDIO_URL . FOLDER_MODULES . '/dates/dates.php',
        $gL10n->get('DAT_DATES'), '/icons/dates_big.png', $gL10n->get('DAT_DATES_DESC')
    );
    $moduleMenu->addSubItem(
        'dates', 'olddates', safeUrl(ADMIDIO_URL . FOLDER_MODULES . '/dates/dates.php', array('mode' => 'old')),
        $gL10n->get('DAT_PREVIOUS_DATES', array($gL10n->get('DAT_DATES')))
    );
}
if((int) $gSettingsManager->get('enable_weblinks_module') === 1
|| ((int) $gSettingsManager->get('enable_weblinks_module') === 2 && $gValidLogin))
{
    $moduleMenu->addItem(
        'links', ADMIDIO_URL . FOLDER_MODULES . '/links/links.php',
        $gL10n->get('LNK_WEBLINKS'), '/icons/weblinks_big.png', $gL10n->get('LNK_WEBLINKS_DESC')
    );
}

$page->addHtml($moduleMenu->show(true));

// menu with links to all administration pages of Admidio if the user has the right to administrate
if($gCurrentUser->isAdministrator() || $gCurrentUser->manageRoles()
|| $gCurrentUser->approveUsers() || $gCurrentUser->editUsers())
{
    $adminMenu = new Menu('index_administration', $gL10n->get('SYS_ADMINISTRATION'));

    if($gCurrentUser->approveUsers() && $gSettingsManager->getBool('registration_enable_module'))
    {
        $adminMenu->addItem(
            'newreg', ADMIDIO_URL . FOLDER_MODULES . '/registration/registration.php',
            $gL10n->get('NWU_NEW_REGISTRATIONS'), '/icons/new_registrations_big.png', $gL10n->get('NWU_MANAGE_NEW_REGISTRATIONS_DESC')
        );
    }

    if($gCurrentUser->editUsers())
    {
        $adminMenu->addItem(
            'usrmgt', ADMIDIO_URL . FOLDER_MODULES . '/members/members.php',
            $gL10n->get('MEM_USER_MANAGEMENT'), '/icons/user_administration_big.png',$gL10n->get('MEM_USER_MANAGEMENT_DESC')
        );
    }

    if($gCurrentUser->manageRoles())
    {
        $adminMenu->addItem(
            'roladm', ADMIDIO_URL . FOLDER_MODULES . '/roles/roles.php',
            $gL10n->get('ROL_ROLE_ADMINISTRATION'), '/icons/roles_big.png', $gL10n->get('ROL_ROLE_ADMINISTRATION_DESC')
        );
    }

    if($gCurrentUser->isAdministrator())
    {
        $adminMenu->addItem(
            'dbback', ADMIDIO_URL . FOLDER_MODULES . '/backup/backup.php',
            $gL10n->get('BAC_DATABASE_BACKUP'), '/icons/backup_big.png', $gL10n->get('BAC_DATABASE_BACKUP_DESC')
        );
        $adminMenu->addItem(
            'orgprop', ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences.php',
            $gL10n->get('SYS_SETTINGS'), '/icons/options_big.png', $gL10n->get('ORG_ORGANIZATION_PROPERTIES_DESC')
        );
    }

    $page->addHtml($adminMenu->show(true));
}

$page->show();
