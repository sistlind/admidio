<?php
/**
 ***********************************************************************************************
 * Class manages access to database table adm_announcements
 *
 * @copyright 2004-2017 The Admidio Team
 * @see https://www.admidio.org/
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU General Public License v2.0 only
 ***********************************************************************************************
 */

/**
 * @class TableAnnouncement
 *
 * Diese Klasse dient dazu ein Ankuendigungsobjekt zu erstellen.
 * Eine Ankuendigung kann ueber diese Klasse in der Datenbank verwaltet werden
 */
class TableAnnouncement extends TableAccess
{
    /**
     * Constructor that will create an object of a recordset of the table adm_announcements.
     * If the id is set than the specific announcement will be loaded.
     * @param Database $database Object of the class Database. This should be the default global object @b $gDb.
     * @param int      $annId    The recordset of the announcement with this id will be loaded. If id isn't set than an empty object of the table is created.
     */
    public function __construct(Database $database, $annId = 0)
    {
        // read also data of assigned category
        $this->connectAdditionalTable(TBL_CATEGORIES, 'cat_id', 'ann_cat_id');

        parent::__construct($database, TBL_ANNOUNCEMENTS, 'ann', $annId);
    }

    /**
     * This method checks if the current user is allowed to edit this announcement. Therefore
     * the announcement must be visible to the user and must be of the current organization.
     * The user must be a member of at least one role that have the right to manage announcements.
     * Global announcements could be only edited by the parent organization.
     * @return bool Return true if the current user is allowed to edit this announcement
     */
    public function editable()
    {
        global $gCurrentOrganization, $gCurrentUser;

        // check if the current user could edit the category of the announcement
        if($gCurrentUser->editAnnouncements()
        || in_array((int) $this->getValue('cat_id'), $gCurrentUser->getAllEditableCategories('ANN'), true))
        {
            if ($gCurrentOrganization->countAllRecords() === 1)
            {
                return true;
            }

            // parent organizations could edit global announcements,
            // child organizations could only edit their own announcements
            if ($gCurrentOrganization->isParentOrganization()
            || ($gCurrentOrganization->isChildOrganization() && (int) $gCurrentOrganization->getValue('org_id') == (int) $this->getValue('cat_org_id')))
            {
                return true;
            }
        }

        return false;
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

        if ($columnName === 'ann_description')
        {
            if (!isset($this->dbColumns['ann_description']))
            {
                $value = '';
            }

            elseif ($format === 'database')
            {
                $value = html_entity_decode(strStripTags($this->dbColumns['ann_description']), ENT_QUOTES, 'UTF-8');
            }
            else
            {
                $value = $this->dbColumns['ann_description'];
            }

            return $value;
        }

        $value = parent::getValue($columnName, $format);

        if($columnName === 'cat_name')
        {
            // if text is a translation-id then translate it
            if ($format !== 'database' && admIsTranslationStrId($value))
            {
                $value = $gL10n->get($value);
            }
        }

        return $value;
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
        if ($columnName === 'ann_description')
        {
            return parent::setValue($columnName, $newValue, false);
        }
        elseif($columnName === 'ann_cat_id')
        {
            $category = new TableCategory($this->db, $newValue);

            if(!$category->visible() || $category->getValue('cat_type') !== 'ANN')
            {
                throw new AdmException('Category of the announcement '. $this->getValue('ann_name'). ' could not be set
                    because the category is not visible to the current user and current organization.');
            }
        }

        return parent::setValue($columnName, $newValue, $checkValue);
    }

    /**
     * This method checks if the current user is allowed to view this announcement. Therefore
     * the visibility of the category is checked.
     * @return bool Return true if the current user is allowed to view this announcement
     */
    public function visible()
    {
        global $gCurrentUser;

        // check if the current user could view the category of the announcement
        return in_array((int) $this->getValue('cat_id'), $gCurrentUser->getAllVisibleCategories('ANN'), true);
    }
}
