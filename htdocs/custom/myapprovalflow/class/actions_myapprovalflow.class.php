<?php
/* Copyright (C) 2020 SuperAdmin
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    myapprovalflow/class/actions_myapprovalflow.class.php
 * \ingroup myapprovalflow
 * \brief   Example hook overload.
 *
 * Put detailed description here.
 */

/**
 * Class ActionsMyApprovalFlow
 */
class ActionsMyApprovalFlow
{
    /**
     * @var DoliDB Database handler.
     */
    public $db;

    /**
     * @var string Error code (or message)
     */
    public $error = '';

    /**
     * @var array Errors
     */
    public $errors = array();


    /**
     * @var array Hook results. Propagated to $hookmanager->resArray for later reuse
     */
    public $results = array();

    /**
     * @var string String displayed by executeHook() immediately after return
     */
    public $resprints;


    /**
     * Constructor
     *
     *  @param		DoliDB		$db      Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;
    }


    /**
     * Execute action
     *
     * @param	array			$parameters		Array of parameters
     * @param	CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param	string			$action      	'add', 'update', 'view'
     * @return	int         					<0 if KO,
     *                           				=0 if OK but we want to process standard actions too,
     *                            				>0 if OK and we want to replace standard actions.
     */
    public function getNomUrl($parameters, &$object, &$action)
    {
        global $db,$langs,$conf,$user;
        $this->resprints = '';
        return 0;
    }

    /**
     * when submit the proposal, init all datas for workflow
     */
    protected function _init_workflow(&$object)
    {
        global $conf, $user, $langs;
        $object->extraparams = array(
            "step" => 1,
            "line_stat" => array(
                "total" => 0,   // total lines for current approval step
                "current" => 0, // approved lines for current approval step
            )
        );
        foreach( $object->lines as $line) {
            if ($line->product_ref != NULL) // only predefined line will go through work flow
            {
                dol_include_once('/myapprovalflow/class/poapprover.class.php');
                require_once DOL_DOCUMENT_ROOT .'/user/class/user.class.php';
                $line->special_code = -1; // 1: approve,  -1: disapprove, 0: undefined
                $pref = preg_replace("/\s+/", "", $line->product_ref);
                $approvers = new POApprover($this->db);
                $appuserInfos = $approvers->fetchAll('ASC', 't.rowid', 1, 0, array('t.ref' => $pref));
                $notifyList = array();
                foreach( $appuserInfos as $ai) {
                    $approverName = $ai->app1;
                    $user = new User($this->db);
                    $user->fetchAll('ASC','t.rowid',100, 0, array("customsql" => "concat(t.firstname, ' ', t.lastname) like '$approverName'"));
                    foreach ($user->users as $item) {
                        if ($notifyList[$item->login] == NULL) {
                            $notifyList[$item->login] = array(
                                'email' => $item->email,
                                'lines' => array()
                            );
                        }
                        $notifyList[$item->]
                        var_dump($item->email);
                    }

                }
            }
        }
        $object->updateCommon($user);
    }
    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param   array           $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    public function doActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        //var_dump($this);
        //echo "<script> alert(0);</script>";
        dol_syslog("testdoaction", LOG_DEBUG);
		//print_r($_SERVER);
		//exit;
        $error = 0; // Error counter
        /* print_r($parameters); print_r($object); echo "action: " . $action; */
        $context = explode(':', $parameters['context']);
		if (in_array('ordersuppliercard', $context) && $action == "valid") {

			/**
			* before create proposal, first get accounts for each lines, and send notify to step-1 approvers
			*/
			$this->_init_workflow($object);

			/**
			* get user list example
			*/
			require_once DOL_DOCUMENT_ROOT .'/user/class/user.class.php';
			$usergroup = new User($this->db);
			$usergroup->fetchAll('ASC','t.rowid',  100, 0, array("customsql" => "t.login like 'mustafa'"));
			//var_dump($usergroup->users);
			//echo "<br><br><br>";
			/**
			* send mail example
			*/
			include_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
			$mail = new CMailFile('test', 'postmaster@localhost', 'test@localhost', 'hello', array(), array(), array(), '', '', 0, '', '', '', '', '', 'standard');

			//$result = $mail->check_server_port('localhost', 25);
			//var_dump($result);
			//var_dump($mail->sendfile());
			dol_syslog("postaction", LOG_DEBUG);
        }

        if (! $error) {
            $this->results = array('myreturn' => 999);
            $this->resprints = 'A text to show';
            return 0; // or return 1 to replace standard code
        } else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }
	/**
     * Overloading the formObjectOptions function : replacing the parent's function with the one below
     *
     * @param   array() $parameters Hook metadatas (context, etc...)
     * @param   CommonObject &$object The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string &$action Current action (if set). Generally create or edit or null
     * @param   HookManager $hookmanager Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
	function printObjectLine($parameters, &$object, &$action, $hookmanager)
    {
	    global $conf, $langs, $user;

	    $context = explode(':', $parameters['context']);
		echo "========yellow means that line is for approver==============<br>";
		$object->lines[0]->product_type = "approval-item";
		//$object->approve($user);
		return 0;
	}
    function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
    {
	    global $conf, $langs, $user;

        require_once DOL_DOCUMENT_ROOT .'/compta/facture/class/facture.class.php';
	    $context = explode(':', $parameters['context']);
		if (in_array('ordersuppliercard', $context)) {
			if ($object->statut == Facture::STATUS_VALIDATED) {
				print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=approve">'.$langs->trans("Ok, Approve").'</a>';
				print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=reopen">'.$langs->trans("No, Disapprove").'</a>';
				return 0;
			}
		}
		return 0;
    }


    /**
     * Overloading the doActions function : replacing the parent's function with the one below
     *
     * @param   array           $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    public function doMassActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        $error = 0; // Error counter

		//echo "========doMassActions==============<br>";
        //print_r($parameters); print_r($object); echo "action: " . $action;

        if (in_array($parameters['currentcontext'], array('somecontext1','somecontext2')))		// do something only for the context 'somecontext1' or 'somecontext2'
        {
            foreach($parameters['toselect'] as $objectid)
            {
                // Do action on each object id
            }
        }

        if (! $error) {
            $this->results = array('myreturn' => 999);
            $this->resprints = 'A text to show';
            return 0; // or return 1 to replace standard code
        } else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }


    /**
     * Overloading the addMoreMassActions function : replacing the parent's function with the one below
     *
     * @param   array           $parameters     Hook metadatas (context, etc...)
     * @param   CommonObject    $object         The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
     * @param   string          $action         Current action (if set). Generally create or edit or null
     * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
     * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
     */
    public function addMoreMassActions($parameters, &$object, &$action, $hookmanager)
    {
        global $conf, $user, $langs;

        $error = 0; // Error counter
		//echo "===========addMoreMassActions===============<br>";
		//var_dump($this);
        /* print_r($parameters); print_r($object); echo "action: " . $action; */
        if (in_array($parameters['currentcontext'], array('somecontext1','somecontext2')))		// do something only for the context 'somecontext1' or 'somecontext2'
        {
            $this->resprints = '<option value="0"'.($disabled?' disabled="disabled"':'').'>'.$langs->trans("MyApprovalFlowMassAction").'</option>';
        }

        if (! $error) {
            return 0; // or return 1 to replace standard code
        } else {
            $this->errors[] = 'Error message';
            return -1;
        }
    }



    /**
     * Execute action
     *
     * @param	array	$parameters     Array of parameters
     * @param   Object	$object		   	Object output on PDF
     * @param   string	$action     	'add', 'update', 'view'
     * @return  int 		        	<0 if KO,
     *                          		=0 if OK but we want to process standard actions too,
     *  	                            >0 if OK and we want to replace standard actions.
     */
    public function beforePDFCreation($parameters, &$object, &$action)
    {
        global $conf, $user, $langs;
        global $hookmanager;

        $outputlangs=$langs;

        $ret=0; $deltemp=array();
        dol_syslog(get_class($this).'::executeHooks action='.$action);

        /* print_r($parameters); print_r($object); echo "action: " . $action; */
        if (in_array($parameters['currentcontext'], array('somecontext1','somecontext2')))		// do something only for the context 'somecontext1' or 'somecontext2'
        {
        }

        return $ret;
    }

    /**
     * Execute action
     *
     * @param	array	$parameters     Array of parameters
     * @param   Object	$pdfhandler     PDF builder handler
     * @param   string	$action         'add', 'update', 'view'
     * @return  int 		            <0 if KO,
     *                                  =0 if OK but we want to process standard actions too,
     *                                  >0 if OK and we want to replace standard actions.
     */
    public function afterPDFCreation($parameters, &$pdfhandler, &$action)
    {
        global $conf, $user, $langs;
        global $hookmanager;

        $outputlangs=$langs;

        $ret=0; $deltemp=array();
        dol_syslog(get_class($this).'::executeHooks action='.$action);

        /* print_r($parameters); print_r($object); echo "action: " . $action; */
        if (in_array($parameters['currentcontext'], array('somecontext1','somecontext2')))		// do something only for the context 'somecontext1' or 'somecontext2'
        {
        }

        return $ret;
    }

    /* Add here any other hooked methods... */
}
