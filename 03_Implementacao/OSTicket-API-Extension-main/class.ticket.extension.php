<?php

/**
 * @file
 * Ticket class extension for the OSTicket API Extension plugin.
 */

include_once INCLUDE_DIR.'class.ticket.php';

//classe que dá override a algumas funções da class api para adaptar a nova tabela api key
class TicketExtension extends Ticket{

    //TEMPORARIO SO PARA FAZER TESTES DEPOIS APAGAR
    function debugToFile($erro)
    {
        $file = PRJ_PLUGIN_DIR . "debug.txt";
        $text =  $erro . "\n";
        file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
    }

    /**
     * Gets an array with the old and the new value
     * 
     * @param mixed $new New value.
     * @param mixed $old Old value.
     * 
     * @return mixed (array or boolean) array if value are diferent, false if not.
     */
    function getChanges($new, $old) {
        return ($old != $new) ? array($old, $new) : false;
    }

    /**
     * Edits the values of the fields Department ('dept') and Priority ('priority') and all the changes associated with them.
     * Used instead of updateFields() and transfer().
     * 
     * @param string $field Name of the field.
     * @param mixed $newValue New value of the field.
     * @param boolean $refer (false by default) When transfering departments if there should be a reference to the old department.
     * 
     * @return boolean true if no errors, false if errors.
     */
    function editFields($field, $newValue, $refer=false){
        global $thisstaff, $cfg;

        switch ($field) {
            case 'dept':
                $oldValue = $this->getDept();
                $this->setDeptId($newValue);
                $dept = $this->getDept();
                if (
                    $this->isAssigned()
                    && ($staff = $this->getStaff())
                    && $dept->assignMembersOnly()
                    && !$dept->isMember($staff)
                ) {
                    $this->setStaffId(0);
                }
                break;
            case 'priority':
                $oldValue = $this->getPriorityId();
                $this->setPriorityId($newValue);
                $oldValue = array(Priority::lookup($oldValue)->getDesc(),$oldValue);
                $newValue = array(Priority::lookup($newValue)->getDesc(),$newValue);
                break;
                //ADICIONAR O RESTO DOS CASES
            default:
        }

        //verifica se o novo valor é igual ao antigo
        if($newValue == $oldValue){
            return false;
        }
        //guarda as mudancas para utilizar no logevent
        $changes = $this->getChanges($newValue, $oldValue);

        if($field == 'priority'){
            $changes['fields'] = array(22 => $changes);
        }
        
        //se nao existir oldvalue, existirem erros e nao der save retorna falso
        if(!$oldValue || $errors || !$this->save(true)){
            return false;
        }

        // Post internal note if any
        if($field == 'dept'){
            // Reopen ticket if closed
            if ($this->isClosed())
                $this->reopen();

            // Set SLA of the new department
            if (!$this->getSLAId() || $this->getSLA()->isTransient())
                if (($slaId = $this->getDept()->getSLAId()))
                    $this->selectSLAId($slaId);

            //DELETES OLD REFERALS
            if (($referral = $this->hasReferral($dept, ObjectModel::OBJECT_TYPE_DEPT)))
                $referral->delete();

            // Log transfer event
            $this->logEvent('transferred', array('dept' => $dept->getName()));

            //REFERS OLD DEPT IF REFER IS TRUE
            if ($refer && $oldValue)
                $this->getThread()->refer($oldValue);

        }else{
            $this->logEvent('edited', $changes);

            $this->lastupdate = SqlFunction::NOW();

            Signal::send('model.updated', $this);
        }

        return true;
    }
    
    /**
     * Adds a comment to the ticket with the comments sent.
     * In the title it shows all fields changed.
     * 
     * @param string $comments comments to add.
     * @param array $fields array with the name of the fields changed.
     * @param Staff $staffAssignee (null by default) new staff assigned to the ticket.
     * @param Team $staffAssignee (null by default) new team assigned to the ticket.
     * 
     * @return mixed (ThreadEntry or boolean) note to alert when the ticket is transfered to a new department, 
     * false if there are no comments and no fields were changed.
     */
    function addComments($comments, $fields, $staffAssignee=null, $teamAssignee=null){
        global $thisstaff;

        if(!$comments || (empty($fields))){
            return false;
        }

        $fieldLabels = array();
        foreach($fields as $field){
            if($field != 'dept' && $field != 'staff' && $field != 'team'){
                $fieldLabels[] = $this->getField($field)->getLabel();
            }
        }

        //muda o titulo dinamicamente de acordo com os fields alterados
        //os fields staff team e dept sao feitos a parte
        $numberFields = count($fieldLabels);
        $titleString = '';
        for ($i = 1; $i <= $numberFields; $i++) {
            $titleString .= "%$i\$s";
            if($numberFields != 1 && $i != $numberFields){
                $titleString .= ($i < $numberFields - 1) ? ', ' : ' and ';
            }
        }

        //ve se um novo staff ou team foi assigned para meter no titulo do comentario
        $assignTitle = '';
        if($staffAssignee && $teamAssignee){
            if ($staffAssignee->getId() == $thisstaff->getId())
                $assignTitle = sprintf(_S('Ticket claimed by %1$s and Assigned to %2$s'), $thisstaff->getName(), $teamAssignee->getName());
            else
                $assignTitle = sprintf(_S('Ticket Assigned to %s and %2$s'), $staffAssignee->getName(), $teamAssignee->getName());
        }
        elseif ($staffAssignee) {
            $assignTitle = ($staffAssignee->getId() == $thisstaff->getId()) 
            ? sprintf(_S('Ticket claimed by %s'), $thisstaff->getName()) 
            : sprintf(_S('Ticket Assigned to %s'), $staffAssignee->getName());
        } elseif ($teamAssignee) {
            $assignTitle = sprintf(_S('Ticket Assigned to %s'), $teamAssignee->getName());
        }

        if(in_array('dept', $fields))
            $transferTitle = 'Ticket transferred'; //INCOMPLETO FALTA METER from oldDept to newDept

        if($titleString){
            $title = sprintf($titleString.' updated', ...$fieldLabels);
            if($assignTitle)
                $title .= ' and '.$assignTitle;
            if($transferTitle)
                $title .= ' and '.$transferTitle;
        }
        elseif($assignTitle){
            $title = $assignTitle;
            if($transferTitle)
                $title .= ' and '.$transferTitle;
        }
        else
            $title .= $transferTitle;

        $_errors = array();
        $note = $this->postNote(
            array('note' => $comments, 'title' => $title),
            $_errors,
            $thisstaff,
            false
        );

        return $note;
    }

    /**
     * Alerts made when transfering ticket to a new department.
     * 
     * @param ThreadEntry $note comment made when ticket was transfered.
     */
    function alerts($note){
        global $thisstaff, $cfg;
        if(!$note)
            $note = null;

        $dept = $this->getDept();

        if (
            ($email = $dept->getAlertEmail())
            && ($tpl = $dept->getTemplate())
            && ($msg = $tpl->getTransferAlertMsgTemplate())
        ) {
            $msg = $this->replaceVars(
                $msg->asArray(),
                array('comments' => $note, 'staff' => $thisstaff)
            );
            // Recipients
            $recipients = array();
            // Assigned staff or team... if any
            if ($this->isAssigned() && $cfg->alertAssignedONTransfer()) {
                if ($this->getStaffId())
                    $recipients[] = $this->getStaff();
                elseif (
                    $this->getTeamId()
                    && ($team = $this->getTeam())
                    && ($members = $team->getMembersForAlerts())
                ) {
                    $recipients = array_merge($recipients, $members);
                }
            } elseif ($cfg->alertDeptMembersONTransfer() && !$this->isAssigned()) {
                // Only alerts dept members if the ticket is NOT assigned.
                foreach ($dept->getMembersForAlerts() as $M)
                    $recipients[] = $M;
            }

            // Always alert dept manager??
            if (
                $cfg->alertDeptManagerONTransfer()
                && $dept
                && ($manager = $dept->getManager())
            ) {
                $recipients[] = $manager;
            }
            $sentlist = $options = array();
            if ($note) {
                $options += array('thread' => $note);
            }
            foreach ($recipients as $k => $staff) {
                if (
                    !is_object($staff)
                    || !$staff->isAvailable()
                    || in_array($staff->getEmail(), $sentlist)
                ) {
                    continue;
                }
                $alert = $this->replaceVars($msg, array('recipient' => $staff));
                $email->sendAlert($staff, $alert['subj'], $alert['body'], null, $options);
                $sentlist[] = $staff->getEmail();
            }
        }
    }

    /**
     * Sets ticket priority to the priority with the specified id.
     * 
     * @param int $priorityId id of the new priority.
     * 
     * @return boolean true if saved succefully, false if not
     */
    function setPriorityId($priorityId)
    {

        //ticket priority já é o que é suposto
        if ($priorityId == $this->getPriorityId())
            return true;
 
        //nao existe priority com essa priority id
        if ($priorityId && !($priority = Priority::lookup($priorityId)))
            return false;

        //muda os valores da priority no field priority do ticket
        $this->getAnswer('priority')->setValue($priority);
        $this->getAnswer('priority')->save(true);

        //altera tambem na tabela entry value porque pelo field nao altera aqui
        $this->setFormEntryValueId($priorityId);

        return $this->save(true);
    }

    /**
     * Executes a query to change the id of the priority in the table FORM_ANSWER_TABLE.
     * 
     * @param int $newValue id of the new priority.
     */
    function setFormEntryValueId($newValue){
        $sql = "UPDATE ".FORM_ANSWER_TABLE." SET value_id = ".$newValue." 
        WHERE entry_id=(SELECT id FROM ".FORM_ENTRY_TABLE." WHERE object_id = ".$this->getId()." AND form_id=".TICKET_DETAILS_FORM.")";
        db_query($sql);
    }

    /**
     * Changes the status of the ticket to suspend or open depending on the prior status of the ticket.
     * When a ticket is suspended the number of the ticket and the date is stored in a table. 
     * When unsuspended the date is also stored in the same row and the due date of the ticket is recalculated to not count
     * the time when the ticket was suspended.
     * 
     * @param string $status actual status of the ticket before the change
     * @param string $comments comments made when suspending/unsuspending ticket.
     * @param string $errors errors found so far.
     */
    function setSuspend($status, $comments = '', &$errors = array())
    {
        global $cfg, $thisstaff;

        if ($thisstaff && !($role = $this->getRole($thisstaff)))
            return false;

        $hadStatus = $this->getStatusId();
        $ecb = $refer = null;



        //tirar o suspend
        if ($status == TicketStatus::lookup(STATE_SUSPENDED)->getValue()) {



             /* $query_update = 'UPDATE ' . SUSPEND_NEW_TABLE . ' SET date_end_suspension=NOW()'
                . 'WHERE number_ticket=' . $this->getNumber()
                . ' AND id=(SELECT MAX(id) FROM ' . SUSPEND_NEW_TABLE . ' WHERE number_ticket =' . $this->getNumber() . ')'; */
            
            $query_update = 'UPDATE ' . SUSPEND_NEW_TABLE . ' SET date_end_suspension=NOW()'
            . ' WHERE number_ticket=' . $this->getNumber()
            . ' AND date_of_suspension=(SELECT MAX(date_of_suspension) FROM ' . SUSPEND_NEW_TABLE 
            . ' WHERE number_ticket=' . $this->getNumber() . ')';


            if (!db_query($query_update))
                return false;


            /* $query_time = 'SELECT TIMEDIFF(date_end_suspension,date_of_suspension) AS suspension_duration FROM ost_suspended_ticket WHERE number_ticket =' . $this->getNumber()
                . '  AND id=(SELECT MAX(id) FROM ' . SUSPEND_NEW_TABLE . ' WHERE number_ticket =' . $this->getNumber() . ')'; */

            $query_time = 'SELECT TIMEDIFF(date_end_suspension, date_of_suspension) AS suspension_duration 
               FROM ost_suspended_ticket 
               WHERE number_ticket=' . $this->getNumber() . 
              ' AND date_of_suspension=(SELECT MAX(date_of_suspension) 
                                       FROM ' . SUSPEND_NEW_TABLE . 
                                       ' WHERE number_ticket=' . $this->getNumber() . ')';

            $res = db_query($query_time);
            if (!$res)
                return false;




            //fazer a diferença e dps meter os valores

            $initialDatetime = new DateTime($this->getSLADueDate(false));
            

            $finalDatetime = clone $initialDatetime;
            
            $durationToSubtract = db_result($res);

        

            list($hours, $minutes, $seconds) = explode(':', $durationToSubtract);
            $interval = new DateInterval("PT{$hours}H{$minutes}M{$seconds}S");
            
            $finalDatetime->add($interval);

            $finalDatetimeFormatted = $finalDatetime->format('Y-m-d H:i:s');

            // $this->est_duedate = $finalDatetimeFormatted; // ja nao é preciso com o updateestdduedate() em baixo
            $this->duedate = $finalDatetimeFormatted;

            
            //limpar o overdue se exitir
            if ($finalDatetime > $initialDatetime)
                $this->clearOverdue(false);


            $this->status = TicketStatus::lookup(STATE_OPEN);
            $status =  STATE_OPEN;

            $this->updateEstDueDate();
        }

        //meter a suspend
        elseif ($status == TicketStatus::lookup(STATE_OPEN)->getValue()) {

            if ($errors) return false;

            $query = 'INSERT INTO ' . SUSPEND_NEW_TABLE . ' SET '
                . 'number_ticket=' . $this->getNumber()
                . ', date_of_suspension=NOW()';
            $query .= ', date_end_suspension=NULL';

            //definir o due date como null
            /* $this->est_duedate = Null;
            $this->duedate = Null; */

            if (!db_query($query))
                return false;

            $this->status = TicketStatus::lookup(STATE_SUSPENDED);
            $status =  STATE_SUSPENDED;
        }

        $this->lastupdate = SqlFunction::NOW();

        if (!$this->save(true))
            return false;

        // Refer thread to previously assigned or closing agent
        if ($refer && $cfg->autoReferTicketsOnClose())
            $this->getThread()->refer($refer);

        // Log status change b4 reload — if currently has a status. (On new
        // ticket, the ticket is opened and thereafter the status is set to
        // the requested status).
        if ($hadStatus) {
            $alert = false;
            if ($comments = ThreadEntryBody::clean($comments)) {
                // Send out alerts if comments are included
                $alert = true;
                $this->logNote(__('Status Changed'), $comments, $thisstaff, $alert);
            }
        }
        // Log events via callback
        if ($ecb)
            $ecb($this);
        elseif ($hadStatus)
            // Don't log the initial status change
            $this->logEvent('edited', array('status' => $status));

        return true;
    }

    /**
     * Changes the due date of the ticket, so it stops being null.
     * This is used to edit the duedate trhough the API, because it didn't work with the normal way.
     * 
     * @return boolean true if saved succefully, false if not
     */
    function setDueDateToNotNull(){
        $date = new DateTime();
        $date->modify('+1 seconds');
        $this->duedate = $date;
        if (!$this->save(true)){
            return false;
        }
        return true;
    }

    /**
     * Gets all the sources defined in the protected variable $sources.
     * These are the possible sources a ticket can have.
     * 
     * @return array array with the string value of every source.
     */
    static function getSources(){
        return Ticket::$sources;
    }
}

