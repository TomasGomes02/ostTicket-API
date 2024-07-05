<?php

/**
 * @file
 * TicketAPIControler class extension for the OSTicket API Extension plugin.
 */

include_once 'plugin.config.php';
include_once 'class.api.extension.php';
include_once PRJ_PLUGIN_DIR . 'class.ticket.extension.php';
include_once PRJ_PLUGIN_DIR . 'debugger.php';

/**
 * Class ApiExtension.
 *
 * This class extends the TicketApiController class to use our own endpoints.
 */
class TicketApiControllerExtension extends TicketApiController
{
    //FUNCAO TEMPORARIA DE DEBUG APAGAR DEPOIS
    function debugToFile($erro)
    {
        $file = PRJ_PLUGIN_DIR . "debug.txt";
        $text =  $erro . "\n";
        file_put_contents($file, $text, FILE_APPEND | LOCK_EX);
    }

    /**
     * Verifies if the API key sent as X-API-Key in the HTTP header is valid, exists and is active.
     * Overrides the function with the same name in TicketApiController but removes all IP verifications.
     * 
     * @return object ApiExtension.
     */
    function requireApiKey()
    {
        // Require a valid API key sent as X-API-Key HTTP header
        // see getApiKey method.
        if (!($key = $this->getKey()))
            return $this->exerr(401, __('Valid API key required'));
        elseif (!$key->isActive())
            return $this->exerr(401, __('API key not found/active'));

        return $key;
    }

    /**
     * Gets the object of the API key sent as X-API-Key in the HTTP header is valid, exists and is active.
     * Overrides the function with the same name in TicketApiController but removes all IP verifications.
     * 
     * @return object ApiExtension.
     */
    function getKey()
    {
        if (
            !$this->key
            && ($key = $this->getApiKey())
        )
            $this->key = ApiExtension::lookupByKeyPRJ($key);

        return $this->key;
    }

    /**
     * Function executed when the endpoint/url requestApiKey/tickets is called.
     * 
     * Verifies if the staff specified exists, if the person making the request is an admin,
     * and if the password inserted matches the password of the person making the request.
     * 
     * Adds a new API key and deactivates the old one to the specified staff, 
     * if no staff is specified, the API key is added to the admin making the request.
     * 
     * Makes a response with the new API key.
     * If there are errors response has code 500 and specified error.
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function requestApiKey($format)
    {
        //trata a informação do json
        $data = $this->getRequest($format);

        //se nao existir $data['staff'] entao o staff é igual ao admin
        $data['staff'] ?
            ($staff = Staff::lookup($data['staff'])) && ($admin = Staff::lookup($data['admin'])) : ($staff = $admin = Staff::lookup($data['admin']));

        //validacao do admin (password corresponde ao username e se é admin) e se existe o staff que vai receber a nova api key
        if (!$staff || !$admin->isAdmin() || !$admin->check_passwd($data['adminPassword'])) {
            return false;
        }

        //id do staff é passado para a variavel $data (no json pode ser passado o nome ou no email do user, não o id)
        $data['idStaff'] = $staff->getId();

        //adiciona uma api key nova ao staff definido e retorna o id da api key se funcionar corretamente
        $id = ApiExtension::add($data, $errors);
        //cria um objeto key com o id recebido
        $key = ApiExtension::lookup($id);

        //se não existir o objeto key é porque algum erro aconteceu e não foi possivel criar a key nova, se existir respoinde com a api key nova
        if ($key)
            $this->response(201, _S('New key ') . $key->getKey() . _S(' added to ') . $staff->getName());
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url getApiKey/tickets is called.
     * 
     * Gets the API key of the user sent in the json.
     * 
     * Authenticates the user to make sure it is really him making the request
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function getUserApiKey($format)
    {
        $errors = '';

        //trata a informação do json
        $data = $this->getRequest($format);

        $staff = Staff::lookup($data['staff']);
        //validacao do staff
        if (!$staff || !$staff->check_passwd($data['password'])) {
            $errors = _S("Staff does not exist or password is incorrect");
        } else {
            //id do staff é passado para a variavel $data (no json pode ser passado o nome ou no email do user, não o id)
            $staffId = $staff->getId();

            //adiciona uma api key nova ao staff definido e retorna o id da api key se funcionar corretamente
            if (!($id = ApiExtension::getKeyIdForUser($staffId))) {
                $errors = $staff->getName() . _S(" has no key");
            }

            $key = ApiExtension::lookup($id);
        }

        //se não existir o objeto key é porque algum erro aconteceu e não foi possivel criar a key nova, se existir respoinde com a api key nova
        if ($key)
            $this->response(201, $staff->getName() . _S('\'s key is: ') . $key->getKey());
        else
            $this->exerr(500, $errors);
    }

    /**
     * Function executed when the endpoint/url create/tickets is called.
     * Overrides the function with the same name in TicketApiController to use our own API keys.
     * 
     * Verifies if the key is valid and has permission to create tickets.
     * 
     * Creates a new ticket with the values inserted.
     * 
     * Makes a response with the created ticket number.
     * If there are errors response has code 500 and specified error.
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function create($format)
    {
        if (!($key = $this->requireApiKey()) || !$key->canCreateTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;

        $data = $this->getRequest($format);

        if(!$data['topicId']){
            return $this->exerr(500, _S("To create a new ticket you need to specify a topic id, in field 'topicId'"));
        }

        $data['name'] = Staff::lookup($key->getStaffId())->username;
        $data['email'] = Staff::lookup($key->getStaffId())->getEmail();
        $ticket = $this->createTicket($data);
        
        if ($ticket)
            $this->response(201, _S("Ticket ") . $ticket->getNumber() . _S(" Created"));
        else
            $this->exerr(500, _S("Fail to create Ticket"));
    }


    /**
     * Function executed when the endpoint/url close/tickets is called.
     * 
     * Verifies if the key is valid and has permission to close tickets.
     * 
     * Closes the specified ticket.
     * 
     * Makes a response with the closed ticket number.
     * If there are errors response has code 500 and specified error.
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function close($format)
    {

        if (!($key = $this->requireApiKey()) || !$key->canCloseTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;
        $ticket = $this->closeTicket($this->getRequest($format), $key);

        if ($ticket)
            $this->response(201, "Ticket " . $ticket->getNumber() . " Closed");
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Changes the status of a ticket to closed, with the specified comments.
     * 
     * @param array $data Array with the values from the Json sent in the HTTP body.
     * @param object $key ApiExtension, the key used to call this endpoint.
     * @param string $source = 'API'.
     * 
     * @return mixed (Ticket or boolean) the ticket that was closed, if ticket was not closed returns false.
     */
    function closeTicket($data, $key, $source = 'API') //source nao esta a fazer nada ja nao me lembro porque
    {
        //variavel global que indica o staff que esta a fazer o pedido da api
        global $thisstaff;

        //cria objetos baseados na informação passada no json
        $number = $data['ticketNumber'];
        $ticket = Ticket::lookup(array('number' => $number));
        $staff = Staff::lookup($key->getStaffId());

        $comments = $data['comments'];

        //vai buscar o id 3 ->fechar atraves do STATE_CLOSE, ver api.config.php
        $status = TicketStatus::lookup(STATE_CLOSE);

        $thisstaff = $staff;
        //altera o status do ticket
        if ($ticket->setStatus(status: $status, comments: $comments)) {
            return $ticket;
        }
        //adicionar tabela para saber a source que fechou talvez

        return false;
    }

    /**
     * Function executed when the endpoint/url reopen/tickets is called.
     * 
     * Verifies if the key is valid and has permission to reopen tickets.
     * 
     * Reopens the specified ticket.
     * 
     * Makes a response with the reopened ticket number.
     * If there are errors response has code 500 and specified error.
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function reopen($format)
    {
        if (!($key = $this->requireApiKey()) || !$key->canReopenTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;
        $ticket = $this->reopenTicket($this->getRequest($format), $key);

        if ($ticket)
            $this->response(201, "Ticket " . $ticket->getNumber() . " Reopened");
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Changes the status of a ticket to open, with the specified comments.
     * 
     * @param array $data Array with the values from the Json sent in the HTTP body.
     * @param object $key ApiExtension, the key used to call this endpoint.
     * @param string $source = 'API'.
     * 
     * @return mixed (Ticket or boolean) the ticket that was reopened, if ticket was not reopened returns false.
     */
    function reopenTicket($data, $key, $source = 'API')
    {
        //variavel global que indica o staff que esta a fazer o pedido da api
        global $thisstaff;

        //cria objetos baseados na informação passada no json
        $number = $data['ticketNumber'];
        $ticket = Ticket::lookup(array('number' => $number));
        $staff = Staff::lookup($key->getStaffId());

        $comments = $data['comments'];

        //vai buscar o id 3 ->fechar atraves do STATE_CLOSE, ver api.config.php
        $status = TicketStatus::lookup(STATE_OPEN);

        $thisstaff = $staff;
        //altera o status do ticket
        if ($ticket->setStatus(status: $status, comments: $comments)) {
            return $ticket;
        }
        //adicionar tabela para saber a source que fechou talvez

        return false;
    }

    /**
     * Function executed when the endpoint/url edit/tickets is called.
     * 
     * Verifies if the key is valid and has permission to edit tickets.
     * 
     * Edits the specified ticket.
     * 
     * Makes a response with the edited ticket number.
     * If there are errors response has code 500 and specified error.
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function edit($format)
    {
        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;

        $msg = '';
        $ticket = $this->editTicket($this->getRequest($format), $key, $msg);

        if ($ticket)
            $this->response(201, $msg);
        else
            $this->exerr(500, _S($msg));
    }

    /**
     * Edits the specified fields of a ticket, with the specified comments.
     * Adds ONLY ONE comment per API call.
     * 
     * @param array $data Array with the values from the Json sent in the HTTP body.
     * @param object $key ApiExtension, the key used to call this endpoint.
     * @param string $source = 'API'.
     * 
     * @return mixed (Ticket or boolean) the ticket that was edited, if ticket was not edited returns false.
     */
    function editTicket($data, $key, &$msg)
    {
        $number = $data['ticketNumber'];
        if (!($ticket = TicketExtension::lookup(array('number' => $number)))) {
            $msg = 'Ticket ' . $number . ' does not exist \n';
            return false;
        }

        $comments = $data['comments'];

        global $thisstaff, $cfg;
        $thisstaff = Staff::lookup($key->getStaffId());
        $thisstaffuser = $thisstaff->getUserName();

        //fields alterados
        $fields = array();
        //assignees
        $staffAssignee = null;
        $teamAssignee = null;

        //staff
        // if (!$data['staff'] && $data['staff'] != null && $ticket->getStaffId() != 0) {
        //     if ($ticket->setStaffId(0)) {
        //         $msg = $msg . 'Staff unassign successfully \n';
        //         $ticket->logEvent('assigned', array('staff' => 'unassign'), user: $thisstaffuser);
        //     } else {
        //         $msg = $msg . 'Unable to unassign staff \n';
        //     }
        // }
        if ($data['staff']) {
            if ($staff = Staff::lookup($data['staff'])) {
                if ($ticket->getStaffId() != $staff->getId()) {
                    if($oldStaff = $ticket->getStaff()){
                        $oldStaff = $oldStaff->getName();
                    }else{
                        $oldStaff = 'No Staff';
                    }
                

                    $ticket->assignToStaff($staff, '', user: $thisstaffuser);
                    $staffAssignee = $staff;
                    $fields[] = 'staff';

                    $msg .= 'Staff Updated. ' . $oldStaff . ' changed to ' . $staff->getName() . "\n";
                } else {
                    $msg .= 'Failed to Update Staff. ' . $staff->getName() . " is already assigned to ticket\n";
                }
            } else {
                $msg .= 'Failed to Update Staff. ' . $data['staff'] . " does not exist\n";
            }
        }
        //staff

        //team
        // if (!$data['team'] && $data['team'] != null && $ticket->getTeamId() != 0) {
        //     $ticket->setTeamId(0); //usar release() maybe
        //     $ticket->logEvent('assigned', array('team' => 'unassign'), user: $thisstaffuser);
        // }
        if ($data['team']) {
            if ($team = Team::lookup($data['team'])) {
                if ($ticket->getTeamId() != $team->getId()) {
                    if($oldTeam = $ticket->getStaff()){
                        $oldTeam = $oldTeam->getName();
                    }else{
                        $oldTeam = 'No Staff';
                    }

                    $ticket->assignToTeam($team, '', user: $thisstaffuser);
                    $teamAssignee = $team;
                    $fields[] = 'team';
 
                    $msg .= 'Team Updated. ' . $oldTeam . ' changed to ' . $team->getName() . "\n";
                } else {
                    $msg .= 'Failed to Update Team. ' . $team->getName() . " is already assigned to ticket\n";
                }
            } else {
                $msg .= 'Failed to Update Team. ' . $data['team'] . " does not exist\n";
            }
        }

        //team

        //user
        if ($data['user']) {
            if ($user = User::lookup($data['user'])) {
                if ($data['user'] != $ticket->getUserId()) {
                    if($oldUser = $ticket->getUser()){
                        $oldUser = $oldUser->getName();
                    }else{
                        $oldUser = 'No User';
                    }
                    $ticket->changeOwner($user);
                    $msg .= 'User Updated. ' . $oldUser . ' changed to ' . $user->getName() . "\n";
                } else {
                    $msg .= 'Failed to Update User. ' . $user->getName() . " is already assigned to ticket\n";
                }
            } else {
                $msg .= 'Failed to Update User. ' . $data['user'] . " does not exist\n";
            }
        }
        //user

        // //source
        // //VERIFICA SE A SOURCE INSERIDA NO JSON É POSSIVEL enum('Web', 'Email', 'Phone', 'API', 'Other')
        if ($data['source']) {
            if (in_array($data['source'], $ticket->getSources())) {
                if ($data['source'] != $ticket->getSource()) {
                    $oldSource = $ticket->getSource();
                    $this->simulatePost($ticket, 'source', $data);
                    $fields[] = 'source';
                    $msg .= 'Source Updated. ' . $oldSource . ' changed to ' . $data['source'] . "\n";
                } else {
                    $msg .= 'Failed to Update Source. ' . $data['source'] . " is already assigned to ticket\n";
                }
            } else {
                $msg .= 'Failed to Update Source. ' . $data['source'] . " is not a valid source\n";
            }
        }
        // //source

        // //topic
        if ($data['topic']) {
            if ($topic = Topic::lookup($data['topic'])) {
                if ($data['topic'] != $ticket->getTopicId()) {
                    $oldTopic = $ticket->getTopic()->getName();
                    $this->simulatePost($ticket, 'topic', $data);
                    $fields[] = 'topic';
                    $msg .= 'Topic Updated. ' . $oldTopic . ' changed to ' . $topic->getName() . "\n";
                } else {
                    $msg .= 'Failed to Update Topic. ' . $topic->getName() . " is already assigned to ticket\n";
                }
            } else {
                $msg .= 'Failed to Update Topic. ' . $data['topic'] . " does not exist\n";
            }
        }
        // //topic

        // //sla
        if ($data['sla']) {
            if ($sla = SLA::lookup($data['sla'])) {
                if ($data['sla'] != $ticket->getSLAId()) {
                    if($oldSLA = $ticket->getSLA()){
                        $oldSLA = $oldSLA->getName();
                    }else{
                        $oldSLA = 'No SLA';
                    }
                    $this->simulatePost($ticket, 'sla', $data);
                    $fields[] = 'sla';
                    $msg .= 'SLA Updated. ' . $oldSLA . ' changed to ' . $sla->getName() . "\n";
                } else {
                    $msg .= 'Failed to Update SLA. ' . $sla->getName() . " is already assigned to ticket\n";
                }
            } else {
                $msg .= 'Failed to Update SLA. ' . $data['sla'] . " does not exist\n";
            }
        }
        // //sla

        // //dept
        if ($data['dept']) {
            if ($dept = Dept::lookup($data['dept'])) {
                if ($data['dept'] != $ticket->getDeptId()) {
                    if($oldDept = $ticket->getDept()){
                        $oldDept = $oldDept->getName();
                    }else{
                        $oldDept = 'No Department';
                    }
                    $ticket->editFields('dept', $data['dept'], $data['refer']);
                    $fields[] = 'dept';
                    $msg .= 'Dept Updated. ' . $oldDept . ' changed to ' . $dept->getName() . "\n";
                } else {
                    $msg .= 'Failed to Update Dept. ' . $dept->getName() . " is already assigned to ticket\n";
                }
            } else {
                $msg .= 'Failed to Update Dept. ' . $data['dept'] . " does not exist\n";
            }
        }
        // //dept

        // //priority
        if ($data['priority']) {
            if ($priority = Priority::lookup($data['priority'])) {
                if ($data['priority'] != $ticket->getPriorityId()) {
                    if($oldPriority = $ticket->getPriority()){
                        $oldPriority = $oldPriority->getDesc();
                    }else{
                        $oldPriority = 'No Priority';
                    }
                    $ticket->editFields('priority', $data['priority'], '');
                    $fields[] = 'priority';
                    $msg .= 'Priority Updated. ' . $oldPriority . ' changed to ' . $priority->getDesc() . "\n";
                } else {
                    $msg .= 'Failed to Update Priority. ' . $priority->getDesc() . " is already assigned to ticket\n";
                }
            } else {
                $msg .= 'Failed to Update Priority. ' . $data['priority'] . " does not exist\n";
            }
        }
        // //priority

        // //duedate
        if ($data['duedate']) {
            if ($this->isValidDateTimeFormat($data['duedate'])) {
                if ($this->isDatePermitted($data['duedate'])) {
                    if ($data['duedate'] != $ticket->getDueDate()) {
                        if (!($oldDueDate = $ticket->getDueDate())) {
                            $oldDueDate = 'Empty Due Date';
                            $ticket->setDueDateToNotNull();
                        }
                        $this->simulatePost($ticket, 'duedate', $data);
                        $fields[] = 'duedate';
                        $msg .= 'Due Date Updated. ' . $oldDueDate . ' changed to ' . $data['duedate'] . "\n";
                    } else {
                        $msg .= 'Failed to Update Due Date. ' . $data['duedate'] . " is already assigned to ticket\n";
                    }
                } else {
                    $msg .= 'Failed to Update Due Date. ' . $data['duedate'] . " is earlier than current date\n";
                }
            } else {
                $msg .= 'Failed to Update Due Date. ' . $data['duedate'] . " is not a valid date, date should have format 'YYYY-mm-dd HH:ii:ss'\n";
            }
        }
        // //duedate

        //Adiciona SÓ UM comentario para todas as alteracoes
        //para se ter comentarios separados tem de se alterar os valores um de cada vez
        if (!$comments || !empty($fields))
            $notes = $ticket->addComments($comments, $fields, $staffAssignee, $teamAssignee);

        //alerta do departamento (se for alterado), tem de estar no fim porque usa os comentarios (notes)
        $alert = $data['alert'];
        if (in_array('dept', $fields) && !$alert || !$cfg->alertONTransfer() || !$ticket->getDept()->getNumMembersForAlerts()) {
            $ticket->alerts($notes);
        }

        $msg .= empty($fields) ? "Ticket " . $number . " Failed to Update\n" : "Ticket " . $number . " Updated\n";

        //se nenhum valor foi alterado return false
        return !empty($fields) ? $ticket : false;
    }

    /**
     * Simulates the POST sent in the OSTicket interface when editing a field.
     * 
     * @param object $ticket Ticket, ticket of the field being edited.
     * @param string $fieldString A string with the name of the field being edited.
     * @param array $data The new values of the fields sent in the body of the HTTP.
     */
    function simulatePost($ticket, $fieldString, $data)
    {
        $field = $ticket->getField($fieldString);
        $post = array("", "", "");
        $field->setValue($data[$fieldString]);
        $form = $field->getEditForm($post);
        if ($form->isValid()) {
            if($ticket->updateField($form, $errors)){
                return true;
            }
        }
        return false;
    }

    /**
     * Validates if the date sent in the HTTP body is valid.
     * 
     * @param string $dateTimeString Date sent in the HTTP body.
     * @param string $format Correct format the date sent should have.
     * 
     * @return boolean true if the date sent is in the correct format, false if not
     */
    function isValidDateTimeFormat($dateTimeString, $format = 'Y-m-d H:i:s')
    {
        $pattern = '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/';
        if (!preg_match($pattern, $dateTimeString)) {
            return false;
        }
        $dateTimeObj = DateTime::createFromFormat($format, $dateTimeString);
        return $dateTimeObj && $dateTimeObj->format($format) == $dateTimeString;
    }

    /**
     * Validates if the date sent in the HTTP body is earlier then permitted, permitted date is current date.
     * 
     * @param string $dateTime Date sent in the HTTP body.
     * 
     * @return boolean true if the date sent is permitted, false if not
     */
    function isDatePermitted($dateString) {
        $receivedDate = new DateTime($dateString);
        $currentDate = new DateTime();

        return $receivedDate >= $currentDate;
    }

    /**
     * Function executed when the endpoint/url suspend/tickets is called.
     * 
     * Verifies if the key is valid and has permission to suspend tickets.
     * 
     * Suspends the specified ticket.
     * 
     * Makes a response with the suspended ticket number.
     * If there are errors response has code 500 and specified error.
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function suspend($format)
    {
        if (!($key = $this->requireApiKey()) || !$key->canSuspendTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;

        $msg = '';

        $ticket = $this->suspendTicket($this->getRequest($format), $key, TicketStatus::lookup(STATE_OPEN)->getValue() 
, $msg);

        if ($ticket)
            $this->response(201, $msg);
        else
            $this->exerr(500, _S($msg));
    }

    /**
     * Function executed when the endpoint/url unSuspend/tickets is called.
     * 
     * Verifies if the key is valid and has permission to suspend tickets.
     * 
     * Unsuspends the specified ticket.
     * 
     * Makes a response with the unsuspended ticket number.
     * If there are errors response has code 500 and specified error.
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function unSuspend($format)
    {
        if (!($key = $this->requireApiKey()) || !$key->canSuspendTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;

        $msg = '';
        
        $ticket = $this->suspendTicket($this->getRequest($format), $key, TicketStatus::lookup(STATE_SUSPENDED)->getValue(), $msg);

        if ($ticket)
            $this->response(201, $msg);
        else
            $this->exerr(500, _S($msg));
    }

    /**
     * Changes the status of a ticket to suspend or open, depending on the previous status, with the specified comments.
     * 
     * @param array $data Array with the values from the Json sent in the HTTP body.
     * @param object $key ApiExtension, the key used to call this endpoint.
     * @param string $source = 'API'.
     * 
     * @return mixed (Ticket or boolean) the ticket that was suspended/unsuspended, if ticket was not suspended/unsuspended returns false.
     */
    function suspendTicket($data, $key, $status, &$msg)
    {
        $number = $data['ticketNumber'];
        $ticket = TicketExtension::lookup(array('number' => $number));
        $staff = Staff::lookup($key->getStaffId());

        $comments = $data['comments'];

        global $thisstaff;
        $thisstaff = $staff;

        if ($status == TicketStatus::lookup(STATE_OPEN)->getValue()) {
            if($ticket->getStatusId() == STATE_SUSPENDED){
                $msg = 'Ticket ' . $number . ' is already Suspended';
                return $msg;
            }
            elseif ($ticket->setSuspend($status, comments: $comments)) {
                $msg = "Ticket " . $number . " Suspended";
                return $msg;
            }
            else{
                $msg = "Ticket " . $number . " Failed to Suspend";
            }
        } elseif(TicketStatus::lookup(STATE_SUSPENDED)->getValue()) {
            if($ticket->getStatusId() == STATE_OPEN){
                $msg = 'Ticket ' . $number . ' is already Unsuspended';
                return $msg;
            }
            elseif ($ticket->setSuspend($status, comments: $comments)) {
                $msg = "Ticket " . $number . " Unsuspended";
                return $msg;
            }
            else{
                $msg = "Ticket " . $number . " Failed to Unsuspend";
            }
        }

        return $msg;
    }

    /**
     * Function executed when the endpoint/url delete/tickets is called.
     * 
     * Verifies if the key is valid and has permission to close tickets.
     * 
     * Deletes the specified ticket.
     * 
     * Makes a response with the deleted ticket number.
     * If there are errors response has code 500 and specified error.
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function delete($format)
    {

        if (!($key = $this->requireApiKey()) || !$key->canCloseTickets())
            return $this->exerr(401, __('API key not authorized'));

        $ticket = null;
        $ticket = $this->deleteTicket($this->getRequest($format), $key);

        if ($ticket)
            $this->response(201, "Ticket " . $ticket->getNumber() . " Deleted");
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Deletes the ticket, with the specified comments.
     * 
     * @param array $data Array with the values from the Json sent in the HTTP body.
     * @param object $key ApiExtension, the key used to call this endpoint.
     * @param string $source = 'API'.
     * 
     * @return mixed (Ticket or boolean) the ticket that was closed, if ticket was not closed returns false.
     */
    function deleteTicket($data, $key) //source nao esta a fazer nada ja nao me lembro porque
    {
        //variavel global que indica o staff que esta a fazer o pedido da api
        global $thisstaff;

        //cria objetos baseados na informação passada no json
        $number = $data['ticketNumber'];
        $ticket = Ticket::lookup(array('number' => $number));
        $staff = Staff::lookup($key->getStaffId());

        $comments = $data['comments'];

        $thisstaff = $staff;
        //apaga o ticket
        if ($ticket->delete($comments)) {
            return $ticket;
        }

        return false;
    }

    /**
     * Function executed when the endpoint/url create/staff is called.
     * 
     * Verifies if the key is valid and the user making the request is an admin.
     * 
     * Creates a new staff.
     * 
     * Makes a response with the staff created info.
     * If there are errors response has code 500 and specified error.
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function createStaff($format)
    {

        if (!($key = $this->requireApiKey()) || !Staff::lookup($key->getStaffId())->isAdmin())
            return $this->exerr(401, __('API key not authorized'));

        $newstaff = null;
        $newstaff = $this->createStaffMain($this->getRequest($format), $msg);

        if ($newstaff)
            $this->response(201, _S($msg));
        else
            $this->exerr(500, _S($msg));
    }

    /**
     * Creates a new agent.
     * 
     * @param array $data Array with the values from the Json sent in the HTTP body.
     * @param object $msg API's response message.
     * 
     * @return mixed (Staff or boolean) staff created, if staff was not created returns false.
     */
    function createStaffMain($data, &$msg){
        if (!isset($data['email']) || !isset($data['username']) || !isset($data['passwd']) || !isset($data['firstname'])
         || !isset($data['lastname']) || !isset($data['dept_id']) || !isset($data['role_id'])){
            $msg = 'The following fields are required: `username`, `email`, `passwd`, `firstname`, `lastname`, `dept_id` and `role_id`';
            return false;
        }

        if (Staff::getIdByUsername($data['username'])) {
            $msg = 'Username already in use by another agent';
            return false;
        }
        if(Staff::getIdByEmail($data['email'])){
            $msg = 'Email already in use by another agent';
            return false;
        }
        if(Email::getIdByEmail($data['email']))
        {
            $msg = 'Already in use system email';
            return false;
        }

        $data['isadmin'] = isset($data['isadmin']) ? 1 : 0;
        $data['isvisible'] = isset($data['isvisible']) ? 1 : 0;
        $data['onvacation'] = isset($data['onvacation']) ? 1 : 0;
        $data['assigned_only'] = isset($data['assigned_only']) ? 1 : 0;

        //$data['backend'] = "local"; #Perguntar se cria com autenticação local e enciar mail para criar password (como na iknterface), ou meter diretamente password

        $agent = Staff::create($data);
        $agent->updatePerms($data['perms'], $msg);
        $agent->setPassword($data['passwd'], null);

        if($agent->save()){
            $agent->setExtraAttr('def_assn_role', isset($vars['assign_use_pri_role']), true);

            if($data['access']){
                $agent->updateAccess($data['access'], $errors);
            }
            if($data['membership']){
                $agent->updateTeams($data['membership'], $errors);
            }

            //$agent->sendResetEmail(); #Perguntar se cria com autenticação local e enciar mail para criar password (como na iknterface), ou meter diretamente password

            $msg = 'Staff ' . $agent->getName() . ' created';
            return $agent;
        }

        $msg = 'Failed to create staff';
        return false;
    }

    /**
     * Function executed when the endpoint/url update/staff is called.
     * 
     * Verifies if the key is valid and the user making the request is an admin.
     * 
     * Creates a new staff.
     * 
     * Makes a response with the staff updated info.
     * If there are errors response has code 500 and specified error.
     * 
     * @param object $format Json sent in the HTTP body.
     */
    function updateStaff($format)
    {
        if (!($key = $this->requireApiKey()) || !Staff::lookup($key->getStaffId())->isAdmin())
            return $this->exerr(401, __('API key not authorized'));

        $newstaff = null;
        $newstaff = $this->updateStaffMain($this->getRequest($format), $msg);

        if ($newstaff)
            $this->response(201, _S($msg));
        else
            $this->exerr(500, _S($msg));
    }

    /**
     * Updates an agent.
     * 
     * @param array $data Array with the values from the Json sent in the HTTP body.
     * @param object $msg API's response message.
     * 
     * @return mixed (Staff or boolean) staff created, if staff was not created returns false.
     */
    function updateStaffMain($data, &$msg){
        
        if (!isset($data['id'])){
            $msg = 'The field `id` is required';
            return false;
        }
        elseif(!($agent = Staff::lookup($data['id']))){
            $msg = 'Staff does not exist';
            return false;
        }

        $data['email'] = $data['email'] ?? $agent->getEmail();
        $data['username'] = $data['username'] ?? $agent->getUsername();
        $data['firstname'] = $data['firstname'] ?? $agent->getFirstName();
        $data['lastname'] = $data['lastname'] ?? $agent->getLastName();
        $data['dept_id'] = $data['dept_id'] ?? $agent->getDeptId();
        $data['role_id'] = $data['role_id'] ?? $agent->ht['role_id'];
        $data['phone'] = $data['phone'] ?? $agent->ht['phone'];
        $data['phone_ext'] = $data['phone_ext'] ?? $agent->ht['phone_ext'];
        $data['mobile'] = $data['mobile'] ?? $agent->ht['mobile'];
        $data['signature'] = $data['signature'] ?? $agent->ht['signature'];
        $data['timezone'] = $data['timezone'] ?? $agent->ht['timezone'];
        $data['locale'] = $data['locale'] ?? $agent->ht['locale'];
        $data['max_page_size'] = $data['max_page_size'] ?? $agent->ht['max_page_size'];
        $data['auto_refresh_rate'] = $data['auto_refresh_rate'] ?? $agent->ht['auto_refresh_rate'];
        $data['default_signature_type'] = $data['default_signature_type'] ?? $agent->ht['default_signature_type'];
        $data['default_paper_size'] = $data['default_paper_size'] ?? $agent->ht['default_paper_size'];
        $data['lang'] = $data['lang'] ?? $agent->ht['lang'];
        $data['onvacation'] = $data['onvacation'] ?? $agent->ht['onvacation'];
        $data['backend'] = $data['backend'] ?? $agent->ht['backend'];
        $data['assigned_only'] = $data['assigned_only'] ?? $agent->ht['assigned_only'];
        $data['onvacation'] = $data['onvacation'] ?? $agent->ht['onvacation'];
        $data['isadmin'] = $data['isadmin'] ?? $agent->ht['isadmin'];
        $data['isactive'] = $data['isactive'] ?? $agent->ht['isactive'];
        $data['isvisible'] = $data['isvisible'] ?? $agent->ht['isvisible'];
        $data['perms'] = $data['perms'] ?? $agent->ht['perms'];

        if($agent->update($data, $msg)){
            $msg = 'Staff ' . $agent->getName() . ' updated';
            return true;
        }
        return false;
    }

    /**
     * Function executed when the endpoint/url departments is called.
     * 
     * Gets all departments.
     * 
     * Makes a response with all departments.
     * If there are errors response has code 500 and specified error.
     */
    function showDeps()
    {

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $res = ApiExtension::getDeps();

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url slas is called.
     * 
     * Gets all slas.
     * 
     * Makes a response with all slas.
     * If there are errors response has code 500 and specified error.
     */
    function showSLAs()
    {

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $res = ApiExtension::getSLAS();

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url teams is called.
     * 
     * Gets all teams.
     * 
     * Makes a response with all teams.
     * If there are errors response has code 500 and specified error.
     */
    function showTeams()
    {

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $res = ApiExtension::getTeams();

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url staff is called.
     * 
     * Gets all staff.
     * 
     * Makes a response with all staff.
     * If there are errors response has code 500 and specified error.
     */
    function showStaff()
    {

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $res = ApiExtension::getStaff();

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url users is called.
     * 
     * Gets all users.
     * 
     * Makes a response with all users.
     * If there are errors response has code 500 and specified error.
     */
    function showUsers()
    {

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $res = ApiExtension::getUsers();

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url priorities is called.
     * 
     * Gets all priorities.
     * 
     * Makes a response with all priorities.
     * If there are errors response has code 500 and specified error.
     */
    function showPriority()
    {

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $res = ApiExtension::getPiority();

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url topics is called.
     * 
     * Gets all topics.
     * 
     * Makes a response with all topics.
     * If there are errors response has code 500 and specified error.
     */
    function showTopic()
    {

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $res = ApiExtension::getTopic();

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url sources is called.
     * 
     * Gets all sources.
     * 
     * Makes a response with all sources.
     * If there are errors response has code 500 and specified error.
     */
    function showSources()
    {

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $res = ApiExtension::getSources();

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
    }

    /**
     * Function executed when the endpoint/url ticketsList is called.
     * 
     * Gets all tickets.
     * 
     * Makes a response with all tickets.
     * If there are errors response has code 500 and specified error.
     */
    function showTickets()
    {

        if (!($key = $this->requireApiKey()) || !$key->canEditTickets())
            return $this->exerr(401, __('API key not authorized'));

        $staff = Staff::lookup($key->getStaffId());
        $deptId = $staff->getDeptId();
        // $deptIds = $staff->getDepts();
        $isAdmin = $staff->isAdmin();

        $res = ApiExtension::getTickets($deptId, $isAdmin);

        if ($res)
            $this->response(201, $res);
        else
            $this->exerr(500, _S("unknown error"));
    }
}
