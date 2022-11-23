<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Application_model extends CI_Model {

    function __construct() {
        parent::__construct();
    }

    function addApplicant($file_name){
        $cus_number = $this->getmaincode('cus_number','CUS','cm_customerms');
		$list_id = $this->input->post('property');
        if($list_id != ''){
			$table = 'pr_rent_application';
        	$next_action = checkApproveLevel($table,'PENDING'); //common helper
		}else{
			$table = 'cm_customerms';
			$next_action = checkApproveLevel($table,'PENDING'); //common helper
		}
        $data=array(
			'cus_number'=>$cus_number,
			'cus_branch'=>$this->session->userdata('branchid'),
			'create_date' => date("Y-m-d"),
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
			'create_by' => $this->session->userdata('userid'),
			'status' => $next_action
			);
        $this->db->trans_start();
        $this->db->insert('cm_customerms',$data);
        $insert_id = $this->db->insert_id();
        $this->db->trans_complete();
        if ($this->db->trans_status() === FALSE) {
            $this->db->trans_rollback();
            return false;
        } else {
            $list_id = $this->input->post('property');
            if($list_id != ''){
                $property_data = $this->getPropertyUnitbyListID($list_id);
                //create application code
                $app_code = $this->getmaincode('application_code','APP','pr_rent_application');
				$table = 'pr_rent_application';
        		$next_action = checkApproveLevel($table,'PENDING'); //common helper
                $list_data = array(
                    'application_code' => $app_code.$property_data->property_id.$property_data->unit_id,
                    'cus_code' => $insert_id,
                    'list_id' => $list_id,
                    'apply_date' => date('Y-m-d'),
					'create_by' => $this->session->userdata('userid'),
					'status' => $next_action
                );
                $this->db->trans_start();
                $this->db->insert('pr_rent_application',$list_data);
				$application_id = $this->db->insert_id();
                $this->db->trans_complete();
                if ($this->db->trans_status() === FALSE) {
                    $this->db->trans_rollback();
                    return false;
                } else {
                    $update_data = array(
                        'status' => 'USED'
                    );
                    $this->db->where('list_id',$list_id);
                    $this->db->update('pr_rent_listing',$update_data);
                    $this->db->trans_commit();
                    return $application_id;
                }
            }else{
                $this->db->trans_commit();
                return true;
            }
        }
		
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
			$this->db->or_like('pr_rent_application.application_code',$this->input->post('name'), 'none');
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

	function addApplicantion($file_name){
		$list_id = $this->input->post('property');
		$cus_code = $this->input->post('cus_code');
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
		$property_data = $this->getPropertyUnitbyListID($list_id);
		//create application code
		$app_code = $this->getmaincode('application_code','APP','pr_rent_application');
		$table = 'pr_rent_application';
        $next_action = checkApproveLevel($table,'PENDING'); //common helper
		$list_data = array(
			'application_code' => $app_code.$property_data->property_id.$property_data->unit_id,
			'cus_code' => $cus_code,
			'list_id' => $list_id,
			'apply_date' => date('Y-m-d'),
			'create_by' => $this->session->userdata('userid'),
			'status' => $next_action 
		);
		$this->db->trans_start();
		$this->db->insert('pr_rent_application',$list_data);
		$insert_id = $this->db->insert_id();
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$update_data = array(
				'status' => 'USED'
			);
			$this->db->where('list_id',$list_id);
			$this->db->update('pr_rent_listing',$update_data);
			$this->db->trans_commit();
			return $insert_id;
		}
	
	}

    function getPropertyUnitbyListID($list_id){
        $this->db->select('pr_rent_propertymain.*,pr_rent_propertyunit.unit_id');
		$this->db->join('pr_rent_propertyunit','pr_rent_propertyunit.unit_id = pr_rent_listing.unit_id');
        $this->db->join('pr_rent_propertymain','pr_rent_propertymain.property_id = pr_rent_propertyunit.property_id');
		$this->db->where('pr_rent_listing.list_id',$list_id);
		$query = $this->db->get('pr_rent_listing');
		if($query->num_rows() > 0){
			return $query->row();
		}else{
			return false;
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

    function checkIDExists($id_type,$id_number){
        $this->db->select('cus_code');
		if($id_type!=''){
			$this->db->where('id_type', $id_type);
		}
		$this->db->where('id_number',$id_number);
		$query = $this->db->get('cm_customerms');

		if ($query->num_rows > 0 ){
			return true;
		}else{
			return false;
		}
    }

	function checkCheckListItem($checklist_id,$application_id){
		$this->db->select('*');
		$this->db->where('application_id',$application_id);
		$this->db->where('checklist_id',$checklist_id);
		$query = $this->db->get('pr_rent_applicationchecklist');

		if ($query->num_rows > 0 ){
			return true;
		}else{
			return false;
		}
	}

	function checkDucumentsList($doctype_id,$application_id){
		$this->db->select('*');
		$this->db->where('application_id',$application_id);
		$this->db->where('doctype_id',$doctype_id);
		$query = $this->db->get('pr_rent_applicationdocs');

		if ($query->num_rows > 0 ){
			return $query->row()->document;
		}else{
			return false;
		}
	}

	function getApplicationbyID($application_id){
		$this->db->select('*');
		$this->db->where('application_id',$application_id);
		$query = $this->db->get('pr_rent_application');

		if ($query->num_rows > 0 ){
			return $query->row();
		}else{
			return false;
		}
	}

	function getApplicationByListId($list_id){
		$this->db->select('pr_rent_application.*,cm_customerms.*');
		$this->db->join('cm_customerms','cm_customerms.cus_code = pr_rent_application.cus_code');
		$this->db->where('pr_rent_application.list_id',$list_id);
		$query = $this->db->get('pr_rent_application');

		if ($query->num_rows > 0 ){
			return $query->row();
		}else{
			return false;
		}
	}
	
}