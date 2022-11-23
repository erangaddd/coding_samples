<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Workorders extends CI_Controller {

	 function __construct() {
        parent::__construct();
		$this->is_logged_in();
		$this->load->model("pr/common_model");
        $this->load->model("pr/property_model");
        $this->load->model("pr/agreement_model");
        $this->load->model("pr/application_model");
        $this->load->model("pr/task_model");
        $this->load->model("pr/workorder_model");
        $this->load->model("supplier_model");
        $this->load->model("pr/configuration_model",'configuration');
        $this->load->library("pagination");
    }
	
	public function allWorkOrders()
	{
		if ( ! check_access('view_work_orders'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
        }
        $this->session->unset_userdata('search_string');
		$config["base_url"] = base_url() . "workorders/allWorkOrders";
        $config["total_rows"] = $this->workorder_model->getCountAllWorkOrdersNotCompleted();
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        $this->pagination->initialize($config);
        $config = array();
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$data['workorders']=$this->workorder_model->getAllWorkOrdersNotCompleted(RAW_COUNT, $page);
        $data["links"] = $this->pagination->create_links();
               
		$data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'Work Orders';
		$data['search'] = 'no';
		$this->load->view('pr/workorders/index',$data);
	}

    function searchWorkorders(){
        $search_data = array(
            'search_string' => $this->input->post('search_string')
        );
        $this->session->set_userdata($search_data);
        $config["base_url"] = base_url() . "workorders/searchWorkorders";
        $config["total_rows"] = $this->workorder_model->getCountSearchWorkOrders();
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        $this->pagination->initialize($config);
        $config = array();
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
		$data['workorders']=$this->workorder_model->getSearchWorkOrders(RAW_COUNT, $page);
        $data["links"] = $this->pagination->create_links();
               
		$data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'Work Orders';
		$data['search'] = 'yes';
		$this->load->view('pr/workorders/index',$data);
    }

    function addWorkOrder(){
        if ( ! check_access('add_work_orders'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('workorders/allWorkOrders');
        }
        if($_POST){
            $file_name = '';
            if (isset($_FILES['fileUpload']['name']) && $_FILES['fileUpload']['error'] == 0) {
                $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                $config['allowed_types'] = '*'; 
                $config['max_size']      = 1024;
                $this->load->library('upload', $config);
                if ( $this->upload->do_upload('fileUpload')) {
                    $uploadedImage = $this->upload->data();
                    $file_name = $uploadedImage['file_name'];
                    $source_path = $path. $file_name;
                    resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                }else{
                    $this->session->set_flashdata('error', 'Attched file size is larger than 1MB.');
                    redirect('workorders/addWorkOrder');
                    return;
                }
            }
            if($workorder_id = $this->workorder_model->addWorkOrder($file_name)){  
                $table = 'pr_task_workorder';
		        $next_action = checkApproveLevel($table,'PENDING'); //common helper
                if($next_action == 'CHECK'){
                    $this->common_model->add_notification('pr_task_workorder','Check new work order','workorders/allWorkOrders',$workorder_id,'check_work_orders');
                }
                if($next_action == 'CONFIRM'){
                    $this->common_model->add_notification('pr_task_workorder','Confirm new work order','workorders/allWorkOrders',$workorder_id,'confirm_work_orders');
                }
                $this->session->set_flashdata('msg', 'Successfully created the work order.');
            }else{
                $this->session->set_flashdata('error', 'Unable to create the work order.');
            }
            redirect('workorders/addWorkOrder');
        }
        $data['tasks'] = $this->task_model->getAllTasksNotClosed();
        $data['suplist']=$this->supplier_model->getSupliersConfirmed();
        $data['expensetypes'] = $this->configuration->getAllExpenseTypesConfirmed();
        $data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'Work Orders';
        $this->load->view('pr/workorders/add_workorder',$data);
    }

    function editWorkOrder($purpose, $id){
        $data['purpose'] = $this->encryption->decode($purpose);
        $data['workorder_id'] = $workorder_id = $this->encryption->decode($id);
        $data['workorder'] = $workorder = $this->workorder_model->getWorkOrderById($workorder_id);
        if($_POST){
            if ( ! check_access('edit_work_orders'))
            {
                $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
                redirect('workorders/editWorkOrder/'.$purpose.'/'. $id);
            }
            $file_name = $this->input->post('old_file');
            if (isset($_FILES['fileUpload']['name']) && $_FILES['fileUpload']['error'] == 0) {
                $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                $config['allowed_types'] = '*'; 
                $config['max_size']      = 1024;
                $this->load->library('upload', $config);
                if ( $this->upload->do_upload('fileUpload')) {
                    $uploadedImage = $this->upload->data();
                    $file_name = $uploadedImage['file_name'];
                    //delete existing file
                    if($this->input->post('old_file')){
                        unlink($path.$this->input->post('old_file'));
                    }
                    $source_path = $path. $file_name;
                    resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                }else{
                    $this->session->set_flashdata('error', 'Attched file size is larger than 1MB.');
                    redirect('workorders/editWorkOrder/'.$purpose.'/'. $id);
                    return;
                }
            }
            if($this->workorder_model->editWorkOrder($workorder_id, $file_name)){               
                $this->session->set_flashdata('msg', 'Successfully updated the work order.');
            }else{
                $this->session->set_flashdata('error', 'Unable to update the work order.');
            }
            redirect('workorders/allWorkOrders');
        }
        
        $data['tasks'] = $this->task_model->getAllTasksNotClosed();
        $data['suplist']=$this->supplier_model->getSupliersConfirmed();
        $data['expensetypes'] = $this->configuration->getAllExpenseTypesConfirmed();
        $data['menu_name'] = 'Tasks';
        $data['submenu_name'] = 'Work Orders';
        $this->load->view('pr/workorders/edit_workorder',$data);
    }

    function checkWorkOrder($id){
        if ( ! check_access('check_work_orders'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('workorders/allWorkOrders');
        }
        $workorder_id = $this->encryption->decode($id);
        if($this->workorder_model->checkWorkOrder($workorder_id)){
            $this->common_model->delete_notification('pr_task_workorder',$workorder_id);
            $this->common_model->add_notification('pr_task_workorder','Confirm new work order','workorders/allWorkOrders',$workorder_id,'confirm_work_orders');            
            $this->session->set_flashdata('msg', 'Successfully checked the work order.');
        }else{
            $this->session->set_flashdata('error', 'Unable to check the work order.');
        }
        redirect('workorders/allWorkOrders');
    }

    function confirmWorkOrder($id){
        if ( ! check_access('confirm_work_orders'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('workorders/allWorkOrders');
        }
        $workorder_id = $this->encryption->decode($id);
        if($this->workorder_model->confirmWorkOrder($workorder_id)){
            $workorder = $this->workorder_model->getWorkOrderById($workorder_id);
            if($workorder->notify == 'YES'){
                $message = '';
                //get supplier data
                $supplier = $this->supplier_model->getSupplierById($workorder->sup_id);
                $property_data = $this->property_model->getPropertybyId($workorder->property_id);
                $unit_data = $this->property_model->getUnitbyId($workorder->unit_id);
                $org_data = getSystemConfig(); //configuration helper
                $subject = 'New work order has been sent by '.$org_data->company_name;
                $message .= 'Dear '.$supplier->first_name.' '.$supplier->last_name;
                $message .= '<br><h3>The work order number '.$workorder->workorder_number.' has been created;</h3>';
                $message .= '<strong>Details:</strong> '.$workorder->description.'<br>';
                $message .= '<strong>Instructions:</strong> '.$workorder->supplier_note.'<br>';
                $message .= '<strong>Contact Person:</strong> '.$workorder->contact_person.'<br>';
                $message .= '<strong>Contact Phone:</strong> '.$workorder->contact_phone.'<br><br>';
                $message .= '<strong><u>Property Details</u></strong><br>';
                if($property_data){
                    $message .= '<strong>Property Name:</strong> '.$property_data->property_name.'<br>';
                    $message .= '<strong>Address:</strong> '.$property_data->prop_address1.', '.$property_data->prop_adress2.', '.$property_data->city.'<br>';
                    $message .= '<strong>Mobile:</strong> '.$property_data->mobile.'<br>';
                    $message .= '<strong>Telephone:</strong> '.$property_data->tel.'<br>';
                    $message .= '<strong>Fax:</strong> '.$property_data->fax.'<br>';
                    $message .= '<strong>Email:</strong> '.$property_data->email.'<br>';
                }else if($unit_data){
                    $message .= '<strong>Property Name:</strong> '.$unit_data->property_name.' '.$unit_data->unit_name.'<br>';
                    $message .= '<strong>Address:</strong> '.$unit_data->prop_address1.', '.$unit_data->prop_adress2.', '.$unit_data->city.'<br>';
                    $message .= '<strong>Mobile:</strong> '.$unit_data->mobile.'<br>';
                    $message .= '<strong>Telephone:</strong> '.$unit_data->tel.'<br>';
                    $message .= '<strong>Fax:</strong> '.$unit_data->fax.'<br>';
                    $message .= '<strong>Email:</strong> '.$unit_data->email.'<br>';
                };
                $to = $supplier->email;
                $sms_to = $supplier->mobile;
                if($to != ''){
                    if(sendemail($to,$subject,$message)){
                        $sms_message = 'Work order '.$workorder->workorder_number.' has been cretaed';
                        if($sms_to != ''){
                            if(sendSMS($sms_to, $sms_message)){
                                $this->session->set_flashdata('msg', 'Successfully confirmed the work order.');
                            }else{
                                $this->session->set_flashdata('error', 'Unable to send the sms.');
                            }
                            $this->common_model->delete_notification('pr_task_workorder',$workorder_id);
                        }
                    }else{
                        $this->session->set_flashdata('error', 'Unable to send the email.');
                    }
                }else{
                    $this->common_model->delete_notification('pr_task_workorder',$workorder_id);
                    $this->session->set_flashdata('msg', 'Successfully confirmed the work order.');
                }
            }else{
                $this->common_model->delete_notification('pr_task_workorder',$workorder_id);
                $this->session->set_flashdata('msg', 'Successfully confirmed the work order.');
            }
        }else{
            $this->session->set_flashdata('error', 'Unable to confirm the work order.');
        }
        redirect('workorders/allWorkOrders');
    }

    function deleteWorkOrder(){
        if ( ! check_access('delete_work_orders'))
        {
           echo 'You do not have permission to perform this action';
           exit;
        }
        $work_order = $this->workorder_model->getWorkOrderById($this->input->post('id'));
        if($this->workorder_model->deleteWorkOrder()){
            if($work_order->file != ''){
                $path = './uploads/'.getUniqueID().'/'; //common helper function
                unlink($path.$work_order->file); //remove file                
            }
            $this->common_model->delete_notification('pr_task_workorder',$this->input->post('id'));
            echo '1';
        }else{
            echo 'Unable to delete the work order';
        }
        return;
    }

    function deleteConfirmedWorkOrder(){
        if ( ! check_access('delete_confirmed_work_orders'))
        {
           echo 'You do not have permission to perform this action';
           exit;
        }
        $work_order = $this->workorder_model->getWorkOrderById($this->input->post('id'));
        if($this->workorder_model->deleteConfirmedWorkOrder()){
            if($work_order->file != ''){
                $path = './uploads/'.getUniqueID().'/'; //common helper function
                unlink($path.$work_order->file); //remove file                
            }
            echo '1';
        }else{
            echo 'Unable to delete the work order';
        }
        return;
    }

    function closeWorkOrder(){
        if ( ! check_access('close_work_orders'))
        {
           echo 'You do not have permission to perform this action';
           exit;
        }
        if($message = $this->workorder_model->checkBeforeClose()){
           echo 'Please fill '.$message.' fields.';
           exit;
        }
        if($this->workorder_model->closeWorkOrder()){
            echo '1';
        }else{
            echo 'Unable to delete the work order';
        }
        return;
    }
}
