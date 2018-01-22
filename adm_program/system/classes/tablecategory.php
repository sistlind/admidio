<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_categories
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class TableCategory
 * Diese Klasse dient dazu einen Kategorieobjekt zu erstellen.
 * Eine Kategorieobjekt kann ueber diese Klasse in der Datenbank verwaltet werden
 *
 * Beside the methods of the parent class there are the following additional methods:
 *
 * getNewNameIntern($name, $index) - diese rekursive Methode ermittelt fuer den
 *                       uebergebenen Namen einen eindeutigen Namen dieser bildet sich
 *                       aus dem Namen in Grossbuchstaben und der naechsten freien Nummer
 * getNumberElements() - number of child recordsets
 * moveSequence($mode) - Kategorie wird um eine Position in der Reihenfolge verschoben
 */
class TableCategory extends TableAccess
{
    /**
     * @var string
     */
    protected $elementTable = '';
    /**
     * @var string
     */
    protected $elementColumn = '';

    /**
     * Constructor that will create an object of a recordset of the table adm_category.
     * If the id is set than the specific category will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int      $catId    The recordset of the category with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $catId = 0)
    {
        parent::__construct($database, TBL_CATEGORIES, 'cat', $catId);
    }

    /**
     * Deletes the selected record of the table and all references in other tables.
     * After that the class will be initialize. The method throws exceptions if
     * the category couldn't be deleted.
     * @throws AdmException SYS_DELETE_SYSTEM_CATEGORY
     *                      SYS_DELETE_LAST_CATEGORY
     *                      CAT_DONT_DELETE_CATEGORY
     * @return bool @b true if no error occurred
     */
    public function delete()
    {
        global $gCurrentSession;

        // system-category couldn't be deleted
        if ((int) $this->getValue('cat_system') === 1)
        {
            throw new AdmException('SYS_DELETE_SYSTEM_CATEGORY');
        }

        // checks if there exists another category of this type. Don't delete the last category of a type!
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_CATEGORIES.'
                 WHERE (  cat_org_id = ? -- $gCurrentSession->getValue(\'ses_org_id\')
                       OR cat_org_id IS NULL )
                   AND cat_type = ? -- $this->getValue(\'cat_type\')';
        $categoriesStatement = $this->db->queryPrepared($sql, array($gCurrentSession->getValue('ses_org_id'), $this->getValue('cat_type')));

        // Don't delete the last category of a type!
        if ((int) $categoriesStatement->fetchColumn() === 1)
        {
            throw new AdmException('SYS_DELETE_LAST_CATEGORY');
        }

        $this->db->startTransaction();

        // Luecke in der Reihenfolge schliessen
        $sql = 'UPDATE '.TBL_CATEGORIES.'
                   SET cat_sequence = cat_sequence - 1
                 WHERE (  cat_org_id = ? -- $gCurrentSession->getValue(\'ses_org_id\')
                       OR cat_org_id IS NULL )
                   AND cat_sequence > ? -- $this->getValue(\'cat_sequence\')
                   AND cat_type     = ? -- $this->getValue(\'cat_type\')';
        $queryParams = array($gCurrentSession->getValue('ses_org_id'), $this->getValue('cat_sequence'), $this->getValue('cat_type'));
        $this->db->queryPrepared($sql, $queryParams);

        $catId = (int) $this->getValue('cat_id');

        // alle zugehoerigen abhaengigen Objekte suchen und mit weiteren Abhaengigkeiten loeschen
        $sql = 'SELECT *
                  FROM '.$this->elementTable.'
                 WHERE '.$this->elementColumn.' = ? -- $this->getValue(\'cat_id\')';
        $recordsetsStatement = $this->db->queryPrepared($sql, array($catId));

        if ($recordsetsStatement->rowCount() > 0)
        {
            throw new AdmException('CAT_DONT_DELETE_CATEGORY', $this->getValue('cat_name'), $this->getNumberElements());
        }

        // delete all roles assignments that have the right to view this category
        $categoryViewRoles = new RolesRights($this->db, 'category_view', $catId);
        $categoryViewRoles->delete();

        // now delete category
        $return = parent::delete();

        $this->db->endTransaction();

        return $return;
    }

    /**
     * This method checks if the current user is allowed to edit this category. Therefore
     * the category must be visible to the user and must be of the current organization.
     * If this is a global category than the current organization must be the parent organization.
     * @return bool Return true if the current user is allowed to edit this category
     */
    public function editable()
    {
        global $gCurrentOrganization, $gCurrentUser;

        $categoryType = $this->getValue('cat_type');

        // check the rights in dependence of the category type
        if(($categoryType === 'ROL' && !$gCurrentUser->manageRoles())
        || ($categoryType === 'LNK' && !$gCurrentUser->editWeblinksRight())
        || ($categoryType === 'ANN' && !$gCurrentUser->editAnnouncements())
        || ($categoryType === 'USF' && !$gCurrentUser->editUsers())
        || ($categoryType === 'DAT' && !$gCurrentUser->editDates())
        || ($categoryType === 'AWA' && !$gCurrentUser->editUsers()))
        {
            return false;
        }

        if($this->visible())
        {
            // if category belongs to current organization than it's editable
            if($this->getValue('cat_org_id') > 0
            && (int) $this->getValue('cat_org_id') === (int) $gCurrentOrganization->getValue('org_id'))
            {
                return true;
            }

            // if category belongs to all organizations only parent organization could edit it
            if((int) $this->getValue('cat_org_id') === 0 && $gCurrentOrganization->isParentOrganization())
            {
                return true;
            }

            // a new record will always be visible until all data is saved
            if($this->newRecord)
            {
                return true;
            }
        }

        return false;
    }

    /**
     * diese rekursive Methode ermittelt fuer den uebergebenen Namen einen eindeutigen Namen
     * dieser bildet sich aus dem Namen in Grossbuchstaben und der naechsten freien Nummer (index)
     * Beispiel: 'Gruppen' => 'GRUPPEN_2'
     * @param string $name
     * @param int    $index
     * @return string
     */
    private function getNewNameIntern($name, $index)
    {
        $newNameIntern = strtoupper(str_replace(' ', '_', $name));

        if ($index > 1)
        {
            $newNameIntern = $newNameIntern . '_' . $index;
        }

        $sql = 'SELECT cat_id
                  FROM '.TBL_CATEGORIES.'
                 WHERE cat_name_intern = ? -- $newNameIntern';
        $categoriesStatement = $this->db->queryPrepared($sql, array($newNameIntern));

        if ($categoriesStatement->rowCount() > 0)
        {
            ++$index;
            $newNameIntern = $this->getNewNameIntern($name, $index);
        }

        return $newNameIntern;
    }

    /**
     * Read number of child recordsets of this category.
     * @return int Returns the number of child elements of this category
     */
    public function getNumberElements()
    {
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.$this->elementTable.'
                 WHERE '.$this->elementColumn.' = ? -- $this->getValue(\'cat_id\')';
        $elementsStatement = $this->db->queryPrepared($sql, array($this->getValue('cat_id')));

        return (int) $elementsStatement->fetchColumn();
    }

    /**
     * Get the value of a column of the database table.
     * If the value was manipulated before with @b setValue than the manipulated value is returned.
     * @param string $columnName The name of the database column whose value should be read
     * @param string $format     For date or timestamp columns the format should be the date/time format e.g. @b d.m.Y = '02.04.2011'. @n
     *                           For text columns the format can be @b database that would return the original database value without any transformations
     * @return int|string|bool Returns the value of the database column.
     *                         If the value was manipulated before with @b setValue than the manipulated value is returned.
     */
    public function getValue($columnName, $format = '')
    {
        global $gL10n;

        if ($columnName === 'cat_name_intern')
        {
            // internal name should be read with no conversion
            $value = parent::getValue($columnName, 'database');
        }
        else
        {
            $value = parent::getValue($columnName, $format);
        }

        // if text is a translation-id then translate it
        if ($columnName === 'cat_name' && $format !== 'database' && admIsTranslationStrId($value))
        {
            $value = $gL10n->get($value);
        }

        return $value;
    }

    /**
     * Change the internal sequence of this category. It can be moved one place up or down
     * @param string $mode This could be @b UP or @b DOWN.
     */
    public function moveSequence($mode)
    {
        global $gCurrentOrganization;

        // count all categories that are organization independent because these categories should not
        // be mixed with the organization categories. Hidden categories are sidelined.
        $sql = 'SELECT COUNT(*) AS count
                  FROM '.TBL_CATEGORIES.'
                 WHERE cat_org_id IS NULL
                   AND cat_name_intern <> \'EVENTS\'
                   AND cat_type = ? -- $this->getValue(\'cat_type\')';
        $countCategoriesStatement = $this->db->queryPrepared($sql, array($this->getValue('cat_type')));
        $rowCount = (int) $countCategoriesStatement->fetchColumn();

        $mode = admStrToUpper($mode);
        $catOrgId    = (int) $this->getValue('cat_org_id');
        $catSequence = (int) $this->getValue('cat_sequence');

        $sql = 'UPDATE '.TBL_CATEGORIES.'
                   SET cat_sequence = ? -- $catSequence
                 WHERE cat_type = ? -- $this->getValue(\'cat_type\')
                   AND ( cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                       OR cat_org_id IS NULL )
                   AND cat_sequence = ? -- $catSequence';
        $queryParams = array($catSequence, $this->getValue('cat_type'), $gCurrentOrganization->getValue('org_id'));

        // die Kategorie wird um eine Nummer gesenkt und wird somit in der Liste weiter nach oben geschoben
        if ($mode === 'UP')
        {
            if ($catOrgId === 0 || $catSequence > $rowCount + 1)
            {
                $queryParams[] = $catSequence - 1;
                $this->db->queryPrepared($sql, $queryParams);
                $this->setValue('cat_sequence', $catSequence - 1);
            }
        }
        // die Kategorie wird um eine Nummer erhoeht und wird somit in der Liste weiter nach unten geschoben
        elseif ($mode === 'DOWN')
        {
            if ($catOrgId > 0 || $catSequence < $rowCount)
            {
                $queryParams[] = $catSequence + 1;
                $this->db->queryPrepared($sql, $queryParams);
                $this->setValue('cat_sequence', $catSequence + 1);
            }
        }

        $this->save();
    }

    /**
     * Reads a category out of the table in database selected by the unique category id in the table.
     * Per default all columns of adm_categories will be read and stored in the object.
     * @param int $catId Unique cat_id
     * @return bool Returns @b true if one record is found
     */
    public function readDataById($catId)
    {
        $returnValue = parent::readDataById($catId);

        if ($returnValue)
        {
            $this->setTableAndColumnByCatType();
        }

        return $returnValue;
    }

    /**
     * Reads a category out of the table in database selected by different columns in the table.
     * The columns are commited with an array where every element index is the column name and the value is the column value.
     * The columns and values must be selected so that they identify only one record.
     * If the sql will find more than one record the method returns @b false.
     * Per default all columns of adm_categories will be read and stored in the object.
     * @param array<string,mixed> $columnArray An array where every element index is the column name and the value is the column value
     * @return bool Returns @b true if one record is found
     */
    public function readDataByColumns(array $columnArray)
    {
        $returnValue = parent::readDataByColumns($columnArray);

        if ($returnValue)
        {
            $this->setTableAndColumnByCatType();
        }

        return $returnValue;
    }

    /**
     * Save all changed columns of the recordset in table of database. Therefore the class remembers if it's
     * a new record or if only an update is necessary. The update statement will only update
     * the changed columns. If the table has columns for creator or editor than these column
     * with their timestamp will be updated.
     * If a new record is inserted than the next free sequence will be determined.
     * @param bool $updateFingerPrint Default @b true. Will update the creator or editor of the recordset if table has columns like @b usr_id_create or @b usr_id_changed
     * @return bool If an update or insert into the database was done then return true, otherwise false.
     */
    public function save($updateFingerPrint = true)
    {
        global $gCurrentOrganization, $gCurrentSession;

        $fieldsChanged = $this->columnsValueChanged;

        $this->db->startTransaction();

        if ($this->newRecord)
        {
            $queryParams = array($this->getValue('cat_type'));
            if ($this->getValue('cat_org_id') > 0)
            {
                $orgCondition = ' AND (   cat_org_id = ? -- $gCurrentOrganization->getValue(\'org_id\')
                                       OR cat_org_id IS NULL ) ';
                $queryParams[] = $gCurrentOrganization->getValue('org_id');
            }
            else
            {
                $orgCondition = ' AND cat_org_id IS NULL ';
            }

            // beim Insert die hoechste Reihenfolgennummer der Kategorie ermitteln
            $sql = 'SELECT COUNT(*) AS count
                      FROM '.TBL_CATEGORIES.'
                     WHERE cat_type = ? -- $this->getValue(\'cat_type\')
                           '.$orgCondition;
            $countCategoriesStatement = $this->db->queryPrepared($sql, $queryParams);

            $this->setValue('cat_sequence', (int) $countCategoriesStatement->fetchColumn() + 1);

            if ((int) $this->getValue('cat_org_id') === 0)
            {
                // eine Orga-uebergreifende Kategorie ist immer am Anfang, also Kategorien anderer Orgas nach hinten schieben
                $sql = 'UPDATE '.TBL_CATEGORIES.'
                           SET cat_sequence = cat_sequence + 1
                         WHERE cat_type     = ? -- $this->getValue(\'cat_type\')
                           AND cat_org_id IS NOT NULL ';
                $this->db->queryPrepared($sql, array($this->getValue('cat_type')));
            }
        }

        // if new category than generate new name intern, otherwise no change will be made
        if ($this->newRecord && $this->getValue('cat_name_intern') === '')
        {
            $this->setValue('cat_name_intern', $this->getNewNameIntern($this->getValue('cat_name'), 1));
        }

        $returnValue = parent::save($updateFingerPrint);

        // Nach dem Speichern noch pruefen, ob Userobjekte neu eingelesen werden muessen,
        if ($fieldsChanged && $gCurrentSession instanceof Session && $this->getValue('cat_type') === 'USF')
        {
            // all active users must renew their user data because the user field structure has been changed
            $gCurrentSession->renewUserObject();
        }

        $this->db->endTransaction();

        return $returnValue;
    }

    /**
     * Set table and table-column by cat_type
     */
    private function setTableAndColumnByCatType()
    {
        global $g_tbl_praefix;

        switch ($this->getValue('cat_type'))
        {
            case 'ROL':
                $this->elementTable = TBL_ROLES;
                $this->elementColumn = 'rol_cat_id';
                break;
            case 'LNK':
                $this->elementTable = TBL_LINKS;
                $this->elementColumn = 'lnk_cat_id';
                break;
            case 'USF':
                $this->elementTable = TBL_USER_FIELDS;
                $this->elementColumn = 'usf_cat_id';
                break;
            case 'DAT':
                $this->elementTable = TBL_DATES;
                $this->elementColumn = 'dat_cat_id';
                break;
            case 'ANN':
                $this->elementTable = TBL_ANNOUNCEMENTS;
                $this->elementColumn = 'ann_cat_id';
                break;
            case 'AWA':
                $this->elementTable = $g_tbl_praefix . '_user_awards';
                $this->elementColumn = 'awa_cat_id';
                break;
        }
    }

    /**
     * Set a new value for a column of the database table.
     * The value is only saved in the object. You must call the method @b save to store the new value to the database
     * @param string $columnName The name of the database column whose value should get a new value
     * @param mixed  $newValue   The new value that should be stored in the database field
     * @param bool   $checkValue The value will be checked if it's valid. If set to @b false than the value will not be checked.
     * @return bool Returns @b true if the value is stored in the current object and @b false if a check failed
     */
    public function setValue($columnName, $newValue, $checkValue = true)
    {
        global $gCurrentOrganization;

        // Systemkategorien duerfen nicht umbenannt werden
        if ($columnName === 'cat_name' && (int) $this->getValue('cat_system') === 1)
        {
            return false;
        }

        if ($columnName === 'cat_default' && $newValue == '1')
        {
            // es darf immer nur eine Default-Kategorie je Bereich geben
            $sql = 'UPDATE '.TBL_CATEGORIES.'
                       SET cat_default = 0
                     WHERE cat_type    = ? -- $this->getValue(\'cat_type\')
                       AND (  cat_org_id IS NOT NULL
                           OR cat_org_id = ?) -- $gCurrentOrganization->getValue(\'org_id\')';
            $this->db->queryPrepared($sql, array($this->getValue('cat_type'), $gCurrentOrganization->getValue('org_id')));
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }

    /**
     * This method checks if the current user is allowed to view this category. Therefore
     * the visibility of the category is checked.
     * @return bool Return true if the current user is allowed to view this category
     */
    public function visible()
    {
        global $gCurrentUser;

        // a new record will always be visible until all data is saved
        if($this->newRecord)
        {
            return true;
        }

        // check if the current user could view this category
        return in_array((int) $this->getValue('cat_id'), $gCurrentUser->getAllVisibleCategories($this->getValue('cat_type')), true);
    }
}
