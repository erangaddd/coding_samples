<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Common extends CI_Controller {

	 function __construct() {
        parent::__construct();
		$this->is_logged_in();
		$this->load->model("pr/common_model");
		$this->load->model("ledger_model");
    }

	function checkExistance(){
		$table = $this->input->post('table');
		$field = $this->input->post('field');
		$value = $this->input->post('value');
		if($this->common_model->checkExistance($table, $field, $value)){
			echo '1';
		}else{
			echo '0';
		}
	}

	function createAccountAjax(){
        $contra = '';
        $data  = json_decode($this->input->post('data'), true);
        $new_array = array();
        foreach($data as $key => $value){
            if($key == 'group_id'){
                $group_id = $value;
                $group = $this->ledger_model->getLedgerGroupbyID($group_id);
            }
            else if($key == 'contra'){
                $contra = $value;
            }
            else if($key == 'type'){
                $type = $value;
            }else if($key == 'op_balance'){
                $new_array[$key] = formatPlain($value); //custom helper funtion
            }else{
                $new_array[$key] = $value;
            }
        }
        $dc = $group->dc;
        //If the account is a contra account, we change opening balance type to negative.
        if($dc == 'D' && $contra == 'on'){
            $dc = 'C';
        }else if($dc == 'C' && $contra == 'on'){
            $dc = 'D';
        }
        $this_type = '0';
        if( $type == 'on'){
            $this_type ='1';
        }
        $this_contra = '0';
        if( $contra == 'on'){
            $this_contra ='1';
        }  
        $new_array['op_balance_dc'] = $dc;
        $new_array['type'] = $this_type;
        $new_array['reconciliation'] =  $this_type;
        $new_array['contra'] = $this_contra;
        $new_array['group_id'] =  $group_id;
        $new_array['create_date'] = date('Y-m-d');
        $new_array['create_by'] = $this->session->userdata('userid');
        $new_array['status'] = 'CONFIRMED';
        $table = 'ac_ledgers';
        if($returned_data = $this->common_model->insertDataReturn($new_array, $table, $id_column = 'id')){
            echo json_encode($returned_data);
        }
   }

   function getLedgerSubGroups(){
        $parent_id = $this->input->post('parent_id');
		$parent_ids = explode(',', $parent_id);
		$groups = $this->common_model->getLedgerSubGroups($parent_ids);
		echo json_encode($groups);
   }

   function getLedgerSubGroupsArray(){
        $parent_ids = $this->input->post('parent_id');
        $groups = $this->common_model->getLedgerSubGroups($parent_ids);
        echo json_encode($groups);
    }

   function createNewAgentAJAX()
   {
        
        $table = 'pr_agent';
        $field = 'nic';
        $value = $this->input->post('nic');
        if($this->common_model->checkExistance($table, $field, $value)){
            $this->session->set_flashdata('error', 'Agent Already Exsistance');
            redirect('pr/property/listings');
            return;
        }

        $existing_agent = $this->input->post("agent_check");
        if($existing_agent == "yes")
        {   
            $employee = $this->input->post("existing_agent");
            $employee_data = $this->common_model->getData("id",$employee,"hr_empmastr","","","","","","yes");
            $data_array = array(
                'title' => $employee_data->title,
                'initial' => $employee_data->initial,
                'surname' => $employee_data->surname,
                'initials_full' => $employee_data->initials_full,
                'dob' => $employee_data->dob,
                'nic' =>  $employee_data->nic_no,
                'mobile' => $employee_data->tel_mob,
                'email' => $employee_data->email,
                'address1' => $employee_data->addr1,
                'address2'=> $employee_data->addr2,
                'create_date' =>date('Y-m-d'),
                'create_by' => $this->session->userdata('userid'),
            );
    
            $table = 'pr_agent';
            if($returned_data = $this->common_model->insertDataReturn($data_array, $table,'agent_id')){
                echo json_encode($returned_data);
            }

        }
        elseif($existing_agent == "no")
        {
            $data_array = array(
                'title' => $this->input->post('title'),
                'initial' => $this->input->post('initial'),
                'surname' => $this->input->post('surname'),
                'initials_full' => $this->input->post('initials_full'),
                'dob' => $this->input->post('dob'),
                'nic' => $this->input->post('nic'),
                'mobile' => $this->input->post('mobile'),
                'email' => $this->input->post('email'),
                'fax' => $this->input->post('fax'),
                'address1' => $this->input->post('address1'),
                'address2'=> $this->input->post('address2'),
                'create_date' =>date('Y-m-d'),
                'create_by' => $this->session->userdata('userid'),
            );
    
            $table = 'pr_agent';
            if($returned_data = $this->common_model->insertDataReturn($data_array, $table,'agent_id')){
                echo json_encode($returned_data);
            }
        }

   }
   function mark_as_read_notification()
   {
	    $table = $this->input->get('table');
		$notification = $this->input->get('notification');
        $module = $this->input->get('module');
        $module = $this->encryption->decode($module);
		if($this->common_model->mark_as_read_notification($table,$notification,$module))
		{
			
			echo "Mark as read";
			
			//echo "This record already open ";
		}
   }
}
