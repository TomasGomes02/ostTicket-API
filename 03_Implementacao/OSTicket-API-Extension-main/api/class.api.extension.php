<?php

/**
 * @file
 * API class extension for the OSTicket API Extension plugin.
 */

// Include necessary files.
include_once INCLUDE_DIR . 'class.api.php';
include_once INCLUDE_DIR . 'plugin.config.php';
include_once PRJ_PLUGIN_DIR . 'class.ticket.extension.php';

/**
 * Class ApiExtension.
 *
 * This class extends the API class to adapt to the new API key table.
 */
class ApiExtension extends API
{

    /**
     * Constructor.
     *
     * @param int $id The ID of the API key.
     */
    function __construct($id)
    {
        $this->id = 0;
        $this->load($id);
    }

    /**
     * Load API key by ID.
     *
     * @param int
     *  $id The ID of the API key to load.
     * @return bool
     *  True if successful, false otherwise.
     */
    function load($id = 0)
    {
        if (!$id && !($id = $this->getId()))
            return false;

        $sql = 'SELECT * FROM ' . API_NEW_TABLE . ' WHERE id=' . db_input($id);
        if (!($res = db_query($sql)) || !db_num_rows($res))
            return false;

        $this->ht = db_fetch_array($res);
        $this->id = $this->ht['id'];

        return true;
    }

    /** Static functions **/

    /**
     * Get department id and name.
     * 
     * @return mixed (string or boolean) string with results from query, false if query didnt work
     */
    static function getDeps()
    {
        $sql = 'SELECT id, signature AS NomeDepartamento FROM ' . DEPT_TABLE;

        if (($res = db_query($sql))) {
            $result = '';
            while ($row = $res->fetch_row()) {
                $result .= sprintf("%s - %s\n", $row[0], $row[1]);
            }
            $result = rtrim($result, "\n");
            $res->free_result();
            return $result;
        }
        return false;
    }

    /**
     * Get SLA (Service Level Agreement) id and name.
     * 
     * @return mixed (string or boolean) string with results from query, false if query didnt work
     */
    static function getSLAS()
    {
        $sql = 'SELECT id, name AS SLA FROM ' . SLA_TABLE;

        if (($res = db_query($sql))) {
            $result = '';
            while ($row = $res->fetch_row()) {
                $result .= sprintf("%s - %s\n", $row[0], $row[1]);
            }
            $result = rtrim($result, "\n");
            $res->free_result();
            return $result;
        }
        return false;
    }

    /**
     * Get Teams id, name and notes.
     * 
     * @return mixed (string or boolean) string with results from query, false if query didnt work
     */
    static function getTeams()
    {
        $sql = 'SELECT team_id, name, notes FROM ' . TEAM_TABLE;

        if (($res = db_query($sql))) {
            $result = '';
            while ($row = $res->fetch_row()) {
                $result .= sprintf("%s - %s (%s)\n", $row[0], $row[1], $row[2]);
            }
            $result = rtrim($result, "\n");
            $res->free_result();
            return $result;
        }
        return false;
    }

    /**
     * Get Staff id, username, first name, last name and email.
     * 
     * @return mixed (string or boolean) string with results from query, false if query didnt work
     */
    static function getStaff()
    {

        $sql = 'SELECT staff_id, firstname, lastname, username, email FROM ' . STAFF_TABLE;

        if (($res = db_query($sql))) {
            $result = '';
            while ($row = $res->fetch_row()) {
                $result .= sprintf("%s - %s %s (%s - %s)\n", $row[0], $row[1], $row[2], $row[3], $row[4]);
            }
            $result = rtrim($result, "\n");
            $res->free_result();
            return $result;
        }
        return false;
    }

    /**
     * Get Users id, username and email.
     * 
     * @return mixed (string or boolean) string with results from query, false if query didnt work
     */
    static function getUsers()
    {

        $sql = 'SELECT u.id, u.name, e.address FROM ' . USER_TABLE . ' u 
        LEFT JOIN '. USER_EMAIL_TABLE . ' e ON u.id = e.id ORDER BY u.id;';

        if (($res = db_query($sql))) {
            $result = '';
            while ($row = $res->fetch_row()) {
                $result .= sprintf("%s - %s (%s)\n", $row[0], $row[1], $row[2]);
            }
            $result = rtrim($result, "\n");
            $res->free_result();
            return $result;
        }
        return false;
    }

    /**
     * Get Priority id and description.
     * 
     * @return mixed (string or boolean) string with results from query, false if query didnt work
     */
    static function getPiority()
    {
        $sql = 'SELECT priority_id, priority_desc FROM ' . TICKET_PRIORITY_TABLE;

        if (($res = db_query($sql))) {
            $result = '';
            while ($row = $res->fetch_row()) {
                $result .= sprintf("%s - %s\n", $row[0], $row[1]);
            }
            $result = rtrim($result, "\n");
            $res->free_result();
            return $result;
        }
        return false;
    }

    /**
     * Get Topic id, name and notes.
     * 
     * @return mixed (string or boolean) string with results from query, false if query didnt work
     */
    static function getTopic()
    {
        $sql = 'SELECT topic_id, topic, notes FROM ' . TOPIC_TABLE;

        if (($res = db_query($sql))) {
            $result = '';
            while ($row = $res->fetch_row()) {
                $result .= sprintf("%s - %s (%s)\n", $row[0], $row[1], $row[2]);
            }
            $result = rtrim($result, "\n");
            $res->free_result();
            return $result;
        }
        return false;
    }

    /**
     * Get Source name.
     * 
     * @return mixed (string or boolean) string with results from TicketExtension::getSources(), false if it didnt work
     */
    static function getSources()
    {
        if($sources = TicketExtension::getSources()){
            $result = '';
            foreach ($sources as $item) {
                $result .= sprintf("%s\n", $item);
            }
            $result = rtrim($result, "\n");
            return $result;
        }
        return false;
    }

    /**
     * Get Source name.
     * 
     * @param int $deptId department id of the agent geting this list
     * @param boolean $isAdmin true if the agent is an admin, false otherwise
     * 
     * @return mixed (string or boolean) string with results from TicketExtension::getSources(), false if it didnt work
     */
    static function getTickets($deptId, $isAdmin)
    {
        if($isAdmin){
            $sql = 'SELECT number FROM ' . TICKET_TABLE;
        }else{
            $sql = 'SELECT number FROM ' . TICKET_TABLE . ' WHERE dept_id=' . db_input($deptId);
        }

        if (($res = db_query($sql))) {
            $result = '';
            while ($row = $res->fetch_row()) {
                $ticket = Ticket::lookup(array('number' => $row[0]));
                $result .= sprintf("Ticket %s (%s) - Status: %s, Last Updated: %s, Subject: %s, From: %s, Priority: %s, Assigned To: %s\n", 
                $row[0], $ticket->getDept(), $ticket->getStatus(), $ticket->getEffectiveDate(), $ticket->getSubject(), User::lookup($ticket->getUserId())->getName(), $ticket->getPriority(), $ticket->getAssignee());
            }
            $result = rtrim($result, "\n");
            $res->free_result();
            return $result;
        }
        return false;
    }

    /**
     * Lookup key object.
     *
     * @param string $key The key of the API key to be looked up.
     * @return object ApiExtension with information regarding the API key.
     */
    static function lookupByKeyPRJ($key)
    {
        return self::lookup(self::getIdByKeyPRJ($key));
    }

    /**
     * Get id of an API key
     *
     * @param string $key The key of the API key.
     * @return int $id corresponding to the API key.
     */
    static function getIdByKeyPRJ($key)
    {

        $sql = 'SELECT id FROM ' . API_NEW_TABLE . ' WHERE apikey=' . db_input($key);

        if (($res = db_query($sql)) && db_num_rows($res))
            list($id) = db_fetch_row($res);

        return $id;
    }

    /**
     * Lookup
     *
     * @param int $id The ID of the API key to load.
     * @return object ApiExtension.
     */
    static function lookup($id)
    {
        return ($id && is_numeric($id) && ($k = new ApiExtension($id)) && $k->getId() == $id) ? $k : null;
    }

    /**
     * Add
     *
     * @param array 
     * @return object ApiExtension.
     */
    static function add($vars, &$errors)
    {
        return ApiExtension::save(0, $vars, $errors);
    }

    /**
     * Save
     *
     * @param int $id 
     * @param array $vars
     * 
     * @return boolean
     */
    static function save($id, $vars, &$errors)
    {
        if ($errors) return false;

        $sql = ' updated=NOW() '
            . ',id_staff=' . db_input($vars['idStaff'])
            . ',isactive=' . db_input($vars['isActive'])
            . ',can_create_tickets=' . db_input($vars['canCreateTickets'])
            . ',can_close_tickets=' . db_input($vars['canCloseTickets'])
            . ',can_reopen_tickets=' . db_input($vars['canReopenTickets'])
            . ',can_edit_tickets=' . db_input($vars['canEditTickets'])
            . ',can_suspend_tickets=' . db_input($vars['canSuspendTickets'])
            . ',notes=' . db_input(Format::sanitize($vars['notes']));

        if ($id) {
            $sql = 'UPDATE ' . API_NEW_TABLE . ' SET ' . $sql . ' WHERE id=' . db_input($id);
            if (db_query($sql))
                return true;

            $errors['err'] = sprintf(__('Unable to update %s.'), __('this API key'))
                . ' ' . __('Internal error occurred');
        } else {
            //query designed to deactivate any existing API keys belonging to staff members who are requesting a new API key, if such keys exist.
            $updateSql = 'UPDATE ' . API_NEW_TABLE . ' SET isactive = 0 WHERE id_staff=' . db_input($vars['idStaff']);
            if (!db_query($updateSql))
                return false;

            $sql = 'INSERT INTO ' . API_NEW_TABLE . ' SET ' . $sql
                . ',created=NOW() '
                . ',apikey=' . db_input(strtoupper(md5(time() . md5(Misc::randCode(16)))));


            if (db_query($sql) && ($id = db_insert_id()))
                return $id;

            $errors['err'] = sprintf(
                '%s %s',
                sprintf(__('Unable to add %s.'), __('this API key')),
                __('Correct any errors below and try again.')
            );
        }

        return false;
    }

     /**
     * Gets the id of the key of the specified staff.
     *
     * @param int $staffId

     * @return mixed (int or boolean) int with the id of the key, false if query didnt work
     */
    static function getKeyIdForUser($staffId){
        $sql = 'SELECT id FROM ' . API_NEW_TABLE . ' WHERE id_staff=' . db_input($staffId) . ' AND isactive = 1';
        $res = db_query($sql);
        if(!$res){
            return false;
        }
        return $res->fetch_row()[0];
    }

    /**
     * Get staff id 
     * 
     * @return int $id corresponding to the API key.
     */
    function getStaffId()
    {
        return ($this->ht['id_staff']);
    }

    /**
     * Get can close tickets,
     * is ment to see if staff has permission to close a ticket
     *
     * @return int value 1 if has permission 0 otherwise.
     */
    function canCloseTickets()
    {
        return ($this->ht['can_close_tickets']);
    }

    /**
     * Get can reopen tickets,
     * is ment to see if staff has permission to reopen a ticket
     *
     * @return int value 1 if has permission 0 otherwise.
     */
    function canReopenTickets()
    {
        return ($this->ht['can_reopen_tickets']);
    }

    /**
     * Get can edit tickets,
     * is ment to see if staff has permission to edit a ticket
     *
     * @return int value 1 if has permission 0 otherwise.
     */
    function canEditTickets()
    {
        return ($this->ht['can_edit_tickets']);
    }

    /**
     * Get can suspend tickets,
     * is ment to see if staff has permission to suspend a ticket
     *
     * @return int value 1 if has permission 0 otherwise.
     */
    function canSuspendTickets()
    {
        return ($this->ht['can_suspend_tickets']);
    }
}
