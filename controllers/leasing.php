<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Leasing extends CI_Controller {

	 function __construct() {
        parent::__construct();
		$this->is_logged_in();
		$this->load->model("pr/configuration_model",'configuration');
		$this->load->model("accesshelper_model");
		$this->load->model("pr/common_model");
		$this->load->model("branch_model");
        $this->load->model("pr/property_model");
        $this->load->model("pr/application_model");
        $this->load->model("pr/agreement_model");
		$this->load->library("pagination");
        $this->load->model("customer_model");
    }
	
	function applications()
	{   
        if ( ! check_access('view_applications'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Applications';

        //Pagination Config
        $table = 'pr_rent_application';
        $config["base_url"] = base_url() . "leasing/applications";
        $config["total_rows"] = $this->common_model->getCount($table);
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        
        $this->pagination->initialize($config);
        $config = array();
        
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
        $data["links"] = $this->pagination->create_links();
        //End of Pagination Config
        $order_by = array(
                        'apply_date' => 'DESC',
                        'status' => 'ASC'
                    );
        $data['applications'] = $this->common_model->getData(
                                    $column = '', 
                                    $id = '', 
                                    $table, 
                                    $like = '', 
                                    $not_like = '', 
                                    RAW_COUNT, 
                                    $page, 
                                    $order_by
                                );
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
        $this->load->view('pr/leasing/applications.php',$data);
        return;

	}

    function searchApplications(){
        if ( ! check_access('view_applications'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Applications';
        $data['name'] = $name = $this->input->post('name');
        $data['date'] = $date =$this->input->post('date');
        $data['property'] = $property = $this->input->post('property');
        if($name == '' && $property == '' && $date == ''){
            $this->session->set_flashdata('error', 'You need to fill at least one field.');
            redirect('leasing/applications');
            return;
        }
        $order_by = array(
                        'apply_date' => 'DESC',
                        'status' => 'ASC'
                    );
        $data['applications'] = $this->application_model->getSearchedApplications();
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
        $this->load->view('pr/leasing/search_applications.php',$data);
        return;
    }

    function deleteApplciation(){
        $application_id = $this->input->post('id');
        
        if ( ! check_access('delete_application'))
        {
            echo 'You do not have permission to perform this action.';
            return;
        }
        $application = $this->common_model->getData(
            $column = 'application_id', 
            $application_id, 
            $table_name = 'pr_rent_application', 
            $like = '', 
            $not_like = '', 
            $limit = '',  
            $start = '',  
            $order_by = '',
            $row_data = 'yes'
        );
        $table_name = 'pr_rent_application';
		$id_column = 'application_id';
		if($this->common_model->deleteData($id_column, $application_id, $table_name)){
            $data_array = array(
                'status' => 'UNUSED'
            );
            $id = $application->list_id;
            $id_column = 'list_id';
            $table_name = 'pr_rent_listing';
            $this->common_model->updateData($data_array, $id_column,$id,$table_name);
            //delete files
            $documents = $this->common_model->getData(
                $column = 'application_id', 
                $application_id, 
                $table_name = 'pr_rent_applicationdocs', 
                $like = '', 
                $not_like = '', 
                $limit = '',  
                $start = '',  
                $order_by = '',
                $row_data = ''
            );
            $path = './uploads/'.getUniqueID().'/'; //common helper function
            if($documents){
                foreach($documents as $document){
                    if($document->document){
                        unlink($path.$document->document); //remove old file
                    }
                }
            }
            //remove document data
            $table_name = 'pr_rent_applicationdocs';
		    $id_column = 'application_id';
            $this->common_model->deleteData($id_column, $application_id, $table_name);
            //remove checklist data
            $table_name = 'pr_rent_applicationchecklist';
		    $id_column = 'application_id';
            $this->common_model->deleteData($id_column, $application_id, $table_name);
            echo '1';
		}else{
			echo 'Something went wrong.';
		}
    }

    function addApplicant(){
        if ( ! check_access('add_applicant'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('leasing/applicants');
            return;
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
                    $this->session->set_flashdata('error', 'Attched file size is larger than 1MB. Please edit and add the image again.');
                }
            }
            
            if($this->application_model->addApplicant($file_name)){
                $this->session->set_flashdata('msg', 'Successfully added the applicant.');
                if($this->input->post('property') != ''){
                    redirect('leasing/applications');
                }else{
                    redirect('leasing/applicants');
                }
            }else{
                $this->session->set_flashdata('error', 'Something went wrong.');
                redirect('leasing/applicants'); 
            }
        }
        $data['properties'] = $this->property_model->getAllListedUnits('','');
        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Applicants';
        $this->load->view('pr/leasing/add_applicant.php',$data);
    }

    function checkApplication(){
        if ( ! check_access('check_application'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('leasing/applications');
            return;
        }

        $update_array = array( 
            'status' => 'CONFIRM',
            'check_date' => date('Y-m-d h:i:s'),
            'check_by' => $this->session->userdata('userid')
        );

        $application_id = $this->input->post('application_id');
        $id_column = 'application_id';
        $table_name = 'pr_rent_application';
        
        if($this->common_model->updateData($update_array, $id_column,$application_id,$table_name)){
            $application = $this->common_model->getData(
                $column = 'application_id', 
                $id = $application_id, 
                $table_name = 'pr_rent_application', 
                $like = '', 
                $not_like = '', 
                $limit = '',  
                $start = '',  
                $order_by = '',
                $row_data = 'yes'
            );

            $update_array = array( 
                'status' => 'CONFIRM',
            );
    
            $cus_code = $application->cus_code;
            $id_column = 'cus_code';
            $table_name = 'cm_customerms';
            $this->common_model->updateData($update_array, $id_column,$cus_code,$table_name);

            $this->common_model->add_notification('pr_rent_application','Confirm new lease application '.$application->application_code,'leasing/applications','confirm_application');
            $this->session->set_flashdata('msg', 'Successfully checked the application.');
            redirect('leasing/applications');
        }else{
            $this->session->set_flashdata('error', 'Unable to check the application.');
            redirect('leasing/applications');
        }
    }

    function confirmApplication(){
        if ( ! check_access('confirm_application'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('leasing/applications');
            return;
        }

        $update_array = array( 
            'status' => 'CONFIRMED',
            'confirm_date' => date('Y-m-d h:i:s'),
            'confirm_by' => $this->session->userdata('userid')
        );

        $application_id = $this->input->post('application_id');
        $id_column = 'application_id';
        $table_name = 'pr_rent_application';
        
        if($this->common_model->updateData($update_array, $id_column,$application_id,$table_name)){
            $update_array = array( 
                'status' => 'CONFIRMED'
            );
    
            $cus_code = $this->input->post('cus_code');
            $id_column = 'cus_code';
            $table_name = 'cm_customerms';
            $this->common_model->updateData($update_array, $id_column,$cus_code,$table_name);
            $this->session->set_flashdata('msg', 'Successfully confirmed the application.');
            redirect('leasing/applications');
        }else{
            $this->session->set_flashdata('error', 'Unable to confirm the application.');
            redirect('leasing/applications');
        }
    }

    function editApplication($action = '',$id = ''){
        $action = $this->encryption->decode($action);
        if($_POST){
            if ( ! check_access('edit_application'))
            {
                $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
                redirect('leasing/applications');
                return;
            }
            $application_id = $this->input->post('application_id');
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
                    $this->session->set_flashdata('error', 'Attched file size is larger than 1MB. Please edit and add the image again.');
                }
            }
            
            if($file_name == ''){
                $file_name = $this->input->post('id_copy_front');
            }
            if($this->application_model->editApplicantion($file_name,$application_id)){
                $this->session->set_flashdata('msg', 'Successfully updated the applicantion.');
            }else{
                $this->session->set_flashdata('error', 'Something went wrong.');
                redirect('leasing/applications'); 
            }
            
            if($application_id != ''){
                //Add checklist
                $checklist = $this->common_model->getData(
                    $column = '', 
                    $id = '', 
                    $table_name = 'pr_config_checklist', 
                    $like = '', 
                    $not_like = '', 
                    $limit = '',  
                    $start = '',  
                    $order_by = ''
                );
                if($checklist){
                    $table = 'pr_rent_applicationchecklist';
                    foreach($checklist as $data){
                        if($this->input->post('checklist_'.$data->checklist_id) == 'on'){
                            $data_array = array(
                                'application_id' => $application_id,
                                'checklist_id' => $data->checklist_id,
                                'checked_by' => $this->session->userdata('userid'),
                                'checked_date' => date("Y-m-d")
                            );
                            $this->common_model->insertData($data_array, $table);
                        }
                    }
                }

                //Add Documents
                $like = array('doc_categery' => 'Rent Application');
                $documents = $this->common_model->getData(
                    $column = '', 
                    $id = '', 
                    $table_name = 'pr_config_doctype', 
                    $like, 
                    $not_like = '', 
                    $limit = '',  
                    $start = '',  
                    $order_by = ''
                );
                foreach($documents as $document){
                    $file_name = '';
                    $path = './uploads/'.getUniqueID().'/'; //common helper function
                    $post_name = 'file_'.$document->doctype_id;
                    if (isset($_FILES[$post_name]['name']) && $_FILES[$post_name]['error'] == 0) {
                        $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                        $config['allowed_types'] = '*'; 
                        $config['max_size']      = 1024;
                        $this->load->library('upload', $config);
                        if ( $this->upload->do_upload($post_name)) {
                            $uploadedImage = $this->upload->data();
                            $file_name = $uploadedImage['file_name'];
                            $source_path = $path. $file_name;
                            resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                            //add to database
                            $table = 'pr_rent_applicationdocs';
                            $data_array = array(
                                'application_id' => $application_id,
                                'doctype_id' => $document->doctype_id,
                                'document' => $file_name
                            );
                            $this->common_model->insertData($data_array, $table);
                            //unlink existed file
                            $file = $this->input->post('exisitng_file'.$document->doctype_id);
                            unlink($path.$file); //remove old file
                        }else{
                            $file_name = $this->input->post('exisitng_file'.$document->doctype_id);
                            $table = 'pr_rent_applicationdocs';
                            $data_array = array(
                                'application_id' => $application_id,
                                'doctype_id' => $document->doctype_id,
                                'document' => $file_name
                            );
                            $this->common_model->insertData($data_array, $table);
                            $this->session->set_flashdata('error', 'Attched '.$document->doctype_name.' file size is larger than 1MB. Please edit and add the image again.');
                        }
                    }else{
                        $file_name = $this->input->post('exisitng_file'.$document->doctype_id);
                        $check_file = $this->input->post('check_file'.$document->doctype_id);
                        //$current_name  = $this->input->post('file_'.$document->doctype_id);
                        if( $file_name == $check_file ){
                            $table = 'pr_rent_applicationdocs';
                            $data_array = array(
                                'application_id' => $application_id,
                                'doctype_id' => $document->doctype_id,
                                'document' => $file_name
                            );
                            $this->common_model->insertData($data_array, $table);
                        }else{
                            unlink($path.$file_name); //remove old file 
                        }
                        
                    }
                }
            }
            redirect('leasing/applications/');
        }
        $application_id = $this->encryption->decode($id);
        $data['application'] = $this->common_model->getData(
            $column = 'application_id', 
            $id = $application_id, 
            $table_name = 'pr_rent_application', 
            $like = '', 
            $not_like = '', 
            $limit = '',  
            $start = '',  
            $order_by = '',
            $row_data = 'yes'
        );
        $data['properties'] = $this->property_model->getAllListedUnits('','');
        $data['customers'] = $this->customer_model->get_all_customer_summery();
        $data['checklist_used'] = $this->common_model->getData(
            $column = 'application_id', 
            $application_id, 
            $table_name = 'pr_rent_applicationchecklist', 
            $like = '', 
            $not_like = '', 
            $limit = '',  
            $start = '',  
            $order_by = ''
        );
        $not_like = array('status' => 'DELETE');
        $data['checklist'] = $this->common_model->getData(
            $column = '', 
            $id = '', 
            $table_name = 'pr_config_checklist', 
            $like = '', 
            $not_like, 
            $limit = '',  
            $start = '',  
            $order_by = ''
        );
        $like = array('doc_categery' => 'Rent Application');
        $data['documents'] = $this->common_model->getData(
            $column = '', 
            $id = '', 
            $table_name = 'pr_config_doctype', 
            $like, 
            $not_like = '', 
            $limit = '',  
            $start = '',  
            $order_by = ''
        );
        $data['action'] = $action;
        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Applications';
        $this->load->view('pr/leasing/edit_applicantion.php',$data);

    }

    function addApplciation(){
        if ( ! check_access('add_applicantion'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('leasing/applications');
            return;
        }
        if($_POST){
            $application_id = '';
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
                    $this->session->set_flashdata('error', 'Attched file size is larger than 1MB. Please edit and add the image again.');
                }
            }
            
            if($this->input->post('cus_code') == ''){
                if($application_id = $this->application_model->addApplicant($file_name)){
                    $this->session->set_flashdata('msg', ' Successfully added the applicantion.');
                }else{
                    $this->session->set_flashdata('error', 'Something went wrong.');
                    redirect('leasing/applications'); 
                }
            }else{
                if($file_name == ''){
                    $file_name = $this->input->post('id_copy_front');
                }
                if($application_id = $this->application_model->addApplicantion($file_name)){
                    $this->session->set_flashdata('msg', ' Successfully added the applicantion.');
                }else{
                    $this->session->set_flashdata('error', 'Something went wrong.');
                    redirect('leasing/applications'); 
                }
            }
            if($application_id != ''){
                //Add checklist
                $checklist = $this->common_model->getData(
                    $column = '', 
                    $id = '', 
                    $table_name = 'pr_config_checklist', 
                    $like = '', 
                    $not_like = '', 
                    $limit = '',  
                    $start = '',  
                    $order_by = ''
                );
                if($checklist){
                    $table = 'pr_rent_applicationchecklist';
                    foreach($checklist as $data){
                        if($this->input->post('checklist_'.$data->checklist_id) == 'on'){
                            $data_array = array(
                                'application_id' => $application_id,
                                'checklist_id' => $data->checklist_id,
                                'checked_by' => $this->session->userdata('userid'),
                                'checked_date' => date("Y-m-d")
                            );
                            $this->common_model->insertData($data_array, $table);
                        }
                    }
                }

                //Add Documents
                $like = array('doc_categery' => 'Rent Application');
                $documents = $this->common_model->getData(
                    $column = '', 
                    $id = '', 
                    $table_name = 'pr_config_doctype', 
                    $like, 
                    $not_like = '', 
                    $limit = '',  
                    $start = '',  
                    $order_by = ''
                );
                foreach($documents as $document){
                    $file_name = '';
                    $post_name = 'file_'.$document->doctype_id;
                    if (isset($_FILES[$post_name]['name']) && $_FILES[$post_name]['error'] == 0) {
                        $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                        $config['allowed_types'] = '*'; 
                        $config['max_size']      = 1024;
                        $this->load->library('upload', $config);
                        if ( $this->upload->do_upload($post_name)) {
                            $uploadedImage = $this->upload->data();
                            $file_name = $uploadedImage['file_name'];
                            $source_path = $path. $file_name;
                            resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                            //add to database
                            $table = 'pr_rent_applicationdocs';
                            $data_array = array(
                                'application_id' => $application_id,
                                'doctype_id' => $document->doctype_id,
                                'document' => $file_name
                            );
                            $this->common_model->insertData($data_array, $table);
                        }else{
                            $this->session->set_flashdata('error', 'Attched '.$document->doctype_name.' file size is larger than 1MB. Please edit and add the image again.');
                        }
                    }
                }
            }
            redirect('leasing/applications/');
        }
        $data['properties'] = $this->property_model->getAllListedConfirmedUnits();
        $data['customers'] = $this->customer_model->get_all_customer_summery();
        $not_like = array('status' => 'DELETE');
        $data['checklist'] = $this->common_model->getData(
            $column = '', 
            $id = '', 
            $table_name = 'pr_config_checklist', 
            $like = '', 
            $not_like, 
            $limit = '',  
            $start = '',  
            $order_by = ''
        );
        $like = array('doc_categery' => 'Rent Application');
        $data['documents'] = $this->common_model->getData(
            $column = '', 
            $id = '', 
            $table_name = 'pr_config_doctype', 
            $like, 
            $not_like = '', 
            $limit = '',  
            $start = '',  
            $order_by = ''
        );
        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Applications';
        $this->load->view('pr/leasing/add_applicantion.php',$data);
    }

    function checkIDExists(){
        $id_number = $this->input->post('id_number');	
		$id_type = $this->input->post('id_type');
		if($this->application_model->checkIDExists($id_type,$id_number)){
			echo 'Existing Customer';
		}
    }

    function loadCustomerForm(){
        $cus_code = $this->input->post('cus_code');
        $data['customer'] = $this->customer_model->get_customer_bycode($cus_code);
        $this->load->view('pr/leasing/customer_form.php',$data);
    }

    function applicants(){
        if ( ! check_access('view_applicants'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Applicants';
        if($_POST){
            $data['search_string'] = $search_string = $this->input->post('search_string');
            if($search_string != ''){
                $this->session->set_userdata('search_string',$search_string);
            }
            $search_string = $this->session->userdata('search_string');
            $table_name = 'cm_customerms';
            $like = array(
                'first_name' => $search_string,
                'last_name' => $search_string,
                'cus_number' => $search_string,
                'other_names' => $search_string,
                'dob' => $search_string,
                'id_number' => $search_string,
                'occupation' => $search_string,
                'employer' => $search_string,
                'raddress1' => $search_string,
                'raddress2' => $search_string,
                'raddress3' => $search_string,
                'address1' => $search_string,
                'address2' => $search_string,
                'address3' => $search_string,
                'otheraddress1' => $search_string,
                'otheraddress2' => $search_string,
                'otheraddress3' => $search_string,
                'otheraddress4' => $search_string,
                'landphone' => $search_string,
                'workphone' => $search_string,
                'mobile' => $search_string,
                'fax' => $search_string,
                'email' => $search_string,
                'status' => $search_string,
                'profession' => $search_string
            );
            $not_like = '';

            $config = array();
            $config["base_url"] = base_url() . "leasing/applicants";
            $config["total_rows"] = $this->common_model->getSearchCount($table_name, $like, $not_like);

            $config["per_page"] = RAW_COUNT;
            $config["uri_segment"] = 3;

            $this->pagination->initialize($config);

            $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;

            $data["links"] = $this->pagination->create_links();
            $data['customers'] = $this->common_model->getData(
                                $column = '', //string
                                $id = '', //int
                                $table_name, //string
                                $like, //array
                                $not_like = '', //array
                                RAW_COUNT, //int
                                $page, //int
                                $order_by = '' //array
                            );          
            $this->load->view('pr/leasing/applicants.php',$data);
        }else{
            //Pagination Config
            $table = 'cm_customerms';
            $config["base_url"] = base_url() . "leasing/applicants";
            $config["total_rows"] = $this->common_model->getCount($table);
            $config["per_page"] = RAW_COUNT;
            $config["uri_segment"] = 3;
            
            $this->pagination->initialize($config);
            $config = array();
            
            $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
            $data["links"] = $this->pagination->create_links();
            //End of Pagination Config
            $order_by = array(
                            'cus_number' => 'DESC',
                        );
            $data['customers'] = $this->common_model->getData(
                                        $column = '', 
                                        $id = '', 
                                        $table, 
                                        $like = '', 
                                        $not_like = '', 
                                        RAW_COUNT, 
                                        $page, 
                                        $order_by
                                    );
            $data['search_string'] = '';               
            $this->load->view('pr/leasing/applicants.php',$data);
            return;
        }
    }

    function deleteApplicant(){
        $cus_code = $this->input->post('id');
        if ( ! check_access('delete_applicants'))
        {
            echo 'You do not have permission to perform this action.';
            return;
        }
        $customer = $this->customer_model->get_customer_bycode($cus_code);
        $table_name = 'cm_customerms';
        $id_column = 'cus_code';
        if($customer->status == 'CONFIRMED'){
            $data_array = array(
                'status' => 'DELETED'
            );
            if($this->common_model->updateData($data_array, $id_column,$cus_code,$table_name)){
                echo '2';
            }else{
                echo 'Something went wrong.';
            }
        }else{
            if($this->common_model->deleteData($id_column, $cus_code, $table_name)){
                echo '1';
            }else{
                echo 'Something went wrong.';
            }   
        }
    }

    function editApplicant($action = '',$id = ''){
        if ( ! check_access('edit_applicants'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('leasing/applicants');
            return;
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
                    $this->session->set_flashdata('error', 'Attched file size is larger than 1MB. Please edit and add the image again.');
                }
            }
            
            if($file_name == ''){
                $file_name = $this->input->post('id_copy_front');
            }
            if($this->customer_model->editApplicant($file_name)){
                $this->session->set_flashdata('msg', ' Successfully updated the applicant.');
            }else{
                $this->session->set_flashdata('error', 'Something went wrong.');
            }
            redirect('leasing/applicants'); 
        }
        $cus_code = $this->encryption->decode($id);
        $action = $this->encryption->decode($action);
        $data['action'] = $action;
        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Applicants';
        $data['customer'] = $this->customer_model->get_customer_bycode($cus_code);
        $this->load->view('pr/leasing/edit_applicant.php',$data);
        return;
    }

    function checkApplicant(){
        if ( ! check_access('check_applicant'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('leasing/applicants');
            return;
        }

        $update_array = array( 
            'status' => 'CONFIRM',
        );

        $cus_code = $this->input->post('cus_code');
        $id_column = 'cus_code';
        $table_name = 'cm_customerms';
        
        if($this->common_model->updateData($update_array, $id_column,$cus_code,$table_name)){
            $applicant = $this->common_model->getData(
                $id_column, 
                $cus_code, 
                $table_name, 
                $like = '', 
                $not_like = '', 
                $limit = '',  
                $start = '',  
                $order_by = '',
                $row_data = 'yes'
            );
            $this->common_model->add_notification('cm_customerms','Confirm applicant '.$applicant->cus_number,'leasing/applicants','confirm_applicant');
            $this->session->set_flashdata('msg', 'Successfully checked the applicant.');
            redirect('leasing/applicants');
        }else{
            $this->session->set_flashdata('error', 'Unable to check the applicant.');
            redirect('leasing/applicants');
        }
    }

    function confirmApplicant(){
        if ( ! check_access('confirm_applicant'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('leasing/applicants');
            return;
        }

        $update_array = array( 
            'status' => 'CONFIRMED',
        );

        $cus_code = $this->input->post('cus_code');
        $id_column = 'cus_code';
        $table_name = 'cm_customerms';
        
        if($this->common_model->updateData($update_array, $id_column,$cus_code,$table_name)){
            $this->session->set_flashdata('msg', 'Successfully confirmed the applicant.');
            redirect('leasing/applicants');
        }else{
            $this->session->set_flashdata('error', 'Unable to confirm the applicant.');
            redirect('leasing/applicants');
        }
    }

    function undeleteApplicant(){
        $cus_code = $this->input->post('id');
    
        if ( ! check_access('undelete_applicants'))
        {
            echo 'You do not have permission to perform this action.';
            return;
        }
        $table_name = 'cm_customerms';
        $id_column = 'cus_code';
        $data_array = array(
            'status' => 'CONFIRMED'
        );
        if($this->common_model->updateData($data_array, $id_column,$cus_code,$table_name)){
            echo '1';
        }else{
            echo 'Something went wrong.';
        }
    }

    function agreements(){
        if ( ! check_access('view_lease_agreements'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Agreements';

        //Pagination Config
        $table = 'pr_rent_leaseagreement';
        $config["base_url"] = base_url() . "leasing/agreements";
        $config["total_rows"] = $this->common_model->getCount($table);
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        
        $this->pagination->initialize($config);
        $config = array();
        
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
        $data["links"] = $this->pagination->create_links();
        //End of Pagination Config
        $order_by = array(
                        'create_date' => 'DESC',
                        'agreement_id' => 'DESC',
                        'status' => 'ASC'
                    );
        $data['agreements'] = $this->common_model->getData(
                                    $column = '', 
                                    $id = '', 
                                    $table, 
                                    $like = '', 
                                    $not_like = '', 
                                    RAW_COUNT, 
                                    $page, 
                                    $order_by
                                );
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
        $this->load->view('pr/leasing/agreements.php',$data);
        return;
    }

    function addAgreement(){
        if ( ! check_access('add_lease_agreements'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('leasing/agreements');
            return;
        }
        if($_POST){
            $security_deposit = $this->input->post('security_deposit');
			$security_amount = $this->input->post('security_amount');
            $application_id = $this->input->post('application_id');
            //Add lease data
            if($agreement_id = $this->agreement_model->addAgreement($application_id)){
                //Add files
                $like = array('doc_categery' => 'Lease Agreement');
                $documents = $this->common_model->getData(
                    $column = '', 
                    $id = '', 
                    $table_name = 'pr_config_doctype', 
                    $like, 
                    $not_like = '', 
                    $limit = '',  
                    $start = '',  
                    $order_by = ''
                );
                foreach($documents as $document){
                    $file_name = '';
                    $post_name = 'file_'.$document->doctype_id;
                    if (isset($_FILES[$post_name]['name']) && $_FILES[$post_name]['error'] == 0) {
                        $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                        $config['allowed_types'] = '*'; 
                        $config['max_size']      = 1024;
                        $this->load->library('upload', $config);
                        if ( $this->upload->do_upload($post_name)) {
                            $uploadedImage = $this->upload->data();
                            $file_name = $uploadedImage['file_name'];
                            $source_path = $path. $file_name;
                            resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                            //add to database
                            $table = 'pr_rent_leaseagreementdocs';
                            $data_array = array(
                                'agreement_id' => $agreement_id,
                                'doctype_id' => $document->doctype_id,
                                'document' => $file_name
                            );
                            $this->common_model->insertData($data_array, $table);
                        }else{
                            $this->session->set_flashdata('error', 'Attched '.$document->doctype_name.' file size is larger than 1MB. Please edit and add the image again.');
                        }
                    }
                }
                //Add tenants
                $tenant_count = $this->input->post('tenant_count');
                for($x = 1; $x <= $tenant_count; $x++){
                    $file_name = '';
                    $post_name = 'id_copy'.$x;
                    if (isset($_FILES[$post_name]['name']) && $_FILES[$post_name]['error'] == 0) {
                        $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                        $config['allowed_types'] = '*'; 
                        $config['max_size']      = 1024;
                        $this->load->library('upload', $config);
                        if ( $this->upload->do_upload($post_name)) {
                            $uploadedImage = $this->upload->data();
                            $file_name = $uploadedImage['file_name'];
                            $source_path = $path. $file_name;
                            resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                        }else{
                            $this->session->set_flashdata('error', 'Attched '.$this->input->post('first_name'.$x).'`s ID file size is larger than 1MB. Please edit and add the image again.');
                        }
                    }
                    $tenant_data = array(
                        'agreement_id' => $agreement_id,
                        'first_name' => $this->input->post('first_name'.$x),
                        'last_name' => $this->input->post('last_name'.$x),
                        'id_number' => $this->input->post('id_number'.$x),
                        'id_copy' => $file_name,
                        'dob' => $this->input->post('dob'.$x),
                        'mobile' => $this->input->post('mobile'.$x),
                        'email' => $this->input->post('email'.$x),
                        'create_date' => date('Y-m-d'),
			            'create_by' => $this->session->userdata('userid')
                    );
                    $table = 'pr_rent_leasetenants';
                    $this->common_model->insertData($tenant_data, $table);
                }

                //change application status
                $table_name = 'pr_rent_application';
                $id_column = 'application_id';
                $data_array = array(
                    'status' => 'USED'
                );
                $this->common_model->updateData($data_array, $id_column,$application_id,$table_name);

                $this->session->set_flashdata('msg', 'Successfully added the agreement.');
                redirect('leasing/agreements'); 
            }else{
                $this->session->set_flashdata('error', 'Something went wrong.');
                redirect('leasing/agreements'); 
            }
        }
        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Agreements';
        $table = 'pr_rent_application';
        $like = array('status' => 'CONFIRMED');
        $data['applications'] = $this->common_model->getData(
                                    $column = '', 
                                    $id = '', 
                                    $table, 
                                    $like, 
                                    $not_like = '', 
                                    $row_count = '', 
                                    $page= '', 
                                    $order_by = ''
                                );
        $table = 'pr_config_renteepaytype';
        $not_like = array('paytype_id' => '2');
        $like = array('status' => 'CONFIRMED');
        $data['pay_types'] = $this->common_model->getData(
                                    $column = '', 
                                    $id = '', 
                                    $table, 
                                    $like, 
                                    $not_like, 
                                    $row_count = '', 
                                    $page= '', 
                                    $order_by = ''
                                );
        $like = array('doc_categery' => 'Lease Agreement');
        $data['documents'] = $this->common_model->getData(
                                    $column = '', 
                                    $id = '', 
                                    $table_name = 'pr_config_doctype', 
                                    $like, 
                                    $not_like = '', 
                                    $limit = '',  
                                    $start = '',  
                                    $order_by = ''
                                );
        $this->load->view('pr/leasing/add_agreement.php',$data);
    }

    function splitRent(){
        $data['count'] = (float)$this->input->post('count') + 1;
        $table = 'pr_config_renteepaytype';
        $not_like = array('paytype_id' => '2');
        $like = array('status' => 'CONFIRMED');
        $data['pay_types'] = $this->common_model->getData(
                                $column = '', 
                                $id = '', 
                                $table, 
                                $like, 
                                $not_like, 
                                $row_count = '', 
                                $page= '', 
                                $order_by = ''
                            );
        $this->load->view('pr/leasing/split_rent.php',$data);
    }

    function addCharge(){
        $data['count'] = (float)$this->input->post('count') + 1;
        $table = 'pr_config_renteepaytype';
        $not_like = array('paytype_id' => '2');
        $like = array('status' => 'CONFIRMED');
        $data['pay_types'] = $this->common_model->getData(
                                $column = '', 
                                $id = '', 
                                $table, 
                                $like, 
                                $not_like, 
                                $row_count = '', 
                                $page= '', 
                                $order_by = ''
                            );
        $this->load->view('pr/leasing/add_charge.php',$data);
    }

    function editAgreement($action = '',$id = ''){
        if($_POST){
            if ( ! check_access('edit_lease_agreements'))
            {
                $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
                redirect('leasing/agreements');
                return;
            }
            $agreement_id = $this->input->post('agreement_id');
            $application_id = $this->input->post('application_id');
            //Add lease data
            if($this->agreement_model->editAgreement($agreement_id,$application_id)){
                //Add files
                $like = array('doc_categery' => 'Lease Agreement');
                $documents = $this->common_model->getData(
                    $column = '', 
                    $id = '', 
                    $table_name = 'pr_config_doctype', 
                    $like, 
                    $not_like = '', 
                    $limit = '',  
                    $start = '',  
                    $order_by = ''
                );
                foreach($documents as $document){
                    $file_name = '';
                    $path = './uploads/'.getUniqueID().'/'; //common helper function
                    $post_name = 'file_'.$document->doctype_id;
                    if (isset($_FILES[$post_name]['name']) && $_FILES[$post_name]['error'] == 0) {
                        $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                        $config['allowed_types'] = '*'; 
                        $config['max_size']      = 1024;
                        $this->load->library('upload', $config);
                        if ( $this->upload->do_upload($post_name)) {
                            $uploadedImage = $this->upload->data();
                            $file_name = $uploadedImage['file_name'];
                            $source_path = $path. $file_name;
                            resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                            //add to database
                            $table = 'pr_rent_leaseagreementdocs';
                            $data_array = array(
                                'agreement_id' => $agreement_id,
                                'doctype_id' => $document->doctype_id,
                                'document' => $file_name
                            );
                            $this->common_model->insertData($data_array, $table);
                            //unlink existed file
                            $file = $this->input->post('exisitng_file'.$document->doctype_id);
                            unlink($path.$file); //remove old file
                        }else{
                            if($this->input->post('exisitng_file'.$document->doctype_id) != ''){
                                $file_name = $this->input->post('exisitng_file'.$document->doctype_id);
                                $table = 'pr_rent_leaseagreementdocs';
                                $data_array = array(
                                    'agreement_id' => $agreement_id,
                                    'doctype_id' => $document->doctype_id,
                                    'document' => $file_name
                                );
                                $this->common_model->insertData($data_array, $table);
                            }
                            $this->session->set_flashdata('error', 'Attched '.$document->doctype_name.' file size is larger than 1MB. Please edit and add the image again.');
                        }
                    }else{
                        $file_name = $this->input->post('exisitng_file'.$document->doctype_id);
                        $check_file = $this->input->post('check_file'.$document->doctype_id);
                        //$current_name  = $this->input->post('file_'.$document->doctype_id);
                        if( $file_name == $check_file ){
                            if($this->input->post('exisitng_file'.$document->doctype_id) != ''){
                                $table = 'pr_rent_leaseagreementdocs';
                                $data_array = array(
                                    'agreement_id' => $agreement_id,
                                    'doctype_id' => $document->doctype_id,
                                    'document' => $file_name
                                );
                                $this->common_model->insertData($data_array, $table);
                            }
                        }else{
                            unlink($path.$file_name); //remove old file 
                        }
                        
                    }
                }
                //Add tenants
                $tenant_count = $this->input->post('tenant_count');
                for($x = 1; $x <= $tenant_count; $x++){
                    if($this->input->post('tenant_id'.$x) != ''){
                        $file_name = '';
                        $post_name = 'id_copy'.$x;
                        if (isset($_FILES[$post_name]['name']) && $_FILES[$post_name]['error'] == 0) {
                            $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                            $config['allowed_types'] = '*'; 
                            $config['max_size']      = 1024;
                            $this->load->library('upload', $config);
                            if ( $this->upload->do_upload($post_name)) {
                                $uploadedImage = $this->upload->data();
                                $file_name = $uploadedImage['file_name'];
                                $source_path = $path. $file_name;
                                resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                                $file = $this->input->post('id_copy_existing'.$x); 
                                unlink($path.$file); //remove old file
                            }else{
                                $file_name = $this->input->post('id_copy_existing'.$x); 
                                $this->session->set_flashdata('error', 'Attched '.$this->input->post('first_name'.$x).'`s ID file size is larger than 1MB. Please edit and add the image again.');
                            }
                        }else{
                            $file_name = $this->input->post('id_copy_existing'.$x); 
                        }
                        $tenant_data = array(
                            'first_name' => $this->input->post('first_name'.$x),
                            'last_name' => $this->input->post('last_name'.$x),
                            'id_number' => $this->input->post('id_number'.$x),
                            'id_copy' => $file_name,
                            'dob' => $this->input->post('dob'.$x),
                            'mobile' => $this->input->post('mobile'.$x),
                            'email' => $this->input->post('email'.$x),
                            'create_date' => date('Y-m-d'),
                            'create_by' => $this->session->userdata('userid')
                        );
                        $table = 'pr_rent_leasetenants';
                        $this->common_model->updateData($tenant_data, 'tenant_id',$this->input->post('tenant_id'.$x),$table);
                    }else{
                        $file_name = '';
                        $post_name = 'id_copy'.$x;
                        if (isset($_FILES[$post_name]['name']) && $_FILES[$post_name]['error'] == 0) {
                            $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                            $config['allowed_types'] = '*'; 
                            $config['max_size']      = 1024;
                            $this->load->library('upload', $config);
                            if ( $this->upload->do_upload($post_name)) {
                                $uploadedImage = $this->upload->data();
                                $file_name = $uploadedImage['file_name'];
                                $source_path = $path. $file_name;
                                resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                            }else{
                                $this->session->set_flashdata('error', 'Attched '.$this->input->post('first_name'.$x).'`s ID file size is larger than 1MB. Please edit and add the image again.');
                            }
                        }
                        $tenant_data = array(
                            'agreement_id' => $agreement_id,
                            'first_name' => $this->input->post('first_name'.$x),
                            'last_name' => $this->input->post('last_name'.$x),
                            'id_number' => $this->input->post('id_number'.$x),
                            'id_copy' => $file_name,
                            'dob' => $this->input->post('dob'.$x),
                            'mobile' => $this->input->post('mobile'.$x),
                            'email' => $this->input->post('email'.$x),
                            'create_date' => date('Y-m-d'),
                            'create_by' => $this->session->userdata('userid')
                        );
                        $table = 'pr_rent_leasetenants';
                        $this->common_model->insertData($tenant_data, $table);
                    }
                }

                //change application status
                $table_name = 'pr_rent_application';
                $id_column = 'application_id';
                $data_array = array(
                    'status' => 'USED'
                );
                $this->common_model->updateData($data_array, $id_column,$application_id,$table_name);

                $this->session->set_flashdata('msg', 'Successfully updated the agreement '.$this->input->post('agreement_code').'.');
                redirect('leasing/agreements'); 
            }else{
                $this->session->set_flashdata('error', 'Something went wrong.');
                redirect('leasing/agreements'); 
            }
        }
        $action = $this->encryption->decode($action);
        $agreement_id = $this->encryption->decode($id);
        if($agreement_id == ''){
            $this->session->set_flashdata('error', 'Something went wrong.');
            redirect('leasing/agreements'); 
        }
        $data['agreement'] = $agreement = $this->common_model->getData(
            $column = 'agreement_id', 
            $id = $agreement_id, 
            $table_name = 'pr_rent_leaseagreement', 
            $like = '', 
            $not_like = '', 
            $limit = '',  
            $start = '',  
            $order_by = '',
            $row_data = 'yes'
        );
        $table = 'pr_rent_application';
        $like = array('status' => 'CONFIRMED', 'application_id' => $agreement->application_id);
        $data['applications'] = $this->common_model->getData(
                $column = '', 
                $id = '', 
                $table, 
                $like, 
                $not_like = '', 
                $row_count = '', 
                $page= '', 
                $order_by = ''
            );
        $table = 'pr_rent_charges';
        $like = array('agreement_id' => $agreement_id);
        $not_like = array('include_rent' => 'no');
        $order_by = array('charge_id' => 'ASC');
        $data['rentals'] = $this->common_model->getData(
                $column = '', 
                $id='', 
                $table, 
                $like, 
                $not_like, 
                $row_count = '', 
                $page= '', 
                $order_by
            );
        $like = array('agreement_id' => $agreement_id);

        $data['security'] = $this->agreement_model->getSecurityDeposit($agreement_id);

        $not_like = array('paytype_id' => '2');
        $not_in = array('yes');
        $data['charges'] = $this->common_model->getData(
                $column = '', 
                $id = '', 
                $table, 
                $like, 
                $not_like, 
                $row_count = '', 
                $page= '', 
                $order_by,
                $row_data = '', 
                $not_in_row = 'include_rent', 
                $not_in
            );
        $data['tenants'] = $this->common_model->getData(
            $column = 'agreement_id', 
            $id = $agreement_id, 
            $table_name = 'pr_rent_leasetenants', 
            $like = '', 
            $not_like = '', 
            $limit = '',  
            $start = '',  
            $order_by = '',
            $row_data = ''
        );
        $like = array('doc_categery' => 'Lease Agreement');
        $data['documents'] = $this->common_model->getData(
            $column = '', 
            $id = '', 
            $table_name = 'pr_config_doctype', 
            $like, 
            $not_like = '', 
            $limit = '',  
            $start = '',  
            $order_by = ''
        );
        $table = 'pr_config_renteepaytype';
        $not_like = array('paytype_id' => '2');
        $like = array('status' => 'CONFIRMED');
        $data['pay_types'] = $this->common_model->getData(
                                    $column = '', 
                                    $id = '', 
                                    $table, 
                                    $like, 
                                    $not_like, 
                                    $row_count = '', 
                                    $page= '', 
                                    $order_by = ''
                                );
        $data['action'] = $action;
        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Agreements';
        $this->load->view('pr/leasing/edit_agreement.php',$data);
    }

    function checkAgreement(){
        if ( ! check_access('check_agreement'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('leasing/agreements');
            return;
        }

        $update_array = array( 
            'status' => 'CONFIRM',
            'check_date' => date('Y-m-d h:i:s'),
            'check_by' => $this->session->userdata('userid')
        );

        $agreement_id = $this->input->post('agreement_id');
        $id_column = 'agreement_id';
        $table_name = 'pr_rent_leaseagreement';
        
        if($this->common_model->updateData($update_array, $id_column,$agreement_id,$table_name)){
            $agreement = $this->common_model->getData(
                $id_column, 
                $agreement_id, 
                $table_name, 
                $like = '', 
                $not_like = '', 
                $limit = '',  
                $start = '',  
                $order_by = '',
                $row_data = 'yes'
            );
            $this->common_model->add_notification('pr_rent_leaseagreement','Confirm new lease agreement '.$agreement->agreement_code,'leasing/agreements','confirm_agreement');
            $this->session->set_flashdata('msg', 'Successfully checked the agreement.');
            redirect('leasing/agreements');
        }else{
            $this->session->set_flashdata('error', 'Unable to check the agreement.');
            redirect('leasing/agreements');
        }
    }

    function confirmAgreement(){
        if ( ! check_access('confirm_agreement'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('leasing/agreements');
            return;
        }

        $update_array = array( 
            'status' => 'CONFIRMED',
            'confirm_date' => date('Y-m-d h:i:s'),
            'confirm_by' => $this->session->userdata('userid')
        );

        $agreement_id = $this->input->post('agreement_id');
        $id_column = 'agreement_id';
        $table_name = 'pr_rent_leaseagreement';
        
        if($this->common_model->updateData($update_array, $id_column,$agreement_id,$table_name)){
           
            $this->session->set_flashdata('msg', 'Successfully confirmed the agreement.');
            redirect('leasing/agreements');
        }else{
            $this->session->set_flashdata('error', 'Unable to confirm the agreement.');
            redirect('leasing/agreements');
        }
    }

    function searchAgreements(){
        if ( ! check_access('view_lease_agreements'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Agreements';
        $data['name'] = $name = $this->input->post('name');
        $data['date'] = $date =$this->input->post('date');
        $data['property'] = $agreement_code = $this->input->post('property');
        if($name == '' && $agreement_code == '' && $date == ''){
            $this->session->set_flashdata('error', 'You need to fill at least one field.');
            redirect('leasing/agreements');
            return;
        }
        $data['agreements'] = $this->agreement_model->getSearchedAgreements();
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
        $this->load->view('pr/leasing/search_agreements.php',$data);
        return;
    }

    function addTenant(){
        $data['count'] = (float)$this->input->post('count') + 1;
        $this->load->view('pr/leasing/add_tenant.php',$data);
    }

    function removeTenant(){
        $tenant_id = $this->input->post('tenant_id');
        $table = 'pr_rent_leasetenants';
        $id_column = 'tenant_id';
        $tenant = $this->common_model->getData(
                        $column = 'tenant_id', 
                        $tenant_id, 
                        $table, 
                        $like = '', 
                        $not_like = '', 
                        $row_count = '', 
                        $page= '', 
                        $order_by = '',
                        $row_data = 'yes'
                    );
        if($this->common_model->deleteData($id_column, $tenant_id, $table)){
            $path = './uploads/'.getUniqueID().'/'; //common helper function
            unlink($path.$tenant->id_copy); //remove old file
            echo $tenant_id;
        }
    }

    function removeLeaseDocument(){
        $document_id = $this->input->post('document_id');
        $table = 'pr_rent_leaseagreementdocs';
        $id_column = 'id';
        $document = $this->common_model->getData(
                        $column = 'id', 
                        $document_id, 
                        $table, 
                        $like = '', 
                        $not_like = '', 
                        $row_count = '', 
                        $page= '', 
                        $order_by = '',
                        $row_data = 'yes'
                    );
        if($this->common_model->deleteData($id_column, $document_id, $table)){
            $path = './uploads/'.getUniqueID().'/'; //common helper function
            unlink($path.$document->document); //remove old file
            echo $document_id;
        }
    }

    function deleteAgreement(){
        $agreement_id = $this->input->post('id');
        
        if ( ! check_access('delete_lease_agreements'))
        {
            echo 'You do not have permission to perform this action.';
            return;
        }
        $agreement = $this->common_model->getData(
            $column = 'agreement_id', 
            $agreement_id, 
            $table_name = 'pr_rent_leaseagreement', 
            $like = '', 
            $not_like = '', 
            $limit = '',  
            $start = '',  
            $order_by = '',
            $row_data = 'yes'
        );
        $table_name = 'pr_rent_leaseagreement';
		$id_column = 'agreement_id';
		if($this->common_model->deleteData($id_column, $agreement_id, $table_name)){
            $data_array = array(
                'status' => 'CONFIRMED'
            );
            $id = $agreement->application_id;
            $id_column = 'application_id';
            $table_name = 'pr_rent_application';
            $this->common_model->updateData($data_array, $id_column,$id,$table_name);
            //delete files
            $documents = $this->common_model->getData(
                $column = 'agreement_id', 
                $agreement_id, 
                $table_name = 'pr_rent_leaseagreementdocs', 
                $like = '', 
                $not_like = '', 
                $limit = '',  
                $start = '',  
                $order_by = '',
                $row_data = ''
            );
            $path = './uploads/'.getUniqueID().'/'; //common helper function
            if($documents){
                foreach($documents as $document){
                    if($document->document){
                        unlink($path.$document->document); //remove old file
                    }
                }
            }
            //remove document data
            $table_name = 'pr_rent_leaseagreementdocs';
		    $id_column = 'agreement_id';
            $this->common_model->deleteData($id_column, $agreement_id, $table_name);
            //remove checklist data
            $table_name = 'pr_rent_charges';
		    $id_column = 'agreement_id';
            $this->common_model->deleteData($id_column, $agreement_id, $table_name);
            //Delete tenants images
            $tenants = $this->common_model->getData(
                $column = 'agreement_id', 
                $agreement_id, 
                $table_name = 'pr_rent_leasetenants', 
                $like = '', 
                $not_like = '', 
                $limit = '',  
                $start = '',  
                $order_by = '',
                $row_data = ''
            );
            $path = './uploads/'.getUniqueID().'/'; //common helper function
            if($tenants){
                foreach($tenants as $tenant){
                    if($tenant->id_copy){
                        unlink($path.$tenant->id_copy); //remove id copy
                    }
                }
            }
            //remove tenants data
            $table_name = 'pr_rent_leasetenants';
		    $id_column = 'agreement_id';
            $this->common_model->deleteData($id_column, $agreement_id, $table_name);
            echo '1';
		}else{
			echo 'Something went wrong.';
		}
    }

    function terminateAgreement(){
        $agreement_id = $this->encryption->decode($this->uri->segment('3'));
        if($this->agreement_model->terminateAgreement($agreement_id)){
            $this->session->set_flashdata('msg', 'Successfully terminated the agreement.');
            redirect('leasing/agreements');
        }else{
            $this->session->set_flashdata('error', 'Unable to terminate the agreement.');
            redirect('leasing/agreements');
        }
    }

    function endAgreement(){
        $agreement_id = $this->encryption->decode($this->input->post('id'));
        $data['agreement'] = $this->common_model->getData(
            $column = 'agreement_id', 
            $id = $agreement_id, 
            $table_name = 'pr_rent_leaseagreement', 
            $like = '', 
            $not_like = '', 
            $limit = '',  
            $start = '',  
            $order_by = '',
            $row_data = 'yes'
        );
        $this->load->view('pr/leasing/end_agreement.php',$data);
    }

    function end(){
        if($_POST){
            if ( ! check_access('end_lease_agreements'))
            {
                echo 'You do not have permission to perform this action.';
                return;
            }
            $agreement_id = $this->input->post('id');
            if($this->agreement_model->endAgreement($agreement_id)){
                $this->session->set_flashdata('msg', 'Successfully ended the agreement.');
                redirect('leasing/agreements');
            }else{
                $this->session->set_flashdata('error', 'Unable to end the agreement.');
                redirect('leasing/agreements');
            }
        }else{
            $this->session->set_flashdata('error', 'Something went wrong.');
            redirect('leasing/agreements');
        }
    }

    function renewAgreement($id = ''){
        if ( ! check_access('renew_agreements'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('leasing/agreements');
        }
        if($_POST){
            $application_id = $this->input->post('application_id');
            //Add lease data
            if($agreement_id = $this->agreement_model->addAgreement($application_id)){
                //Add files
                $like = array('doc_categery' => 'Lease Agreement');
                $documents = $this->common_model->getData(
                    $column = '', 
                    $id = '', 
                    $table_name = 'pr_config_doctype', 
                    $like, 
                    $not_like = '', 
                    $limit = '',  
                    $start = '',  
                    $order_by = ''
                );
                foreach($documents as $document){
                    $file_name = '';
                    $post_name = 'check_file'.$document->doctype_id;
                    if (isset($_FILES[$post_name]['name']) && $_FILES[$post_name]['error'] == 0) {
                        $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                        $config['allowed_types'] = '*'; 
                        $config['max_size']      = 1024;
                        $this->load->library('upload', $config);
                        if ( $this->upload->do_upload($post_name)) {
                            $uploadedImage = $this->upload->data();
                            $file_name = $uploadedImage['file_name'];
                            $source_path = $path. $file_name;
                            resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                        }else{
                            $this->session->set_flashdata('error', 'Attched '.$document->doctype_name.' file size is larger than 1MB. Please edit and add the image again.');
                        }
                    }else{
                        $file_name = $this->input->post('check_file'.$document->doctype_id);
                    }
                    if($file_name){
                        //add to database
                        $table = 'pr_rent_leaseagreementdocs';
                        $data_array = array(
                            'agreement_id' => $agreement_id,
                            'doctype_id' => $document->doctype_id,
                            'document' => $file_name
                        );
                        $this->common_model->insertData($data_array, $table);
                    }
                }
                //Add tenants
                $tenant_count = $this->input->post('tenant_count');
                for($x = 1; $x <= $tenant_count; $x++){
                    $file_name = '';
                    $post_name = 'id_copy'.$x;
                    if (isset($_FILES[$post_name]['name']) && $_FILES[$post_name]['error'] == 0) {
                        $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                        $config['allowed_types'] = '*'; 
                        $config['max_size']      = 1024;
                        $this->load->library('upload', $config);
                        if ( $this->upload->do_upload($post_name)) {
                            $uploadedImage = $this->upload->data();
                            $file_name = $uploadedImage['file_name'];
                            $source_path = $path. $file_name;
                            resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                        }else{
                            $this->session->set_flashdata('error', 'Attched '.$this->input->post('first_name'.$x).'`s ID file size is larger than 1MB. Please edit and add the image again.');
                        }
                    }else{
                        $file_name = $this->input->post('id_copy_existing'.$x);
                    }
                    $tenant_data = array(
                        'agreement_id' => $agreement_id,
                        'first_name' => $this->input->post('first_name'.$x),
                        'last_name' => $this->input->post('last_name'.$x),
                        'id_number' => $this->input->post('id_number'.$x),
                        'id_copy' => $file_name,
                        'dob' => $this->input->post('dob'.$x),
                        'mobile' => $this->input->post('mobile'.$x),
                        'email' => $this->input->post('email'.$x),
                        'create_date' => date('Y-m-d'),
			            'create_by' => $this->session->userdata('userid')
                    );
                    $table = 'pr_rent_leasetenants';
                    $this->common_model->insertData($tenant_data, $table);
                }

                //change application status
                $table_name = 'pr_rent_application';
                $id_column = 'application_id';
                $data_array = array(
                    'status' => 'USED'
                );
                $this->common_model->updateData($data_array, $id_column,$application_id,$table_name);

                $this->session->set_flashdata('msg', 'Successfully renewed the agreement.');
                redirect('leasing/agreements'); 
            }else{
                $this->session->set_flashdata('error', 'Something went wrong.');
                redirect('leasing/agreements'); 
            }
        }
        $agreement_id = $this->encryption->decode($id);
        $data['agreement'] = $agreement = $this->common_model->getData(
            $column = 'agreement_id', 
            $id = $agreement_id, 
            $table_name = 'pr_rent_leaseagreement', 
            $like = '', 
            $not_like = '', 
            $limit = '',  
            $start = '',  
            $order_by = '',
            $row_data = 'yes'
        );
        $table = 'pr_rent_application';
        $like = array('status' => 'CONFIRMED', 'application_id' => $agreement->application_id);
        $data['applications'] = $this->common_model->getData(
                $column = '', 
                $id = '', 
                $table, 
                $like, 
                $not_like = '', 
                $row_count = '', 
                $page= '', 
                $order_by = ''
            );
        $table = 'pr_rent_charges';
        $like = array('agreement_id' => $agreement_id);
        $not_like = array('include_rent' => 'no');
        $order_by = array('charge_id' => 'ASC');
        $data['rentals'] = $this->common_model->getData(
                $column = '', 
                $id='', 
                $table, 
                $like, 
                $not_like, 
                $row_count = '', 
                $page= '', 
                $order_by
            );
        $like = array('agreement_id' => $agreement_id);

        $data['security'] = $this->agreement_model->getSecurityDeposit($agreement_id);

        $not_like = array('paytype_id' => '2');
        $not_in = array('yes');
        $data['charges'] = $this->common_model->getData(
                $column = '', 
                $id = '', 
                $table, 
                $like, 
                $not_like, 
                $row_count = '', 
                $page= '', 
                $order_by,
                $row_data = '', 
                $not_in_row = 'include_rent', 
                $not_in
            );
        $data['tenants'] = $this->common_model->getData(
            $column = 'agreement_id', 
            $id = $agreement_id, 
            $table_name = 'pr_rent_leasetenants', 
            $like = '', 
            $not_like = '', 
            $limit = '',  
            $start = '',  
            $order_by = '',
            $row_data = ''
        );
        $like = array('doc_categery' => 'Lease Agreement');
        $data['documents'] = $this->common_model->getData(
            $column = '', 
            $id = '', 
            $table_name = 'pr_config_doctype', 
            $like, 
            $not_like = '', 
            $limit = '',  
            $start = '',  
            $order_by = ''
        );
        $table = 'pr_config_renteepaytype';
        $not_like = array('paytype_id' => '2');
        $data['pay_types'] = $this->common_model->getData(
                                    $column = '', 
                                    $id = '', 
                                    $table, 
                                    $like = '', 
                                    $not_like, 
                                    $row_count = '', 
                                    $page= '', 
                                    $order_by = ''
                                );
        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Agreements';
        $this->load->view('pr/leasing/renew_agreement.php',$data);
    }
}
