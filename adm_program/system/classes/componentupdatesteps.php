<?php
/**
 ***********************************************************************************************
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class ComponentUpdateSteps
 */
final class ComponentUpdateSteps
{
    /**
     * @var Database
     */
    private static $db;

    /**
     * Set the database
     * @param Database $database The database instance
     */
    public static function setDatabase(Database $database)
    {
        self::$db = $database;
    }

    /**
     * This method add new categories for announcements to the database.
     */
    public static function updateStepAddAnnouncementsCategories()
    {
        global $gL10n;

        // read id of system user from database
        $sql = 'SELECT usr_id
                  FROM '.TBL_USERS.'
                 WHERE usr_login_name = ? -- $gL10n->get(\'SYS_SYSTEM\')';
        $systemUserStatement = self::$db->queryPrepared($sql, array($gL10n->get('SYS_SYSTEM')));
        $systemUserId = (int) $systemUserStatement->fetchColumn();

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while($row = $organizationStatement->fetch())
        {
            $rowId = (int) $row['org_id'];

            $sql = 'INSERT INTO '.TBL_CATEGORIES.'
                           (cat_org_id, cat_type, cat_name_intern, cat_name, cat_hidden, cat_default, cat_system, cat_sequence, cat_usr_id_create, cat_timestamp_create)
                    VALUES (?, \'ANN\', \'COMMON\',    \'SYS_COMMON\',    0, 1, 0, 1, ?, ?) -- $rowId, $systemUserId, DATETIME_NOW
                         , (?, \'ANN\', \'IMPORTANT\', \'SYS_IMPORTANT\', 0, 0, 0, 2, ?, ?) -- $rowId, $systemUserId, DATETIME_NOW';
            $params = array(
                $rowId, $systemUserId, DATETIME_NOW,
                $rowId, $systemUserId, DATETIME_NOW
            );
            self::$db->queryPrepared($sql, $params);

            $sql = 'UPDATE '.TBL_ANNOUNCEMENTS.'
                       SET ann_cat_id = (SELECT cat_id
                                           FROM '.TBL_CATEGORIES.'
                                          WHERE cat_type = \'ANN\'
                                            AND cat_name_intern = \'COMMON\'
                                            AND cat_org_id = ? ) -- $rowId
                     WHERE ann_org_id = ? -- $rowId';
            self::$db->queryPrepared($sql, array($rowId, $rowId));
        }
    }

    /**
     * This method adds a new global list configuration for Participients of Events.
     */
    public static function updateStepAddDefaultParticipantList()
    {
        global $gL10n;

        // read id of system user from database
        $sql = 'SELECT usr_id
                  FROM '.TBL_USERS.'
                 WHERE usr_login_name = ? -- $gL10n->get(\'SYS_SYSTEM\')';
        $systemUserStatement = self::$db->queryPrepared($sql, array($gL10n->get('SYS_SYSTEM')));
        $systemUserId = (int) $systemUserStatement->fetchColumn();

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while($row = $organizationStatement->fetch())
        {
            $rowId = (int) $row['org_id'];

            // Add new list configuration
            $sql = 'INSERT INTO '.TBL_LISTS.'
                           (lst_org_id, lst_usr_id, lst_name, lst_timestamp, lst_global)
                    VALUES (?, ?, ?, ?, 1) -- $rowId, $systemUserId, $gL10n->get(\'SYS_PARTICIPANTS\'), DATETIME_NOW';
            $params = array(
                $rowId,
                $systemUserId,
                $gL10n->get('SYS_PARTICIPANTS'),
                DATETIME_NOW
            );
            self::$db->queryPrepared($sql, $params);

            // Add list columns
            $sql = 'SELECT lst_id
                      FROM '.TBL_LISTS.'
                     WHERE lst_name = ? -- $gL10n->get(\'SYS_PARTICIPANTS\')
                       AND lst_org_id = ? -- $rowId';
            $listStatement = self::$db->queryPrepared($sql, array($gL10n->get('SYS_PARTICIPANTS'), $rowId));
            $listId = (int) $listStatement->fetchColumn();

            $sql = 'INSERT INTO '.TBL_LIST_COLUMNS.'
                           (lsc_lst_id, lsc_number, lsc_usf_id, lsc_special_field, lsc_sort, lsc_filter)
                    VALUES (?, 1, 1,    NULL,                 \'ASC\', NULL) -- $listId
                         , (?, 2, 2,    NULL,                 NULL,    NULL) -- $listId
                         , (?, 3, NULL, \'mem_approved\',     NULL,    NULL) -- $listId
                         , (?, 4, NULL, \'mem_comment\',      NULL,    NULL) -- $listId
                         , (?, 5, NULL, \'mem_count_guests\', NULL,    NULL) -- $listId';
            self::$db->queryPrepared($sql, array($listId, $listId, $listId, $listId, $listId));

            // Set as default configuration list
            $sql = 'UPDATE ' . TBL_PREFERENCES . '
                       SET prf_value = ? -- $listId
                     WHERE prf_name = \'dates_default_list_configuration\'
                       AND prf_org_id = ? -- $rowId';
            self::$db->queryPrepared($sql, array($listId, $rowId));
        }
    }

    /**
     * This method adds new categories for all organizations.
     */
    public static function updateStepAddGlobalCategories()
    {
        global $gCurrentOrganization;

        if($gCurrentOrganization->countAllRecords() > 1)
        {
            $categoryAnnouncement = new TableCategory(self::$db);
            $categoryAnnouncement->setValue('cat_type', 'ANN');
            $categoryAnnouncement->setValue('cat_name_intern', 'ANN_ALL_ORGANIZATIONS');
            $categoryAnnouncement->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryAnnouncement->save();

            $categoryEvents = new TableCategory(self::$db);
            $categoryEvents->setValue('cat_type', 'DAT');
            $categoryEvents->setValue('cat_name_intern', 'DAT_ALL_ORGANIZATIONS');
            $categoryEvents->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryEvents->save();

            $categoryWeblinks = new TableCategory(self::$db);
            $categoryWeblinks->setValue('cat_type', 'LNK');
            $categoryWeblinks->setValue('cat_name_intern', 'LNK_ALL_ORGANIZATIONS');
            $categoryWeblinks->setValue('cat_name', 'SYS_ALL_ORGANIZATIONS');
            $categoryWeblinks->save();
        }
    }

    /**
     * This method deletes all roles that belongs to still deleted dates.
     */
    public static function updateStepDeleteDateRoles()
    {
        $sql = 'SELECT rol_id
                  FROM '.TBL_ROLES.'
            INNER JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
                 WHERE cat_name_intern = \'CONFIRMATION_OF_PARTICIPATION\'
                   AND NOT exists (SELECT 1
                                     FROM '.TBL_DATES.'
                                    WHERE dat_rol_id = rol_id)';
        $rolesStatement = self::$db->queryPrepared($sql);

        while($roleId = $rolesStatement->fetchColumn())
        {
            $role = new TableRoles(self::$db, (int) $roleId);
            $role->delete(); // TODO Exception handling
        }
    }

    /**
     * Update the existing category confirmation of participation and make it
     * organization depending.
     */
    public static function updateStepEventCategory()
    {
        global $g_organization, $gL10n;

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while($row = $organizationStatement->fetch())
        {
            $rowId = (int) $row['org_id'];

            if($g_organization === $row['org_shortname'])
            {
                $sql = 'UPDATE '.TBL_CATEGORIES.'
                           SET cat_name_intern = \'EVENTS\'
                             , cat_name   = ? -- $gL10n->get(\'SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION\')
                             , cat_org_id = ? -- $rowId
                         WHERE cat_org_id IS NULL
                           AND cat_type        = \'ROL\'
                           AND cat_name_intern = \'CONFIRMATION_OF_PARTICIPATION\' ';
                self::$db->queryPrepared($sql, array($gL10n->get('SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION'), $rowId));
            }
            else
            {
                // create organization depending category for events
                $category = new TableCategory(self::$db);
                $category->setValue('cat_org_id', $rowId);
                $category->setValue('cat_type', 'ROL');
                $category->setValue('cat_name', $gL10n->get('SYS_EVENTS_CONFIRMATION_OF_PARTICIPATION'));
                $category->setValue('cat_hidden', '1');
                $category->setValue('cat_system', '1');
                $category->save();

                // now set name intern explicit to EVENTS
                $category->setValue('cat_name_intern', 'EVENTS');
                $category->save();

                // all existing events of this organization must get the new category
                $sql = 'UPDATE '.TBL_ROLES.'
                           SET rol_cat_id = ? -- $category->getValue(\'cat_id\')
                         WHERE rol_id IN (SELECT dat_rol_id
                                            FROM '.TBL_DATES.'
                                      INNER JOIN '.TBL_CATEGORIES.'
                                              ON cat_id = dat_cat_id
                                           WHERE dat_rol_id IS NOT NULL
                                             AND cat_org_id = ?) -- $rowId';
                self::$db->queryPrepared($sql, array((int) $category->getValue('cat_id'), $rowId));
            }
        }
    }

    /**
     * This method migrate the data of the table adm_date_role to the table adm_roles_rights_data.
     */
    public static function updateStepMigrateDatesRightsToFolderRights()
    {
        global $g_tbl_praefix, $gCurrentUser;

        // migrate adm_folder_roles to adm_roles_rights
        $sql = 'SELECT ror_id
                  FROM '.TBL_ROLES_RIGHTS.'
                 WHERE ror_name_intern = \'event_participation\'';
        $rolesRightsStatement = self::$db->queryPrepared($sql);
        $rolesRightId = (int) $rolesRightsStatement->fetchColumn();

        $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS_DATA.'
                       (rrd_ror_id, rrd_rol_id, rrd_object_id, rrd_usr_id_create, rrd_timestamp_create)
                SELECT '.$rolesRightId.', dtr_rol_id, dtr_dat_id, ?, ? -- $gCurrentUser->getValue(\'usr_id\'), DATETIME_NOW
                  FROM '.$g_tbl_praefix.'_date_role
                 WHERE dtr_rol_id IS NOT NULL';
        self::$db->queryPrepared($sql, array((int) $gCurrentUser->getValue('usr_id'), DATETIME_NOW));
    }

    /**
     * This method migrate the data of the table adm_folder_roles to the
     * new table adm_roles_rights_data.
     */
    public static function updateStepMigrateToFolderRights()
    {
        global $g_tbl_praefix, $g_organization, $gCurrentUser;

        // migrate adm_folder_roles to adm_roles_rights
        $sql = 'SELECT ror_id
                  FROM '.TBL_ROLES_RIGHTS.'
                 WHERE ror_name_intern = \'folder_view\'';
        $rolesRightsStatement = self::$db->queryPrepared($sql);
        $rolesRightId = (int) $rolesRightsStatement->fetchColumn();

        $sql = 'INSERT INTO '.TBL_ROLES_RIGHTS_DATA.'
                       (rrd_ror_id, rrd_rol_id, rrd_object_id, rrd_usr_id_create, rrd_timestamp_create)
                SELECT '.$rolesRightId.', flr_rol_id, flr_fol_id, ?, ? -- $gCurrentUser->getValue(\'usr_id\'), DATETIME_NOW
                  FROM '.$g_tbl_praefix.'_folder_roles ';
        self::$db->queryPrepared($sql, array((int) $gCurrentUser->getValue('usr_id'), DATETIME_NOW));

        // add new right folder_update to adm_roles_rights
        $sql = 'SELECT fol_id
                  FROM '.TBL_FOLDERS.'
                 WHERE fol_type = \'DOWNLOAD\'
                   AND fol_name = \'download\' ';
        $rolesRightsStatement = self::$db->queryPrepared($sql);
        $folderId = (int) $rolesRightsStatement->fetchColumn();

        $sql = 'SELECT rol_id
                  FROM '.TBL_ROLES.'
             LEFT JOIN '.TBL_CATEGORIES.'
                    ON cat_id = rol_cat_id
             LEFT JOIN '.TBL_ORGANIZATIONS.'
                    ON org_id = cat_org_id
                 WHERE rol_download  = 1
                   AND org_shortname = ? -- $g_organization';
        $rolesDownloadStatement = self::$db->queryPrepared($sql, array($g_organization));

        $rolesArray = array();
        while($roleId = $rolesDownloadStatement->fetchColumn())
        {
            $rolesArray[] = (int) $roleId;
        }

        try
        {
            // get recordset of current folder from database
            $folder = new TableFolder(self::$db, $folderId);
            $folder->addRolesOnFolder('folder_upload', $rolesArray);
        }
        catch(AdmException $e)
        {
            $e->showText();
            // => EXIT
        }
    }

    /**
     * Create a unique folder name for the root folder of the download module that contains
     * the shortname of the current organization
     */
    public static function updateStepNewDownloadRootFolderName()
    {
        global $gCurrentOrganization, $g_organization;

        $tempOrganization = $gCurrentOrganization;

        $sql = 'SELECT org_id, org_shortname FROM ' . TBL_ORGANIZATIONS;
        $organizationStatement = self::$db->queryPrepared($sql);

        while($row = $organizationStatement->fetch())
        {
            $rowId = (int) $row['org_id'];

            $gCurrentOrganization->readDataById($rowId);

            $sql = 'SELECT fol_id, fol_name
                      FROM '.TBL_FOLDERS.'
                     WHERE fol_fol_id_parent IS NULL
                       AND fol_org_id = ? -- $rowId';
            $folderStatement = self::$db->queryPrepared($sql, array($rowId));

            if($rowFolder = $folderStatement->fetch())
            {
                $folder = new TableFolder(self::$db, $rowFolder['fol_id']);
                $folderOldName = $folder->getFullFolderPath();
                $folder->setValue('fol_name', TableFolder::getRootFolderName());
                $folder->save();

                $sql = 'UPDATE '.TBL_FOLDERS.'
                           SET fol_path = REPLACE(fol_path, \'/'.$rowFolder['fol_name'].'\', \'/'.TableFolder::getRootFolderName().'\')
                         WHERE fol_org_id = '.$rowId;
                self::$db->query($sql); // TODO add more params

                if($row['org_shortname'] === $g_organization && is_dir($folderOldName))
                {
                    rename($folderOldName, $folder->getFullFolderPath());
                }
            }
            else
            {
                $sql = 'INSERT INTO '.TBL_FOLDERS.'
                               (fol_org_id, fol_type, fol_name, fol_path, fol_locked, fol_public, fol_timestamp)
                        VALUES (?, \'DOWNLOAD\', ?, ?, 0, 1, ?) -- $rowId, TableFolder::getRootFolderName(), FOLDER_DATA, DATETIME_NOW';
                $params = array(
                    $rowId,
                    TableFolder::getRootFolderName(),
                    FOLDER_DATA,
                    DATETIME_NOW
                );
                self::$db->queryPrepared($sql, $params);
            }
        }

        $gCurrentOrganization = $tempOrganization;
    }

    /**
     * This method renames the webmaster role to administrator.
     */
    public static function updateStepRenameWebmasterToAdministrator()
    {
        global $gL10n;

        $sql = 'UPDATE '.TBL_ROLES.'
                   SET rol_name = ? -- $gL10n->get(\'SYS_ADMINISTRATOR\')_1
                 WHERE rol_name = ? -- $gL10n->get(\'SYS_ADMINISTRATOR\')';
        self::$db->queryPrepared($sql, array($gL10n->get('SYS_ADMINISTRATOR') . '_1', $gL10n->get('SYS_ADMINISTRATOR')));

        $sql = 'UPDATE '.TBL_ROLES.'
                   SET rol_name = ? -- $gL10n->get(\'SYS_ADMINISTRATOR\')
                 WHERE rol_name = ? -- $gL10n->get(\'SYS_WEBMASTER\')';
        self::$db->queryPrepared($sql, array($gL10n->get('SYS_ADMINISTRATOR'), $gL10n->get('SYS_WEBMASTER')));
    }

    /**
     * Check all folders in adm_my_files and set the rights to 0777
     * @param string $folder
     * @return bool
     */
    public static function updateStepRewriteFolderRights($folder = '')
    {
        $returnValue = true;

        if ($folder === '')
        {
            $folder = ADMIDIO_PATH . FOLDER_DATA;
        }

        $dirHandle = @opendir($folder);
        if ($dirHandle)
        {
            while (($entry = readdir($dirHandle)) !== false)
            {
                if ($entry !== '.' && $entry !== '..')
                {
                    $resource = $folder . '/' . $entry;

                    if (is_dir($resource))
                    {
                        // now check the subfolder
                        $returnValue = $returnValue && self::updateStepRewriteFolderRights($resource);

                        // set rights to 0777
                        $returnValue = $returnValue && @chmod($resource, 0777);
                    }
                }
            }
            closedir($dirHandle);
        }

        return $returnValue;
    }

    /**
     * This method set the default configuration for all organizations
     */
    public static function updateStepSetDefaultConfiguration()
    {
        $sql = 'SELECT org_id FROM ' . TBL_ORGANIZATIONS;
        $organizationsStatement = self::$db->queryPrepared($sql);
        $organizationsArray     = $organizationsStatement->fetchAll();

        foreach($organizationsArray as $organization)
        {
            $orgId = (int) $organization['org_id'];

            $sql = 'SELECT lst_id
                      FROM '.TBL_LISTS.'
                     WHERE lst_default = 1
                       AND lst_org_id  = ? -- $orgId';
            $defaultListStatement = self::$db->queryPrepared($sql, array($orgId));
            $listId = (int) $defaultListStatement->fetchColumn();

            // save default list to preferences
            $sql = 'UPDATE '.TBL_PREFERENCES.'
                       SET prf_value  = ? -- $listId
                     WHERE prf_name   = \'lists_default_configuation\'
                       AND prf_org_id = ? -- $orgId';
            self::$db->queryPrepared($sql, array($listId, $orgId));
        }
    }

    /**
     * This method set the approval states for all members of an event in the past to confirmed.
     */
    public static function updateStepSetParticipantsApprovalStates()
    {
        $sql = 'UPDATE '.TBL_MEMBERS.'
                           SET mem_approved = 2
                         WHERE mem_approved IS NULL
                           AND mem_begin < ? -- DATE_NOW
                           AND mem_rol_id IN (SELECT rol_id
                                                FROM '.TBL_ROLES.'
                                          INNER JOIN '.TBL_CATEGORIES.'
                                                  ON cat_id = rol_cat_id
                                               WHERE cat_name_intern = \'EVENTS\'
                                                 AND rol_id IN (SELECT dat_rol_id
                                                                  FROM '.TBL_DATES.'
                                                                 WHERE dat_rol_id = rol_id))';

        self::$db->queryPrepared($sql, array(DATE_NOW));
    }

    /**
     * This method installs the default user relation types
     */
    public static function updateStepInstallDefaultUserRelationTypes()
    {
        global $gL10n, $gCurrentUser;

        $currUsrId = (int) $gCurrentUser->getValue('usr_id');

        $sql = 'INSERT INTO '.TBL_USER_RELATION_TYPES.'
                       (urt_id, urt_name, urt_name_male, urt_name_female, urt_id_inverse, urt_usr_id_create, urt_timestamp_create)
                VALUES (1, \''.$gL10n->get('INS_PARENT').'\',      \''.$gL10n->get('INS_FATHER').'\',           \''.$gL10n->get('INS_MOTHER').'\',             2, '.$currUsrId.', \''.DATETIME_NOW.'\')
                     , (2, \''.$gL10n->get('INS_CHILD').'\',       \''.$gL10n->get('INS_SON').'\',              \''.$gL10n->get('INS_DAUGHTER').'\',           1, '.$currUsrId.', \''.DATETIME_NOW.'\')
                     , (3, \''.$gL10n->get('INS_SIBLING').'\',     \''.$gL10n->get('INS_BROTHER').'\',          \''.$gL10n->get('INS_SISTER').'\',             3, '.$currUsrId.', \''.DATETIME_NOW.'\')
                     , (4, \''.$gL10n->get('INS_SPOUSE').'\',      \''.$gL10n->get('INS_HUSBAND').'\',          \''.$gL10n->get('INS_WIFE').'\',               4, '.$currUsrId.', \''.DATETIME_NOW.'\')
                     , (5, \''.$gL10n->get('INS_COHABITANT').'\',  \''.$gL10n->get('INS_COHABITANT_MALE').'\',  \''.$gL10n->get('INS_COHABITANT_FEMALE').'\',  5, '.$currUsrId.', \''.DATETIME_NOW.'\')
                     , (6, \''.$gL10n->get('INS_COMPANION').'\',   \''.$gL10n->get('INS_BOYFRIEND').'\',        \''.$gL10n->get('INS_GIRLFRIEND').'\',         6, '.$currUsrId.', \''.DATETIME_NOW.'\')
                     , (7, \''.$gL10n->get('INS_SUPERIOR').'\',    \''.$gL10n->get('INS_SUPERIOR_MALE').'\',    \''.$gL10n->get('INS_SUPERIOR_FEMALE').'\',    8, '.$currUsrId.', \''.DATETIME_NOW.'\')
                     , (8, \''.$gL10n->get('INS_SUBORDINATE').'\', \''.$gL10n->get('INS_SUBORDINATE_MALE').'\', \''.$gL10n->get('INS_SUBORDINATE_FEMALE').'\', 7, '.$currUsrId.', \''.DATETIME_NOW.'\')';
        self::$db->query($sql); // TODO add more params
    }

    /**
     * This method add all roles to the role right category_view if the role had set the flag cat_hidden = 1
     */
    public static function updateStepVisibleCategories()
    {
        $sql = 'SELECT cat_id, cat_org_id
                  FROM ' . TBL_CATEGORIES . '
                 WHERE cat_type IN (\'ANN\', \'DAT\', \'LNK\', \'USF\')
                   AND cat_org_id IS NOT NULL
                   AND cat_hidden = 1 ';
        $categoryStatement = self::$db->queryPrepared($sql);

        while($row = $categoryStatement->fetch())
        {
            $roles = array();
            $sql = 'SELECT rol_id
                      FROM ' . TBL_ROLES . '
                INNER JOIN ' . TBL_CATEGORIES . '
                        ON cat_id = rol_cat_id
                     WHERE rol_valid  = 1
                       AND cat_name_intern <> \'EVENTS\'
                       AND cat_org_id = ? -- $row[\'cat_org_id\']';
            $rolesStatement = self::$db->queryPrepared($sql, array((int) $row['cat_org_id']));

            while($rowRole = $rolesStatement->fetch())
            {
                $roles[] = (int) $rowRole['rol_id'];
            }

            // save roles to role right
            $rightCategoryView = new RolesRights(self::$db, 'category_view', (int) $row['cat_id']);
            $rightCategoryView->saveRoles($roles);
        }
    }
}
