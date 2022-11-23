<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Agreement_model extends CI_Model {

    function __construct() {
        parent::__construct();
    }

	function editApplicantion($file_name,$application_id){
		$list_id = $this->input->post('property');
		$cus_code = $this->input->post('cus_code');
		$application_id = $this->input->post('application_id');
		$data=array(
			'title' => $this->input->post('title'),
			'first_name' => $this->input->post('first_name'),
			'last_name' => $this->input->post('last_name'),
			'full_name' => $this->input->post('full_name'),
			'other_names' => $this->input->post('other_names'),
			'gender' => $this->input->post('gender'),
			'dob' => $this->input->post('dob'),
			'spouse_name' => $this->input->post('spouse_name'),
			'spouse_employer' => $this->input->post('spouse_employer'),
			'spouse_designation' => $this->input->post('spouse_designation'),
			'spouse_income' => $this->input->post('spouse_income'),
			'dependent' => $this->input->post('dependent'),
			'id_type' => $this->input->post('id_type'),
			'id_number' => $this->input->post('id_number'),
			'id_doi' => $this->input->post('id_doi'),
			'id_copy_front' => $file_name,
			'occupation' => $this->input->post('occupation'),
			'employer' => $this->input->post('employer'),
			'employer_address' => $this->input->post('employer_address'),
			'employer_phone' => $this->input->post('employer_phone'),
			'monthly_income' => $this->input->post('monthly_income'),
			'income_source' => $this->input->post('income_source'),
			'monthly_expence' => $this->input->post('monthly_expence'),
			'moveable_property' => $this->input->post('moveable_property'),
			'imovable_property' => $this->input->post('imovable_property'),
			'raddress1' => $this->input->post('raddress1'),
			'raddress2' => $this->input->post('raddress2'),
			'raddress3' => $this->input->post('raddress3'),
            'rpostal_code' => $this->input->post('rpostal_code'),
			'otheraddress1' => $this->input->post('otheraddress1'),
			'otheraddress2' => $this->input->post('otheraddress2'),
			'otheraddress3' => $this->input->post('otheraddress3'),
			'otheraddress4' => $this->input->post('otheraddress4'),
            'otherpostal_code'=> $this->input->post('otherpostal_code'),
			'raddress_duration' => $this->input->post('raddress_duration'),
			'raddress_ownership' => $this->input->post('raddress_ownership'),
			'address1' => $this->input->post('address1'),
			'address2' => $this->input->post('address2'),
			'address3' => $this->input->post('address3'),
            'postal_code' => $this->input->post('postal_code'),
			'landphone' => $this->input->post('landphone'),
			'workphone' => $this->input->post('workphone'),
			'mobile' => $this->input->post('mobile'),
			'fax' => $this->input->post('fax'),
			'email' => $this->input->post('email'),
			'update_by' => $this->session->userdata('userid'),
			'update_date' => date("Y-m-d")
			);
		$this->db->where('cus_code',$cus_code);
        $this->db->update('cm_customerms',$data);
		$list_data = array(
			'update_date' => date('Y-m-d'),
			'update_by' => $this->session->userdata('userid')
		);
		$this->db->trans_start();
		$this->db->where('application_id',$application_id);
		$this->db->update('pr_rent_application',$list_data);
		$insert_id = $this->db->insert_id();
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->where('application_id',$application_id);
			$this->db->delete('pr_rent_applicationchecklist');

			$this->db->where('application_id',$application_id);
			$this->db->delete('pr_rent_applicationdocs');
			$this->db->trans_commit();
			return $application_id;
		}
	}

	function getSearchedApplications(){
		$this->db->select('pr_rent_application.*');
		$this->db->join('cm_customerms','cm_customerms.cus_code = pr_rent_application.cus_code');
        $this->db->join('pr_rent_listing','pr_rent_listing.list_id = pr_rent_application.list_id');
		$this->db->join('pr_rent_propertyunit','pr_rent_propertyunit.unit_id = pr_rent_listing.unit_id');
		if($this->input->post('date')){
        	$this->db->like('pr_rent_application.apply_date',$this->input->post('date'));
		}
		if($this->input->post('name')){
        	$this->db->or_like('cm_customerms.first_name',$this->input->post('name'));
		}
		if($this->input->post('name')){
			$this->db->or_like('cm_customerms.last_name',$this->input->post('name'));
		}
		if($this->input->post('name')){
			$this->db->or_like('cm_customerms.full_name',$this->input->post('name'));
		}
		if($this->input->post('name')){
			$this->db->or_like('pr_rent_application.application_code',$this->input->post('name'));
		}
		if($this->input->post('property')){
        	$this->db->or_like('pr_rent_listing.unit_id',$this->input->post('property'));
		}
		$query = $this->db->get('pr_rent_application');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function editAgreement($agreement_id,$application_id){
		//Change status of currently used applciation
		$this->db->select('pr_rent_leaseagreement.*');
		$this->db->where('agreement_id',$agreement_id);
		$query = $this->db->get('pr_rent_leaseagreement');
		if($query->num_rows() > 0){
			$agreement_data = $query->row();
			$data_array = array(
				'status' => 'CONFIRMED'
			);
			$this->db->where('application_id',$agreement_data->application_id);
			$this->db->update('pr_rent_application',$data_array);
			$agr_code = $agreement_data->agreement_code;
		}else{
			return false;
		}

		//Get listing and property details by application_id
		$this->db->select('pr_rent_application.list_id,pr_rent_application.cus_code,pr_rent_propertyunit.property_id,pr_rent_propertyunit.unit_id');
		$this->db->join('pr_rent_listing','pr_rent_listing.list_id = pr_rent_application.list_id');
		$this->db->join('pr_rent_propertyunit','pr_rent_propertyunit.unit_id = pr_rent_listing.unit_id');
		$this->db->where('pr_rent_application.application_id',$application_id);
		$query = $this->db->get('pr_rent_application');
		if($query->num_rows() > 0){
			$application_data = $query->row();
		}else{
			return false;
		}
		//create application code
		$table = 'pr_rent_leaseagreement';
		$agreement_data = array(
			'rent_cycle' => $this->input->post('rent_cycle'),
			'application_id' => $application_id,
			'property_id' => $application_data->property_id,
			'unit_id' => $application_data->unit_id,
			'lease_type' => $this->input->post('lease_type'),
			'start_date' => $this->input->post('start_date'),
			'end_date' => $this->input->post('end_date'),
			'master_tenant' => $application_data->cus_code,
			'list_id' => $application_data->list_id,
			'update_date' => date('Y-m-d h:i:s'),
			'update_by' => $this->session->userdata('userid')
		);
		$this->db->trans_start();
		$this->db->where('agreement_id',$agreement_id);
		$this->db->update('pr_rent_leaseagreement',$agreement_data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			//remove payment type data
			$this->db->where('agreement_id',$agreement_id);
			$this->db->delete('pr_rent_charges');
			//Add Rental
			$rental_count = $this->input->post('split_count');
			for($x = 1; $x <= $rental_count; $x++){
				$rent_data = array(
					'paytype_id' => $this->input->post('paytype_id'.$x),
					'agreement_id' => $agreement_id,
					'include_rent' => 'yes',
					'default_amount' => $this->input->post('default_amount'.$x),
					'amount_type' => 'fixed',
					'occurrence' => 'recurring',
					'first_due_date' => $this->input->post('first_due_date'.$x),
					'description' => $this->input->post('description'.$x),
				);
				$this->db->insert('pr_rent_charges',$rent_data);
			}
			
			//Add Security charges
			$security_deposit = $this->input->post('security_deposit');
			$security_amount = $this->input->post('security_amount');
			if($security_deposit != '' && $security_amount !=''){
				$rent_data = array(
					'paytype_id' => '2',
					'agreement_id' => $agreement_id,
					'include_rent' => 'no',
					'default_amount' => $security_amount,
					'amount_type' => 'fixed',
					'occurrence' => 'onetime',
					'first_due_date' => $security_deposit,
					'description' => 'Security Deposit',
				);
				$this->db->insert('pr_rent_charges',$rent_data);
			}
		
			//Add charges
			$charge_count = $this->input->post('charge_count');
			for($x = 1; $x <= $charge_count; $x++){
				$rent_data = array(
					'paytype_id' => $this->input->post('charge_id'.$x),
					'agreement_id' => $agreement_id,
					'include_rent' => 'no',
					'default_amount' => $this->input->post('charge_amount'.$x),
					'amount_type' => $this->input->post('charge_amount_type'.$x),
					'occurrence' => $this->input->post('charge_occurrence'.$x),
					'first_due_date' => $this->input->post('charge_due_date'.$x),
					'description' => $this->input->post('charge_description'.$x),
				);
				$this->db->insert('pr_rent_charges',$rent_data);
			}

			$this->db->where('agreement_id',$agreement_id);
			$this->db->delete('pr_rent_leaseagreementdocs');
			
			$this->db->trans_commit();
			return $agreement_id;
		}
	}

	function getSearchedAgreements(){
		$this->db->select('pr_rent_leaseagreement.*');
		$this->db->join('pr_rent_application','pr_rent_application.application_id = pr_rent_leaseagreement.application_id');
		$this->db->join('cm_customerms','cm_customerms.cus_code = pr_rent_application.cus_code');
        $this->db->join('pr_rent_listing','pr_rent_listing.list_id = pr_rent_application.list_id');
		$this->db->join('pr_rent_propertyunit','pr_rent_propertyunit.unit_id = pr_rent_listing.unit_id');
		if($this->input->post('date')){
        	$this->db->like('pr_rent_leaseagreement.start_date',$this->input->post('date'));
		}
		if($this->input->post('name')){
        	$this->db->or_like('cm_customerms.first_name',$this->input->post('name'));
		}
		if($this->input->post('name')){
			$this->db->or_like('cm_customerms.last_name',$this->input->post('name'));
		}
		if($this->input->post('name')){
			$this->db->or_like('cm_customerms.full_name',$this->input->post('name'));
		}
		if($this->input->post('name')){
			$this->db->or_like('pr_rent_application.application_code',$this->input->post('name'), 'none');
		}
		if($this->input->post('name')){
			$this->db->or_like('pr_rent_leaseagreement.agreement_code',$this->input->post('name'), 'none');
		}
		if($this->input->post('property')){
        	$this->db->or_like('pr_rent_listing.unit_id',$this->input->post('property'));
		}
		$query = $this->db->get('pr_rent_leaseagreement');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function addAgreement($application_id){
		//Get listing and property details by application_id
		$this->db->select('pr_rent_application.list_id,pr_rent_application.cus_code,pr_rent_propertyunit.property_id,pr_rent_propertyunit.unit_id');
		$this->db->join('pr_rent_listing','pr_rent_listing.list_id = pr_rent_application.list_id');
		$this->db->join('pr_rent_propertyunit','pr_rent_propertyunit.unit_id = pr_rent_listing.unit_id');
		$this->db->where('pr_rent_application.application_id',$application_id);
		$query = $this->db->get('pr_rent_application');
		if($query->num_rows() > 0){
			$application_data = $query->row();
		}else{
			return false;
		}
		//create application code
		$agr_code = $this->getmaincode('agreement_code','AGR','pr_rent_leaseagreement');
		$table = 'pr_rent_leaseagreement';
        $next_action = checkApproveLevel($table,'PENDING'); //common helper
		$agreement_data = array(
			'agreement_code' => $agr_code,
			'branch_code' => $this->session->userdata('branchid'),
			'rent_cycle' => $this->input->post('rent_cycle'),
			'application_id' => $application_id,
			'property_id' => $application_data->property_id,
			'unit_id' => $application_data->unit_id,
			'lease_type' => $this->input->post('lease_type'),
			'start_date' => $this->input->post('start_date'),
			'end_date' => $this->input->post('end_date'),
			'master_tenant' => $application_data->cus_code,
			'list_id' => $application_data->list_id,
			'create_date' => date('Y-m-d'),
			'create_by' => $this->session->userdata('userid'),
			'status' => $next_action 
		);
		$this->db->trans_start();
		$this->db->insert('pr_rent_leaseagreement',$agreement_data);
		$agreement_id = $this->db->insert_id();
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			//Add Rental
			$rental_count = $this->input->post('split_count');
			for($x = 1; $x <= $rental_count; $x++){
				$rent_data = array(
					'paytype_id' => $this->input->post('paytype_id'.$x),
					'agreement_id' => $agreement_id,
					'include_rent' => 'yes',
					'default_amount' => $this->input->post('default_amount'.$x),
					'amount_type' => 'fixed',
					'occurrence' => 'recurring',
					'first_due_date' => $this->input->post('first_due_date'.$x),
					'description' => $this->input->post('description'.$x),
				);
				$this->db->insert('pr_rent_charges',$rent_data);
			}
			
			//Add Security charges
			$security_deposit = $this->input->post('security_deposit');
			$security_amount = $this->input->post('security_amount');
			if($security_deposit != '' && $security_amount !=''){
				$rent_data = array(
					'paytype_id' => '2',
					'agreement_id' => $agreement_id,
					'include_rent' => 'no',
					'default_amount' => $security_amount,
					'amount_type' => 'fixed',
					'occurrence' => 'onetime',
					'first_due_date' => $security_deposit,
					'description' => 'Security Deposit',
				);
				$this->db->insert('pr_rent_charges',$rent_data);
			}
		
			//Add charges
			$charge_count = $this->input->post('charge_count');
			for($x = 1; $x <= $charge_count; $x++){
				$rent_data = array(
					'paytype_id' => $this->input->post('charge_id'.$x),
					'agreement_id' => $agreement_id,
					'include_rent' => 'no',
					'default_amount' => $this->input->post('charge_amount'.$x),
					'amount_type' => $this->input->post('charge_amount_type'.$x),
					'occurrence' => $this->input->post('charge_occurrence'.$x),
					'first_due_date' => $this->input->post('charge_due_date'.$x),
					'description' => $this->input->post('charge_description'.$x),
				);
				$this->db->insert('pr_rent_charges',$rent_data);
			}
			$this->db->trans_commit();
			return $agreement_id;
		}
	
	}

    function getmaincode($idfield,$prifix,$table)
	{
 	    $query = $this->db->query("SELECT MAX(".$idfield.") as id  FROM ".$table);
		$newid="";
        if ($query->num_rows > 0) {
             $data = $query->row();
			 $prjid=$data->id;
			 if($data->id==NULL)
			 {
			 $newid=$prifix.str_pad(1, 7, "0", STR_PAD_LEFT);


			 }
			 else{
			 $prjid=substr($prjid,3,7);
			 $id=intval($prjid);
			 $newid=$id+1;
			 $newid=$prifix.str_pad($newid, 7, "0", STR_PAD_LEFT);


			 }
        }
		else
		{
		$newid=str_pad(1, 7, "0", STR_PAD_LEFT);
		$newid=$prifix.$newid;
		}
	    return $newid;
	}

	function checkDucumentsList($doctype_id,$agreement_id){
		$this->db->select('*');
		$this->db->where('agreement_id',$agreement_id);
		$this->db->where('doctype_id',$doctype_id);
		$query = $this->db->get('pr_rent_leaseagreementdocs');

		if ($query->num_rows > 0 ){
			return $query->row();
		}else{
			return false;
		}
	}

	function getSecurityDeposit($agreement_id){
		$this->db->select('*');
		$this->db->where('agreement_id',$agreement_id);
		$this->db->where('paytype_id','2');
		$query = $this->db->get('pr_rent_charges');

		if ($query->num_rows > 0 ){
			return $query->row();
		}else{
			return false;
		}
	}

	function getApplicationByAgreementID($agreement_id){
		$this->db->select('*');
		$this->db->where('agreement_id',$agreement_id);
		$query = $this->db->get('pr_rent_leaseagreement');
		if ($query->num_rows > 0 ){
			$agreement =  $query->row();
			$this->db->select('*');
			$this->db->where('application_id',$agreement->application_id);
			$query = $this->db->get('pr_rent_application');
			if ($query->num_rows > 0 ){
				return $query->row();
			}else{
				return false;
			}
		}else{
			return false;
		}
	}

	function getAgreementById($agreement_id){
		$this->db->select('pr_rent_leaseagreement.*,pr_rent_application.cus_code');
		$this->db->join('pr_rent_application','pr_rent_application.application_id = pr_rent_leaseagreement.application_id');
		$this->db->where('agreement_id',$agreement_id);
		$query = $this->db->get('pr_rent_leaseagreement');
		if ($query->num_rows > 0 ){
			return $query->row();
		}else{
			return false;
		}
	}

	function terminateAgreement($agreement_id){
		$data = array(
			'status' => 'TERMINATED',
			'terminate_date' => date('Y-m-d')
		);
		$this->db->trans_start();
		$this->db->where('agreement_id',$agreement_id);
		$this->db->update('pr_rent_leaseagreement',$data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function endAgreement($agreement_id){
		$date = $this->input->post('end_date');
		if($date == date('Y-m-d')){
			$data = array(
				'status' => 'EXPIRED',
				'end_date' => $date
			);
		}else{
			$data = array(
				'end_date' => $date
			);
		}
		$this->db->trans_start();
		$this->db->where('agreement_id',$agreement_id);
		$this->db->update('pr_rent_leaseagreement',$data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function getAgreementByListId($list_id){
		$this->db->select('*');
		$this->db->where('pr_rent_leaseagreement.list_id',$list_id);
		$query = $this->db->get('pr_rent_leaseagreement');
		if ($query->num_rows > 0 ){
			return $query->row();
		}else{
			return false;
		}
	}
}