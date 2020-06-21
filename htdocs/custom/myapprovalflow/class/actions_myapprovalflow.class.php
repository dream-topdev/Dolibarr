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

dol_include_once('/myapprovalflow/class/poapprover.class.php');
require_once DOL_DOCUMENT_ROOT .'/user/class/user.class.php';

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
     * get User Object
     */
    protected function _getUserFromAcct($apprName)
    {
        $user = new User($this->db);
        $user->fetchAll('ASC','t.rowid', 1, 0, array("customsql" => "concat(t.firstname, ' ', t.lastname) like '$apprName'"));
        if ($user->users == NULL)
            return NULL; // No Approver
        
        foreach ($user->users as $item) {
            return $item;                        
        }
    }

    /**
     * add email info to notify list
     */
    protected function _addEmailToNotify(&$notify, $apprName, $line)
    {
        $user = $this->_getUserFromAcct($apprName);
        if($user == NULL)
            return -1; // No Approver;

        if ($notify[$user->login] == NULL) {
            $notify[$user->login] = array(
                'email' => $user->email,
                'lines' => array()
            );
        }
        $notify[$user->login]['lines'][] = $line->product_ref;                        
        return 1; // Add successful
    }

    /**
     * get POApprover Object
     */
    protected function _getPOApprover($line)
    {
        $pref = preg_replace("/\s+/", "", $line->product_ref);
        $approvers = new POApprover($this->db);
        $appuserInfos = $approvers->fetchAll('ASC', 't.rowid', 1, 0, array('t.ref' => $pref));

        foreach( $appuserInfos as $item) {
            return $item;                    
        }
        return NULL;
    }

    /**
     * when submit the proposal, init all datas for workflow
     */
    protected function _initWorkflow(&$object)
    {
        global $conf, $user, $langs;
        $extraparams = array(
            "step" => 1,
            "line_stat" => array(
                "total" => 0,   // total lines for current approval step
                "current" => 0, // approved lines for current approval step
            )
        );
        $notifyList = array();        
        foreach( $object->lines as $line) {
            if ($line->product_ref != NULL) // only predefined line will go through work flow
            {
                $line->special_code = -1; // 1: approve, 0: disapprove,  -1: undefined
                $approverObj = $this->_getPOApprover($line);
                if ($approverObj != NULL) {
                    $this->_addEmailToNotify($notifyList, $approverObj->app1, $line);
                    if ($approverObj->orapp1) // if alternative approver exists?
                        $this->_addEmailToNotify($notifyList, $approverObj->orapp1, $line);
                }
                $extraparams['line_stat']['total'] ++;
                $line->update();
            }
        }

        var_dump($notifyList);
        $object->extraparams = $extraparams;
        $object->setExtraParameters();
    }

    /**
     * check for approve/reject permsion for user
     */
    protected function _checkApprovePermission(&$object)
    {
        global $conf, $user, $langs;
        foreach( $object->lines as $line) {
            if ($this->_isMyApproveTarget($object, $line))
                return true;            
        }
        return false;
    }

     /**
     * check whether line is for user
     */
    protected function _isMyApproveTarget($object, $line)
    {
        global $conf, $user, $langs;

        if ($line->product_ref != NULL) // only predefined line will go through work flow
        {
            if ($line->special_code == -1)                    
            {
                $approverObj = $this->_getPOApprover($line);
                switch ($object->extraparams['step'])
                {
                    case 1:
                        $appUser = $this->_getUserFromAcct($approverObj->app1);
                        if ($appUser && $appUser->login == $user->login)
                            return true;
                        $appUser = $this->_getUserFromAcct($approverObj->orapp1);
                        if ($appUser && $appUser->login == $user->login)
                            return true;
                        break;
                    case 2;
                        $appUser = $this->_getUserFromAcct($approverObj->app2);
                        if ($appUser && $appUser->login == $user->login)
                            return true;
                        break;
                    case 3:
                        $appUser = $this->_getUserFromAcct($approverObj->app3);
                        if ($appUser && $appUser->login == $user->login)
                            return true;
                        break;
                    case 4:                            
                        $appUser = $this->_getUserFromAcct($approverObj->app4);
                        if ($appUser && $appUser->login == $user->login)
                            return true;
                        break;
                    default:
                        break;
                }
            }
        }
        return false;
    }

    /**
     * approve user's line
     */
    protected function _approveLinesForUser(&$object)
    {
        global $conf, $user, $langs;

        foreach( $object->lines as $line) {
            if ($this->_isMyApproveTarget($object, $line))
            {
                $line->special_code = 1; //approve
                $object->extraparams['line_stat']['current'] ++;
                $line->update();
            }
        }
        if ($object->extraparams['line_stat']['current'] == $object->extraparams['line_stat']['total'])
        {
            // if all lines in step are approved, then go to next step, or make validate
            $object->extraparams['step'] ++;
            $object->extraparams['line_stat']['total'] = 0;
            $object->extraparams['line_stat']['current'] = 0;
            foreach( $object->lines as $line) {
                if ($line->product_ref != NULL) // only predefined line will go through work flow
                {
                    $line->special_code = -1; // 1: approve, 0: disapprove,  -1: undefined
                    $approverObj = $this->_getPOApprover($line);
                    if ($approverObj != NULL) {
                        switch ($object->extraparams['step'])
                        {
                            case 2:
                                $approverName = $approverObj->app2;
                                break;
                            case 3:
                                $approverName = $approverObj->app3;
                                break;
                            case 4:
                                $approverName = $approverObj->app4;
                                break;
                        }
                        if ($approverName)
                            $object->extraparams['line_stat']['total'] ++;
                        else
                            $line->special_code = 1; // no more approver, so this line is approved
                    }
                    $line->update();
                }
            }
            if ($object->extraparams['line_stat']['total'] == 0)  
            {
                $this->_approve($object); // finally approved
            }
        }
        $object->setExtraParameters();
        return false;
    }

    /**
     * disapprove user's line
     */
    protected function _disapproveLinesForUser(&$object)
    {
        global $conf, $user, $langs;

        $this->_refuse($object);
    }

    /**
     * approve purchase order 
     */
    protected function _approve($object)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."commande_fournisseur SET fk_statut = 2"; // accepted : 2
        $sql .= " WHERE rowid = ".$object->id;

        if ($this->db->query($sql))
            return true;
        return false;
    }
    
    /**
     * approve purchase order 
     */
    protected function _refuse($object)
    {
        $sql = "UPDATE ".MAIN_DB_PREFIX."commande_fournisseur SET fk_statut = 9"; // refused : 9
        $sql .= " WHERE rowid = ".$object->id;

        if ($this->db->query($sql))
            return true;
        return false;
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
        //print_r($parameters); print_r($object); echo "action: " . $action;
        $context = explode(':', $parameters['context']);
		if (in_array('ordersuppliercard', $context)) {

			/**
			* before create proposal, first get accounts for each lines, and send notify to step-1 approvers
            */
            switch($action)
            {
                case "confirm_valid":
                    $object->valid($user);                    
	           	    header('Location: '.$_SERVER["PHP_SELF"].'?id='.$object->id);
                    return 1;
                    break;
                case "valid":
                    $this->_initWorkflow($object);
                    return 1;
                    break;
                case 'lineapprove':
                    $this->_approveLinesForUser($object);
                    break;
                case 'linereject':
                    $this->_disapproveLinesForUser($object);
                    return 1;
                    break;
                default:
                    break;
            }

			/**
			* get user list example
			*/
			//require_once DOL_DOCUMENT_ROOT .'/user/class/user.class.php';
			//$usergroup = new User($this->db);
			//$usergroup->fetchAll('ASC','t.rowid',  100, 0, array("customsql" => "t.login like 'mustafa'"));
			//var_dump($usergroup->users);
			//echo "<br><br><br>";
			/**
			* send mail example
			*/
			//include_once DOL_DOCUMENT_ROOT.'/core/class/CMailFile.class.php';
			//$mail = new CMailFile('test', 'postmaster@localhost', 'test@localhost', 'hello', array(), array(), array(), '', '', 0, '', '', '', '', '', 'standard');

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
        $line = $parameters['line'];
        if (in_array('ordersuppliercard', $context)) {            
            if ($this->_isMyApproveTarget($object, $line))
            {
                $line->product_type = "approval-item";
            }
        }
		//$object->approve($user);
		return 0;
	}
    function addMoreActionsButtons($parameters, &$object, &$action, $hookmanager)
    {
	    global $conf, $langs, $user;

        require_once DOL_DOCUMENT_ROOT .'/compta/facture/class/facture.class.php';
	    $context = explode(':', $parameters['context']);
		if (in_array('ordersuppliercard', $context)) {
            
			if ($object->statut == Facture::STATUS_VALIDATED && $this->_checkApprovePermission($object)) {
				print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=lineapprove">'.$langs->trans("Ok, Approve").'</a>';
				print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=linereject">'.$langs->trans("No, Disapprove").'</a>';
				return 1;
            }

            if ($object->statut == 0 && count($object->lines))
			{
				if ((empty($conf->global->MAIN_USE_ADVANCED_PERMS) && !empty($user->rights->fournisseur->commande->creer))
			   	|| (!empty($conf->global->MAIN_USE_ADVANCED_PERMS) && !empty($user->rights->fournisseur->supplier_order_advance->validate)))
				{
					$tmpbuttonlabel = "Yes,".$langs->trans('Validate');
					
					print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=valid">';
					print $tmpbuttonlabel;
                    print '</a>';
				}
            }
            // Clone
			if ($user->rights->fournisseur->commande->creer)
			{
				print '<a class="butAction" href="'.$_SERVER['PHP_SELF'].'?id='.$object->id.'&amp;socid='.$object->socid.'&amp;action=clone&amp;object=order">'.$langs->trans("ToClone").'</a>';
			}
            // Delete
			if (!empty($user->rights->fournisseur->commande->supprimer) || ($object->statut == CommandeFournisseur::STATUS_DRAFT && !empty($user->rights->fournisseur->commande->creer)))
			{
				print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&amp;action=delete">'.$langs->trans("Delete").'</a>';
            }
            return 1;
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
