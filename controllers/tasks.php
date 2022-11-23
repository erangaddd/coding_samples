<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Tasks extends CI_Controller {

	 function __construct() {
        parent::__construct();
		$this->is_logged_in();
		$this->load->model("pr/common_model");
        $this->load->model("pr/property_model");
        $this->load->model("pr/agreement_model");
        $this->load->model("pr/application_model");
        $this->load->model("pr/task_model");
        $this->load->model("pr/workorder_model");
        $this->load->model("pr/rental_model");
        $this->load->library("pagination");
    }

    //***************************************Task Requests ****************************/
	
	public function requests()
	{
		if ( ! check_access('view_requests'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
        }
        $this->session->unset_userdata('property');
        $this->session->unset_userdata('search_string');
		$config["base_url"] = base_url() . "tasks/requests";
        $config["total_rows"] = $this->task_model->getTaskRequestCount();
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        $this->pagination->initialize($config);
        $config = array();
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$data['requests']=$this->task_model->getAllTaskRequests(RAW_COUNT, $page);
        $data["links"] = $this->pagination->create_links();
        $properties = $this->property_model->getAllPropertyUnits();
        $properties_array = array();
        foreach($properties as $property){
            $properties_array[$property->property_id]['id'] = $property->property_id;
            $properties_array[$property->property_id]['name'] = $property->property_name;
            $properties_array[$property->property_id]['units'][] = array(
                'uid' => $property->unit_id,
                'uname' => $property->unit_name
            );
        }
        $data['properties'] = $properties_array;
		$data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'Requests';
		$data['search'] = 'no';
		$this->load->view('pr/tasks/requests',$data);
	}
    
    function searchTaskRequests(){
        if($_POST){
            $property = $this->input->post('property');
            $search_string = $this->input->post('search_string');
            if($property == '' &&  $search_string == ''){
                $this->session->set_flashdata('error', 'At least one field needs to be filled.');
                redirect('tasks/requests');
            }
            $search_data = array(
                'property'  => $property,
                'search_string'     => $search_string
            );
            $this->session->set_userdata($search_data);
        }
        $config["base_url"] = base_url() . "tasks/searchTaskRequests";
        $config["total_rows"] = $this->task_model->getTaskRequestCountSearch();
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        $this->pagination->initialize($config);
        $config = array();
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$data['requests']=$this->task_model->getAllTaskRequestsSearch(RAW_COUNT, $page);
        $data["links"] = $this->pagination->create_links();
        $properties = $this->property_model->getAllPropertyUnits();
        $properties_array = array();
        foreach($properties as $property){
            $properties_array[$property->property_id]['id'] = $property->property_id;
            $properties_array[$property->property_id]['name'] = $property->property_name;
            $properties_array[$property->property_id]['units'][] = array(
                'uid' => $property->unit_id,
                'uname' => $property->unit_name
            );
        }
        $data['properties'] = $properties_array;
		$data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'Requests';
		$data['search'] = 'yes';
		$this->load->view('pr/tasks/requests',$data);
    }

    function rejectRequest($request_id){
        if ( ! check_access('reject_requests'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('tasks/requests');
		}
        $request_id = $this->encryption->decode($request_id);
        if($_POST){
            if($this->task_model->rejectRequest( $request_id)){
                $org_data = getSystemConfig(); //configuration helper
                $request_data = $this->task_model->getDatabyRequestId($request_id);
                $subject = 'Maintanance request rejected by '.$org_data->company_name;
                $message .= 'Dear '.$request_data->first_name.' '.$request_data->last_name;
                $message .= '<br><h3>Maintenance request '.$request_data->number.' has been rejected with the following reason;</h3>';
                $message .= '<br>"'.$this->input->post('reason').'"<br>';
                $to = $request_data->email;
                $sms_to = $request_data->mobile;
                if($to != ''){
                    if(sendemail($to,$subject,$message)){
                        $sms_message = 'Request '.$request_data->number.' rejected - '.$this->input->post('reason');
                        if($sms_to != ''){
                            if(sendSMS($sms_to, $sms_message)){
                                $this->session->set_flashdata('msg', 'Successfully rejected the request.');
                            }else{
                                $this->session->set_flashdata('error', 'Unable to send the sms.');
                            }
                            $this->common_model->delete_notification('pr_task_requests',$request_id);
                        }
                    }else{
                        $this->session->set_flashdata('error', 'Unable to send the email.');
                    }
                }else{
                    $this->common_model->delete_notification('pr_task_requests',$request_id);
                    $this->session->set_flashdata('msg', 'Successfully rejected the request.');
                }
            }else{
                $this->session->set_flashdata('error', 'Unable to reject the request.');
            }
            redirect('tasks/requests');
        }
        $data['request'] = $this->task_model->getRequest($request_id);
		$data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'Requests';
		$this->load->view('pr/tasks/reject_request',$data);
    }

    function deleteRequest(){
        if ( ! check_access('delete_requests'))
		{
			echo 'You do not have permission to perform this action.';
			exit;
		}
        $request_id = $this->input->post('id');
        if($this->task_model->deleteRequest($request_id)){
            $table_name = 'pr_task_request_attachments';
            $request_files = $this->common_model->getData(
                $column = 'request_id', //string
                $id = $request_id, //int
                $table_name, //string
                $like = '', //array
                $not_like = '', //array
                $count = '', //int
                $page = '', //int
                $order_by = '' //array
            ); 
            if($request_files){
                $path = './uploads/'.getUniqueID().'/'; //common helper function
                foreach($request_files as $request_file){
                    $this->common_model->deleteData('id', $request_file->id, 'pr_task_request_attachments');
                    unlink($path.$request_file->file); //remove file
                    unlink($path.'/thumbnail/'.$request_file->file); //remove file
                }
            }
            $this->common_model->delete_notification('pr_task_requests',$request_id);
            echo '1';
		}else{
            echo 'Unable to delete the request.';
		}
    }

    function resetRequest(){
        if ( ! check_access('reset_requests'))
		{
			echo 'You do not have permission to perform this action.';
			exit;
		}
        $request_id = $this->input->post('id');
        if($this->task_model->resetRequest($request_id)){
            $this->common_model->add_notification('pr_task_requests','Create task from requests','tasks/requests',$request_id,'confirm_requests');
            echo '1';
		}else{
            echo 'Unable to reset the request.';
		}
    }

    function addRequest(){
        if ( ! check_access('add_requests'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('tasks/requests');
		}
        if($_POST){
            if($this->input->post('request_type') == 'customer'){
                $table_name = 'pr_rent_leaseagreement';
                $agreement_data = $this->common_model->getData(
                    $column = 'list_id', //string
                    $id = $this->input->post('list_id'), //int
                    $table_name, //string
                    $like = '', //array
                    $not_like = '', //array
                    $count = '', //int
                    $page = '', //int
                    $order_by = '', //array
                    $raw = 'yes' 
                );
                $list_id = $agreement_data->list_id;
                $p_id = $agreement_data->property_id;
                $unit_id = $agreement_data->unit_id;
            }else if($this->input->post('request_type') == 'general'){
                $property_id = $this->input->post('property');
                $p_id = NULL;
                $unit_id = NULL;
                $property_id_type = substr($property_id, 0, 1); //check whether property id or unit id
                if($property_id_type == 'p'){
                    $p_id = substr($property_id, 1);
                }else if($property_id_type == 'u'){
                    $unit_id = substr($property_id, 1);
                }
                $list_id = NULL;
            }
           
            if($request_id = $this->task_model->addRequest($list_id, $p_id, $unit_id)){
                //now we move files from temp folder to unique folder
                if ($this->input->post('file_array')){
                    $image_count = 1;
                    $path = 'uploads/'.getUniqueID().'/'; //common helper function
                    $files = explode(',', $this->input->post('file_array')); //create an array from files
                    //move files
                    foreach($files as $raw){
                        if (file_exists($path)){
                            if (file_exists('uploads/temp/'.$raw)) {
                                rename('./uploads/temp/'.$raw, './uploads/'.getUniqueID().'/'.$raw);
                            }
                            if (file_exists('uploads/temp/thumbnail/'.$raw)) {
                                rename('./uploads/temp/thumbnail/'.$raw, './uploads/'.getUniqueID().'/thumbnail/'.$raw);
                            }
                            $this->task_model->addRequestAttachments($request_id,$raw);
                        }
                    }
                }
                $org_data = getSystemConfig(); //configuration helper
                $request_data = $this->task_model->getDatabyRequestId($request_id);
                $subject = 'Maintanance request created by '.$org_data->company_name;
                $message .= 'Dear '.$request_data->first_name.' '.$request_data->last_name;
                $message .= '<br><h3>Your maintenance request has been created.</h3>';
                $message .= 'Request Number: '.$request_data->number.'<br>';
                $message .= 'Request Detail: '.$request_data->task_details.'<br>';
                $message .= 'Property: '.getUnitNamebyId($request_data->unit_id).'<br>';
                $to = $request_data->email;
                $sms_to = $request_data->mobile;
                if($to != ''){
                    if(sendemail($to,$subject,$message)){
                        $sms_message = 'Maintenance request '.$request_data->number.' created - '.$request_data->task_details;
                        if($sms_to != ''){
                            if(sendSMS($sms_to, $sms_message)){
                                $this->session->set_flashdata('msg', 'Successfully created the request.');
                            }else{
                                $this->session->set_flashdata('error', 'Unable to send the sms.');
                            }
                        }
                        $this->common_model->add_notification('pr_task_requests','Create task from requests','tasks/requests',$request_id,'confirm_requests');
                    }else{
                        $this->session->set_flashdata('error', 'Unable to send the email.');
                    }
                }else{
                    $this->common_model->add_notification('pr_task_requests','Create task from requests','tasks/requests',$request_id,'confirm_requests');
                    $this->session->set_flashdata('msg', 'Successfully created the request.');
                }
                
            }else{
                $this->session->set_flashdata('error', 'Unable to create the request.');
            }
            redirect('tasks/addRequest');
        }
        $table_name = 'pr_config_task';
        $data['types'] = $this->common_model->getData(
            $column = '', //string
            $id = '', //int
            $table_name, //string
            $like = '', //array
            $not_like = '', //array
            $count = '', //int
            $page = '', //int
            $order_by = '' //array
        );
        //get properties with moved in agreements
        $data['properties'] = $this->property_model->getRentPropertyUnits();
		$data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'Requests';
		$this->load->view('pr/tasks/add_request',$data);
    }

    function getRequestdata(){
        $request_id = $this->input->post('id');
        $data['request'] = $request = $this->task_model->getDatabyRequestId($request_id);
        $table_name = 'pr_task_request_attachments';
        $data['files'] = $this->common_model->getData(
            $column = 'request_id', //string
            $id = $request_id, //int
            $table_name, //string
            $like = '', //array
            $not_like = '', //array
            $count = '', //int
            $page = '', //int
            $order_by = '' //array
        );
        $this->load->view('pr/tasks/view_request',$data);
    }

    function confirmRequest($request_id){
        if ( ! check_access('confirm_requests'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('tasks/requests');
		}
        $request_id = $this->encryption->decode($request_id);
        $data['request'] = $request = $this->task_model->getDatabyRequestId($request_id);
        if($_POST){
            if($task_id = $this->task_model->confirmRequest($request)){
                $table = 'pr_task_masterdata';
		        $next_action = checkApproveLevel($table,'PENDING'); //common helper
                if($next_action == 'CHECK'){
                    $this->common_model->add_notification('pr_task_masterdata','Check new task','tasks/allTasks',$task_id,'check_tasks');
                }
                if($next_action == 'CONFIRM'){
                    $this->common_model->add_notification('pr_task_masterdata','Confirm new task','tasks/allTasks',$task_id,'confirm_tasks');
                }
                if($next_action == 'CONFIRMED'){
                    $task = $this->task_model->getTaskById($task_id);
                    $this->common_model->add_notification_officer('pr_task_masterdata','Attend task','tasks/updateTask/'.$this->encryption->encode($task_id),$task_id,$task->assign_officer_id);
                }
                $this->common_model->delete_notification('pr_task_requests',$request_id);
                $this->session->set_flashdata('msg', 'Successfully created the task.');
            }else{
                $this->session->set_flashdata('error', 'Unable to create the task.');
            }
            redirect('tasks/requests');
        }
        $table_name = 'pr_config_task';
        $data['types'] = $this->common_model->getData(
            $column = '', //string
            $id = '', //int
            $table_name, //string
            $like = '', //array
            $not_like = '', //array
            $count = '', //int
            $page = '', //int
            $order_by = '' //array
        );
        $data['units'] = $this->property_model->getAllPropertyUnits();
        $data['properties'] = $this->property_model->getAllProperties();
        $data['user_list'] = $this->common_model->getData('status','A','hr_empmastr','','','','','');
        $data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'Requests';
		$this->load->view('pr/tasks/confirm_request',$data);
    }

    //**************************************** TASKS **************************************/

    function addTask(){
        if ( ! check_access('add_task'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('tasks/allTasks');
		}
        if($_POST){
            $property_id = $this->input->post('property');
            $p_id = NULL;
            $unit_id = NULL;
            $property_id_type = substr($property_id, 0, 1); //check whether property id or unit id
            if($property_id_type == 'p'){
                $p_id = substr($property_id, 1);
            }else if($property_id_type == 'u'){
                $unit_id = substr($property_id, 1);
            }
            $list_id = NULL;
            if($task_id = $this->task_model->addTask($p_id, $unit_id)){
                if ($this->input->post('file_array')){
                    $image_count = 1;
                    $path = 'uploads/'.getUniqueID().'/'; //common helper function
                    $files = explode(',', $this->input->post('file_array')); //create an array from files
                    //move files
                    foreach($files as $raw){
                        if (file_exists($path)){
                            if (file_exists('uploads/temp/'.$raw)) {
                                rename('./uploads/temp/'.$raw, './uploads/'.getUniqueID().'/'.$raw);
                            }
                            if (file_exists('uploads/temp/thumbnail/'.$raw)) {
                                rename('./uploads/temp/thumbnail/'.$raw, './uploads/'.getUniqueID().'/thumbnail/'.$raw);
                            }
                            $this->task_model->addTaskAttachments($task_id,$raw);
                        }
                    }
                }
                $table = 'pr_task_masterdata';
		        $next_action = checkApproveLevel($table,'PENDING'); //common helper
                if($next_action == 'CHECK'){
                    $this->common_model->add_notification('pr_task_masterdata','Check new task','tasks/allTasks',$task_id,'check_tasks');
                }
                if($next_action == 'CONFIRM'){
                    $this->common_model->add_notification('pr_task_masterdata','Confirm new task','tasks/allTasks',$task_id,'confirm_tasks');
                }
                if($next_action == 'CONFIRMED'){
                    $task = $this->task_model->getTaskById($task_id);
                    $this->common_model->add_notification_officer('pr_task_masterdata','Attend task','tasks/updateTask/'.$this->encryption->encode($task_id),$task_id,$task->assign_officer_id);
                }
                $this->session->set_flashdata('msg', 'Successfully created the task.');
            }else{
                $this->session->set_flashdata('error', 'Unable to create the task.');
            }
            redirect('tasks/allTasks');
        }
        $table_name = 'pr_config_task';
        $data['types'] = $this->common_model->getData(
            $column = '', //string
            $id = '', //int
            $table_name, //string
            $like = '', //array
            $not_like = '', //array
            $count = '', //int
            $page = '', //int
            $order_by = '' //array
        );
        $data['units'] = $this->property_model->getAllPropertyUnits();
        $data['properties'] = $this->property_model->getAllProperties();
        $data['user_list'] = $this->common_model->getData('status','A','hr_empmastr','','','','','');
        $data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'All Tasks';
		$this->load->view('pr/tasks/add_task',$data);
    }

    function allTasks(){
        if ( ! check_access('view_tasks'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
        }
        $this->session->unset_userdata('property_search');
        $this->session->unset_userdata('search_string');
        $this->session->unset_userdata('task_status');
        $this->session->unset_userdata('priority');
		$config["base_url"] = base_url() . "tasks/allTasks";
        $config["total_rows"] = $this->task_model->getAllTaskNotComepletedCount();
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        $this->pagination->initialize($config);
        $config = array();
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$data['tasks']=$this->task_model->getAllTaskNotComepleted(RAW_COUNT, $page);
        $data["links"] = $this->pagination->create_links();
        $data['units'] = $this->property_model->getAllPropertyUnits();
        $data['properties'] = $this->property_model->getAllProperties();
		$data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'All Tasks';
		$data['search'] = 'no';
		$this->load->view('pr/tasks/all_tasks',$data);
    }

    function editTask($action, $id){
        $action = $this->encryption->decode($action);
        $task_id = $this->encryption->decode($id);

        if($_POST){
            if ( ! check_access('edit_task'))
            {
                $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
                 redirect('tasks/allTasks');
            }
            $task_id = $this->input->post('id');
            if($this->task_model->editTask()){
                if ($this->input->post('file_array')){
                    $image_count = 1;
                    $path = 'uploads/'.getUniqueID().'/'; //common helper function
                    $files = explode(',', $this->input->post('file_array')); //create an array from files
                    //move files
                    foreach($files as $raw){
                        if (file_exists($path)){
                            if (file_exists('uploads/temp/'.$raw)) {
                                rename('./uploads/temp/'.$raw, './uploads/'.getUniqueID().'/'.$raw);
                            }
                            if (file_exists('uploads/temp/thumbnail/'.$raw)) {
                                rename('./uploads/temp/thumbnail/'.$raw, './uploads/'.getUniqueID().'/thumbnail/'.$raw);
                            }
                            $this->task_model->addTaskAttachments($task_id,$raw);
                        }
                    }
                }
                $this->session->set_flashdata('msg', 'Successfully updated the task.');
            }else{
                $this->session->set_flashdata('error', 'Unable to update the task.');
            }
            redirect('tasks/allTasks');
        }
        
        $table_name = 'pr_config_task';
        $data['types'] = $this->common_model->getData(
            $column = '', //string
            $id = '', //int
            $table_name, //string
            $like = '', //array
            $not_like = '', //array
            $count = '', //int
            $page = '', //int
            $order_by = '' //array
        );
        $table_name = 'pr_task_masterdata';
        $data['task'] = $task =  $this->common_model->getData(
            $column = 'id', //string
            $id = $task_id, //int
            $table_name, //string
            $like = '', //array
            $not_like = '', //array
            $count = '', //int
            $page = '', //int
            $order_by = '', //array
            $raw = 'yes'
        );
        $data['attachments'] = $this->task_model->getAttachemntsByTaskId($task_id);
        $data['action'] = $action;
        $data['units'] = $this->property_model->getAllPropertyUnits();
        $data['properties'] = $this->property_model->getAllProperties();
        $data['user_list'] = $this->common_model->getData('status','A','hr_empmastr','','','','','');
        $data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'All Tasks';
		$this->load->view('pr/tasks/edit_task',$data);
    }

    function deleteImage($action, $task_id, $file_id){
        $file_id = $this->encryption->decode($file_id);
        if ( ! check_access('edit_task'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('tasks/allTasks');
        }
        if($this->task_model->deleteAttachemnt($file_id)){
            $this->session->set_flashdata('msg', 'Successfully deleted the file.');
        }else{
            $this->session->set_flashdata('error', 'Unable to delete the file.');
        }
        redirect('tasks/editTask/'.$action.'/'.$task_id);
    }

    function checkTask($id){
        $id = $this->encryption->decode($id);
        if ( ! check_access('check_tasks'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('tasks/allTasks');
        }
        if($this->task_model->changeStatus('CONFIRM',$id)){
            $this->common_model->delete_notification('pr_task_masterdata',$id);
            $this->common_model->add_notification('pr_task_masterdata','Confirm new task','tasks/allTasks',$id,'confirm_tasks');
            $this->session->set_flashdata('msg', 'Successfully checked the task.');
        }else{
            $this->session->set_flashdata('error', 'Unable to check the task.');
        }
        redirect('tasks/allTasks');
    }
    
    function confirmTask($task_id){
        $id = $this->encryption->decode($task_id);
        if ( ! check_access('confirm_tasks'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('tasks/allTasks');
        }
        if($this->task_model->changeStatus('CONFIRMED',$id)){
            $task = $this->task_model->getTaskById($id);
            $this->common_model->delete_notification('pr_task_masterdata',$id);
            $this->common_model->add_notification_officer('pr_task_masterdata','Attend task','tasks/updateTask/'.$task_id,$id,$task->assign_officer_id);
            $this->session->set_flashdata('msg', 'Successfully confirmed the task.');
        }else{
            $this->session->set_flashdata('error', 'Unable to confirm the task.');
        }
        redirect('tasks/allTasks');
    }

    function deleteTask(){
        if ( ! check_access('delete_task'))
        {
           echo 'You do not have permission to perform this action';
           exit;
        }
        if($this->task_model->deleteTask()){
            $task_attachments = $this->task_model->getAttachemntsByTaskId($this->input->post('id'));
            if($task_attachments){
                $path = './uploads/'.getUniqueID().'/'; //common helper function
                foreach($task_attachments as $task_attachment){
                    $this->common_model->deleteData('id', $task_attachment->id, 'pr_task_attachment');
                    unlink($path.$task_attachment->file_name); //remove file
                    unlink($path.'/thumbnail/'.$task_attachment->file_name); //remove file
                }
            }
            $this->common_model->delete_notification('pr_task_masterdata',$this->input->post('id'));
            echo '1';
        }else{
            echo 'Unable to delete the task';
        }
        return;
    }

    function deleteConfirmedTask(){
        if ( ! check_access('delete_confirmed_task'))
        {
           echo 'You do not have permission to perform this action';
           exit;
        }
        if($this->task_model->deleteConfirmedTask()){
            $this->common_model->delete_notification('pr_task_masterdata',$this->input->post('id'));
            echo '1';
        }else{
            echo 'Unable to delete the task';
        }
        return;
    }

    function updateTask($id){
        $task_id = $this->encryption->decode($id);
        $data['task'] = $task =$this->task_model->getTaskById($task_id);
        if($_POST){
            if ( ! check_access('update_confirmed_tasks'))
            {
                $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
                redirect('tasks/updateTask/'.$id);
            }
            if($this->task_model->updateTask($task_id)){
                //check officer and send notification
                if($task->assign_officer_id != $this->input->post('assign_officer_id')){
                    $this->common_model->delete_notification('pr_task_masterdata',$task_id);
                    $this->common_model->add_notification_officer('pr_task_masterdata','Attend task','tasks/updateTask/'.$id,$task_id,$this->input->post('assign_officer_id'));
                }
                $this->session->set_flashdata('msg', 'Successfully updated the task.');
            }else{
                $this->session->set_flashdata('error', 'Unable to update the task.');
            }
            redirect('tasks/updateTask/'.$id);
        }
        $data['task_updates'] =$this->task_model->getTaskUpdatesByTaskId($task_id);
        $data['task_bills'] =$this->task_model->getTaskBillsByTaskId($task_id);
        $data['unit_images'] =  $this->common_model->getData('unit_id',$task->unit_id,'pr_rent_propertyunit_images','','','','','');
        $data['unit_data'] = $this->property_model->getUnitbyId($task->unit_id);
        $data['application_data'] = $this->application_model->getApplicationByListId($task->list_id);
        $data['property_data'] = $this->property_model->getPropertybyId($task->property_id);
        $data['attachments'] = $this->task_model->getAttachemntsByTaskId($task_id);
        $data['user_list'] = $this->common_model->getData('status','A','hr_empmastr','','','','','');
        $data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'All Tasks';
		$this->load->view('pr/tasks/update_task',$data);
    }

    function confirmTaskUpdate(){
        if ( ! check_access('confirm_task_progress'))
        {
           echo 'You do not have permission to perform this action';
           exit;
        }
        if($this->task_model->confirmTaskUpdate()){
            $this->common_model->delete_notification('pr_task_progress',$this->input->post('id'));
            echo '1';
        }else{
            echo 'Unable to confirm the task progress';
        }
        return;
    }

    function deleteTaskUpdate(){
        if ( ! check_access('delete_task_progress'))
        {
           echo 'You do not have permission to perform this action';
           exit;
        }
        if($this->task_model->deleteTaskUpdate()){
            $progress_attachments = $this->task_model->getTaskProgressImages($this->input->post('id'));
            if($progress_attachments){
                $path = './uploads/'.getUniqueID().'/'; //common helper function
                foreach($progress_attachments as $progress_attachment){
                    $this->common_model->deleteData('id', $progress_attachment->id, 'pr_task_progress_images');
                    unlink($path.$progress_attachment->file_name); //remove file
                    unlink($path.'/thumbnail/'.$progress_attachment->file_name); //remove file
                }
            }
            $this->common_model->delete_notification('pr_task_progress',$this->input->post('id'));
            echo '1';
        }else{
            echo 'Unable to delete the task progress';
        }
        return;
    }

    function loadAddProgress(){
        $data['task_id'] = $this->input->post('id');
        $this->load->view('pr/tasks/add_progress',$data);
    }

    function loadTaskProgress(){
        $data['task_progress'] = $this->task_model->getTaskProgress($this->input->post('id'));
        $data['task_progress_images'] = $this->task_model->getTaskProgressImages($this->input->post('id'));
        $this->load->view('pr/tasks/view_progress',$data);
    }

    function addTaskProgress(){
        if($_POST){
            if ( ! check_access('add_task_progress'))
            {
                $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
                redirect('tasks/allTasks');
            }
            if($progress_id = $this->task_model->addTaskProgress()){
                if ($this->input->post('file_array')){
                    $image_count = 1;
                    $path = 'uploads/'.getUniqueID().'/'; //common helper function
                    $files = explode(',', $this->input->post('file_array')); //create an array from files
                    //move files
                    foreach($files as $raw){
                        if (file_exists($path)){
                            if (file_exists('uploads/temp/'.$raw)) {
                                rename('./uploads/temp/'.$raw, './uploads/'.getUniqueID().'/'.$raw);
                            }
                            if (file_exists('uploads/temp/thumbnail/'.$raw)) {
                                rename('./uploads/temp/thumbnail/'.$raw, './uploads/'.getUniqueID().'/thumbnail/'.$raw);
                            }
                            $this->task_model->addTaskProgressImages($progress_id,$raw);
                        }
                    }
                }
                $this->common_model->add_notification('pr_task_progress','Confirm task progress','tasks/updateTask/'.$this->encryption->encode($this->input->post('task_id')),$progress_id,'confirm_task_progress');
                $this->session->set_flashdata('msg', 'Successfully added the task progress.');
            }else{
                $this->session->set_flashdata('error', 'Unable to add the task progress.');
            }
            redirect('tasks/updateTask/'.$this->encryption->encode($this->input->post('task_id')));
        }
    }

    function loadAddBill(){
        $data['task_id'] = $this->input->post('id');
        $this->load->view('pr/tasks/add_bill',$data);
    }

    function confirmTaskBill($task_id, $id){
        //$task_id = $this->encryption->decode($task_id);
        if ( ! check_access('confirm_task_bills'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('tasks/updateTask/'.$task_id);
        }
        $bill_id = $this->encryption->decode($id);
        if($this->task_model->confirmTaskBill($bill_id)){
            $this->common_model->delete_notification('pr_task_bills',$bill_id);
            $this->session->set_flashdata('msg', 'Successfully confirmed the task bill.');
        }else{
            $this->session->set_flashdata('error', 'Unable to confirm the task bill.');
        }
        redirect('tasks/updateTask/'.$task_id);
    }

    function rejectTaskBill($task_id, $id){
        //$task_id = $this->encryption->decode($task_id);
        if ( ! check_access('reject_task_bills'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('tasks/updateTask/'.$task_id);
        }
        $bill_id = $this->encryption->decode($id);
        if($this->task_model->deleteTaskBill($bill_id)){
            $this->common_model->delete_notification('pr_task_bills',$bill_id);
            $this->session->set_flashdata('msg', 'Successfully rejected the task bill.');
        }else{
            $this->session->set_flashdata('error', 'Unable to reject the task bill.');
        }
        redirect('tasks/updateTask/'.$task_id);
    }

    function deleteTaskBill($task_id, $id){
        //$task_id = $this->encryption->decode($task_id);
        if ( ! check_access('delete_task_bills'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('tasks/updateTask/'.$task_id);
        }
        $bill_id = $this->encryption->decode($id);
        if($this->task_model->deleteTaskBill($bill_id)){
            $this->session->set_flashdata('msg', 'Successfully deleted the task bill.');
        }else{
            $this->session->set_flashdata('error', 'Unable to delete the task bill.');
        }
        redirect('tasks/updateTask/'.$task_id);
    }

    function addTaskBill(){
        if($_POST){
            if ( ! check_access('add_task_bills'))
            {
                $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
                redirect('tasks/allTasks');
            }
            if($bill_id = $this->task_model->addTaskBill()){
                $this->common_model->add_notification('pr_task_bills','Confirm task bill','tasks/updateTask/'.$this->encryption->encode($this->input->post('task_id')),$bill_id,'confirm_task_bills');
                $this->session->set_flashdata('msg', 'Successfully added the task bill.');
            }else{
                $this->session->set_flashdata('error', 'Unable to add the task bill.');
            }
            redirect('tasks/updateTask/'.$this->encryption->encode($this->input->post('task_id')));
        }
    }

    function searchTasks(){
        if($_POST){
            $property_search = $this->input->post('property');
            $search_string = $this->input->post('search_string');
            $task_status = $this->input->post('task_status');
            $priority = $this->input->post('priority');
            if($property_search == '' &&  $search_string == '' &&  $priority == '' &&  $task_status == ''){
                $this->session->set_flashdata('error', 'At least one field needs to be filled.');
                redirect('tasks/allTasks');
            }
            $search_data = array(
                'property_search'  => $property_search,
                'search_string'     => $search_string,
                'task_status' => $task_status,
                'priority' => $priority
            );
            $this->session->set_userdata($search_data);
        }
        $config["base_url"] = base_url() . "tasks/searchTasks";
        $config["total_rows"] = $this->task_model->getSearchTasksCount();
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        $this->pagination->initialize($config);
        $config = array();
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$data['tasks']=$this->task_model->getSearchTasks(RAW_COUNT, $page);
        $data["links"] = $this->pagination->create_links();
        $data['units'] = $this->property_model->getAllPropertyUnits();
        $data['properties'] = $this->property_model->getAllProperties();
		$data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'All Tasks';
		$data['search'] = 'yes';
		$this->load->view('pr/tasks/all_tasks',$data);
    }

    function getPropertyType(){
        $data['request_type'] = $request_type = $this->input->post('request_type');
        if($request_type == 'general'){
            $data['units'] = $this->property_model->getAllPropertyUnits();
            $data['properties'] = $this->property_model->getAllProperties();
        }else if($request_type == 'customer'){
            $data['properties'] = $this->property_model->getRentPropertyUnits();
        }else{
            return false;
        }
        $this->load->view('pr/tasks/property_type',$data);
    }

    function closeTask(){
        $task_id = $this->input->post('id');
        $errors = '';
        //check pending work orders
        if($workorders = $this->workorder_model->getPendingWorkordersByTaskId($task_id)){
            $errors .= count($workorders).' pending work orders to close<br>';
        }
        //check unconfirmed bills
        if($bills = $this->task_model->getPendingBillsByTaskId($task_id)){
            $errors .= count($bills).' pending bills to confirm<br>';
        }
        //check unconfirmed task progress
        if($progress = $this->task_model->getPendingProgressByTaskId($task_id)){
            $errors .= count($progress).' pending progress to confirm<br>';
        }
        if($errors != ''){
            echo 'Fix following issue(s) before close the task.<br>';
            echo $errors;
            exit;
        }else{
            if($this->task_model->closeTask($task_id)){
                //send bill
                $task = $this->task_model->getTaskById($task_id);
                if($task->bill_id != ''){
                    $this->sendTaskBill($task_id);
                }
                $this->common_model->delete_notification('pr_task_masterdata',$task_id);
                echo '1';
                exit;
            }else{
                echo 'Something went wrong.';
                exit;
            }
        }
    }

    function sendTaskBill($task_id){
        $message = '';
        $org_data = getSystemConfig(); //configuration helper
        $task = $this->task_model->getTaskById($task_id);
        $request_data = $this->task_model->getDatabyRequestId($task->request_id);
        $bill = $this->rental_model->getBillById($task->bill_id);
        $task_bills = $this->task_model->getTaskBillsByTaskId($task_id);
        $subject = 'Invoice for the maintenance request '.$request_data->number;
        $message .= 'Dear '.$request_data->first_name.' '.$request_data->last_name;
        $message .= '<br><h3>Here is the invoice for the recent maintenance work.</h3>';
        $message .= 'Request Number: '.$request_data->number.'<br>';
        $message .= 'Request Detail: '.$request_data->task_details.'<br>';
        $message .= 'Property: '.getUnitNamebyId($request_data->unit_id).'<br><br><br>';
        $message .= '<table class="table table-responsive" cellpadding="0" cellspacing="0" style="min-width:400px;">
            <tr style="background:#06C; color:#ffffff;">
            <td colspan="2" style="padding:10px;">
                <b>Invoice Number:</b> '.$bill->bill_number.'<br>
                <b>Invoice Date:</b> '.date('d/m/Y', strtotime($bill->due_date)).'<br><br>
            </td>
            </tr>
            <tr style="background:#333333; color:#ffffff;">
            <th style="padding:5px; text-align:left;">Description</th>
            <th style="padding:5px;  text-align:right;">Amount ('.get_currency_symbol().')</th>
        </tr>';
        $total = 0;
        if($task_bills){
            foreach($task_bills as $task_bill){
                $message .= ' <tr>
                    <td style="padding:5px; border-bottom:1px solid #999999; border-left:1px solid #999999;">'.$task_bill->description.'</td>
                    <td style="padding:5px; border-bottom:1px solid #999999; border-left:1px solid #999999; border-right:1px solid #999999; text-align:right;">'.number_format($task_bill->bill_amount,2).'</td>
                </tr>';
                $total = $total + $task_bill->bill_amount;
            }
        }
                           
            $message .=  '<tr style="background:#333333; color:#ffffff;">
                            <th style="padding:5px; text-align:left;">Total</th>
                            <th style="padding:5px; text-align:right;">'.number_format($total,2).'</th>
                            </tr>
                    </table>';
        $to = $request_data->email;
        $sms_to = $request_data->mobile;
        if($to != ''){
            if(sendemail($to,$subject,$message)){
                $sms_message = 'Invoice amount for the maintenance request '.$request_data->number.' is - '.get_currency_symbol().'. '.$bill->bill_amount;
                if($sms_to != ''){
                    if(sendSMS($sms_to, $sms_message)){
                        $this->session->set_flashdata('msg', 'Successfully sent the invoice.');
                    }else{
                        $this->session->set_flashdata('error', 'Unable to send the sms.');
                    }
                }
            }else{
                $this->session->set_flashdata('error', 'Unable to send the email.');
            }
        }else{
            $this->session->set_flashdata('msg', 'Successfully ent the invoice.');
        }
    }

    // ******************************************Recurring Tasks*******************************

    function recurringTasks(){
        if ( ! check_access('view_recurring_tasks'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
        }
        $this->session->unset_userdata('property_search');
        $this->session->unset_userdata('search_string');
        $this->session->unset_userdata('priority');
		$config["base_url"] = base_url() . "tasks/recurringTasks";
        $config["total_rows"] = $this->task_model->getAllActiveRecurringTasksCount();
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        $this->pagination->initialize($config);
        $config = array();
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$data['tasks']=$this->task_model->getAllActiveRecurringTasks(RAW_COUNT, $page);
        $data["links"] = $this->pagination->create_links();
        $data['units'] = $this->property_model->getAllPropertyUnits();
        $data['properties'] = $this->property_model->getAllProperties();
		$data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'Recurring Tasks';
		$data['search'] = 'no';
		$this->load->view('pr/recurring_tasks/index',$data);
    }

    function addRecurringTask(){
        if ( ! check_access('add_recurring_tasks'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('tasks/recurringTasks');
		}
        if($_POST){
            $property_id = $this->input->post('property');
            $p_id = NULL;
            $unit_id = NULL;
            $property_id_type = substr($property_id, 0, 1); //check whether property id or unit id
            if($property_id_type == 'p'){
                $p_id = substr($property_id, 1);
            }else if($property_id_type == 'u'){
                $unit_id = substr($property_id, 1);
            }
            if($id = $this->task_model->addRecurringTask($p_id, $unit_id)){
                $table = 'pr_task_recurring';
		        $next_action = checkApproveLevel($table,'PENDING'); //common helper
                if($next_action == 'CHECK'){
                    $this->common_model->add_notification('pr_task_recurring','Check new recurring task','tasks/recurringTasks',$id,'check_recurring_tasks');
                }
                if($next_action == 'CONFIRM'){
                    $this->common_model->add_notification('pr_task_recurring','Confirm new recurring task','tasks/recurringTasks',$id,'confirm_recurring_tasks');
                }
                $this->session->set_flashdata('msg', 'Successfully created the recurring task.');
            }else{
                $this->session->set_flashdata('error', 'Unable to create the recurring task.');
            }
            redirect('tasks/recurringTasks');
        }
        $table_name = 'pr_config_task';
        $data['types'] = $this->common_model->getData(
            $column = '', //string
            $id = '', //int
            $table_name, //string
            $like = '', //array
            $not_like = '', //array
            $count = '', //int
            $page = '', //int
            $order_by = '' //array
        );
        $data['units'] = $this->property_model->getAllPropertyUnits();
        $data['properties'] = $this->property_model->getAllProperties();
        $data['user_list'] = $this->common_model->getData('status','A','hr_empmastr','','','','','');
        $data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'Recurring Tasks';
		$this->load->view('pr/recurring_tasks/add_recurring_task',$data);
    }

    function editRecurringTask($action, $id){
        if ( ! check_access('edit_recurring_tasks'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('tasks/recurringTasks');
		}
        $action = $this->encryption->decode($action);
        $recurring_task_id = $this->encryption->decode($id);
        if($_POST){
            $property_id = $this->input->post('property');
            $p_id = NULL;
            $unit_id = NULL;
            $property_id_type = substr($property_id, 0, 1); //check whether property id or unit id
            if($property_id_type == 'p'){
                $p_id = substr($property_id, 1);
            }else if($property_id_type == 'u'){
                $unit_id = substr($property_id, 1);
            }
            if($this->task_model->editRecurringTask($recurring_task_id, $p_id, $unit_id)){
                $this->session->set_flashdata('msg', 'Successfully edited the recurring task.');
            }else{
                $this->session->set_flashdata('error', 'Unable to edit the recurring task.');
            }
            redirect('tasks/recurringTasks');
        }
        $table_name = 'pr_config_task';
        $data['types'] = $this->common_model->getData(
            $column = '', //string
            $id = '', //int
            $table_name, //string
            $like = '', //array
            $not_like = '', //array
            $count = '', //int
            $page = '', //int
            $order_by = '' //array
        );
        $data['action'] = $action;
        $data['task'] = $this->task_model->getRecurringTaskById($recurring_task_id);
        $data['units'] = $this->property_model->getAllPropertyUnits();
        $data['properties'] = $this->property_model->getAllProperties();
        $data['user_list'] = $this->common_model->getData('status','A','hr_empmastr','','','','','');
        $data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'Recurring Tasks';
		$this->load->view('pr/recurring_tasks/edit_reccuring_task',$data);
    }

    function checkRecurringTask($id){
        if ( ! check_access('check_recurring_tasks'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('tasks/recurringTasks');
		}
        $recurring_task_id = $this->encryption->decode($id);
        if($this->task_model->changeRecurringTaskStatus($recurring_task_id, 'Check')){
            $this->common_model->delete_notification('pr_task_recurring',$recurring_task_id);
            $this->common_model->add_notification('pr_task_recurring','Confirm new recurring task','tasks/recurringTasks',$recurring_task_id,'confirm_recurring_tasks');
            $this->session->set_flashdata('msg', 'Successfully checked the recurring task.');
        }else{
            $this->session->set_flashdata('error', 'Unable to check the recurring task.');
        }
        redirect('tasks/recurringTasks');
    }

    function confirmRecurringTask($id){
        if ( ! check_access('confirm_recurring_tasks'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('tasks/recurringTasks');
		}
        $recurring_task_id = $this->encryption->decode($id);
        if($this->task_model->changeRecurringTaskStatus($recurring_task_id, 'Confirm')){
            $this->common_model->delete_notification('pr_task_recurring',$recurring_task_id);
            $this->session->set_flashdata('msg', 'Successfully confirmed the recurring task.');
        }else{
            $this->session->set_flashdata('error', 'Unable to confirm the recurring task.');
        }
        redirect('tasks/recurringTasks');
    }

    function deleteRecurringTask(){
        if ( ! check_access('delete_recurring_tasks'))
        {
           echo 'You do not have permission to perform this action';
           exit;
        }
        if($this->task_model->deleteRecurringTask()){
            $this->common_model->delete_notification('pr_task_recurring',$this->input->post('id'));
            echo '1';
        }else{
            echo 'Unable to delete the recurring task';
        }
        return;
    }

    function deleteConfirmedRecurringTask(){
        if ( ! check_access('delete_confirmed_recurring_tasks'))
        {
           echo 'You do not have permission to perform this action';
           exit;
        }
        if($this->task_model->deleteRecurringTask()){
            echo '1';
        }else{
            echo 'Unable to delete the recurring task';
        }
        return;
    }

    function searchRecurringTasks(){
        if($_POST){
            $property_search = $this->input->post('property');
            $search_string = $this->input->post('search_string');
            if($property_search == '' &&  $search_string == ''){
                $this->session->set_flashdata('error', 'At least one field needs to be filled.');
                redirect('tasks/recurringTasks');
            }
            $search_data = array(
                'property_search'  => $property_search,
                'search_string'     => $search_string,
            );
            $this->session->set_userdata($search_data);
        }
        $config["base_url"] = base_url() . "tasks/searchRecurringTasks";
        $config["total_rows"] = $this->task_model->getAllSearchRecurringTasksCount();
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        $this->pagination->initialize($config);
        $config = array();
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$data['tasks']=$this->task_model->getAllSearchRecurringTasks(RAW_COUNT, $page);
        $data["links"] = $this->pagination->create_links();
        $data['units'] = $this->property_model->getAllPropertyUnits();
        $data['properties'] = $this->property_model->getAllProperties();
		$data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'Recurring Tasks';
		$data['search'] = 'yes';
		$this->load->view('pr/recurring_tasks/index',$data);
    }

    /* My tasks */

    function myTasks(){
        if ( ! check_access('view_tasks'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
        }
        $this->session->unset_userdata('property_search');
        $this->session->unset_userdata('search_string');
        $this->session->unset_userdata('task_status');
        $this->session->unset_userdata('priority');
		$config["base_url"] = base_url() . "tasks/myTasks";
        $config["total_rows"] = $this->task_model->getMyTaskNotComepletedCount();
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        $this->pagination->initialize($config);
        $config = array();
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$data['tasks']=$this->task_model->getMyTaskNotComepleted(RAW_COUNT, $page);
        $data["links"] = $this->pagination->create_links();
        $data['units'] = $this->property_model->getAllPropertyUnits();
        $data['properties'] = $this->property_model->getAllProperties();
		$data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'My Tasks';
		$data['search'] = 'no';
		$this->load->view('pr/tasks/my_tasks',$data);
    }

    function searchMyTasks(){
        if($_POST){
            $property_search = $this->input->post('property');
            $search_string = $this->input->post('search_string');
            $task_status = $this->input->post('task_status');
            $priority = $this->input->post('priority');
            if($property_search == '' &&  $search_string == '' &&  $priority == '' &&  $task_status == ''){
                $this->session->set_flashdata('error', 'At least one field needs to be filled.');
                redirect('tasks/myTasks');
            }
            $search_data = array(
                'property_search'  => $property_search,
                'search_string'     => $search_string,
                'task_status' => $task_status,
                'priority' => $priority
            );
            $this->session->set_userdata($search_data);
        }
        $config["base_url"] = base_url() . "tasks/searchMyTasks";
        $config["total_rows"] = $this->task_model->getSearchMyTasksCount();
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        $this->pagination->initialize($config);
        $config = array();
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$data['tasks']=$this->task_model->getSearchMyTasks(RAW_COUNT, $page);
        $data["links"] = $this->pagination->create_links();
        $data['units'] = $this->property_model->getAllPropertyUnits();
        $data['properties'] = $this->property_model->getAllProperties();
		$data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'My Tasks';
		$data['search'] = 'yes';
		$this->load->view('pr/tasks/my_tasks',$data);
    }
}
