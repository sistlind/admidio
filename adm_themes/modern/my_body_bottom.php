
<!-- Here you can add your html code. This code will be applied at the end of the <body> area
     and after the Admidio module code.
-->

            </div><!-- closes "div#left-block" -->
        </div><!-- closes "div.col-md-9" -->
        <div class="col-md-3">
            <div id="right-block" class="admidio-container">
                <?php

                require(ADMIDIO_PATH . FOLDER_PLUGINS . '/login_form/login_form.php');

                ?>
                <div id="plugin_menu" class="admidio-plugin-content">
                    <?php

                    // Module Menu
                    $moduleMenu = new Menu('modules', $gL10n->get('SYS_MODULES'));

                    $moduleMenu->addItem(
                        'overview', '/adm_program/index.php',
                        $gL10n->get('SYS_OVERVIEW'), '/icons/home.png'
                    );

                    if((int) $gSettingsManager->get('enable_announcements_module') === 1
                    || ((int) $gSettingsManager->get('enable_announcements_module') === 2 && $gValidLogin))
                    {
                        $moduleMenu->addItem(
                            'announcements', ADMIDIO_URL . FOLDER_MODULES . '/announcements/announcements.php',
                            $gL10n->get('ANN_ANNOUNCEMENTS'), '/icons/announcements.png'
                        );
                    }
                    if($gSettingsManager->getBool('enable_download_module'))
                    {
                        $moduleMenu->addItem(
                            'download', ADMIDIO_URL . FOLDER_MODULES . '/downloads/downloads.php',
                            $gL10n->get('DOW_DOWNLOADS'), '/icons/download.png'
                        );
                    }
                    if($gSettingsManager->getBool('enable_mail_module') && !$gValidLogin)
                    {
                        $moduleMenu->addItem(
                            'email', ADMIDIO_URL . FOLDER_MODULES . '/messages/messages_write.php',
                            $gL10n->get('SYS_EMAIL'), '/icons/email.png'
                        );
                    }
                    if(($gSettingsManager->getBool('enable_pm_module') || $gSettingsManager->getBool('enable_mail_module')) && $gValidLogin)
                    {
                        $unreadBadge = '';

                        // get number of unread messages for user
                        $message = new TableMessage($gDb);
                        $unread = $message->countUnreadMessageRecords($gCurrentUser->getValue('usr_id'));

                        if($unread > 0)
                        {
                            $unreadBadge = '<span class="badge">' . $unread . '</span>';
                        }

                        $moduleMenu->addItem(
                            'private_message', ADMIDIO_URL . FOLDER_MODULES . '/messages/messages.php',
                            $gL10n->get('SYS_MESSAGES') . $unreadBadge, '/icons/messages.png'
                        );
                    }
                    if((int) $gSettingsManager->get('enable_photo_module') === 1
                    || ((int) $gSettingsManager->get('enable_photo_module') === 2 && $gValidLogin))
                    {
                        $moduleMenu->addItem(
                            'photo', ADMIDIO_URL . FOLDER_MODULES . '/photos/photos.php',
                            $gL10n->get('PHO_PHOTOS'), '/icons/photo.png'
                        );
                    }
                    if((int) $gSettingsManager->get('enable_guestbook_module') === 1
                    || ((int) $gSettingsManager->get('enable_guestbook_module') === 2 && $gValidLogin))
                    {
                        $moduleMenu->addItem(
                            'guestbk', ADMIDIO_URL . FOLDER_MODULES . '/guestbook/guestbook.php',
                            $gL10n->get('GBO_GUESTBOOK'), '/icons/guestbook.png'
                        );
                    }

                    if($gSettingsManager->getBool('lists_enable_module') && $gValidLogin)
                    {
                        $moduleMenu->addItem(
                            'lists', ADMIDIO_URL . FOLDER_MODULES . '/lists/lists.php',
                            $gL10n->get('LST_LISTS'), '/icons/lists.png'
                        );
                        $moduleMenu->addItem(
                            'mylist', ADMIDIO_URL . FOLDER_MODULES . '/lists/mylist.php',
                            $gL10n->get('LST_MY_LIST'), '/icons/mylist.png'
                        );
                    }

                    if((int) $gSettingsManager->get('enable_dates_module') === 1
                    || ((int) $gSettingsManager->get('enable_dates_module') === 2 && $gValidLogin))
                    {
                        $moduleMenu->addItem(
                            'dates', ADMIDIO_URL . FOLDER_MODULES . '/dates/dates.php',
                            $gL10n->get('DAT_DATES'), '/icons/dates.png'
                        );
                    }

                    if((int) $gSettingsManager->get('enable_weblinks_module') === 1
                    || ((int) $gSettingsManager->get('enable_weblinks_module') === 2 && $gValidLogin))
                    {
                        $moduleMenu->addItem(
                            'links', ADMIDIO_URL . FOLDER_MODULES . '/links/links.php',
                            $gL10n->get('LNK_WEBLINKS'), '/icons/weblinks.png'
                        );
                    }

                    echo $moduleMenu->show();

                    // Administration Menu
                    if($gCurrentUser->approveUsers() || $gCurrentUser->editUsers()
                    || $gCurrentUser->manageRoles()  || $gCurrentUser->isAdministrator())
                    {
                        $adminMenu = new Menu('administration', $gL10n->get('SYS_ADMINISTRATION'));

                        if($gCurrentUser->approveUsers() && $gSettingsManager->getBool('registration_enable_module'))
                        {
                            $adminMenu->addItem(
                                'newreg', ADMIDIO_URL . FOLDER_MODULES . '/registration/registration.php',
                                $gL10n->get('NWU_NEW_REGISTRATIONS'), '/icons/new_registrations.png'
                            );
                        }
                        if($gCurrentUser->editUsers())
                        {
                            $adminMenu->addItem(
                                'usrmgt', ADMIDIO_URL . FOLDER_MODULES . '/members/members.php',
                                $gL10n->get('MEM_USER_MANAGEMENT'), '/icons/user_administration.png'
                            );
                        }
                        if($gCurrentUser->manageRoles())
                        {
                            $adminMenu->addItem(
                                'roladm', ADMIDIO_URL . FOLDER_MODULES . '/roles/roles.php',
                                $gL10n->get('ROL_ROLE_ADMINISTRATION'), '/icons/roles.png'
                            );
                        }
                        if($gCurrentUser->isAdministrator())
                        {
                            $adminMenu->addItem(
                                'dbback', ADMIDIO_URL . FOLDER_MODULES . '/backup/backup.php',
                                $gL10n->get('BAC_DATABASE_BACKUP'), '/icons/backup.png'
                            );
                            $adminMenu->addItem(
                                'orgprop', ADMIDIO_URL . FOLDER_MODULES . '/preferences/preferences.php',
                                $gL10n->get('SYS_SETTINGS'), '/icons/options.png'
                            );
                        }

                        echo $adminMenu->show();
                    }

                    ?>
                </div><!-- closes "div#plugin_menu" -->
            </div><!-- closes "div#right-block" -->
        </div><!-- closes "div.col-md-3" -->
    </div><!-- closes "div.row" -->
</div><!-- closes "div#page" -->


<p id="copyright">
    <a href="<?php echo ADMIDIO_HOMEPAGE; ?>" style="text-decoration: none;">
        <img src="<?php echo THEME_URL; ?>/images/admidio_writing_100.png"
             alt="<?php echo $gL10n->get('SYS_ADMIDIO_SHORT_DESC'); ?>"
             title="<?php echo $gL10n->get('SYS_ADMIDIO_SHORT_DESC'); ?>"
             style="border: 0; vertical-align: bottom;" />
    </a><br />
    <span style="font-size: 9pt; padding-left: 8px;">&copy; 2004 - 2017&nbsp;&nbsp;<?php echo $gL10n->get('SYS_ADMIDIO_TEAM'); ?></span>
</p>
