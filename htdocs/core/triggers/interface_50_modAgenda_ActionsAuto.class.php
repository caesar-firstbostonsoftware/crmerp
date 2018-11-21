<?php
/* Copyright (C) 2005-2017	Laurent Destailleur 	<eldy@users.sourceforge.net>
 * Copyright (C) 2009-2017	Regis Houssin		<regis.houssin@capnetworks.com>
 * Copyright (C) 2011-2014	Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2013		Cedric GROSS			<c.gross@kreiz-it.fr>
 * Copyright (C) 2014		Marcos García		<marcosgdf@gmail.com>
 * Copyright (C) 2015		Bahfir Abbes			<bafbes@gmail.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/core/triggers/interface_50_modAgenda_ActionsAuto.class.php
 *  \ingroup    agenda
 *  \brief      Trigger file for agenda module
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';


/**
 *  Class of triggered functions for agenda module
 */
class InterfaceActionsAuto extends dolibarrTriggers
{
	public $family = 'agenda';
	public $description = "Triggers of this module add actions in agenda according to setup made in agenda setup.";
	public $version = self::VERSION_dolibarr;
	public $picto = 'action';

	/**
	 * Function called when a dolibarrr business event is done.
	 * All functions "runTrigger" are triggered if file is inside directory htdocs/core/triggers or htdocs/module/code/triggers (and declared)
	 *
	 * Following properties may be set before calling trigger. The may be completed by this trigger to be used for writing the event into database:
	 *      $object->actiontypecode (translation action code: AC_OTH, ...)
	 *      $object->actionmsg (note, long text)
	 *      $object->actionmsg2 (label, short text)
	 *      $object->sendtoid (id of contact or array of ids)
	 *      $object->socid (id of thirdparty)
	 *      $object->fk_project
	 *      $object->fk_element
	 *      $object->elementtype
	 *
	 * @param string		$action		Event action code
	 * @param Object		$object     Object
	 * @param User		    $user       Object user
	 * @param Translate 	$langs      Object langs
	 * @param conf		    $conf       Object conf
	 * @return int         				<0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
        if (empty($conf->agenda->enabled)) return 0;     // Module not active, we do nothing

		$key = 'MAIN_AGENDA_ACTIONAUTO_'.$action;

		// Do not log events not enabled for this action
		if (empty($conf->global->$key)) {
			return 0;
		}

		$langs->load("agenda");

		if (empty($object->actiontypecode)) $object->actiontypecode='AC_OTH_AUTO';

		// Actions
		if ($action == 'COMPANY_CREATE')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("companies");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("NewCompanyTodolibarr",$object->name);
            $object->actionmsg=$langs->transnoentities("NewCompanyTodolibarr",$object->name);
            if (! empty($object->prefix)) $object->actionmsg.=" (".$object->prefix.")";

			$object->sendtoid=0;
			$object->socid=$object->id;
        }
        elseif ($action == 'COMPANY_SENTBYMAIL')
        {
            $langs->load("agenda");
            $langs->load("other");
        	$langs->load("orders");

            if (empty($object->actionmsg2)) dol_syslog('Trigger called with property actionmsg2 on object not defined', LOG_ERR);

            // Parameters $object->sendtoid defined by caller
            //$object->sendtoid=0;
		}
        elseif ($action == 'CONTRACT_VALIDATE')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("contracts");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("ContractValidatedIndolibarr",($object->newref?$object->newref:$object->ref));
            $object->actionmsg=$langs->transnoentities("ContractValidatedIndolibarr",($object->newref?$object->newref:$object->ref));

            $object->sendtoid=0;
		}
		elseif ($action == 'CONTRACT_SENTBYMAIL')
		{
			$langs->load("agenda");
			$langs->load("other");
			$langs->load("contract");

			if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("ContractSentByEMail",$object->ref);
			if (empty($object->actionmsg))
			{
				$object->actionmsg=$langs->transnoentities("ContractSentByEMail",$object->ref);
			}

			// Parameters $object->sendtoid defined by caller
			//$object->sendtoid=0;
		}
		elseif ($action == 'PROPAL_VALIDATE')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("propal");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("PropalValidatedIndolibarr",($object->newref?$object->newref:$object->ref));
            $object->actionmsg=$langs->transnoentities("PropalValidatedIndolibarr",($object->newref?$object->newref:$object->ref));

			$object->sendtoid=0;
		}
        elseif ($action == 'PROPAL_SENTBYMAIL')
        {
            $langs->load("agenda");
            $langs->load("other");
        	$langs->load("propal");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("ProposalSentByEMail",$object->ref);
            if (empty($object->actionmsg))
            {
                $object->actionmsg=$langs->transnoentities("ProposalSentByEMail",$object->ref);
            }

            // Parameters $object->sendtoid defined by caller
            //$object->sendtoid=0;
		}
		elseif ($action == 'PROPAL_CLOSE_SIGNED')
        {
            $langs->load("agenda");
            $langs->load("other");
        	$langs->load("propal");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("PropalClosedSignedIndolibarr",$object->ref);
            $object->actionmsg=$langs->transnoentities("PropalClosedSignedIndolibarr",$object->ref);

			$object->sendtoid=0;
		}
		elseif ($action == 'PROPAL_CLASSIFY_BILLED')
        {
            $langs->load("agenda");
            $langs->load("other");
        	$langs->load("propal");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("PropalClassifiedBilledIndolibarr",$object->ref);
            $object->actionmsg=$langs->transnoentities("PropalClassifiedBilledIndolibarr",$object->ref);

			$object->sendtoid=0;
		}
		elseif ($action == 'PROPAL_CLOSE_REFUSED')
        {
            $langs->load("agenda");
            $langs->load("other");
        	$langs->load("propal");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("PropalClosedRefusedIndolibarr",$object->ref);
            $object->actionmsg=$langs->transnoentities("PropalClosedRefusedIndolibarr",$object->ref);

			$object->sendtoid=0;
		}
		elseif ($action == 'ORDER_VALIDATE')
        {
            $langs->load("agenda");
            $langs->load("orders");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("OrderValidatedIndolibarr",($object->newref?$object->newref:$object->ref));
            $object->actionmsg=$langs->transnoentities("OrderValidatedIndolibarr",($object->newref?$object->newref:$object->ref));

			$object->sendtoid=0;
		}
		elseif ($action == 'ORDER_CLOSE')
        {
            $langs->load("agenda");
            $langs->load("other");
        	$langs->load("orders");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("OrderDeliveredIndolibarr",$object->ref);
            $object->actionmsg=$langs->transnoentities("OrderDeliveredIndolibarr",$object->ref);

			$object->sendtoid=0;
		}
		elseif ($action == 'ORDER_CLASSIFY_BILLED')
        {
            $langs->load("agenda");
            $langs->load("other");
        	$langs->load("orders");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("OrderBilledIndolibarr",$object->ref);
            $object->actionmsg=$langs->transnoentities("OrderBilledIndolibarr",$object->ref);

			$object->sendtoid=0;
		}
		elseif ($action == 'ORDER_CANCEL')
        {
            $langs->load("agenda");
            $langs->load("other");
        	$langs->load("orders");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("OrderCanceledIndolibarr",$object->ref);
            $object->actionmsg=$langs->transnoentities("OrderCanceledIndolibarr",$object->ref);

			$object->sendtoid=0;
		}
		elseif ($action == 'ORDER_SENTBYMAIL')
        {
            $langs->load("agenda");
            $langs->load("other");
        	$langs->load("orders");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("OrderSentByEMail",$object->ref);
            if (empty($object->actionmsg))
            {
                $object->actionmsg=$langs->transnoentities("OrderSentByEMail",$object->ref);
            }

            // Parameters $object->sendtoid defined by caller
            //$object->sendtoid=0;
		}
		elseif ($action == 'BILL_VALIDATE')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("bills");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("InvoiceValidatedIndolibarr",($object->newref?$object->newref:$object->ref));
            $object->actionmsg=$langs->transnoentities("InvoiceValidatedIndolibarr",($object->newref?$object->newref:$object->ref));

			$object->sendtoid=0;
		}
		elseif ($action == 'BILL_UNVALIDATE')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("bills");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("InvoiceBackToDraftIndolibarr",$object->ref);
            $object->actionmsg=$langs->transnoentities("InvoiceBackToDraftIndolibarr",$object->ref);

			$object->sendtoid=0;
		}
        elseif ($action == 'BILL_SENTBYMAIL')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("bills");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("InvoiceSentByEMail",$object->ref);
            if (empty($object->actionmsg))
            {
                $object->actionmsg=$langs->transnoentities("InvoiceSentByEMail",$object->ref);
            }

            // Parameters $object->sendtoid defined by caller
            //$object->sendtoid=0;
		}
		elseif ($action == 'BILL_PAYED')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("bills");

            // Values for this action can't be defined by caller.
            $object->actionmsg2=$langs->transnoentities("InvoicePaidIndolibarr",$object->ref);
            $object->actionmsg=$langs->transnoentities("InvoicePaidIndolibarr",$object->ref);

            $object->sendtoid=0;
		}
		elseif ($action == 'BILL_CANCEL')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("bills");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("InvoiceCanceledIndolibarr",$object->ref);
            $object->actionmsg=$langs->transnoentities("InvoiceCanceledIndolibarr",$object->ref);

            $object->sendtoid=0;
		}
		elseif ($action == 'FICHINTER_CREATE')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("interventions");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("InterventionCreatedIndolibarr",$object->ref);
            $object->actionmsg=$langs->transnoentities("InterventionCreatedIndolibarr",$object->ref);

            $object->sendtoid=0;
			$object->fk_element=0;
			$object->elementtype='';
		}
		elseif ($action == 'FICHINTER_VALIDATE')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("interventions");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("InterventionValidatedIndolibarr",($object->newref?$object->newref:$object->ref));
            $object->actionmsg=$langs->transnoentities("InterventionValidatedIndolibarr",($object->newref?$object->newref:$object->ref));

            $object->sendtoid=0;
			$object->fk_element=0;
			$object->elementtype='';
		}
		elseif ($action == 'FICHINTER_MODIFY')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("interventions");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("InterventionModifiedIndolibarr",$object->ref);
            $object->actionmsg=$langs->transnoentities("InterventionModifiedIndolibarr",$object->ref);

            $object->sendtoid=0;
			$object->fk_element=0;
			$object->elementtype='';
		}
		elseif ($action == 'FICHINTER_SENTBYMAIL')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("interventions");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("InterventionSentByEMail",$object->ref);
            if (empty($object->actionmsg))
            {
            	$object->actionmsg=$langs->transnoentities("InterventionSentByEMail",$object->ref);
            }

            // Parameters $object->sendtoid defined by caller
            //$object->sendtoid=0;
        }
        elseif ($action == 'FICHINTER_CLASSIFY_BILLED')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("interventions");

           	if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("InterventionClassifiedBilledIndolibarr",$object->ref);
           	$object->actionmsg=$langs->transnoentities("InterventionClassifiedBilledIndolibarr",$object->ref);

            $object->sendtoid=0;
        }
	    elseif ($action == 'FICHINTER_CLASSIFY_UNBILLED')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("interventions");

           	if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("InterventionClassifiedUnbilledIndolibarr",$object->ref);
           	$object->actionmsg=$langs->transnoentities("InterventionClassifiedUnbilledIndolibarr",$object->ref);

            $object->sendtoid=0;
        }
        elseif ($action == 'FICHINTER_DELETE')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("interventions");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("InterventionDeletedIndolibarr",$object->ref);
            $object->actionmsg=$langs->transnoentities("InterventionDeletedIndolibarr",$object->ref);

            $object->sendtoid=0;
			$object->fk_element=0;
			$object->elementtype='';
		}
        elseif ($action == 'SHIPPING_VALIDATE')
        {
            $langs->load("agenda");
            $langs->load("other");
        	$langs->load("sendings");

        	if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("ShippingValidated",($object->newref?$object->newref:$object->ref));
        	if (empty($object->actionmsg))
        	{
        		$object->actionmsg=$langs->transnoentities("ShippingValidated",($object->newref?$object->newref:$object->ref));
        	}

        	// Parameters $object->sendtoid defined by caller
        	//$object->sendtoid=0;
        }
		elseif ($action == 'SHIPPING_SENTBYMAIL')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("sendings");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("ShippingSentByEMail",$object->ref);
            if (empty($object->actionmsg))
            {
                $object->actionmsg=$langs->transnoentities("ShippingSentByEMail",$object->ref);
            }

            // Parameters $object->sendtoid defined by caller
            //$object->sendtoid=0;
		}
		elseif ($action == 'PROPOSAL_SUPPLIER_VALIDATE')
		{
			$langs->load("agenda");
			$langs->load("other");
			$langs->load("propal");

			if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("PropalValidatedIndolibarr",($object->newref?$object->newref:$object->ref));
			$object->actionmsg=$langs->transnoentities("PropalValidatedIndolibarr",($object->newref?$object->newref:$object->ref));

			$object->sendtoid=0;
		}
		elseif ($action == 'PROPOSAL_SUPPLIER_SENTBYMAIL')
		{
			$langs->load("agenda");
			$langs->load("other");
			$langs->load("propal");

			if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("ProposalSentByEMail",$object->ref);
			if (empty($object->actionmsg))
			{
				$object->actionmsg=$langs->transnoentities("ProposalSentByEMail",$object->ref);
			}

			// Parameters $object->sendtoid defined by caller
			//$object->sendtoid=0;
		}
		elseif ($action == 'PROPOSAL_SUPPLIER_CLOSE_SIGNED')
		{
			$langs->load("agenda");
			$langs->load("other");
			$langs->load("propal");

			if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("PropalClosedSignedIndolibarr",$object->ref);
			$object->actionmsg=$langs->transnoentities("PropalClosedSignedIndolibarr",$object->ref);

			$object->sendtoid=0;
		}
		elseif ($action == 'PROPOSAL_SUPPLIER_CLOSE_REFUSED')
		{
			$langs->load("agenda");
			$langs->load("other");
			$langs->load("propal");

			if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("PropalClosedRefusedIndolibarr",$object->ref);
			$object->actionmsg=$langs->transnoentities("PropalClosedRefusedIndolibarr",$object->ref);

			$object->sendtoid=0;
		}
		elseif ($action == 'ORDER_SUPPLIER_CREATE')
        {
            $langs->load("agenda");
            $langs->load("other");
        	$langs->load("orders");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("OrderCreatedIndolibarr",($object->newref?$object->newref:$object->ref));
            $object->actionmsg=$langs->transnoentities("OrderCreatedIndolibarr",($object->newref?$object->newref:$object->ref));

            $object->sendtoid=0;
		}
		elseif ($action == 'ORDER_SUPPLIER_VALIDATE')
        {
            $langs->load("agenda");
            $langs->load("other");
        	$langs->load("orders");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("OrderValidatedIndolibarr",($object->newref?$object->newref:$object->ref));
            $object->actionmsg=$langs->transnoentities("OrderValidatedIndolibarr",($object->newref?$object->newref:$object->ref));

            $object->sendtoid=0;
		}
		elseif ($action == 'ORDER_SUPPLIER_APPROVE')
		{
            $langs->load("agenda");
		    $langs->load("other");
			$langs->load("orders");

			if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("OrderApprovedIndolibarr",$object->ref);
			$object->actionmsg=$langs->transnoentities("OrderApprovedIndolibarr",$object->ref);

			$object->sendtoid=0;
		}
		elseif ($action == 'ORDER_SUPPLIER_REFUSE')
		{
            $langs->load("agenda");
		    $langs->load("other");
			$langs->load("orders");

			if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("OrderRefusedIndolibarr",$object->ref);
			$object->actionmsg=$langs->transnoentities("OrderRefusedIndolibarr",$object->ref);

			$object->sendtoid=0;
		}
		elseif ($action == 'ORDER_SUPPLIER_SUBMIT')
        {
            $langs->load("agenda");
            $langs->load("other");
        	$langs->load("orders");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("SupplierOrderSubmitedIndolibarr",($object->newref?$object->newref:$object->ref));
            $object->actionmsg=$langs->transnoentities("SupplierOrderSubmitedIndolibarr",($object->newref?$object->newref:$object->ref));

            $object->sendtoid=0;
		}
		elseif ($action == 'ORDER_SUPPLIER_RECEIVE')
        {
            $langs->load("agenda");
            $langs->load("other");
        	$langs->load("orders");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("SupplierOrderReceivedIndolibarr",($object->newref?$object->newref:$object->ref));
            $object->actionmsg=$langs->transnoentities("SupplierOrderReceivedIndolibarr",($object->newref?$object->newref:$object->ref));

            $object->sendtoid=0;
		}
		elseif ($action == 'ORDER_SUPPLIER_SENTBYMAIL')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("bills");
            $langs->load("orders");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("SupplierOrderSentByEMail",$object->ref);
            if (empty($object->actionmsg))
            {
                $object->actionmsg=$langs->transnoentities("SupplierOrderSentByEMail",$object->ref);
            }

            // Parameters $object->sendtoid defined by caller
            //$object->sendtoid=0;
        }
		elseif ($action == 'ORDER_SUPPLIER_CLASSIFY_BILLED')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("bills");
            $langs->load("orders");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("SupplierOrderClassifiedBilled",$object->ref);
            if (empty($object->actionmsg))
            {
                $object->actionmsg=$langs->transnoentities("SupplierOrderClassifiedBilled",$object->ref);
            }

            $object->sendtoid=0;
        }
		elseif ($action == 'BILL_SUPPLIER_VALIDATE')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("bills");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("InvoiceValidatedIndolibarr",($object->newref?$object->newref:$object->ref));
            $object->actionmsg=$langs->transnoentities("InvoiceValidatedIndolibarr",($object->newref?$object->newref:$object->ref));

            $object->sendtoid=0;
		}
		elseif ($action == 'BILL_SUPPLIER_UNVALIDATE')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("bills");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("InvoiceBackToDraftIndolibarr",$object->ref);
            $object->actionmsg=$langs->transnoentities("InvoiceBackToDraftIndolibarr",$object->ref);

            $object->sendtoid=0;
		}
        elseif ($action == 'BILL_SUPPLIER_SENTBYMAIL')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("bills");
            $langs->load("orders");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("SupplierInvoiceSentByEMail",$object->ref);
            if (empty($object->actionmsg))
            {
                $object->actionmsg=$langs->transnoentities("SupplierInvoiceSentByEMail",$object->ref);
            }

            // Parameters $object->sendtoid defined by caller
            //$object->sendtoid=0;
        }
		elseif ($action == 'BILL_SUPPLIER_PAYED')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("bills");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("InvoicePaidIndolibarr",$object->ref);
            $object->actionmsg=$langs->transnoentities("InvoicePaidIndolibarr",$object->ref);

			$object->sendtoid=0;
		}
		elseif ($action == 'BILL_SUPPLIER_CANCELED')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("bills");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("InvoiceCanceledIndolibarr",$object->ref);
            $object->actionmsg=$langs->transnoentities("InvoiceCanceledIndolibarr",$object->ref);

			$object->sendtoid=0;
		}

        // Members
        elseif ($action == 'MEMBER_VALIDATE')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("members");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("MemberValidatedIndolibarr",$object->getFullName($langs));
            $object->actionmsg=$langs->transnoentities("MemberValidatedIndolibarr",$object->getFullName($langs));
            $object->actionmsg.="\n".$langs->transnoentities("Member").': '.$object->getFullName($langs);
            $object->actionmsg.="\n".$langs->transnoentities("Type").': '.$object->type;

			$object->sendtoid=0;
        }
		elseif ($action == 'MEMBER_MODIFY')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("members");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("MemberModifiedIndolibarr",$object->getFullName($langs));
            $object->actionmsg=$langs->transnoentities("MemberModifiedIndolibarr",$object->getFullName($langs));
            $object->actionmsg.="\n".$langs->transnoentities("Member").': '.$object->getFullName($langs);
            $object->actionmsg.="\n".$langs->transnoentities("Type").': '.$object->type;

            $object->sendtoid=0;
		}
        elseif ($action == 'MEMBER_SUBSCRIPTION_CREATE')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("members");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("MemberSubscriptionAddedIndolibarr",$object->ref,$object->getFullName($langs));
            $object->actionmsg=$langs->transnoentities("MemberSubscriptionAddedIndolibarr",$object->ref,$object->getFullName($langs));
            $object->actionmsg.="\n".$langs->transnoentities("Member").': '.$object->getFullName($langs);
            $object->actionmsg.="\n".$langs->transnoentities("Type").': '.$object->type;
            $object->actionmsg.="\n".$langs->transnoentities("Amount").': '.$object->last_subscription_amount;
            $object->actionmsg.="\n".$langs->transnoentities("Period").': '.dol_print_date($object->last_subscription_date_start,'day').' - '.dol_print_date($object->last_subscription_date_end,'day');

			$object->sendtoid=0;
			if ($object->fk_soc > 0) $object->socid=$object->fk_soc;
        }
        elseif ($action == 'MEMBER_SUBSCRIPTION_MODIFY')
        {
        	$langs->load("agenda");
        	$langs->load("other");
        	$langs->load("members");

        	if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("MemberSubscriptionModifiedIndolibarr",$object->ref,$object->getFullName($langs));
        	$object->actionmsg=$langs->transnoentities("MemberSubscriptionModifiedIndolibarr",$object->ref,$object->getFullName($langs));
        	$object->actionmsg.="\n".$langs->transnoentities("Member").': '.$object->getFullName($langs);
        	$object->actionmsg.="\n".$langs->transnoentities("Type").': '.$object->type;
        	$object->actionmsg.="\n".$langs->transnoentities("Amount").': '.$object->last_subscription_amount;
        	$object->actionmsg.="\n".$langs->transnoentities("Period").': '.dol_print_date($object->last_subscription_date_start,'day').' - '.dol_print_date($object->last_subscription_date_end,'day');

        	$object->sendtoid=0;
        	if ($object->fk_soc > 0) $object->socid=$object->fk_soc;
        }
        elseif ($action == 'MEMBER_SUBSCRIPTION_DELETE')
        {
        	$langs->load("agenda");
        	$langs->load("other");
        	$langs->load("members");

        	if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("MemberSubscriptionDeletedIndolibarr",$object->ref,$object->getFullName($langs));
        	$object->actionmsg=$langs->transnoentities("MemberSubscriptionDeletedIndolibarr",$object->ref,$object->getFullName($langs));
        	$object->actionmsg.="\n".$langs->transnoentities("Member").': '.$object->getFullName($langs);
        	$object->actionmsg.="\n".$langs->transnoentities("Type").': '.$object->type;
        	$object->actionmsg.="\n".$langs->transnoentities("Amount").': '.$object->last_subscription_amount;
        	$object->actionmsg.="\n".$langs->transnoentities("Period").': '.dol_print_date($object->last_subscription_date_start,'day').' - '.dol_print_date($object->last_subscription_date_end,'day');

        	$object->sendtoid=0;
        	if ($object->fk_soc > 0) $object->socid=$object->fk_soc;
        }
        elseif ($action == 'MEMBER_RESILIATE')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("members");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("MemberResiliatedIndolibarr",$object->getFullName($langs));
            $object->actionmsg=$langs->transnoentities("MemberResiliatedIndolibarr",$object->getFullName($langs));
            $object->actionmsg.="\n".$langs->transnoentities("Member").': '.$object->getFullName($langs);
            $object->actionmsg.="\n".$langs->transnoentities("Type").': '.$object->type;

			$object->sendtoid=0;
        }
        elseif ($action == 'MEMBER_DELETE')
        {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("members");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("MemberDeletedIndolibarr",$object->getFullName($langs));
            $object->actionmsg=$langs->transnoentities("MemberDeletedIndolibarr",$object->getFullName($langs));
            $object->actionmsg.="\n".$langs->transnoentities("Member").': '.$object->getFullName($langs);
            $object->actionmsg.="\n".$langs->transnoentities("Type").': '.$object->type;

			$object->sendtoid=0;
        }

        // Projects
        elseif ($action == 'PROJECT_CREATE')
        {
            $langs->load("agenda");
            $langs->load("other");
        	$langs->load("projects");

        	if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("ProjectCreatedIndolibarr",$object->ref);
        	$object->actionmsg=$langs->transnoentities("ProjectCreatedIndolibarr",$object->ref);
        	$object->actionmsg.="\n".$langs->transnoentities("Project").': '.$object->ref;

        	$object->sendtoid=0;
        }
        elseif($action == 'PROJECT_VALIDATE') {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("projects");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("ProjectValidatedIndolibarr",$object->ref);
            $object->actionmsg=$langs->transnoentities("ProjectValidatedIndolibarr",$object->ref);
            $object->actionmsg.="\n".$langs->transnoentities("Project").': '.$object->ref;

            $object->sendtoid=0;
        }
        elseif($action == 'PROJECT_MODIFY') {
            $langs->load("agenda");
            $langs->load("other");
            $langs->load("projects");

            if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("ProjectModifiedIndolibarr",$object->ref);
            $object->actionmsg=$langs->transnoentities("ProjectModifiedIndolibarr",$object->ref);
            $object->actionmsg.="\n".$langs->transnoentities("Task").': '.$object->ref;

            $object->sendtoid=0;
        }

		// Project tasks
		elseif($action == 'TASK_CREATE') {
            $langs->load("agenda");
		    $langs->load("other");
			$langs->load("projects");

			if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("TaskCreatedIndolibarr",$object->ref);
			$object->actionmsg=$langs->transnoentities("TaskCreatedIndolibarr",$object->ref);
			$object->actionmsg.="\n".$langs->transnoentities("Task").': '.$object->ref;

			$object->sendtoid=0;
		}

		elseif($action == 'TASK_MODIFY') {
            $langs->load("agenda");
		    $langs->load("other");
			$langs->load("projects");

			if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("TaskModifiedIndolibarr",$object->ref);
			$object->actionmsg=$langs->transnoentities("TaskModifieddIndolibarr",$object->ref);
			$object->actionmsg.="\n".$langs->transnoentities("Task").': '.$object->ref;

			$object->sendtoid=0;
		}

		elseif($action == 'TASK_DELETE') {
            $langs->load("agenda");
		    $langs->load("other");
			$langs->load("projects");

			if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities("TaskDeletedIndolibarr",$object->ref);
			$object->actionmsg=$langs->transnoentities("TaskDeletedIndolibarr",$object->ref);
			$object->actionmsg.="\n".$langs->transnoentities("Task").': '.$object->ref;

			$object->sendtoid=0;
		}
		// TODO Merge all previous cases into this generic one
		else {
		    // Note: We are here only if $conf->global->MAIN_AGENDA_ACTIONAUTO_action is on (tested at begining of this function)
		    $langs->load("agenda");
		    $langs->load("other");

		    if (empty($object->actionmsg2)) $object->actionmsg2=$langs->transnoentities($action."Indolibarr",$object->ref);
		    if (empty($object->actionmsg))  $object->actionmsg=$langs->transnoentities($action."Indolibarr",$object->ref);

		    $object->sendtoid=0;
		}

		$object->actionmsg.="\n".$langs->transnoentities("Author").': '.$user->login;

		dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);

        // Add entry in event table
		$now=dol_now();

		if (isset($_SESSION['listofnames-'.$object->trackid]))
		{
			$attachs=$_SESSION['listofnames-'.$object->trackid];
			if ($attachs && strpos($action,'SENTBYMAIL'))
			{
                $object->actionmsg=dol_concatdesc($object->actionmsg, "\n".$langs->transnoentities("AttachedFiles").': '.$attachs);
			}
		}

        require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
        require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$contactforaction=new Contact($this->db);
        $societeforaction=new Societe($this->db);
        // Set contactforaction if there is only 1 contact.
        if (is_array($object->sendtoid))
        {
            if (count($object->sendtoid) == 1) $contactforaction->fetch(reset($object->sendtoid));
        }
        else
        {
            if ($object->sendtoid > 0) $contactforaction->fetch($object->sendtoid);
        }
        // Set societeforaction.
        if ($object->socid > 0)    $societeforaction->fetch($object->socid);

        $projectid = isset($object->fk_project)?$object->fk_project:0;
        if ($object->element == 'project') $projectid = $object->id;

        $elementid = $object->id;
        $elementtype = $object->element;
        if ($object->element == 'subscription')
        {
        	$elementid = $object->fk_adherent;
        	$elementtype = 'member';
        }

		// Insertion action
		require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';
		$actioncomm = new ActionComm($this->db);
		$actioncomm->type_code   = $object->actiontypecode;		// Type of event ('AC_OTH', 'AC_OTH_AUTO', 'AC_XXX'...)
		$actioncomm->code        = 'AC_'.$action;
		$actioncomm->label       = $object->actionmsg2;
		$actioncomm->note        = $object->actionmsg;          // TODO Replace with $actioncomm->email_msgid ? $object->email_content : $object->actionmsg
		$actioncomm->fk_project  = $projectid;
		$actioncomm->datep       = $now;
		$actioncomm->datef       = $now;
		$actioncomm->durationp   = 0;
		$actioncomm->punctual    = 1;
		$actioncomm->percentage  = -1;   // Not applicable
		$actioncomm->societe     = $societeforaction;
		$actioncomm->contact     = $contactforaction;
		$actioncomm->socid       = $societeforaction->id;
		$actioncomm->contactid   = $contactforaction->id;
		$actioncomm->authorid    = $user->id;   // User saving action
		$actioncomm->userownerid = $user->id;	// Owner of action
        // Fields when action is en email (content should be added into note)
		$actioncomm->email_msgid = $object->email_msgid;
		$actioncomm->email_from  = $object->email_from;
		$actioncomm->email_sender= $object->email_sender;
		$actioncomm->email_to    = $object->email_to;
		$actioncomm->email_tocc  = $object->email_tocc;
		$actioncomm->email_tobcc = $object->email_tobcc;
		$actioncomm->email_subject = $object->email_subject;
		$actioncomm->errors_to   = $object->errors_to;

		$actioncomm->fk_element  = $elementid;
		$actioncomm->elementtype = $elementtype;

		if (property_exists($object,'sendtouserid') && is_array($object->sendtouserid) && count($object->sendtouserid)>0) {
			$actioncomm->userassigned=$object->sendtouserid;
		}

		$ret=$actioncomm->create($user);       // User creating action

		if ($ret > 0 && $conf->global->MAIN_COPY_FILE_IN_EVENT_AUTO)
		{
			if (is_array($object->attachedfiles) && array_key_exists('paths',$object->attachedfiles) && count($object->attachedfiles['paths'])>0) {
				foreach($object->attachedfiles['paths'] as $key=>$filespath) {
					$srcfile = $filespath;
					$destdir = $conf->agenda->dir_output . '/' . $ret;
					$destfile = $destdir . '/' . $object->attachedfiles['names'][$key];
					if (dol_mkdir($destdir) >= 0) {
						require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
						dol_copy($srcfile, $destfile);
					}
				}
			}
		}

		unset($object->actionmsg); unset($object->actionmsg2); unset($object->actiontypecode);	// When several action are called on same object, we must be sure to not reuse value of first action.

		if ($ret > 0)
		{
			$_SESSION['LAST_ACTION_CREATED'] = $ret;
			return 1;
		}
		else
		{
            $error ="Failed to insert event : ".$actioncomm->error." ".join(',',$actioncomm->errors);
            $this->error=$error;
            $this->errors=$actioncomm->errors;

            dol_syslog("interface_modAgenda_ActionsAuto.class.php: ".$this->error, LOG_ERR);
            return -1;
		}
    }

}
