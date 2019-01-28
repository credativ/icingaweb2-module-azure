<?php
/** ***************************************************************************
 * @author Peter Dreuw <peter.dreuw@credativ.de>
 * @copyright Copyright (c) 2019 credativ GmbH
 * @license https://github.com/credativ/icingaweb2-module-azure/blob/master/LICENSE MIT License
 *
 *
 */
namespace Icinga\Module\Azure;

use Icinga\Module\Director\Web\Form\QuickForm;
use Icinga\Exception\ConfigurationError;
use Icinga\Exception\QueryException;
use Icinga\Application\Logger;

use Icinga\Module\Azure\Api;

/**
 * Class Api
 *
 * This abstract class extends the common API for PostgreSQL subtype
 * common handling functions.
 *
 */

abstract class MsPgSQLabstract extends Api
{
    /**
     * static array with names of fields that get configured in a form extension
     * delivered by this class, cf. function extendForm().
     *
     * @staticvar string CONFIG_FIELDS
     */
    public const CONFIG_FIELDS = [ 'postgresql_server' ];


    /** ***********************************************************************
     * Generates an dictionary for PostgreSQL Servers available in given
     * resource group.
     *
     * @param QuickForm form
     * a form object to be extended
     *
     * @return void
     *
     */

    protected function enumPostgreSQLservers( $resgroupname )
    {

        $resgroups = $this->getResourceGroups( $resgroupname );

        $retval = array();

        foreach( $resgroups as $group )
        {
            $pgsql = $this->getMsDbPostgreSQLServers( $group );
            foreach($pgsql as $server)
            {
                $retval[$server->id] = $server->name;
            }
        }

        Logger::debug( "Azure API: Dump of available PostgreSQL servers: ".
                       print_r($retval, true));

        return $retval;
    }


    /** ***********************************************************************
     * callback for the importer form manager to call for extensions of
     * the config form. This subclass needs the name of the dependent
     * MS PostgreSQL server. For uniqueness, we save the ID not the name.
     *
     * @param QuickForm form
     * a form object to be extended
     *
     * @return void
     *
     */

    public function extendForm( QuickForm $form )
    {
        $rgn = $form->getSentOrObjectSetting('resource_group_names');

        $form->addElement('select', 'postgresql_server', array(
            'label'        => $form->translate('PostgreSQL server'),
            'description'  => $form->translate(
                'Select the PostgreSQL server you want to query. '),
            'required'     => true,
            'multiOptions' => $form->optionalEnum(
                $this->enumPostgreSQLservers($rgn)
            ),
        ));
        return;
    }
}
