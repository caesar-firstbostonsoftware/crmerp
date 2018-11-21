<?php
/* Copyright (C) 2011		Dimitri Mouillard	<dmouillard@teclib.com>
 * Copyright (C) 2012-2014	Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2012-2016	Regis Houssin		<regis.houssin@capnetworks.com>
 * Copyright (C) 2013		Florian Henry		<florian.henry@open-concept.pro>
 * Copyright (C) 2016       Juanjo Menent       <jmenent@2byte.es>
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
 *    \file       holiday.class.php
 *    \ingroup    holiday
 *    \brief      Class file of the module paid holiday.
 */
require_once DOL_DOCUMENT_ROOT .'/core/class/commonobject.class.php';


/**
 *	Class of the module paid holiday. Developed by Teclib ( http://www.teclib.com/ )
 */
class Holiday extends CommonObject
{
	public $element='holiday';
	public $table_element='holiday';
	public $ismultientitymanaged = 0;	// 0=No test on entity, 1=Test with field entity, 2=Test with link by societe
	var $fk_element = 'fk_holiday';
	public $picto = 'holiday';

	/**
	 * @deprecated
	 * @see id
	 */
	var $rowid;

	var $fk_user;
	var $date_create='';
	var $description;
	var $date_debut='';			// Date start in PHP server TZ
	var $date_fin='';			// Date end in PHP server TZ
	var $date_debut_gmt='';		// Date start in GMT
	var $date_fin_gmt='';		// Date end in GMT
	var $halfday='';			// 0:Full days, 2:Start afternoon end morning, -1:Start afternoon end afternoon, 1:Start morning end morning
	var $statut='';				// 1=draft, 2=validated, 3=approved
	var $fk_validator;
	var $date_valid='';
	var $fk_user_valid;
	var $date_refuse='';
	var $fk_user_refuse;
	var $date_cancel='';
	var $fk_user_cancel;
	var $detail_refuse='';
	var $fk_type;

	var $holiday = array();
	var $events = array();
	var $logs = array();

	var $optName = '';
	var $optValue = '';
	var $optRowid = '';

	/**
	 * Draft status
	 */
	const STATUS_DRAFT = 1;
	/**
	 * Validated status
	 */
	const STATUS_VALIDATED = 2;
	/**
	 * Approved
	 */
	const STATUS_APPROVED = 3;
	/**
	 * Canceled
	 */
	const STATUS_CANCELED = 4;
	/**
	 * Refused
	 */
	const STATUS_REFUSED = 5;


	/**
	 *   Constructor
	 *
	 *   @param		DoliDB		$db      Database handler
	 */
	function __construct($db)
	{
		$this->db = $db;
	}


	/**
	 * Update balance of vacations and check table of users for holidays is complete. If not complete.
	 *
	 * @return	int			<0 if KO, >0 if OK
	 */
	function updateBalance()
	{
		$this->db->begin();

		// Update sold of vocations
		$result = $this->updateSoldeCP();

		// Check nb of users into table llx_holiday_users and update with empty lines
		//if ($result > 0) $result = $this->verifNbUsers($this->countActiveUsersWithoutCP(), $this->getConfCP('nbUser'));

		if ($result >= 0)
		{
			$this->db->commit();
			return 1;
		}
		else
		{
			$this->db->rollback();
			return -1;
		}
	}

	/**
	 *   Créer un congés payés dans la base de données
	 *
	 *   @param		User	$user        	User that create
	 *   @param     int		$notrigger	    0=launch triggers after, 1=disable triggers
	 *   @return    int			         	<0 if KO, Id of created object if OK
	 */
	function create($user, $notrigger=0)
	{
		global $conf;
		$error=0;

		$now=dol_now();

		// Check parameters
		if (empty($this->fk_user) || ! is_numeric($this->fk_user) || $this->fk_user < 0) { $this->error="ErrorBadParameterFkUser"; return -1; }
		if (empty($this->fk_validator) || ! is_numeric($this->fk_validator) || $this->fk_validator < 0)  { $this->error="ErrorBadParameterFkValidator"; return -1; }
		if (empty($this->fk_type) || ! is_numeric($this->fk_type) || $this->fk_type < 0) { $this->error="ErrorBadParameterFkType"; return -1; }

		// Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."holiday(";
		$sql.= "fk_user,";
		$sql.= "date_create,";
		$sql.= "description,";
		$sql.= "date_debut,";
		$sql.= "date_fin,";
		$sql.= "halfday,";
		$sql.= "statut,";
		$sql.= "fk_validator,";
		$sql.= "fk_type,";
		$sql.= "fk_user_create,";
		$sql.= "entity";
		$sql.= ") VALUES (";
		$sql.= "'".$this->db->escape($this->fk_user)."',";
		$sql.= " '".$this->db->idate($now)."',";
		$sql.= " '".$this->db->escape($this->description)."',";
		$sql.= " '".$this->db->idate($this->date_debut)."',";
		$sql.= " '".$this->db->idate($this->date_fin)."',";
		$sql.= " ".$this->halfday.",";
		$sql.= " '1',";
		$sql.= " '".$this->db->escape($this->fk_validator)."',";
		$sql.= " ".$this->fk_type.",";
		$sql.= " ".$user->id.",";
		$sql.= " ".$conf->entity;
		$sql.= ")";

		$this->db->begin();

		dol_syslog(get_class($this)."::create", LOG_DEBUG);
		$resql=$this->db->query($sql);
		if (! $resql) {
			$error++; $this->errors[]="Error ".$this->db->lasterror();
		}

		if (! $error)
		{
			$this->id = $this->db->last_insert_id(MAIN_DB_PREFIX."holiday");

			if (! $notrigger)
			{
				// Call trigger
				$result=$this->call_trigger('HOLIDAY_CREATE',$user);
				if ($result < 0) { $error++; }
				// End call triggers
			}
		}

		// Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
				dol_syslog(get_class($this)."::create ".$errmsg, LOG_ERR);
				$this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return $this->id;
		}
	}


	/**
	 *	Load object in memory from database
	 *
	 *  @param	int		$id         Id object
	 *  @return int         		<0 if KO, >0 if OK
	 */
	function fetch($id)
	{
		global $langs;

		$sql = "SELECT";
		$sql.= " cp.rowid,";
		$sql.= " cp.fk_user,";
		$sql.= " cp.date_create,";
		$sql.= " cp.description,";
		$sql.= " cp.date_debut,";
		$sql.= " cp.date_fin,";
		$sql.= " cp.halfday,";
		$sql.= " cp.statut,";
		$sql.= " cp.fk_validator,";
		$sql.= " cp.date_valid,";
		$sql.= " cp.fk_user_valid,";
		$sql.= " cp.date_refuse,";
		$sql.= " cp.fk_user_refuse,";
		$sql.= " cp.date_cancel,";
		$sql.= " cp.fk_user_cancel,";
		$sql.= " cp.detail_refuse,";
		$sql.= " cp.note_private,";
		$sql.= " cp.note_public,";
		$sql.= " cp.fk_user_create,";
		$sql.= " cp.fk_type,";
		$sql.= " cp.entity";
		$sql.= " FROM ".MAIN_DB_PREFIX."holiday as cp";
		$sql.= " WHERE cp.rowid = ".$id;

		dol_syslog(get_class($this)."::fetch", LOG_DEBUG);
		$resql=$this->db->query($sql);
		if ($resql)
		{
			if ($this->db->num_rows($resql))
			{
				$obj = $this->db->fetch_object($resql);

				$this->id    = $obj->rowid;
				$this->rowid = $obj->rowid;	// deprecated
				$this->ref   = $obj->rowid;
				$this->fk_user = $obj->fk_user;
				$this->date_create = $this->db->jdate($obj->date_create);
				$this->description = $obj->description;
				$this->date_debut = $this->db->jdate($obj->date_debut);
				$this->date_fin = $this->db->jdate($obj->date_fin);
				$this->date_debut_gmt = $this->db->jdate($obj->date_debut,1);
				$this->date_fin_gmt = $this->db->jdate($obj->date_fin,1);
				$this->halfday = $obj->halfday;
				$this->statut = $obj->statut;
				$this->fk_validator = $obj->fk_validator;
				$this->date_valid = $this->db->jdate($obj->date_valid);
				$this->fk_user_valid = $obj->fk_user_valid;
				$this->date_refuse = $this->db->jdate($obj->date_refuse);
				$this->fk_user_refuse = $obj->fk_user_refuse;
				$this->date_cancel = $this->db->jdate($obj->date_cancel);
				$this->fk_user_cancel = $obj->fk_user_cancel;
				$this->detail_refuse = $obj->detail_refuse;
				$this->note_private = $obj->note_private;
				$this->note_public = $obj->note_public;
				$this->fk_user_create = $obj->fk_user_create;
				$this->fk_type = $obj->fk_type;
				$this->entity = $obj->entity;
			}
			$this->db->free($resql);

			return 1;
		}
		else
		{
			$this->error="Error ".$this->db->lasterror();
			return -1;
		}
	}

	/**
	 *	List holidays for a particular user or list of users
	 *
	 *  @param		int|string		$user_id    ID of user to list, or comma separated list of IDs of users to list
	 *  @param      string			$order      Sort order
	 *  @param      string			$filter     SQL Filter
	 *  @return     int      					-1 if KO, 1 if OK, 2 if no result
	 */
	function fetchByUser($user_id, $order='', $filter='')
	{
		global $langs, $conf;

		$sql = "SELECT";
		$sql.= " cp.rowid,";

		$sql.= " cp.fk_user,";
		$sql.= " cp.fk_type,";
		$sql.= " cp.date_create,";
		$sql.= " cp.description,";
		$sql.= " cp.date_debut,";
		$sql.= " cp.date_fin,";
		$sql.= " cp.halfday,";
		$sql.= " cp.statut,";
		$sql.= " cp.fk_validator,";
		$sql.= " cp.date_valid,";
		$sql.= " cp.fk_user_valid,";
		$sql.= " cp.date_refuse,";
		$sql.= " cp.fk_user_refuse,";
		$sql.= " cp.date_cancel,";
		$sql.= " cp.fk_user_cancel,";
		$sql.= " cp.detail_refuse,";

		$sql.= " uu.lastname as user_lastname,";
		$sql.= " uu.firstname as user_firstname,";
		$sql.= " uu.login as user_login,";
		$sql.= " uu.statut as user_statut,";
		$sql.= " uu.photo as user_photo,";

		$sql.= " ua.lastname as validator_lastname,";
		$sql.= " ua.firstname as validator_firstname,";
		$sql.= " ua.login as validator_login,";
		$sql.= " ua.statut as validator_statut,";
		$sql.= " ua.photo as validator_photo";

		$sql.= " FROM ".MAIN_DB_PREFIX."holiday as cp, ".MAIN_DB_PREFIX."user as uu, ".MAIN_DB_PREFIX."user as ua";
		$sql.= " WHERE cp.entity IN (".getEntity('holiday').")";
		$sql.= " AND cp.fk_user = uu.rowid AND cp.fk_validator = ua.rowid"; // Hack pour la recherche sur le tableau
		$sql.= " AND cp.fk_user IN (".$user_id.")";

		// Filtre de séléction
		if(!empty($filter)) {
			$sql.= $filter;
		}

		// Ordre d'affichage du résultat
		if(!empty($order)) {
			$sql.= $order;
		}

		dol_syslog(get_class($this)."::fetchByUser", LOG_DEBUG);
		$resql=$this->db->query($sql);

		// Si pas d'erreur SQL
		if ($resql) {

			$i = 0;
			$tab_result = $this->holiday;
			$num = $this->db->num_rows($resql);

			// Si pas d'enregistrement
			if(!$num) {
				return 2;
			}

			// Liste les enregistrements et les ajoutent au tableau
			while($i < $num) {

				$obj = $this->db->fetch_object($resql);

				$tab_result[$i]['rowid'] = $obj->rowid;
				$tab_result[$i]['ref'] = $obj->rowid;
				$tab_result[$i]['fk_user'] = $obj->fk_user;
				$tab_result[$i]['fk_type'] = $obj->fk_type;
				$tab_result[$i]['date_create'] = $this->db->jdate($obj->date_create);
				$tab_result[$i]['description'] = $obj->description;
				$tab_result[$i]['date_debut'] = $this->db->jdate($obj->date_debut);
				$tab_result[$i]['date_fin'] = $this->db->jdate($obj->date_fin);
				$tab_result[$i]['date_debut_gmt'] = $this->db->jdate($obj->date_debut,1);
				$tab_result[$i]['date_fin_gmt'] = $this->db->jdate($obj->date_fin,1);
				$tab_result[$i]['halfday'] = $obj->halfday;
				$tab_result[$i]['statut'] = $obj->statut;
				$tab_result[$i]['fk_validator'] = $obj->fk_validator;
				$tab_result[$i]['date_valid'] = $this->db->jdate($obj->date_valid);
				$tab_result[$i]['fk_user_valid'] = $obj->fk_user_valid;
				$tab_result[$i]['date_refuse'] = $this->db->jdate($obj->date_refuse);
				$tab_result[$i]['fk_user_refuse'] = $obj->fk_user_refuse;
				$tab_result[$i]['date_cancel'] = $this->db->jdate($obj->date_cancel);
				$tab_result[$i]['fk_user_cancel'] = $obj->fk_user_cancel;
				$tab_result[$i]['detail_refuse'] = $obj->detail_refuse;

				$tab_result[$i]['user_firstname'] = $obj->user_firstname;
				$tab_result[$i]['user_lastname'] = $obj->user_lastname;
				$tab_result[$i]['user_login'] = $obj->user_login;
				$tab_result[$i]['user_statut'] = $obj->user_statut;
				$tab_result[$i]['user_photo'] = $obj->user_photo;

				$tab_result[$i]['validator_firstname'] = $obj->validator_firstname;
				$tab_result[$i]['validator_lastname'] = $obj->validator_lastname;
				$tab_result[$i]['validator_login'] = $obj->validator_login;
				$tab_result[$i]['validator_statut'] = $obj->validator_statut;
				$tab_result[$i]['validator_photo'] = $obj->validator_photo;

				$i++;
			}

			// Retourne 1 avec le tableau rempli
			$this->holiday = $tab_result;
			return 1;
		}
		else
		{
			// Erreur SQL
			$this->error="Error ".$this->db->lasterror();
			return -1;
		}
	}

	/**
	 *	List all holidays of all users
	 *
	 *  @param      string	$order      Sort order
	 *  @param      string	$filter     SQL Filter
	 *  @return     int      			-1 if KO, 1 if OK, 2 if no result
	 */
	function fetchAll($order,$filter)
	{
		global $langs;

		$sql = "SELECT";
		$sql.= " cp.rowid,";

		$sql.= " cp.fk_user,";
		$sql.= " cp.fk_type,";
		$sql.= " cp.date_create,";
		$sql.= " cp.description,";
		$sql.= " cp.date_debut,";
		$sql.= " cp.date_fin,";
		$sql.= " cp.halfday,";
		$sql.= " cp.statut,";
		$sql.= " cp.fk_validator,";
		$sql.= " cp.date_valid,";
		$sql.= " cp.fk_user_valid,";
		$sql.= " cp.date_refuse,";
		$sql.= " cp.fk_user_refuse,";
		$sql.= " cp.date_cancel,";
		$sql.= " cp.fk_user_cancel,";
		$sql.= " cp.detail_refuse,";

		$sql.= " uu.lastname as user_lastname,";
		$sql.= " uu.firstname as user_firstname,";
		$sql.= " uu.login as user_login,";
		$sql.= " uu.statut as user_statut,";
		$sql.= " uu.photo as user_photo,";

		$sql.= " ua.lastname as validator_lastname,";
		$sql.= " ua.firstname as validator_firstname,";
		$sql.= " ua.login as validator_login,";
		$sql.= " ua.statut as validator_statut,";
		$sql.= " ua.photo as validator_photo";

		$sql.= " FROM ".MAIN_DB_PREFIX."holiday as cp, ".MAIN_DB_PREFIX."user as uu, ".MAIN_DB_PREFIX."user as ua";
		$sql.= " WHERE cp.entity IN (".getEntity('holiday').")";
		$sql.= " AND cp.fk_user = uu.rowid AND cp.fk_validator = ua.rowid "; // Hack pour la recherche sur le tableau

		// Filtrage de séléction
		if(!empty($filter)) {
			$sql.= $filter;
		}

		// Ordre d'affichage
		if(!empty($order)) {
			$sql.= $order;
		}

		dol_syslog(get_class($this)."::fetchAll", LOG_DEBUG);
		$resql=$this->db->query($sql);

		// Si pas d'erreur SQL
		if ($resql) {

			$i = 0;
			$tab_result = $this->holiday;
			$num = $this->db->num_rows($resql);

			// Si pas d'enregistrement
			if(!$num) {
				return 2;
			}

			// On liste les résultats et on les ajoutent dans le tableau
			while($i < $num) {

				$obj = $this->db->fetch_object($resql);

				$tab_result[$i]['rowid'] = $obj->rowid;
				$tab_result[$i]['ref'] = $obj->rowid;
				$tab_result[$i]['fk_user'] = $obj->fk_user;
				$tab_result[$i]['fk_type'] = $obj->fk_type;
				$tab_result[$i]['date_create'] = $this->db->jdate($obj->date_create);
				$tab_result[$i]['description'] = $obj->description;
				$tab_result[$i]['date_debut'] = $this->db->jdate($obj->date_debut);
				$tab_result[$i]['date_fin'] = $this->db->jdate($obj->date_fin);
				$tab_result[$i]['date_debut_gmt'] = $this->db->jdate($obj->date_debut,1);
				$tab_result[$i]['date_fin_gmt'] = $this->db->jdate($obj->date_fin,1);
				$tab_result[$i]['halfday'] = $obj->halfday;
				$tab_result[$i]['statut'] = $obj->statut;
				$tab_result[$i]['fk_validator'] = $obj->fk_validator;
				$tab_result[$i]['date_valid'] = $this->db->jdate($obj->date_valid);
				$tab_result[$i]['fk_user_valid'] = $obj->fk_user_valid;
				$tab_result[$i]['date_refuse'] = $obj->date_refuse;
				$tab_result[$i]['fk_user_refuse'] = $obj->fk_user_refuse;
				$tab_result[$i]['date_cancel'] = $obj->date_cancel;
				$tab_result[$i]['fk_user_cancel'] = $obj->fk_user_cancel;
				$tab_result[$i]['detail_refuse'] = $obj->detail_refuse;

				$tab_result[$i]['user_firstname'] = $obj->user_firstname;
				$tab_result[$i]['user_lastname'] = $obj->user_lastname;
				$tab_result[$i]['user_login'] = $obj->user_login;
				$tab_result[$i]['user_statut'] = $obj->user_statut;
				$tab_result[$i]['user_photo'] = $obj->user_photo;

				$tab_result[$i]['validator_firstname'] = $obj->validator_firstname;
				$tab_result[$i]['validator_lastname'] = $obj->validator_lastname;
				$tab_result[$i]['validator_login'] = $obj->validator_login;
				$tab_result[$i]['validator_statut'] = $obj->validator_statut;
				$tab_result[$i]['validator_photo'] = $obj->validator_photo;

				$i++;
			}
			// Retourne 1 et ajoute le tableau à la variable
			$this->holiday = $tab_result;
			return 1;
		}
		else
		{
			// Erreur SQL
			$this->error="Error ".$this->db->lasterror();
			return -1;
		}
	}

	/**
	 *	Update database
	 *
	 *  @param	User	$user        	User that modify
	 *  @param  int		$notrigger	    0=launch triggers after, 1=disable triggers
	 *  @return int         			<0 if KO, >0 if OK
	 */
	function update($user=null, $notrigger=0)
	{
		global $conf, $langs;
		$error=0;

		// Update request
		$sql = "UPDATE ".MAIN_DB_PREFIX."holiday SET";

		$sql.= " description= '".$this->db->escape($this->description)."',";

		if(!empty($this->date_debut)) {
			$sql.= " date_debut = '".$this->db->idate($this->date_debut)."',";
		} else {
			$error++;
		}
		if(!empty($this->date_fin)) {
			$sql.= " date_fin = '".$this->db->idate($this->date_fin)."',";
		} else {
			$error++;
		}
		$sql.= " halfday = ".$this->halfday.",";
		if(!empty($this->statut) && is_numeric($this->statut)) {
			$sql.= " statut = ".$this->statut.",";
		} else {
			$error++;
		}
		if(!empty($this->fk_validator)) {
			$sql.= " fk_validator = '".$this->db->escape($this->fk_validator)."',";
		} else {
			$error++;
		}
		if(!empty($this->date_valid)) {
			$sql.= " date_valid = '".$this->db->idate($this->date_valid)."',";
		} else {
			$sql.= " date_valid = NULL,";
		}
		if(!empty($this->fk_user_valid)) {
			$sql.= " fk_user_valid = '".$this->db->escape($this->fk_user_valid)."',";
		} else {
			$sql.= " fk_user_valid = NULL,";
		}
		if(!empty($this->date_refuse)) {
			$sql.= " date_refuse = '".$this->db->idate($this->date_refuse)."',";
		} else {
			$sql.= " date_refuse = NULL,";
		}
		if(!empty($this->fk_user_refuse)) {
			$sql.= " fk_user_refuse = '".$this->db->escape($this->fk_user_refuse)."',";
		} else {
			$sql.= " fk_user_refuse = NULL,";
		}
		if(!empty($this->date_cancel)) {
			$sql.= " date_cancel = '".$this->db->idate($this->date_cancel)."',";
		} else {
			$sql.= " date_cancel = NULL,";
		}
		if(!empty($this->fk_user_cancel)) {
			$sql.= " fk_user_cancel = '".$this->db->escape($this->fk_user_cancel)."',";
		} else {
			$sql.= " fk_user_cancel = NULL,";
		}
		if(!empty($this->detail_refuse)) {
			$sql.= " detail_refuse = '".$this->db->escape($this->detail_refuse)."'";
		} else {
			$sql.= " detail_refuse = NULL";
		}

		$sql.= " WHERE rowid= ".$this->id;

		$this->db->begin();

		dol_syslog(get_class($this)."::update", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (! $resql) {
			$error++; $this->errors[]="Error ".$this->db->lasterror();
		}

		if (! $error)
		{
			if (! $notrigger)
			{
				// Call trigger
				$result=$this->call_trigger('HOLIDAY_MODIFY',$user);
				if ($result < 0) { $error++; }
				// End call triggers
			}
		}

		// Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
				dol_syslog(get_class($this)."::update ".$errmsg, LOG_ERR);
				$this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}
	}


	/**
	 *   Delete object in database
	 *
	 *	 @param		User	$user        	User that delete
	 *   @param     int		$notrigger	    0=launch triggers after, 1=disable triggers
	 *	 @return	int						<0 if KO, >0 if OK
	 */
	function delete($user, $notrigger=0)
	{
		global $conf, $langs;
		$error=0;

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."holiday";
		$sql.= " WHERE rowid=".$this->id;

		$this->db->begin();

		dol_syslog(get_class($this)."::delete", LOG_DEBUG);
		$resql = $this->db->query($sql);
		if (! $resql) {
			$error++; $this->errors[]="Error ".$this->db->lasterror();
		}

		if (! $error)
		{
			if (! $notrigger)
			{
				// Call trigger
				$result=$this->call_trigger('HOLIDAY_DELETE',$user);
				if ($result < 0) { $error++; }
				// End call triggers
			}
		}

		// Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
				dol_syslog(get_class($this)."::delete ".$errmsg, LOG_ERR);
				$this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return 1;
		}
	}

	/**
	 *	Check if a user is on holiday (partially or completely) into a period.
	 *  This function can be used to avoid to have 2 leave requests on same period for example.
	 *  Warning: It consumes a lot of memory because it load in ->holiday all holiday of a dedicated user at each call.
	 *
	 * 	@param 	int		$fk_user		Id user
	 * 	@param 	date	$dateStart		Start date of period to check
	 * 	@param 	date	$dateEnd		End date of period to check
	 *  @param	int		$halfday		Tag to define how start and end the period to check:
	 *  	    						0:Full days, 2:Start afternoon end morning, -1:Start afternoon end afternoon, 1:Start morning end morning
	 * 	@return boolean					False = New range overlap an existing holiday, True = no overlapping (is never on holiday during checked period).
	 *  @see verifDateHolidayForTimestamp
	 */
	function verifDateHolidayCP($fk_user, $dateStart, $dateEnd, $halfday=0)
	{
		$this->fetchByUser($fk_user,'','');

		foreach($this->holiday as $infos_CP)
		{
			if ($infos_CP['statut'] == 4) continue;		// ignore not validated holidays
			if ($infos_CP['statut'] == 5) continue;		// ignore not validated holidays
			/*
			 var_dump("--");
			 var_dump("old: ".dol_print_date($infos_CP['date_debut'],'dayhour').' '.dol_print_date($infos_CP['date_fin'],'dayhour').' '.$infos_CP['halfday']);
			 var_dump("new: ".dol_print_date($dateStart,'dayhour').' '.dol_print_date($dateEnd,'dayhour').' '.$halfday);
			 */

			if ($halfday == 0)
			{
				if ($dateStart >= $infos_CP['date_debut'] && $dateStart <= $infos_CP['date_fin'])
				{
					return false;
				}
				if ($dateEnd <= $infos_CP['date_fin'] && $dateEnd >= $infos_CP['date_debut'])
				{
					return false;
				}
			}
			elseif ($halfday == -1)
			{
				// new start afternoon, new end afternoon
				if ($dateStart >= $infos_CP['date_debut'] && $dateStart <= $infos_CP['date_fin'])
				{
					if ($dateStart < $infos_CP['date_fin'] || in_array($infos_CP['halfday'], array(0, -1))) return false;
				}
				if ($dateEnd <= $infos_CP['date_fin'] && $dateEnd >= $infos_CP['date_debut'])
				{
					if ($dateStart < $dateEnd) return false;
					if ($dateEnd < $infos_CP['date_fin'] || in_array($infos_CP['halfday'], array(0, -1))) return false;
				}
			}
			elseif ($halfday == 1)
			{
				// new start morning, new end morning
				if ($dateStart >= $infos_CP['date_debut'] && $dateStart <= $infos_CP['date_fin'])
				{
					if ($dateStart < $dateEnd) return false;
					if ($dateStart > $infos_CP['date_debut'] || in_array($infos_CP['halfday'], array(0, 1))) return false;
				}
				if ($dateEnd <= $infos_CP['date_fin'] && $dateEnd >= $infos_CP['date_debut'])
				{
					if ($dateEnd > $infos_CP['date_debut'] || in_array($infos_CP['halfday'], array(0, 1))) return false;
				}
			}
			elseif ($halfday == 2)
			{
				// new start afternoon, new end morning
				if ($dateStart >= $infos_CP['date_debut'] && $dateStart <= $infos_CP['date_fin'])
				{
					if ($dateStart < $infos_CP['date_fin'] || in_array($infos_CP['halfday'], array(0, -1))) return false;
				}
				if ($dateEnd <= $infos_CP['date_fin'] && $dateEnd >= $infos_CP['date_debut'])
				{
					if ($dateEnd > $infos_CP['date_debut'] || in_array($infos_CP['halfday'], array(0, 1))) return false;
				}
			}
			else
			{
				dol_print_error('', 'Bad value of parameter halfday when calling function verifDateHolidayCP');
			}
		}

		return true;
	}


	/**
	 *	Check that a user is not on holiday for a particular timestamp
	 *
	 * 	@param 	int			$fk_user				Id user
	 *  @param	timestamp	$timestamp				Time stamp date for a day (YYYY-MM-DD) without hours  (= 12:00AM in english and not 12:00PM that is 12:00)
	 *  @param	string		$status					Filter on holiday status. '-1' = no filter.
	 * 	@return array								array('morning'=> ,'afternoon'=> ), Boolean is true if user is available for day timestamp.
	 *  @see verifDateHolidayCP
	 */
	function verifDateHolidayForTimestamp($fk_user, $timestamp, $status='-1')
	{
		global $langs, $conf;

		$isavailablemorning=true;
		$isavailableafternoon=true;

		$sql = "SELECT cp.rowid, cp.date_debut as date_start, cp.date_fin as date_end, cp.halfday, cp.statut";
		$sql.= " FROM ".MAIN_DB_PREFIX."holiday as cp";
		$sql.= " WHERE cp.entity IN (".getEntity('holiday').")";
		$sql.= " AND cp.fk_user = ".(int) $fk_user;
		$sql.= " AND cp.date_debut <= '".$this->db->idate($timestamp)."' AND cp.date_fin >= '".$this->db->idate($timestamp)."'";
		if ($status != '-1') $sql.=" AND cp.statut IN (".$this->db->escape($status).")";

		$resql = $this->db->query($sql);
		if ($resql)
		{
			$num_rows = $this->db->num_rows($resql);	// Note, we can have 2 records if on is morning and the other one is afternoon
			if ($num_rows > 0)
			{
				$arrayofrecord=array();
				$i=0;
				while ($i < $num_rows)
				{
					$obj = $this->db->fetch_object($resql);

					// Note: $obj->halfday is  0:Full days, 2:Sart afternoon end morning, -1:Start afternoon, 1:End morning
					$arrayofrecord[$obj->rowid]=array('date_start'=>$this->db->jdate($obj->date_start), 'date_end'=>$this->db->jdate($obj->date_end), 'halfday'=>$obj->halfday);
					$i++;
				}

				// We found a record, user is on holiday by default, so is not available is true.
				$isavailablemorning = true;
				foreach($arrayofrecord as $record)
				{
					if ($timestamp == $record['date_start'] && $record['halfday'] == 2)  continue;
					if ($timestamp == $record['date_start'] && $record['halfday'] == -1) continue;
					$isavailablemorning = false;
					break;
				}
				$isavailableafternoon = true;
				foreach($arrayofrecord as $record)
				{
					if ($timestamp == $record['date_end'] && $record['halfday'] == 2) continue;
					if ($timestamp == $record['date_end'] && $record['halfday'] == 1) continue;
					$isavailableafternoon = false;
					break;
				}
			}
		}
		else dol_print_error($this->db);

		return array('morning'=>$isavailablemorning, 'afternoon'=>$isavailableafternoon);
	}


	/**
	 *	Return clicable name (with picto eventually)
	 *
	 *	@param	int			$withpicto					0=_No picto, 1=Includes the picto in the linkn, 2=Picto only
	 *  @param  int     	$save_lastsearch_value    	-1=Auto, 0=No save of lastsearch_values when clicking, 1=Save lastsearch_values whenclicking
	 *	@return	string									String with URL
	 */
	function getNomUrl($withpicto=0, $save_lastsearch_value=-1)
	{
		global $langs;

		$result='';

		$label=$langs->trans("Show").': '.$this->ref;

		$url = DOL_URL_ROOT.'/holiday/card.php?id='.$this->id;

		//if ($option != 'nolink')
		//{
		// Add param to save lastsearch_values or not
		$add_save_lastsearch_values=($save_lastsearch_value == 1 ? 1 : 0);
		if ($save_lastsearch_value == -1 && preg_match('/list\.php/',$_SERVER["PHP_SELF"])) $add_save_lastsearch_values=1;
		if ($add_save_lastsearch_values) $url.='&save_lastsearch_values=1';
		//}

		$linkstart = '<a href="'.$url.'" title="'.dol_escape_htmltag($label, 1).'" class="classfortooltip">';
		$linkend='</a>';

		$result .= $linkstart;
		if ($withpicto) $result.=img_object(($notooltip?'':$label), $this->picto, ($notooltip?(($withpicto != 2) ? 'class="paddingright"' : ''):'class="'.(($withpicto != 2) ? 'paddingright ' : '').'classfortooltip"'), 0, 0, $notooltip?0:1);
		if ($withpicto != 2) $result.= $this->ref;
		$result .= $linkend;

		return $result;
	}


	/**
	 *	Returns the label status
	 *
	 *	@param      int		$mode       0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto
	 *	@return     string      		Label
	 */
	function getLibStatut($mode=0)
	{
		return $this->LibStatut($this->statut, $mode, $this->date_debut);
	}

	/**
	 *	Returns the label of a statut
	 *
	 *	@param      int		$statut     id statut
	 *	@param      int		$mode       0=long label, 1=short label, 2=Picto + short label, 3=Picto, 4=Picto + long label, 5=Short label + Picto
	 *  @param		date	$startdate	Date holiday should start
	 *	@return     string      		Label
	 */
	function LibStatut($statut, $mode=0, $startdate='')
	{
		global $langs;

		if ($mode == 0)
		{
			if ($statut == 1) return $langs->trans('DraftCP');
			if ($statut == 2) return $langs->trans('ToReviewCP');
			if ($statut == 3) return $langs->trans('ApprovedCP');
			if ($statut == 4) return $langs->trans('CancelCP');
			if ($statut == 5) return $langs->trans('RefuseCP');
		}
		if ($mode == 2)
		{
			$pictoapproved='statut6';
			if (! empty($startdate) && $startdate > dol_now()) $pictoapproved='statut4';
			if ($statut == 1) return img_picto($langs->trans('DraftCP'),'statut0').' '.$langs->trans('DraftCP');				// Draft
			if ($statut == 2) return img_picto($langs->trans('ToReviewCP'),'statut1').' '.$langs->trans('ToReviewCP');		// Waiting approval
			if ($statut == 3) return img_picto($langs->trans('ApprovedCP'),$pictoapproved).' '.$langs->trans('ApprovedCP');
			if ($statut == 4) return img_picto($langs->trans('CancelCP'),'statut5').' '.$langs->trans('CancelCP');
			if ($statut == 5) return img_picto($langs->trans('RefuseCP'),'statut5').' '.$langs->trans('RefuseCP');
		}
		if ($mode == 3)
		{
			$pictoapproved='statut6';
			if (! empty($startdate) && $startdate > dol_now()) $pictoapproved='statut4';
			if ($statut == 1) return img_picto($langs->trans('DraftCP'),'statut0');
			if ($statut == 2) return img_picto($langs->trans('ToReviewCP'),'statut1');
			if ($statut == 3) return img_picto($langs->trans('ApprovedCP'),$pictoapproved);
			if ($statut == 4) return img_picto($langs->trans('CancelCP'),'statut5');
			if ($statut == 5) return img_picto($langs->trans('RefuseCP'),'statut5');
		}
		if ($mode == 5)
		{
			$pictoapproved='statut6';
			if (! empty($startdate) && $startdate > dol_now()) $pictoapproved='statut4';
			if ($statut == 1) return $langs->trans('DraftCP').' '.img_picto($langs->trans('DraftCP'),'statut0');				// Draft
			if ($statut == 2) return $langs->trans('ToReviewCP').' '.img_picto($langs->trans('ToReviewCP'),'statut1');		// Waiting approval
			if ($statut == 3) return $langs->trans('ApprovedCP').' '.img_picto($langs->trans('ApprovedCP'),$pictoapproved);
			if ($statut == 4) return $langs->trans('CancelCP').' '.img_picto($langs->trans('CancelCP'),'statut5');
			if ($statut == 5) return $langs->trans('RefuseCP').' '.img_picto($langs->trans('RefuseCP'),'statut5');
		}
		if ($mode == 6)
		{
			$pictoapproved='statut6';
			if (! empty($startdate) && $startdate > dol_now()) $pictoapproved='statut4';
			if ($statut == 1) return $langs->trans('DraftCP').' '.img_picto($langs->trans('DraftCP'),'statut0');				// Draft
			if ($statut == 2) return $langs->trans('ToReviewCP').' '.img_picto($langs->trans('ToReviewCP'),'statut1');		// Waiting approval
			if ($statut == 3) return $langs->trans('ApprovedCP').' '.img_picto($langs->trans('ApprovedCP'),$pictoapproved);
			if ($statut == 4) return $langs->trans('CancelCP').' '.img_picto($langs->trans('CancelCP'),'statut5');
			if ($statut == 5) return $langs->trans('RefuseCP').' '.img_picto($langs->trans('RefuseCP'),'statut5');
		}

		return $statut;
	}


	/**
	 *   Affiche un select HTML des statuts de congés payés
	 *
	 *   @param 	int		$selected   	Id of preselected status
	 *   @param		string	$htmlname		Name of HTML select field
	 *   @return    string					Show select of status
	 */
	function selectStatutCP($selected='', $htmlname='select_statut') {

		global $langs;

		// Liste des statuts
		$name = array('DraftCP','ToReviewCP','ApprovedCP','CancelCP','RefuseCP');
		$nb = count($name)+1;

		// Select HTML
		$statut = '<select name="'.$htmlname.'" class="flat">'."\n";
		$statut.= '<option value="-1">&nbsp;</option>'."\n";

		// Boucle des statuts
		for($i=1; $i < $nb; $i++) {
			if($i==$selected) {
				$statut.= '<option value="'.$i.'" selected>'.$langs->trans($name[$i-1]).'</option>'."\n";
			}
			else {
				$statut.= '<option value="'.$i.'">'.$langs->trans($name[$i-1]).'</option>'."\n";
			}
		}

		$statut.= '</select>'."\n";
		print $statut;

	}

	/**
	 *  Met à jour une option du module Holiday Payés
	 *
	 *  @param	string	$name       name du paramètre de configuration
	 *  @param	string	$value      vrai si mise à jour OK sinon faux
	 *  @return boolean				ok or ko
	 */
	function updateConfCP($name,$value) {

		$sql = "UPDATE ".MAIN_DB_PREFIX."holiday_config SET";
		$sql.= " value = '".$value."'";
		$sql.= " WHERE name = '".$name."'";

		dol_syslog(get_class($this).'::updateConfCP name='.$name.'', LOG_DEBUG);
		$result = $this->db->query($sql);
		if($result) {
			return true;
		}

		return false;
	}

	/**
	 *  Return value of a conf parameterfor leave module
	 *  TODO Move this into llx_const table
	 *
	 *  @param	string	$name                 Name of parameter
	 *  @param  string  $createifnotfound     'stringvalue'=Create entry with string value if not found. For example 'YYYYMMDDHHMMSS'.
	 *  @return string      		          Value of parameter. Example: 'YYYYMMDDHHMMSS' or < 0 if error
	 */
	function getConfCP($name, $createifnotfound='')
	{
		$sql = "SELECT value";
		$sql.= " FROM ".MAIN_DB_PREFIX."holiday_config";
		$sql.= " WHERE name = '".$this->db->escape($name)."'";

		dol_syslog(get_class($this).'::getConfCP name='.$name.' createifnotfound='.$createifnotfound, LOG_DEBUG);
		$result = $this->db->query($sql);

		if($result) {

			$obj = $this->db->fetch_object($result);
			// Return value
			if (empty($obj))
			{
				if ($createifnotfound)
				{
					$sql = "INSERT INTO ".MAIN_DB_PREFIX."holiday_config(name, value)";
					$sql.= " VALUES('".$this->db->escape($name)."', '".$this->db->escape($createifnotfound)."')";
					$result = $this->db->query($sql);
					if ($result)
					{
						return $createifnotfound;
					}
					else
					{
						$this->error=$this->db->lasterror();
						return -2;
					}
				}
				else
				{
					return '';
				}
			}
			else
			{
				return $obj->value;
			}
		} else {

			// Erreur SQL
			$this->error=$this->db->lasterror();
			return -1;
		}
	}

	/**
	 *	Met à jour le timestamp de la dernière mise à jour du solde des CP
	 *
	 *	@param		int		$userID		Id of user
	 *	@param		int		$nbHoliday	Nb of days
	 *  @param		int		$fk_type	Type of vacation
	 *  @return     int					0=Nothing done, 1=OK, -1=KO
	 */
	function updateSoldeCP($userID='',$nbHoliday='', $fk_type='')
	{
		global $user, $langs;

		$error = 0;

		if (empty($userID) && empty($nbHoliday) && empty($fk_type))
		{
			$langs->load("holiday");

			// Si mise à jour pour tout le monde en début de mois
			$now=dol_now();

			$month = date('m',$now);
			$newdateforlastupdate = dol_print_date($now, '%Y%m%d%H%M%S');

			// Get month of last update
			$lastUpdate = $this->getConfCP('lastUpdate', $newdateforlastupdate);
			$monthLastUpdate = $lastUpdate[4].$lastUpdate[5];
			//print 'month: '.$month.' lastUpdate:'.$lastUpdate.' monthLastUpdate:'.$monthLastUpdate;exit;

			// Si la date du mois n'est pas la même que celle sauvegardée, on met à jour le timestamp
			if ($month != $monthLastUpdate)
			{
				$this->db->begin();

				$users = $this->fetchUsers(false,false);
				$nbUser = count($users);

				$sql = "UPDATE ".MAIN_DB_PREFIX."holiday_config SET";
				$sql.= " value = '".$this->db->escape($newdateforlastupdate)."'";
				$sql.= " WHERE name = 'lastUpdate'";
				$result = $this->db->query($sql);

				$typeleaves=$this->getTypes(1,1);
				foreach($typeleaves as $key => $val)
				{
					// On ajoute x jours à chaque utilisateurs
					$nb_holiday = $val['newByMonth'];
					if (empty($nb_holiday)) $nb_holiday=0;

					if ($nb_holiday > 0)
					{
						dol_syslog("We update leavefor everybody for type ".$key, LOG_DEBUG);

						$i = 0;
						while ($i < $nbUser)
						{
							$now_holiday = $this->getCPforUser($users[$i]['rowid'], $val['rowid']);
							$new_solde = $now_holiday + $nb_holiday;

							// We add a log for each user
							$this->addLogCP($user->id, $users[$i]['rowid'], $langs->trans('HolidaysMonthlyUpdate'), $new_solde, $val['rowid']);

							$i++;
						}

						// Now we update counter for all users at once
						$sql2 = "UPDATE ".MAIN_DB_PREFIX."holiday_users SET";
						$sql2.= " nb_holiday = nb_holiday + ".$nb_holiday;
						$sql2.= " WHERE fk_type = ".$val['rowid'];

						$result= $this->db->query($sql2);

						if (! $result)
						{
							dol_print_error($this->db);
							break;
						}
					}
					else dol_syslog("No change for leave of type ".$key, LOG_DEBUG);
				}

				if ($result)
				{
					$this->db->commit();
					return 1;
				}
				else
				{
					$this->db->rollback();
					return -1;
				}
			}

			return 0;
		}
		else
		{
			// Mise à jour pour un utilisateur
			$nbHoliday = price2num($nbHoliday,5);

			$sql = "SELECT nb_holiday FROM ".MAIN_DB_PREFIX."holiday_users";
			$sql.= " WHERE fk_user = '".$userID."' AND fk_type = ".$fk_type;
			$resql = $this->db->query($sql);
			if ($resql)
			{
				$num = $this->db->num_rows($resql);

				if ($num > 0)
				{
					// Update for user
					$sql = "UPDATE ".MAIN_DB_PREFIX."holiday_users SET";
					$sql.= " nb_holiday = ".$nbHoliday;
					$sql.= " WHERE fk_user = '".$userID."' AND fk_type = ".$fk_type;
					$result = $this->db->query($sql);
					if (! $result)
					{
						$error++;
						$this->errors[]=$this->db->lasterror();
					}
				}
				else
				{
					// Insert for user
					$sql = "INSERT INTO ".MAIN_DB_PREFIX."holiday_users(nb_holiday, fk_user, fk_type) VALUES (";
					$sql.= $nbHoliday;
					$sql.= ", '".$userID."', ".$fk_type.")";
					$result = $this->db->query($sql);
					if (! $result)
					{
						$error++;
						$this->errors[]=$this->db->lasterror();
					}
				}
			}
			else
			{
				$this->errors[]=$this->db->lasterror();
				$error++;
			}

			if (! $error)
			{
				return 1;
			}
			else
			{
				return -1;
			}
		}

	}

	/**
	 *	Retourne un checked si vrai
	 *
	 *  @param	string	$name       name du paramètre de configuration
	 *  @return string      		retourne checked si > 0
	 */
	function getCheckOption($name) {

		$sql = "SELECT value";
		$sql.= " FROM ".MAIN_DB_PREFIX."holiday_config";
		$sql.= " WHERE name = '".$name."'";

		$result = $this->db->query($sql);

		if($result) {
			$obj = $this->db->fetch_object($result);

			// Si la valeur est 1 on retourne checked
			if($obj->value) {
				return 'checked';
			}
		}
	}


	/**
	 *  Créer les entrées pour chaque utilisateur au moment de la configuration
	 *
	 *  @param	boolean		$single		Single
	 *  @param	int			$userid		Id user
	 *  @return void
	 */
	function createCPusers($single=false,$userid='')
	{
		// Si c'est l'ensemble des utilisateurs à ajouter
		if (! $single)
		{
			dol_syslog(get_class($this).'::createCPusers');
			$arrayofusers = $this->fetchUsers(false,true);

			foreach($arrayofusers as $users)
			{
				$sql = "INSERT INTO ".MAIN_DB_PREFIX."holiday_users";
				$sql.= " (fk_user, nb_holiday)";
				$sql.= " VALUES ('".$users['rowid']."','0')";

				$resql=$this->db->query($sql);
				if (! $resql) dol_print_error($this->db);
			}
		}
		else
		{
			$sql = "INSERT INTO ".MAIN_DB_PREFIX."holiday_users";
			$sql.= " (fk_user, nb_holiday)";
			$sql.= " VALUES ('".$userid."','0')";

			$resql=$this->db->query($sql);
			if (! $resql) dol_print_error($this->db);
		}
	}

	/**
	 *  Supprime un utilisateur du module Congés Payés
	 *
	 *  @param	int		$user_id        ID de l'utilisateur à supprimer
	 *  @return boolean      			Vrai si pas d'erreur, faut si Erreur
	 */
	function deleteCPuser($user_id) {

		$sql = "DELETE FROM ".MAIN_DB_PREFIX."holiday_users";
		$sql.= " WHERE fk_user = '".$user_id."'";

		$this->db->query($sql);

	}


	/**
	 *  Retourne le solde de congés payés pour un utilisateur
	 *
	 *  @param	int		$user_id    ID de l'utilisateur
	 *  @param	int		$fk_type	Filter on type
	 *  @return float        		Retourne le solde de congés payés de l'utilisateur
	 */
	function getCPforUser($user_id, $fk_type=0)
	{
		$sql = "SELECT nb_holiday";
		$sql.= " FROM ".MAIN_DB_PREFIX."holiday_users";
		$sql.= " WHERE fk_user = '".$user_id."'";
		if ($fk_type > 0) $sql.=" AND fk_type = ".$fk_type;

		dol_syslog(get_class($this).'::getCPforUser', LOG_DEBUG);
		$result = $this->db->query($sql);
		if($result)
		{
			$obj = $this->db->fetch_object($result);
			//return number_format($obj->nb_holiday,2);
			if ($obj) return $obj->nb_holiday;
			else return null;
		}
		else
		{
			return null;
		}
	}

	/**
	 *    Get list of Users or list of vacation balance.
	 *
	 *    @param      boolean			$stringlist	    If true return a string list of id. If false, return an array with detail.
	 *    @param      boolean   		$type			If true, read dolibarr user list, if false, return vacation balance list.
	 *    @param      string            $filters        Filters
	 *    @return     array|string|int      			Return an array
	 */
	function fetchUsers($stringlist=true, $type=true, $filters='')
	{
		global $conf;

		dol_syslog(get_class($this)."::fetchUsers", LOG_DEBUG);

		if ($stringlist)
		{
			if ($type)
			{
				// Si utilisateur de dolibarr

				$sql = "SELECT u.rowid";
				$sql.= " FROM ".MAIN_DB_PREFIX."user as u";

				if (! empty($conf->multicompany->enabled) && ! empty($conf->global->MULTICOMPANY_TRANSVERSE_MODE))
				{
					$sql.= ", ".MAIN_DB_PREFIX."usergroup_user as ug";
					$sql.= " WHERE (ug.fk_user = u.rowid";
					$sql.= " AND ug.entity = ".$conf->entity.")";
					$sql.= " OR u.admin = 1";
				}
				else
				{
					$sql.= " WHERE u.entity IN (0,".$conf->entity.")";
				}
				$sql.= " AND u.statut > 0";
				if ($filters) $sql.=$filters;

				$resql=$this->db->query($sql);

				// Si pas d'erreur SQL
				if ($resql) {

					$i = 0;
					$num = $this->db->num_rows($resql);
					$stringlist = '';

					// Boucles du listage des utilisateurs
					while($i < $num)
					{
						$obj = $this->db->fetch_object($resql);

						if ($i == 0) {
							$stringlist.= $obj->rowid;
						} else {
							$stringlist.= ', '.$obj->rowid;
						}

						$i++;
					}
					// Retoune le tableau des utilisateurs
					return $stringlist;
				}
				else
				{
					// Erreur SQL
					$this->error="Error ".$this->db->lasterror();
					return -1;
				}

			}
			else
			{
				// We want only list of vacation balance for user ids
				$sql = "SELECT DISTINCT cpu.fk_user";
				$sql.= " FROM ".MAIN_DB_PREFIX."holiday_users as cpu, ".MAIN_DB_PREFIX."user as u";
				$sql.= " WHERE cpu.fk_user = u.user";
				if ($filters) $sql.=$filters;

				$resql=$this->db->query($sql);

				// Si pas d'erreur SQL
				if ($resql) {

					$i = 0;
					$num = $this->db->num_rows($resql);
					$stringlist = '';

					// Boucles du listage des utilisateurs
					while($i < $num)
					{
						$obj = $this->db->fetch_object($resql);

						if($i == 0) {
							$stringlist.= $obj->fk_user;
						} else {
							$stringlist.= ', '.$obj->fk_user;
						}

						$i++;
					}
					// Retoune le tableau des utilisateurs
					return $stringlist;
				}
				else
				{
					// Erreur SQL
					$this->error="Error ".$this->db->lasterror();
					return -1;
				}
			}

		}
		else
		{ // Si faux donc return array

			// List for dolibarr users
			if ($type)
			{
				$sql = "SELECT u.rowid, u.lastname, u.firstname, u.gender, u.photo, u.employee, u.statut, u.fk_user";
				$sql.= " FROM ".MAIN_DB_PREFIX."user as u";

				if (! empty($conf->multicompany->enabled) && ! empty($conf->global->MULTICOMPANY_TRANSVERSE_MODE))
				{
					$sql.= ", ".MAIN_DB_PREFIX."usergroup_user as ug";
					$sql.= " WHERE (ug.fk_user = u.rowid";
					$sql.= " AND ug.entity = ".$conf->entity.")";
					$sql.= " OR u.admin = 1";
				}
				else
					$sql.= " WHERE u.entity IN (0,".$conf->entity.")";

					$sql.= " AND u.statut > 0";
					if ($filters) $sql.=$filters;

					$resql=$this->db->query($sql);

					// Si pas d'erreur SQL
					if ($resql)
					{
						$i = 0;
						$tab_result = $this->holiday;
						$num = $this->db->num_rows($resql);

						// Boucles du listage des utilisateurs
						while($i < $num) {

							$obj = $this->db->fetch_object($resql);

							$tab_result[$i]['rowid'] = $obj->rowid;
							$tab_result[$i]['name'] = $obj->lastname;       // deprecated
							$tab_result[$i]['lastname'] = $obj->lastname;
							$tab_result[$i]['firstname'] = $obj->firstname;
							$tab_result[$i]['gender'] = $obj->gender;
							$tab_result[$i]['status'] = $obj->statut;
							$tab_result[$i]['employee'] = $obj->employee;
							$tab_result[$i]['photo'] = $obj->photo;
							$tab_result[$i]['fk_user'] = $obj->fk_user;
							//$tab_result[$i]['type'] = $obj->type;
							//$tab_result[$i]['nb_holiday'] = $obj->nb_holiday;

							$i++;
						}
						// Retoune le tableau des utilisateurs
						return $tab_result;
					}
					else
					{
						// Erreur SQL
						$this->errors[]="Error ".$this->db->lasterror();
						return -1;
					}
			}
			else
			{
				// List of vacation balance users
				$sql = "SELECT cpu.fk_user, cpu.fk_type, cpu.nb_holiday, u.lastname, u.firstname, u.gender, u.photo, u.employee, u.statut, u.fk_user";
				$sql.= " FROM ".MAIN_DB_PREFIX."holiday_users as cpu, ".MAIN_DB_PREFIX."user as u";
				$sql.= " WHERE cpu.fk_user = u.rowid";
				if ($filters) $sql.=$filters;

				$resql=$this->db->query($sql);

				// Si pas d'erreur SQL
				if ($resql)
				{
					$i = 0;
					$tab_result = $this->holiday;
					$num = $this->db->num_rows($resql);

					// Boucles du listage des utilisateurs
					while($i < $num)
					{
						$obj = $this->db->fetch_object($resql);

						$tab_result[$i]['rowid'] = $obj->fk_user;
						$tab_result[$i]['name'] = $obj->lastname;			// deprecated
						$tab_result[$i]['lastname'] = $obj->lastname;
						$tab_result[$i]['firstname'] = $obj->firstname;
						$tab_result[$i]['gender'] = $obj->gender;
						$tab_result[$i]['status'] = $obj->statut;
						$tab_result[$i]['employee'] = $obj->employee;
						$tab_result[$i]['photo'] = $obj->photo;
						$tab_result[$i]['fk_user'] = $obj->fk_user;

						$tab_result[$i]['type'] = $obj->type;
						$tab_result[$i]['nb_holiday'] = $obj->nb_holiday;

						$i++;
					}
					// Retoune le tableau des utilisateurs
					return $tab_result;
				}
				else
				{
					// Erreur SQL
					$this->error="Error ".$this->db->lasterror();
					return -1;
				}
			}
		}
	}


	/**
	 * Return list of people with permission to validate leave requests.
	 * Search for permission "approve leave requests"
	 *
	 * @return  array       Array of user ids
	 */
	function fetch_users_approver_holiday()
	{
		$users_validator=array();

		$sql = "SELECT DISTINCT ur.fk_user";
		$sql.= " FROM ".MAIN_DB_PREFIX."user_rights as ur, ".MAIN_DB_PREFIX."rights_def as rd";
		$sql.= " WHERE ur.fk_id = rd.id and rd.module = 'holiday' AND rd.perms = 'approve'";                                              // Permission 'Approve';
		$sql.= "UNION";
		$sql.= " SELECT DISTINCT ugu.fk_user";
		$sql.= " FROM ".MAIN_DB_PREFIX."usergroup_user as ugu, ".MAIN_DB_PREFIX."usergroup_rights as ur, ".MAIN_DB_PREFIX."rights_def as rd";
		$sql.= " WHERE ugu.fk_usergroup = ur.fk_usergroup AND ur.fk_id = rd.id and rd.module = 'holiday' AND rd.perms = 'approve'";       // Permission 'Approve';
		//print $sql;

		dol_syslog(get_class($this)."::fetch_users_approver_holiday sql=".$sql);
		$result = $this->db->query($sql);
		if($result)
		{
			$num_lignes = $this->db->num_rows($result); $i = 0;
			while ($i < $num_lignes)
			{
				$objp = $this->db->fetch_object($result);
				array_push($users_validator,$objp->fk_user);
				$i++;
			}
			return $users_validator;
		}
		else
		{
			$this->error=$this->db->lasterror();
			dol_syslog(get_class($this)."::fetch_users_approver_holiday  Error ".$this->error, LOG_ERR);
			return -1;
		}
	}


	/**
	 *	Compte le nombre d'utilisateur actifs dans dolibarr
	 *
	 *  @return     int      retourne le nombre d'utilisateur
	 */
	function countActiveUsers()
	{
		$sql = "SELECT count(u.rowid) as compteur";
		$sql.= " FROM ".MAIN_DB_PREFIX."user as u";
		$sql.= " WHERE u.statut > 0";

		$result = $this->db->query($sql);
		$objet = $this->db->fetch_object($result);

		return $objet->compteur;
	}
	/**
	 *	Compte le nombre d'utilisateur actifs dans dolibarr sans CP
	 *
	 *  @return     int      retourne le nombre d'utilisateur
	 */
	function countActiveUsersWithoutCP() {

		$sql = "SELECT count(u.rowid) as compteur";
		$sql.= " FROM ".MAIN_DB_PREFIX."user as u LEFT OUTER JOIN ".MAIN_DB_PREFIX."holiday_users hu ON (hu.fk_user=u.rowid)";
		$sql.= " WHERE u.statut > 0 AND hu.fk_user IS NULL";

		$result = $this->db->query($sql);
		$objet = $this->db->fetch_object($result);

		return $objet->compteur;
	}

	/**
	 *  Compare le nombre d'utilisateur actif de dolibarr à celui des utilisateurs des congés payés
	 *
	 *  @param    int	$userdolibarrWithoutCP	Number of active users in dolibarr without holidays
	 *  @param    int	$userCP    				Number of active users into table of holidays
	 *  @return   int							<0 if KO, >0 if OK
	 */
	function verifNbUsers($userdolibarrWithoutCP, $userCP)
	{
		if (empty($userCP)) $userCP=0;
		dol_syslog(get_class($this).'::verifNbUsers userdolibarr='.$userdolibarrWithoutCP.' userCP='.$userCP);
		return 1;
	}


	/**
	 * addLogCP
	 *
	 * @param 	int		$fk_user_action		Id user creation
	 * @param 	int		$fk_user_update		Id user update
	 * @param 	string	$label				Label
	 * @param 	int		$new_solde			New value
	 * @param	int		$fk_type			Type of vacation
	 * @return 	int							Id of record added, 0 if nothing done, < 0 if KO
	 */
	function addLogCP($fk_user_action, $fk_user_update, $label, $new_solde, $fk_type)
	{
		global $conf, $langs;

		$error=0;

		$prev_solde = price2num($this->getCPforUser($fk_user_update, $fk_type), 5);
		$new_solde = price2num($new_solde, 5);
		//print "$prev_solde == $new_solde";

		if ($prev_solde == $new_solde) return 0;

		$this->db->begin();

		// Insert request
		$sql = "INSERT INTO ".MAIN_DB_PREFIX."holiday_logs (";
		$sql.= "date_action,";
		$sql.= "fk_user_action,";
		$sql.= "fk_user_update,";
		$sql.= "type_action,";
		$sql.= "prev_solde,";
		$sql.= "new_solde,";
		$sql.= "fk_type";
		$sql.= ") VALUES (";
		$sql.= " '".$this->db->idate(dol_now())."',";
		$sql.= " '".$fk_user_action."',";
		$sql.= " '".$fk_user_update."',";
		$sql.= " '".$this->db->escape($label)."',";
		$sql.= " '".$prev_solde."',";
		$sql.= " '".$new_solde."',";
		$sql.= " ".$fk_type;
		$sql.= ")";

		$resql=$this->db->query($sql);
		if (! $resql)
		{
			$error++; $this->errors[]="Error ".$this->db->lasterror();
		}

		if (! $error)
		{
			$this->optRowid = $this->db->last_insert_id(MAIN_DB_PREFIX."holiday_logs");
		}

		// Commit or rollback
		if ($error)
		{
			foreach($this->errors as $errmsg)
			{
				dol_syslog(get_class($this)."::addLogCP ".$errmsg, LOG_ERR);
				$this->error.=($this->error?', '.$errmsg:$errmsg);
			}
			$this->db->rollback();
			return -1*$error;
		}
		else
		{
			$this->db->commit();
			return $this->optRowid;
		}
	}

	/**
	 *  Liste le log des congés payés
	 *
	 *  @param	string	$order      Filtrage par ordre
	 *  @param  string	$filter     Filtre de séléction
	 *  @return int         		-1 si erreur, 1 si OK et 2 si pas de résultat
	 */
	function fetchLog($order,$filter)
	{
		global $langs;

		$sql = "SELECT";
		$sql.= " cpl.rowid,";
		$sql.= " cpl.date_action,";
		$sql.= " cpl.fk_user_action,";
		$sql.= " cpl.fk_user_update,";
		$sql.= " cpl.type_action,";
		$sql.= " cpl.prev_solde,";
		$sql.= " cpl.new_solde,";
		$sql.= " cpl.fk_type";
		$sql.= " FROM ".MAIN_DB_PREFIX."holiday_logs as cpl";
		$sql.= " WHERE cpl.rowid > 0"; // To avoid error with other search and criteria

		// Filtrage de séléction
		if(!empty($filter)) {
			$sql.= " ".$filter;
		}

		// Ordre d'affichage
		if(!empty($order)) {
			$sql.= " ".$order;
		}

		dol_syslog(get_class($this)."::fetchLog", LOG_DEBUG);
		$resql=$this->db->query($sql);

		// Si pas d'erreur SQL
		if ($resql) {

			$i = 0;
			$tab_result = $this->logs;
			$num = $this->db->num_rows($resql);

			// Si pas d'enregistrement
			if(!$num) {
				return 2;
			}

			// On liste les résultats et on les ajoutent dans le tableau
			while($i < $num) {

				$obj = $this->db->fetch_object($resql);

				$tab_result[$i]['rowid'] = $obj->rowid;
				$tab_result[$i]['date_action'] = $obj->date_action;
				$tab_result[$i]['fk_user_action'] = $obj->fk_user_action;
				$tab_result[$i]['fk_user_update'] = $obj->fk_user_update;
				$tab_result[$i]['type_action'] = $obj->type_action;
				$tab_result[$i]['prev_solde'] = $obj->prev_solde;
				$tab_result[$i]['new_solde'] = $obj->new_solde;
				$tab_result[$i]['fk_type'] = $obj->fk_type;

				$i++;
			}
			// Retourne 1 et ajoute le tableau à la variable
			$this->logs = $tab_result;
			return 1;
		}
		else
		{
			// Erreur SQL
			$this->error="Error ".$this->db->lasterror();
			return -1;
		}
	}


	/**
	 *  Return array with list of types
	 *
	 *  @param		int		$active		Status of type. -1 = Both
	 *  @param		int		$affect		Filter on affect (a request will change sold or not). -1 = Both
	 *  @return     array	    		Return array with list of types
	 */
	function getTypes($active=-1, $affect=-1)
	{
		global $mysoc;

		$sql = "SELECT rowid, code, label, affect, delay, newByMonth";
		$sql.= " FROM " . MAIN_DB_PREFIX . "c_holiday_types";
		$sql.= " WHERE (fk_country IS NULL OR fk_country = ".$mysoc->country_id.')';
		if ($active >= 0) $sql.=" AND active = ".((int) $active);
		if ($affect >= 0) $sql.=" AND affect = ".((int) $affect);

		$result = $this->db->query($sql);
		if ($result)
		{
			$num = $this->db->num_rows($result);
			if ($num)
			{
				while ($obj = $this->db->fetch_object($result))
				{
					$types[$obj->rowid] = array('rowid'=> $obj->rowid, 'code'=> $obj->code, 'label'=>$obj->label, 'affect'=>$obj->affect, 'delay'=>$obj->delay, 'newByMonth'=>$obj->newByMonth);
				}

				return $types;
			}
		}
		else dol_print_error($this->db);

		return array();
	}


	/**
	 *  Initialise an instance with random values.
	 *  Used to build previews or test instances.
	 *	id must be 0 if object instance is a specimen.
	 *
	 *  @return	void
	 */
	function initAsSpecimen()
	{
		global $user,$langs;

		// Initialise parameters
		$this->id=0;
		$this->specimen=1;

		$this->fk_user=1;
		$this->description='SPECIMEN description';
		$this->date_debut=dol_now();
		$this->date_fin=dol_now()+(24*3600);
		$this->fk_validator=1;
		$this->halfday=0;
		$this->fk_type=1;
	}

}
