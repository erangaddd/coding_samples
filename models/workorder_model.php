<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Workorder_model extends CI_Model {

    function __construct() {
        parent::__construct();
		$this->load->model("invoice_model");
		$this->load->model("pr/task_model");
    }

	function getAllWorkOrdersNotCompleted($limit, $page){
        $this->db->select('pr_task_workorder.*, pr_task_masterdata.task_number, pr_task_masterdata.id as task_id, cm_supplierms.first_name, cm_supplierms.last_name');
		$this->db->join('pr_task_masterdata','pr_task_masterdata.id = pr_task_workorder.taskmaster_id');
		$this->db->join('cm_supplierms','cm_supplierms.sup_id = pr_task_workorder.sup_id');
		$this->db->where('pr_task_workorder.status !=','CLOSED');
		$this->db->order_by('pr_task_workorder.status','ASC');
		$this->db->order_by('pr_task_workorder.workorder_id','ASC');
		$this->db->limit($limit, $page);
		$query = $this->db->get('pr_task_workorder'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
    }

    function getCountAllWorkOrdersNotCompleted(){
        $this->db->select('pr_task_workorder.*, pr_task_masterdata.task_number');
		$this->db->join('pr_task_masterdata','pr_task_masterdata.id = pr_task_workorder.taskmaster_id');
		$this->db->where('pr_task_workorder.status !=','CLOSED');
		$query = $this->db->get('pr_task_workorder'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return false;
		}
    }

	function getSearchWorkOrders($limit, $page){
		$this->db->select('pr_task_workorder.*, pr_task_masterdata.task_number, pr_task_masterdata.id as task_id, cm_supplierms.first_name, cm_supplierms.last_name');
		$this->db->join('pr_task_masterdata','pr_task_masterdata.id = pr_task_workorder.taskmaster_id');
		$this->db->join('cm_supplierms','cm_supplierms.sup_id = pr_task_workorder.sup_id');
		$this->db->like('pr_task_workorder.description',$this->session->userdata('search_string'));
		$this->db->or_like('pr_task_workorder.workorder_number',$this->session->userdata('search_string'));
		$this->db->or_like('pr_task_workorder.bill_amount',$this->session->userdata('search_string'));
		$this->db->or_like('cm_supplierms.first_name',$this->session->userdata('search_string'));
		$this->db->or_like('cm_supplierms.last_name',$this->session->userdata('search_string'));
		$this->db->or_like('pr_task_masterdata.task_number',$this->session->userdata('search_string'));
		$this->db->order_by('pr_task_workorder.workorder_id','ASC');
		$this->db->limit($limit, $page);
		$query = $this->db->get('pr_task_workorder'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function getCountSearchWorkOrders(){
		$this->db->select('pr_task_workorder.*, pr_task_masterdata.task_number, pr_task_masterdata.id as task_id, cm_supplierms.first_name, cm_supplierms.last_name');
		$this->db->join('pr_task_masterdata','pr_task_masterdata.id = pr_task_workorder.taskmaster_id');
		$this->db->join('cm_supplierms','cm_supplierms.sup_id = pr_task_workorder.sup_id');
		$this->db->like('pr_task_workorder.description',$this->session->userdata('search_string'));
		$this->db->or_like('pr_task_workorder.workorder_number',$this->session->userdata('search_string'));
		$this->db->or_like('pr_task_workorder.bill_amount',$this->session->userdata('search_string'));
		$this->db->or_like('cm_supplierms.first_name',$this->session->userdata('search_string'));
		$this->db->or_like('cm_supplierms.last_name',$this->session->userdata('search_string'));
		$this->db->or_like('pr_task_masterdata.task_number',$this->session->userdata('search_string'));
		$query = $this->db->get('pr_task_workorder'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return false;
		}
	}

	function getWorkOrderById($id){
		$this->db->select('pr_task_workorder.*,pr_task_masterdata.property_id, pr_task_masterdata.unit_id');
		$this->db->join('pr_task_masterdata','pr_task_masterdata.id = pr_task_workorder.taskmaster_id');
		$this->db->where('pr_task_workorder.workorder_id',$id);
		$query = $this->db->get('pr_task_workorder'); 
		if($query->num_rows() > 0){
			return $query->row(); 
		}else{
			return false;
		}
	}

	function addWorkOrder($file_name){
		$table = 'pr_task_workorder';
		$next_action = checkApproveLevel($table,'PENDING'); //common helper
		$number = $this->getmaincode('workorder_number','W','pr_task_workorder');
		$data = array(
			'workorder_number' => $number,
			'taskmaster_id' => $this->input->post('taskmaster_id'),
			'expense_id' => $this->input->post('expense_id'),
			'sup_id' => $this->input->post('sup_id'),
			'bill_date' => $this->input->post('bill_date'),
			'bill_number' => $this->input->post('bill_number'),
			'bill_amount' => $this->input->post('bill_amount'),
			'isvat' => $this->input->post('isvat'),
			'vat_amount' => $this->input->post('vat_amount'),
			'bill_note' => $this->input->post('bill_note'),
			'description' => $this->input->post('description'),
			'file' => $file_name,
			'notify' => $this->input->post('notify'),
			'supplier_note' => $this->input->post('supplier_note'),
			'contact_person' => $this->input->post('contact_person'),
			'contact_phone' => $this->input->post('contact_phone'),
			'unit_bill_amount' => $this->input->post('unit_bill_amount'),
			'due_date' => $this->input->post('due_date'),
			'status' => $next_action,
			'create_by' => $this->session->userdata('userid'),
			'create_date' => date('Y-m-d')
		);
		$this->db->trans_start();
		$this->db->insert('pr_task_workorder', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$insert_id = $this->db->insert_id();
			$this->db->trans_commit();
			return $insert_id;
		}
	}

	function editWorkOrder($workorder_id, $file_name){
		$data = array(
			'taskmaster_id' => $this->input->post('taskmaster_id'),
			'expense_id' => $this->input->post('expense_id'),
			'sup_id' => $this->input->post('sup_id'),
			'bill_date' => $this->input->post('bill_date'),
			'bill_number' => $this->input->post('bill_number'),
			'bill_amount' => $this->input->post('bill_amount'),
			'isvat' => $this->input->post('isvat'),
			'vat_amount' => $this->input->post('vat_amount'),
			'bill_note' => $this->input->post('bill_note'),
			'description' => $this->input->post('description'),
			'file' => $file_name,
			'notify' => $this->input->post('notify'),
			'supplier_note' => $this->input->post('supplier_note'),
			'contact_person' => $this->input->post('contact_person'),
			'contact_phone' => $this->input->post('contact_phone'),
			'unit_bill_amount' => $this->input->post('unit_bill_amount'),
			'due_date' => $this->input->post('due_date'),
			'update_by' => $this->session->userdata('userid'),
			'update_date' => date('Y-m-d')
		);
		$this->db->trans_start();
		$this->db->where('pr_task_workorder.workorder_id', $workorder_id);
		$this->db->update('pr_task_workorder', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function checkWorkOrder($workorder_id){
		$data = array(
			'status' => 'CONFIRM',
			'check_by' => $this->session->userdata('userid'),
			'check_date' => date('Y-m-d')
		);
		$this->db->trans_start();
		$this->db->where('pr_task_workorder.workorder_id', $workorder_id);
		$this->db->update('pr_task_workorder', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function confirmWorkOrder($workorder_id){
		$data = array(
			'status' => 'CONFIRMED',
			'confirm_by' => $this->session->userdata('userid'),
			'confirm_date' => date('Y-m-d')
		);
		$this->db->trans_start();
		$this->db->where('pr_task_workorder.workorder_id', $workorder_id);
		$this->db->update('pr_task_workorder', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function deleteWorkOrder(){
		$this->db->where('workorder_id',$this->input->post('id'));
		$this->db->trans_start();
		$this->db->delete('pr_task_workorder');
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function deleteConfirmedWorkOrder(){
		$workorder = $this->getWorkOrderById($this->input->post('id'));
		$this->db->where('workorder_id',$this->input->post('id'));
		$this->db->trans_start();
		$this->db->delete('pr_task_workorder');
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->where('id',$workorder->unit_bill_id);
			$this->db->delete('pr_task_bills');
			$this->db->trans_commit();
			return true;
		}
	}

	function closeWorkOrder(){
		$data = array(
			'status' => 'CLOSED',
			'close_by' => $this->session->userdata('userid'),
			'close_date' => date('Y-m-d')
		);
		$this->db->trans_start();
		$this->db->where('pr_task_workorder.workorder_id', $this->input->post('id'));
		$this->db->update('pr_task_workorder', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$workorder = $this->getWorkOrderById($this->input->post('id'));
			$task = $this->task_model->getTaskById($workorder->taskmaster_id);
			//Create invoice
			$invoice_id = $this->invoice_model->addInvoiceforWorkOrder($workorder, $task);
			$data = array(
				'bill_id' => $invoice_id
			);
			$this->db->where('pr_task_workorder.workorder_id', $this->input->post('id'));
			$this->db->update('pr_task_workorder', $data);
			if($workorder->unit_bill_amount != '0.00'){
				$bill_data = array(
					'taskmaster_id' => $workorder->taskmaster_id,
					'bill_amount' => $workorder->unit_bill_amount,
					'due_date' => $workorder->due_date,
					'description' => $workorder->description,
					'create_by' => $this->session->userdata('userid'),
					'create_date' => date('Y-m-d'),
					'confirm_by' => $this->session->userdata('userid'),
					'confirm_date' => date('Y-m-d'),
					'status' => 'CONFIRMED'
				);
				$this->db->insert('pr_task_bills', $bill_data);
				$insert_id = $this->db->insert_id();
				$data = array(
					'unit_bill_id' => $insert_id
				);
				$this->db->where('pr_task_workorder.workorder_id', $this->input->post('id'));
				$this->db->update('pr_task_workorder', $data);
			}
			$this->db->trans_commit();
			return true;
		}
	}

	function checkBeforeClose(){
		$workorder = $this->getWorkOrderById($this->input->post('id'));
		$message = '';
		if($workorder->bill_number == ''){
			$message .= 'invoice number ';
		}
		if($workorder->bill_amount == 0){
			$message .= ',invoice amount ';
		}
		if($workorder->bill_note == ''){
			$message .= 'invoice description ';
		}
	    if($message != ''){
			return $message;
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
			 $prjid=substr($prjid,1,7);
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

	function getWorkOrdersbyTaskId($task_id){
		$statuses = array('CLOSED', 'CONFIRMED');
		$this->db->select('pr_task_workorder.*');
		$this->db->where('pr_task_workorder.taskmaster_id',$task_id);
		$this->db->where_in('pr_task_workorder.status', $statuses);
		$query = $this->db->get('pr_task_workorder'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function getPendingWorkordersByTaskId($task_id){
		$this->db->select('pr_task_workorder.*');
		$this->db->where('pr_task_workorder.taskmaster_id',$task_id);
		$this->db->where('pr_task_workorder.status !=','CLOSED');
		$query = $this->db->get('pr_task_workorder'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}
}