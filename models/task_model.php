<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Task_model extends CI_Model {

    function __construct() {
        parent::__construct();
		$this->load->model("pr/agreement_model");
		$this->load->model("pr/rental_model");
		$this->load->model("pr/common_model");
		$this->load->model("entry_model");
    }

	function getTaskRequestCount(){
		$this->db->select('*');
		$this->db->where('status','PENDING');
		$query = $this->db->get('pr_task_requests'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return false;
		}
	}

	function getAllTaskRequests($limit, $page){
		$this->db->select('pr_task_requests.*, pr_config_task.task_name');
		$this->db->join('pr_config_task','pr_config_task.task_id = pr_task_requests.task_id');
		$this->db->where('pr_task_requests.status','PENDING');
		$this->db->order_by('pr_task_requests.id','ASC');
		$this->db->limit($limit, $page);
		$query = $this->db->get('pr_task_requests'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function getAllTaskNotComepleted($limit, $page){
		$this->db->select('pr_task_masterdata.*, pr_config_task.task_name, pr_task_requests.number');
		$this->db->join('pr_config_task','pr_config_task.task_id = pr_task_masterdata.task_id');
		$this->db->join('pr_task_requests','pr_task_requests.id = pr_task_masterdata.request_id','left');
		$this->db->where('pr_task_masterdata.task_status !=','Closed');
		$this->db->where('pr_task_masterdata.status !=','DELETED');
		$this->db->order_by('pr_task_masterdata.status','ASC');
		$this->db->order_by('pr_task_masterdata.priority','DESC');
		$this->db->order_by('pr_task_masterdata.due_date','ASC');
		$this->db->limit($limit, $page);
		$query = $this->db->get('pr_task_masterdata'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function getAllTasksNotClosed(){
		$this->db->select('pr_task_masterdata.*, pr_config_task.task_name, pr_task_requests.number');
		$this->db->join('pr_config_task','pr_config_task.task_id = pr_task_masterdata.task_id');
		$this->db->join('pr_task_requests','pr_task_requests.id = pr_task_masterdata.request_id','left');
		$this->db->where('pr_task_masterdata.task_status !=','Closed');
		$this->db->where('pr_task_masterdata.status','CONFIRMED');
		$this->db->order_by('pr_task_masterdata.priority','DESC');
		$this->db->order_by('pr_task_masterdata.due_date','ASC');
		$query = $this->db->get('pr_task_masterdata'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function deleteRequest($request_id){
		$this->db->where('id',$request_id);
		$this->db->trans_start();
		$this->db->delete('pr_task_requests');
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function getAllTaskNotComepletedCount(){
		$this->db->select('pr_task_masterdata.*');
		$this->db->where('pr_task_masterdata.task_status !=','Closed');
		$this->db->where('pr_task_masterdata.status !=','DELETED');
		$query = $this->db->get('pr_task_masterdata'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return false;
		}
	}

	function getMyTaskNotComepleted($limit, $page){
		$this->db->select('pr_task_masterdata.*, pr_config_task.task_name, pr_task_requests.number');
		$this->db->join('pr_config_task','pr_config_task.task_id = pr_task_masterdata.task_id');
		$this->db->join('pr_task_requests','pr_task_requests.id = pr_task_masterdata.request_id','left');
		$this->db->where('pr_task_masterdata.task_status !=','Closed');
		$this->db->where('pr_task_masterdata.status !=','DELETED');
		$this->db->where('pr_task_masterdata.assign_officer_id',$this->session->userdata('userid'));
		$this->db->order_by('pr_task_masterdata.status','ASC');
		$this->db->order_by('pr_task_masterdata.priority','DESC');
		$this->db->order_by('pr_task_masterdata.due_date','ASC');
		$this->db->limit($limit, $page);
		$query = $this->db->get('pr_task_masterdata'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function getMyTaskNotComepletedCount(){
		$this->db->select('pr_task_masterdata.*');
		$this->db->where('pr_task_masterdata.task_status !=','Closed');
		$this->db->where('pr_task_masterdata.status !=','DELETED');
		$this->db->where('pr_task_masterdata.assign_officer_id',$this->session->userdata('userid'));
		$query = $this->db->get('pr_task_masterdata'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return false;
		}
	}

	function getTaskRequestCountSearch(){
		$this->db->select('pr_task_requests.*, pr_config_task.task_name');
		$this->db->join('pr_config_task','pr_config_task.task_id = pr_task_requests.task_id');
		if($this->session->userdata('property')){
			$this->db->where('pr_task_requests.unit_id',$this->session->userdata('property'));
		}
		if($this->session->userdata('search_string')){
			$this->db->like('pr_task_requests.task_details',$this->session->userdata('search_string'));
			$this->db->or_like('pr_task_requests.number',$this->session->userdata('search_string'));
			$this->db->or_like('pr_config_task.task_name',$this->session->userdata('search_string'));
		}
		$query = $this->db->get('pr_task_requests'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return false;
		}
	}

	function getAllTaskRequestsSearch($limit, $page){
		$this->db->select('pr_task_requests.*, pr_config_task.task_name');
		$this->db->join('pr_config_task','pr_config_task.task_id = pr_task_requests.task_id');
		if($this->session->userdata('property')){
			$this->db->where('pr_task_requests.unit_id',$this->session->userdata('property'));
		}
		if($this->session->userdata('search_string')){
			$this->db->like('pr_task_requests.task_details',$this->session->userdata('search_string'));
			$this->db->or_like('pr_task_requests.number',$this->session->userdata('search_string'));
			$this->db->or_like('pr_config_task.task_name',$this->session->userdata('search_string'));
		}
		$this->db->order_by('pr_task_requests.id','ASC');
		$this->db->limit($limit, $page);
		$query = $this->db->get('pr_task_requests'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function rejectRequest($request_id){
		$data = array(
			'status' => 'REJECTED',
			'reject_reason' => $this->input->post('reason')
		);
		$this->db->where('id',$request_id);
		$this->db->update('pr_task_requests',$data);
		return ($this->db->affected_rows() != 1) ? false : true;
	}

	function resetRequest($id){
		$data = array(
			'status' => 'PENDING',
			'reject_reason' => ''
		);
		$this->db->where('id',$id);
		$this->db->update('pr_task_requests',$data);
		return ($this->db->affected_rows() != 1) ? false : true;
	}

	function getRequest($request_id){
		$this->db->select('pr_task_requests.*, pr_config_task.task_name');
		$this->db->join('pr_config_task','pr_config_task.task_id = pr_task_requests.task_id');
		$this->db->where('pr_task_requests.id',$request_id);
		$query = $this->db->get('pr_task_requests'); 
		if($query->num_rows() > 0){
			return $query->row(); 
		}else{
			return false;
		}
	}

	function getDatabyRequestId($request_id){
		$this->db->select('pr_task_requests.*, pr_config_task.task_name, cm_customerms.cus_code, cm_customerms.first_name, cm_customerms.last_name, cm_customerms.email, cm_customerms.mobile');
		$this->db->join('pr_config_task','pr_config_task.task_id = pr_task_requests.task_id');
		$this->db->join('pr_rent_application','pr_rent_application.list_id = pr_task_requests.list_id','left');
		$this->db->join('cm_customerms','cm_customerms.cus_code = pr_rent_application.cus_code','left');
		$this->db->where('pr_task_requests.id',$request_id);
		$query = $this->db->get('pr_task_requests'); 
		if($query->num_rows() > 0){
			return $query->row(); 
		}else{
			return false;
		}
	}

	function getSearchTasks($limit, $page){
		$this->db->select('pr_task_masterdata.*, pr_config_task.task_name, pr_task_requests.number');
		$this->db->join('pr_config_task','pr_config_task.task_id = pr_task_masterdata.task_id');
		$this->db->join('pr_task_requests','pr_task_requests.id = pr_task_masterdata.request_id','left');
		if($this->session->userdata('property_search')){
			$property_id = $this->session->userdata('property_search');
			$p_id = NULL;
            $unit_id = NULL;
            $property_id_type = substr($property_id, 0, 1); //check whether property id or unit id
            if($property_id_type == 'p'){
                $p_id = substr($property_id, 1);
            }else if($property_id_type == 'u'){
                $unit_id = substr($property_id, 1);
            }
			$this->db->where('pr_task_masterdata.unit_id',$unit_id);
			$this->db->where('pr_task_masterdata.property_id',$p_id);
		}
		if($this->session->userdata('task_status')){
			$this->db->where('pr_task_masterdata.task_status',$this->session->userdata('task_status'));
		}
		if($this->session->userdata('priority')){
			$this->db->where('pr_task_masterdata.priority',$this->session->userdata('priority'));
		}
		if($this->session->userdata('search_string')){
			$this->db->like('pr_task_masterdata.task_details',$this->session->userdata('search_string'));
			$this->db->or_like('pr_task_requests.number',$this->session->userdata('search_string'));
			$this->db->or_like('pr_config_task.task_name',$this->session->userdata('search_string'));
		}
		$this->db->order_by('pr_task_masterdata.due_date','ASC');
		$this->db->limit($limit, $page);
		$query = $this->db->get('pr_task_masterdata'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function getSearchTasksCount(){
		$this->db->select('pr_task_masterdata.*, pr_config_task.task_name, pr_task_requests.number');
		$this->db->join('pr_config_task','pr_config_task.task_id = pr_task_masterdata.task_id');
		$this->db->join('pr_task_requests','pr_task_requests.id = pr_task_masterdata.request_id','left');
		if($this->session->userdata('property_search')){
			$this->db->where('pr_task_masterdata.unit_id',$this->session->userdata('property_search'));
		}
		if($this->session->userdata('task_status')){
			$this->db->where('pr_task_masterdata.task_status',$this->session->userdata('task_status'));
		}
		if($this->session->userdata('priority')){
			$this->db->where('pr_task_masterdata.priority',$this->session->userdata('priority'));
		}
		if($this->session->userdata('search_string')){
			$this->db->like('pr_task_masterdata.task_details',$this->session->userdata('search_string'));
			$this->db->or_like('pr_task_requests.number',$this->session->userdata('search_string'));
			$this->db->or_like('pr_config_task.task_name',$this->session->userdata('search_string'));
		}
		$query = $this->db->get('pr_task_masterdata'); 
		if($query->num_rows() > 0){
			return $query->num_rows() ; 
		}else{
			return false;
		}
	}

	function getSearchMyTasks($limit, $page){
		$this->db->select('pr_task_masterdata.*, pr_config_task.task_name, pr_task_requests.number');
		$this->db->join('pr_config_task','pr_config_task.task_id = pr_task_masterdata.task_id');
		$this->db->join('pr_task_requests','pr_task_requests.id = pr_task_masterdata.request_id','left');
		$this->db->where('pr_task_masterdata.assign_officer_id',$this->session->userdata('userid'));
		if($this->session->userdata('property_search')){
			$property_id = $this->session->userdata('property_search');
			$p_id = NULL;
            $unit_id = NULL;
            $property_id_type = substr($property_id, 0, 1); //check whether property id or unit id
            if($property_id_type == 'p'){
                $p_id = substr($property_id, 1);
            }else if($property_id_type == 'u'){
                $unit_id = substr($property_id, 1);
            }
			$this->db->where('pr_task_masterdata.unit_id',$unit_id);
			$this->db->where('pr_task_masterdata.property_id',$p_id);
		}
		if($this->session->userdata('task_status')){
			$this->db->where('pr_task_masterdata.task_status',$this->session->userdata('task_status'));
		}
		if($this->session->userdata('priority')){
			$this->db->where('pr_task_masterdata.priority',$this->session->userdata('priority'));
		}
		if($this->session->userdata('search_string')){
			$this->db->like('pr_task_masterdata.task_details',$this->session->userdata('search_string'));
			$this->db->or_like('pr_task_requests.number',$this->session->userdata('search_string'));
			$this->db->or_like('pr_config_task.task_name',$this->session->userdata('search_string'));
		}
		$this->db->order_by('pr_task_masterdata.due_date','ASC');
		$this->db->limit($limit, $page);
		$query = $this->db->get('pr_task_masterdata'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function getSearchMyTasksCount(){
		$this->db->select('pr_task_masterdata.*, pr_config_task.task_name, pr_task_requests.number');
		$this->db->join('pr_config_task','pr_config_task.task_id = pr_task_masterdata.task_id');
		$this->db->join('pr_task_requests','pr_task_requests.id = pr_task_masterdata.request_id','left');
		$this->db->where('pr_task_masterdata.assign_officer_id',$this->session->userdata('userid'));
		if($this->session->userdata('property_search')){
			$this->db->where('pr_task_masterdata.unit_id',$this->session->userdata('property_search'));
		}
		if($this->session->userdata('task_status')){
			$this->db->where('pr_task_masterdata.task_status',$this->session->userdata('task_status'));
		}
		if($this->session->userdata('priority')){
			$this->db->where('pr_task_masterdata.priority',$this->session->userdata('priority'));
		}
		if($this->session->userdata('search_string')){
			$this->db->like('pr_task_masterdata.task_details',$this->session->userdata('search_string'));
			$this->db->or_like('pr_task_requests.number',$this->session->userdata('search_string'));
			$this->db->or_like('pr_config_task.task_name',$this->session->userdata('search_string'));
		}
		$query = $this->db->get('pr_task_masterdata'); 
		if($query->num_rows() > 0){
			return $query->num_rows() ; 
		}else{
			return false;
		}
	}

	function getTaskById($task_id){
		$this->db->select('pr_task_masterdata.*, pr_config_task.task_name, pr_task_requests.number');
		$this->db->join('pr_config_task','pr_config_task.task_id = pr_task_masterdata.task_id');
		$this->db->join('pr_task_requests','pr_task_requests.id = pr_task_masterdata.request_id','left');
		$this->db->where('pr_task_masterdata.id',$task_id);
		$query = $this->db->get('pr_task_masterdata'); 
		if($query->num_rows() > 0){
			return $query->row(); 
		}else{
			return false;
		}
	}
	
	function deleteTaskBill($bill_id){
		$this->db->trans_start();
		$this->db->where('id', $bill_id);
		$this->db->delete('pr_task_bills');
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function getTaskUpdatesByTaskId($task_id){
		$this->db->select('pr_task_progress.*');
		$this->db->where('pr_task_progress.taskmaster_id',$task_id);
		$this->db->order_by('id','DESC');
		$query = $this->db->get('pr_task_progress'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function confirmTaskUpdate(){
		$data = array(
			'status' => 'CONFIRMED',
			'confirm_by' => $this->session->userdata('userid'),
			'confirm_date' => date('Y-m-d')
		);
		$this->db->trans_start();
		$this->db->where('id', $this->input->post('id'));
		$this->db->update('pr_task_progress', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function updateTask($task_id){
		$data = array(
			'priority' =>  $this->input->post('priority'),
			'task_status' => $this->input->post('task_status'),
			'assign_officer_id' => $this->input->post('assign_officer_id'),
			'update_by' => $this->session->userdata('userid'),
			'update_date' => date('Y-m-d')
		);
		$this->db->trans_start();
		$this->db->where('id', $task_id);
		$this->db->update('pr_task_masterdata', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}	
	}

	function deleteTaskUpdate(){
		$this->db->trans_start();
		$this->db->where('id', $this->input->post('id'));
		$this->db->delete('pr_task_progress');
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function addTaskProgress(){
		$data = array(
			'taskmaster_id' => $this->input->post('task_id'),
			'description' => $this->input->post('description'),
			'create_by' => $this->session->userdata('userid'),
			'create_date' => date('Y-m-d')
		);
		$this->db->trans_start();
		$this->db->insert('pr_task_progress', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$entry_id = $this->db->insert_id();
			$this->db->trans_commit();
			return $entry_id;
		}
	}

	function getTaskProgress($progress_id){
		$this->db->select('pr_task_progress.*');
		$this->db->where('pr_task_progress.id',$progress_id);
		$query = $this->db->get('pr_task_progress'); 
		if($query->num_rows() > 0){
			return $query->row(); 
		}else{
			return false;
		}
	}

	function getTaskProgressImages($progress_id){
		$this->db->select('pr_task_progress_images.*');
		$this->db->where('pr_task_progress_images.progress_id',$progress_id);
		$query = $this->db->get('pr_task_progress_images'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function addTaskProgressImages($progress_id,$file){
		$data = array(
			'progress_id' => $progress_id,
			'file_name' => $file
		);
		$this->db->trans_start();
		$this->db->insert('pr_task_progress_images', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function addRequest($list_id, $property_id, $unit_id){
		$number = $this->getmaincode('number','','pr_task_requests');
		$data = array(
			'number' => $number,
			'task_id' => $this->input->post('task_id'),
			'unit_id' => $unit_id,
			'property_id' => $property_id,
			'list_id' => $list_id,
			'task_details' => $this->input->post('task_details'),
			'task_adddate' => $this->input->post('task_adddate'),
			'create_by' => $this->session->userdata('userid'),
			'create_date' => date('Y-m-d')
		);
		$this->db->trans_start();
		$this->db->insert('pr_task_requests', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$entry_id = $this->db->insert_id();
			$this->db->trans_commit();
			return $entry_id;
		}
	}

	function addRequestAttachments($request_id,$file){
		$data = array(
			'request_id' => $request_id,
			'file' => $file,
		);
		$this->db->trans_start();
		$this->db->insert('pr_task_request_attachments', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$entry_id = $this->db->insert_id();
			$this->db->trans_commit();
		}
	}

	function addTaskAttachments($task_id,$file){
		$data = array(
			'taskmaster_id' => $task_id,
			'file_name' => $file,
		);
		$this->db->trans_start();
		$this->db->insert('pr_task_attachment', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$entry_id = $this->db->insert_id();
			$this->db->trans_commit();
		}
	}

	function getTaskBillsByTaskId($task_id){
		$this->db->select('pr_task_bills.*');
		$this->db->where('pr_task_bills.taskmaster_id',$task_id);
		$query = $this->db->get('pr_task_bills'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function addTaskBill(){
		$data = array(
			'taskmaster_id' => $this->input->post('task_id'),
			'bill_amount' => $this->input->post('bill_amount'),
			'due_date' => $this->input->post('due_date'),
			'description' => $this->input->post('description'),
			'create_by' => $this->session->userdata('userid'),
			'create_date' => date('Y-m-d')
		);
		$this->db->trans_start();
		$this->db->insert('pr_task_bills', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$entry_id = $this->db->insert_id();
			$this->db->trans_commit();
			return $entry_id;
		}
	}

	function confirmTaskBill($bill_id){
		$data = array(
			'confirm_by' => $this->session->userdata('userid'),
			'confirm_date' => date('Y-m-d'),
			'status' => 'CONFIRMED'
		);
		$this->db->trans_start();
		$this->db->where('id', $bill_id);
		$this->db->update('pr_task_bills', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
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
			 	$newid= '1'.str_pad(1, 7, "0", STR_PAD_LEFT);
			 }
			 else{
			 //$prjid=substr($prjid,3,7);
			 $id=intval($prjid);
			 $newid=$id+1;
			 $newid=$prifix.str_pad($newid, 7, "0", STR_PAD_LEFT);
			 }
        }
		else
		{
		$newid=str_pad(1, 7, "0", STR_PAD_LEFT);
		$newid='1'.$newid;
		}
		return $newid;
	}

	function getAttachemntsByRequestId($request_id){
		$this->db->select('*');
		$this->db->where('request_id',$request_id);
		$query = $this->db->get('pr_task_request_attachments'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function getAttachemntsByTaskId($task_id){
		$this->db->select('*');
		$this->db->where('taskmaster_id',$task_id);
		$query = $this->db->get('pr_task_attachment'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function deleteAttachemnt($file_id){
		$this->db->where('id',$file_id);
		$this->db->trans_start();
		$this->db->delete('pr_task_attachment');
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function confirmRequest($request){
		$table = 'pr_task_masterdata';
		$next_action = checkApproveLevel($table,'PENDING'); //common helper
		$number = $this->getmaincode('task_number','','pr_task_masterdata');
		$data = array(
			'task_number' => $number,
			'task_id' => $request->task_id,
			'request_id' => $request->id,
			'cus_code' => $request->cus_code,
			'unit_id' => $request->unit_id,
			'property_id' => $request->property_id,
			'list_id' => $request->list_id,
			'task_details' => $request->task_details,
			'due_date' => $this->input->post('due_date'),
			'task_type' => 'Resident Request',
			'assign_officer_id' => $this->input->post('assign_officer_id'),
			'create_by' => $this->session->userdata('userid'),
			'create_date' => date('Y-m-d'),
			'priority' => $this->input->post('priority'),
			'task_status' => $this->input->post('task_status'),
			'status' => $next_action
		);
		$this->db->trans_start();
		$this->db->insert('pr_task_masterdata', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$entry_id = $this->db->insert_id();
			if($files = $this->getAttachemntsByRequestId($request->id)){
				foreach($files as $file){
					$file_data = array(
						'taskmaster_id' => $entry_id,
						'file_name' => $file->file
					);
					$this->db->insert('pr_task_attachment', $file_data);
				}
			}
			$request_data = array(
				'status' => 'CONFIRMED',
				'confirm_by' => $this->session->userdata('userid'),
				'confirm_date' => date('Y-m-d')
			);
			$this->db->where('id',$request->id);
			$this->db->update('pr_task_requests', $request_data);
			$this->db->trans_commit();
			return $entry_id;
		}
	}

	function addTask($p_id, $unit_id){
		$table = 'pr_task_masterdata';
		$next_action = checkApproveLevel($table,'PENDING'); //common helper
		$number = $this->getmaincode('task_number','','pr_task_masterdata');
		$data = array(
			'task_number' => $number,
			'task_id' => $this->input->post('task_id'),
			'cus_code' => $agreement->cus_code,
			'unit_id' => $unit_id,
			'property_id' => $p_id,
			'task_details' => $this->input->post('task_details'),
			'due_date' => $this->input->post('due_date'),
			'task_type' => 'System Created',
			'assign_officer_id' => $this->input->post('assign_officer_id'),
			'create_by' => $this->session->userdata('userid'),
			'create_date' => date('Y-m-d'),
			'priority' => $this->input->post('priority'),
			'task_status' => $this->input->post('task_status'),
			'status' => $next_action
		);
		$this->db->trans_start();
		$this->db->insert('pr_task_masterdata', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$entry_id = $this->db->insert_id();
			$this->db->trans_commit();
			return $entry_id;
		}
	}

	function editTask(){
		$data = array(
			'task_details' => $this->input->post('task_details'),
			'due_date' => $this->input->post('due_date'),
			'assign_officer_id' => $this->input->post('assign_officer_id'),
			'update_by' => $this->session->userdata('userid'),
			'update_date' => date('Y-m-d'),
			'task_id' => $this->input->post('task_id'),
			'priority' => $this->input->post('priority'),
			'task_status' => $this->input->post('task_status'),
		);
		$this->db->trans_start();
		$this->db->where('id', $this->input->post('id'));
		$this->db->update('pr_task_masterdata', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function changeStatus($status ,$id){
		if($status == 'CONFIRM'){
			$data = array(
				'status' => $status,
				'check_by' => $this->session->userdata('userid'),
				'check_date' => date('Y-m-d')
			);
		}

		if($status == 'CONFIRMED'){
			$data = array(
				'status' => $status,
				'confirm_by' => $this->session->userdata('userid'),
				'confirm_date' => date('Y-m-d')
			);
		}
		
		$this->db->trans_start();
		$this->db->where('id', $id);
		$this->db->update('pr_task_masterdata', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function deleteTask(){
		$this->db->where('id',$this->input->post('id'));
		$this->db->trans_start();
		$this->db->delete('pr_task_masterdata');
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function deleteConfirmedTask(){
		$data = array(
			'status' => 'DELETED',
		);
		$this->db->trans_start();
		$this->db->where('id', $this->input->post('id'));
		$this->db->update('pr_task_masterdata', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function getPendingBillsByTaskId($task_id){
		$this->db->select('pr_task_bills.*');
		$this->db->where('pr_task_bills.taskmaster_id',$task_id);
		$this->db->where('pr_task_bills.status !=','CONFIRMED');
		$query = $this->db->get('pr_task_bills'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function getPendingProgressByTaskId($task_id){
		$this->db->select('pr_task_progress.*');
		$this->db->where('pr_task_progress.taskmaster_id',$task_id);
		$this->db->where('pr_task_progress.status !=','CONFIRMED');
		$query = $this->db->get('pr_task_progress'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function closeTask($task_id){
		$insert_id = '';
		//check bills
		$bills = $this->getTaskBillsByTaskId($task_id);
		if($bills){
			$task = $this->getTaskById($task_id);
			//get request 
			$request = $this->getRequest($task->request_id);
			//find agreement
			$agreement = $this->agreement_model->getAgreementByListId($task->list_id);
			//get charge details
			$charge = $this->rental_model->getChargeByAgreementId($agreement->agreement_id, '4');
			$total = 0;
			//$dates = array();
			foreach($bills as $bill){
				$total = $total + $bill->bill_amount;
				//array_push($dates, $bill->due_date);
			}
			if($total > 0){
				//add charge bill
				$data = array(
					'charge_id' => $charge->charge_id,
					'agreement_id' => $agreement->agreement_id,
					'bill_number' => 'R'.$request->number,
					'bill_amount' => $total,
					//'due_date' => max($dates),
					'due_date' => date('Y-m-d'),
					'delay_interest' => '0',
					'description' => $task->task_details,
					'generate_by' => $this->session->userdata('userid'),
					'generate_date' => date('Y-m-d'),
					'generate_type' => 'system',
					'status' => 'CONFIRMED'
				);
				$this->db->insert('pr_rent_charges_bills', $data);
				$this->db->trans_start();
				$insert_id = $this->db->insert_id();
				$this->db->trans_complete();
				if( $this->db->trans_status() === FALSE ){
					$this->db->trans_rollback();
				}else{
					$entry_type_id = '4';
					$entry_number = $this->entry_model->next_entry_number($entry_type_id);
					$insert_data = array(
						'number' => $entry_number,
						'date' => date('Y-m-d'),
						'narration' => $agreement->agreement_code.' '.$task->task_details.' - R'.$request->number,
						'entry_type' => $entry_type_id,
						'dr_total' => $total,
						'cr_total' => $total, 
						'module' => 'P',
						'pid' => $agreement->property_id,
						'uid' => $agreement->unit_id,
						'create_date' => date("Y-m-d"),
						'status' => 'CONFIRMED'
					);
					$this->db->insert('ac_entries', $insert_data);
					$entry_id = $this->db->insert_id();

					if(get_accounting_mothod()=='Cash')//pr/account_helper function
						$crledger=get_unrealized_incomeaccount();//pr/account_helper function
					else
						$crledger=$charge->ledger_id;
					if($total > 0){
						$insert_ledger_data = array(
							'entry_id' => $entry_id,
							'ledger_id' => $charge->receivable_ledger,
							'amount' => $total,
							'dc' => 'D',
						);
						$this->db->insert('ac_entry_items', $insert_ledger_data);
						
						$insert_ledger_data = array(
							'entry_id' => $entry_id,
							'ledger_id' =>$crledger,
							'amount' => $total,
							'dc' => 'C',
						);
						$this->db->insert('ac_entry_items', $insert_ledger_data);
					}
				}
			}
		}
		if($insert_id != ''){
			$data = array(
				'bill_id' => $insert_id,
				'task_status' => 'Closed',
				'close_date' => date('Y-m-d'),
				'close_by' => $this->session->userdata('userid')
			);
		}else{
			$data = array(
				'task_status' => 'Closed',
				'close_date' => date('Y-m-d'),
				'close_by' => $this->session->userdata('userid')
			);
		}
		$this->db->trans_start();
		$this->db->where('id', $task_id);
		$this->db->update('pr_task_masterdata', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	// *********************** Reccuring Tasks *************************//

	function getAllActiveRecurringTasks($limit, $page){
		$statuses = array('DELETED','EXPIRED');
		$this->db->select('pr_task_recurring.*, pr_config_task.task_name');
		$this->db->join('pr_config_task','pr_config_task.task_id = pr_task_recurring.task_id');
		$this->db->where_not_in('pr_task_recurring.status',$statuses);
		$this->db->order_by('pr_task_recurring.id','DESC');
		$this->db->limit($limit, $page);
		$query = $this->db->get('pr_task_recurring'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function getAllActiveRecurringTasksCount(){
		$this->db->select('pr_task_recurring.*');
		$this->db->where('pr_task_recurring.status !=','DELETED');
		$query = $this->db->get('pr_task_recurring'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return false;
		}
	}

	function addRecurringTask($p_id, $unit_id){
		$table = 'pr_task_recurring';
		$next_action = checkApproveLevel($table,'PENDING'); //common helper
		$data = array(
			'task_id' => $this->input->post('task_id'),
			'unit_id' => $unit_id,
			'property_id' => $p_id,
			'task_details' => $this->input->post('task_details'),
			'due_date' => $this->input->post('due_date'),
			'end_date' => $this->input->post('end_date'),
			'recurring_type' => $this->input->post('recurring_type'),
			'notify_days' => $this->input->post('notify_days'),
			'assign_officer_id' => $this->input->post('assign_officer_id'),
			'create_by' => $this->session->userdata('userid'),
			'create_date' => date('Y-m-d'),
			'priority' => $this->input->post('priority'),
			'status' => $next_action
		);
		$this->db->trans_start();
		$this->db->insert('pr_task_recurring', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$insert_id = $this->db->insert_id();
			$recurring_task_id = $this->db->insert_id();
			if($next_action == 'CONFIRMED' && $this->input->post('due_date') == date('Y-m-d')){
				$this->addTaskFromRecurring($recurring_task_id);
			}
			$this->db->trans_commit();
			return $insert_id;
		}
	}

	function getRecurringTaskById($recurring_task_id){
		$this->db->select('pr_task_recurring.*, pr_config_task.task_name');
		$this->db->join('pr_config_task','pr_config_task.task_id = pr_task_recurring.task_id');
		$this->db->where('pr_task_recurring.id',$recurring_task_id);
		$query = $this->db->get('pr_task_recurring'); 
		if($query->num_rows() > 0){
			return $query->row(); 
		}else{
			return false;
		}
	}

	function addTaskFromRecurring($recurring_task_id){
		$recurring_task = $this->getRecurringTaskById($recurring_task_id);
		$number = $this->getmaincode('task_number','','pr_task_masterdata');
		$data = array(
			'task_number' => $number,
			'task_id' => $recurring_task->task_id,
			'recurrent_task_id' => $recurring_task_id,
			'unit_id' => $recurring_task->unit_id,
			'property_id' => $recurring_task->property_id,
			'task_details' => $recurring_task->task_details,
			'due_date' => $recurring_task->due_date,
			'task_type' => 'Recurring Task',
			'assign_officer_id' => $recurring_task->assign_officer_id,
			'create_by' => $this->session->userdata('userid'),
			'create_date' => date('Y-m-d'),
			'priority' => $recurring_task->priority,
			'task_status' => 'New',
			'status' => 'CONFIRMED'
		);
		$this->db->trans_start();
		$this->db->insert('pr_task_masterdata', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$entry_id = $this->db->insert_id();
			$this->common_model->add_notification_officer('pr_task_masterdata','Attend task','tasks/updateTask/'.$this->encryption->encode($entry_id),$entry_id,$recurring_task->assign_officer_id);
			$this->db->trans_commit();
			return $entry_id;
		}
	}

	function editRecurringTask($id, $p_id, $unit_id){
		$data = array(
			'task_id' => $this->input->post('task_id'),
			'unit_id' => $unit_id,
			'property_id' => $p_id,
			'task_details' => $this->input->post('task_details'),
			'due_date' => $this->input->post('due_date'),
			'end_date' => $this->input->post('end_date'),
			'recurring_type' => $this->input->post('recurring_type'),
			'notify_days' => $this->input->post('notify_days'),
			'assign_officer_id' => $this->input->post('assign_officer_id'),
			'update_by' => $this->session->userdata('userid'),
			'update_date' => date('Y-m-d'),
			'priority' => $this->input->post('priority'),
		);
		$this->db->trans_start();
		$this->db->where('id', $id);
		$this->db->update('pr_task_recurring', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function changeRecurringTaskStatus($recurring_task_id, $status){
		if($status == 'Check'){
			$data = array(
				'check_by' => $this->session->userdata('userid'),
				'check_date' => date('Y-m-d'),
				'status' => 'CONFIRM',
			);
		}
		if($status == 'Confirm'){
			$data = array(
				'confirm_by' => $this->session->userdata('userid'),
				'confirm_date' => date('Y-m-d'),
				'status' => 'CONFIRMED',
			);
			$reccureing_task = $this->getRecurringTaskById($recurring_task_id);
			if($reccureing_task->due_date <= date('Y-m-d')){
				$this->addTaskFromRecurring($recurring_task_id);
			}
		}
		$this->db->trans_start();
		$this->db->where('id', $recurring_task_id);
		$this->db->update('pr_task_recurring', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function deleteRecurringTask(){
		$recurring_task = $this->getRecurringTaskById($this->input->post('id'));
		if($recurring_task->status == 'CONFIRMED'){
			$data = array(
				'status' => 'DELETED',
			);
			$this->db->trans_start();
			$this->db->where('id', $this->input->post('id'));
			$this->db->update('pr_task_recurring', $data);
			$this->db->trans_complete();
			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return false;
			} else {
				$this->db->trans_commit();
				return true;
			}
		}else{
			$this->db->where('id',$this->input->post('id'));
			$this->db->trans_start();
			$this->db->delete('pr_task_recurring');
			$this->db->trans_complete();
			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return false;
			} else {
				$this->db->trans_commit();
				return true;
			}
		}
	}

	function getAllSearchRecurringTasks($limit, $page){
		$this->db->select('pr_task_recurring.*, pr_config_task.task_name');
		$this->db->join('pr_config_task','pr_config_task.task_id = pr_task_recurring.task_id');
		if($this->session->userdata('property_search')){
			$property_id = $this->session->userdata('property_search');
			$p_id = NULL;
            $unit_id = NULL;
            $property_id_type = substr($property_id, 0, 1); //check whether property id or unit id
            if($property_id_type == 'p'){
                $p_id = substr($property_id, 1);
            }else if($property_id_type == 'u'){
                $unit_id = substr($property_id, 1);
            }
			$this->db->where('pr_task_recurring.unit_id',$unit_id);
			$this->db->where('pr_task_recurring.property_id',$p_id);
		}
		if($this->session->userdata('search_string')){
			$this->db->like('pr_task_recurring.task_details',$this->session->userdata('search_string'));
			$this->db->or_like('pr_task_recurring.recurring_type',$this->session->userdata('search_string'));
			$this->db->or_like('pr_config_task.task_name',$this->session->userdata('search_string'));
		}
		$this->db->order_by('pr_task_recurring.id','DESC');
		$this->db->limit($limit, $page);
		$query = $this->db->get('pr_task_recurring'); 
		if($query->num_rows() > 0){
			return $query->result(); 
		}else{
			return false;
		}
	}

	function getAllSearchRecurringTasksCount(){
		$this->db->select('pr_task_recurring.*, pr_config_task.task_name');
		$this->db->join('pr_config_task','pr_config_task.task_id = pr_task_recurring.task_id');
		if($this->session->userdata('property_search')){
			$property_id = $this->session->userdata('property_search');
			$p_id = NULL;
            $unit_id = NULL;
            $property_id_type = substr($property_id, 0, 1); //check whether property id or unit id
            if($property_id_type == 'p'){
                $p_id = substr($property_id, 1);
            }else if($property_id_type == 'u'){
                $unit_id = substr($property_id, 1);
            }
			$this->db->where('pr_task_recurring.unit_id',$unit_id);
			$this->db->where('pr_task_recurring.property_id',$p_id);
		}
		if($this->session->userdata('search_string')){
			$this->db->like('pr_task_recurring.task_details',$this->session->userdata('search_string'));
			$this->db->like('pr_task_recurring.recurring_type',$this->session->userdata('search_string'));
			$this->db->or_like('pr_config_task.task_name',$this->session->userdata('search_string'));
		}
		$query = $this->db->get('pr_task_recurring'); 
		if($query->num_rows() > 0){
			return $query->num_rows(); 
		}else{
			return false;
		}
	}
}