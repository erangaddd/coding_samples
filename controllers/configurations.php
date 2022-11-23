<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Configurations extends CI_Controller {

	 function __construct() {
        parent::__construct();
		$this->is_logged_in();
		$this->load->model("pr/configuration_model",'configuration');
		$this->load->model("accesshelper_model");
		$this->load->model("pr/common_model");
		$this->load->model("branch_model");
		$this->load->model("ledger_model");
		$this->load->library("pagination");
    }
	
	function index()
	{
		if(!$_POST){
			if ( ! check_access('view_system_settings'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('home');
				return;
			}
			$data['menu_name'] = 'Configurations';
			$data['submenu_name'] = 'Organisation Details';
			$data['accmethods'] = $this->common_model->getData(
										$column = '',
										$id = '', 
										$table_name = 'pr_config_accmethod', 
										$like = '', 
										$not_like = '', 
										$limit = '',
										$start = '',
										$order_by = '', 
										$row_data = 'no');
			$data['cm_settings'] = $this->common_model->getData(
										$column = '',
										$id = '1', 
										$table_name = 'cm_settings', 
										$like = '', 
										$not_like = '', 
										$limit = '',
										$start = '',
										$order_by = '', 
										$row_data = 'yes');
			$data['settings'] = $this->configuration->get_config_systemsettings();
			$this->load->view('pr/configurations/settings', $data);
		}else{
			if ( ! check_access('update_system_settings'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('configurations');
				return;
			}

			$data_array = array();
			foreach ($_POST as $key => $value) 
			   $data_array[$key] = $value;

			if (isset($_FILES['fileUpload']['name']) && $_FILES['fileUpload']['error'] == 0) {
				$config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
				$config['allowed_types'] = 'jpg|png'; 
				$config['max_size']      = 1024;
				$this->load->library('upload', $config);
				if ( $this->upload->do_upload('fileUpload')) {
					$settings = $this->configuration->get_config_systemsettings();
					unlink($path.$settings->logo); //remove old file
					$uploadedImage = $this->upload->data();
					$file_name = $uploadedImage['file_name'];
					$source_path = $path. $file_name;
					resizeImage($uploadedImage['file_name'],$source_path); //common helper function
					$data_array['logo'] = $file_name;
				}else{
					$this->session->set_flashdata('error', 'Logo image size is larger than 1MB.');
					redirect('configurations');
					return;
				}
			}
			$id_column = 'id';
			$data_array['update_date'] = date('Y-m-d h:i:s');
			$data_array['update_by'] = $this->session->userdata('userid');
			if($this->common_model->updateData($data_array, $id_column, $id = '1', $table_name='pr_config_systemsettings')){
				$this->session->set_flashdata('msg', 'Successfully updated the organisation details.');
				redirect('configurations');
			}else{
				$this->session->set_flashdata('error', 'Something went wrong.');
				redirect('configurations');
				return;
			}
		}
	}

	function approveLevels($id=''){
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Approval Levels';
		if(!$_POST){
			if ( ! check_access('view_approval_levels'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('home');
				return;
			}
			$data['approval_id'] = '';
			$data['approvals'] = $this->configuration->get_approval_tables("");
			$this->load->view('pr/configurations/approval_levels', $data);
		}else{
			$id = $this->input->post('approvallevels');
			$data['approval_id'] = $id;
			$data['approvals'] = $this->configuration->get_approval_tables("");
			$data['approval'] = $this->configuration->get_approval_tables($id);
			$this->load->view('pr/configurations/approval_levels', $data);
		}
	}

	function updateDataoncheck()
	{
		if ( ! check_access('edit_approval_levels'))
		{
			return;
		}
		$ischecked = $this->input->post('ischecked');
		$table_name =  $this->input->post('table');
		$id_column =  'id';
		$entryid =  $this->input->post('entryid');
		$field =  $this->input->post('field');
		$update_by = $this->session->userdata('userid');
		$update_date = date('Y-m-d h:i:s');
		$data_array = array(
			$field 		=> $ischecked,
			'update_by' => $update_by,
			'update_date' => $update_date
		);
		if($ischecked!='' && $table_name != '' && $field!=''){
			if($this->common_model->updateData($data_array, $id_column,$entryid,$table_name)){
				//get permission name and add it to cm_menu_controllers
				$approve_level = $this->common_model->getData(
					$column = $id_column,
					$id = $entryid, 
					$table_name, 
					$like = '', 
					$not_like = '', 
					$limit = '',
					$start = '',
					$order_by = '', 
					$row_data = 'yes');
				
				$table_name = 'cm_menu_controllers';
				$id_column = 'controller_name';
				if($ischecked == '1'){
					if($field == 'need_check'){
						$insert_array = array(
							'main_id'	=> $approve_level->main_id,
							'sub_id' =>  $approve_level->sub_id,
							'controller_name' => $approve_level->access_type_check
						);
						$id =  $approve_level->access_type_check;
					}
					if($field == 'need_confirm'){
						$insert_array = array(
							'main_id'	=> $approve_level->main_id,
							'sub_id' =>  $approve_level->sub_id,
							'controller_name' => $approve_level->access_type_confirm
						);
						$id =  $approve_level->access_type_confirm;
					}
					$this->common_model->deleteData($id_column, $id, $table_name);
					$this->common_model->insertData($insert_array, $table_name);
				}else{
					if($field == 'need_check'){
						$id =  $approve_level->access_type_check;
					}
					if($field == 'need_confirm'){
						$id =  $approve_level->access_type_confirm;
					}
					$this->common_model->deleteData($id_column, $id, $table_name);
				}
			}
		}
	}

	function checklists(){
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Document Checklists';
		$data['search_string'] = '';
		if(!$_POST){
			if ( ! check_access('view_checklists'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('home');
				return;
			}
			$table = 'pr_config_checklist';
			$not_like = array(
				'status' => 'DELETE'
			);
			$config["base_url"] = base_url() . "configurations/checklists";
			$config["total_rows"] = $this->common_model->getSearchCount( $table, $like = '', $not_like);
			$config["per_page"] = RAW_COUNT;
			$config["uri_segment"] = 3;

			$this->pagination->initialize($config);
			$config = array();

			$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
			$data["links"] = $this->pagination->create_links();

			$not_like_array = array(
				'status' =>'DELETE'
			);
			$order_by = array(
				'status' => 'DESC',
				'checklist_name' => 'ACS',
			);
			$data['checklists'] = $this->common_model->getData(
												$column = '', //string
												$id = '', //int
												$table, //string
												$like = '', //array
												$not_like_array, //array
												RAW_COUNT, //int
												$page, //int
												$order_by //array
												);
			$this->load->view('pr/configurations/document_checklists', $data);
		}else{
			if ( ! check_access('create_checklists'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('configurations/checklists');
				return;
			}
			$table = 'pr_config_checklist';
			$checklist_name = $this->input->post('checklist_name');
			$like_array = array(
				'checklist_name' => $checklist_name
			);
			$check_exists = $this->common_model->getData(
								$column = 'checklist_name', 
								$id = '', //int
								$table, //string
								$like_array, //array
								$not_like = '', //array
								$per_page = '', //int
								$page = '', //int
								$order_by = '' //array
							);
			if($check_exists){
				$this->session->set_flashdata('error', 'Duplicate checklist name.');
				redirect('configurations/checklists');
				return;
			}
			$insert_array = array(
				'checklist_name' => $checklist_name,
				'create_date' => date('Y-m-d'),
				'create_by' => $this->session->userdata('userid')
			);
			if($this->common_model->insertData($insert_array, $table)){
				$this->session->set_flashdata('msg', 'Successfully created the checklist.');
				redirect('configurations/checklists');
			}else{
				$this->session->set_flashdata('error', 'Something went wrong.');
				redirect('configurations/checklists');
				return;
			}
			
			$this->load->view('pr/configurations/document_checklists', $data);
		}
	}

	function confirmChecklist($id){
		if ( ! check_access('confirm_checklists'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/checklists');
			return;
		}
		$id =  $this->encryption->decode($id);
		$status = 'confirm';
		$data_array = array(
			'status' => 'CONFIRMED',
			'confirm_by' => $this->session->userdata('userid'),
			'confirm_date' => date('Y-m-d')
		);
		$table_name = 'pr_config_checklist';
		$id_column = 'checklist_id';
		if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
			$this->session->set_flashdata('msg', ' Successfully confirmed the checklist.');
			redirect('configurations/checklists');
		}else{
			$this->session->set_flashdata('error', 'Something went wrong.');
			redirect('configurations/checklists');
		}
	}

	function deleteChecklist(){
		if ( ! check_access('delete_checklists'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/checklists');
			return;
		}
		$id = $this->input->post('id');
		$table_name = 'pr_config_checklist';
		$id_column = 'checklist_id';
		$data_array = array(
			'status' => 'DELETE'
		);
		if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
			echo '1';
		}else{
			echo '0';
		}	
	}

	function searchChecklists(){
		if ( ! check_access('view_checklists'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/checklists');
			return;
		}
		
		$data['search_string'] = $search_string = $this->input->post('search_string');
		if($search_string != ''){
			$this->session->set_userdata('search_string',$search_string);
		}
		$search_string = $this->session->userdata('search_string');
		$column = 'checklist_name';
		$table_name = 'pr_config_checklist';
		$like = array(
			$column => $search_string
		);
		$not_like = '';

		$config = array();
		$config["base_url"] = base_url() . "configurations/searchChecklists";
		$config["total_rows"] = $this->common_model->getSearchCount($table_name, $like, $not_like);

		$config["per_page"] = RAW_COUNT;
		$config["uri_segment"] = 3;

		$this->pagination->initialize($config);

		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;

		$data["links"] = $this->pagination->create_links();
		$data['checklists'] = $this->common_model->getData(
							$column, //string
							$id = '', //int
							$table_name, //string
							$like, //array
							$not_like = '', //array
							RAW_COUNT, //int
							$page, //int
							$order_by = '' //array
						);
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Document Checklists';
		$this->load->view('pr/configurations/document_checklists', $data);
	}

	function activateChecklist(){
		if ( ! check_access('reactivate_checklists'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/checklists');
			return;
		}
		$id = $this->input->post('id');
		$table_name = 'pr_config_checklist';
		$id_column = 'checklist_id';
		$data_array = array(
			'status' => 'CONFIRMED'
		);
		if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
			echo '1';
		}else{
			echo '0';
		}	
	}

	function branches(){
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Branches';
		$data['search_string'] = '';
		if(!$_POST){
			if ( ! check_access('view_branches'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('home');
				return;
			}
			$table = 'cm_branchms';
			$not_like = array(
				'status' => 'DELETE'
			);
			$config["base_url"] = base_url() . "configurations/branches";
			$config["total_rows"] = $this->common_model->getSearchCount( $table, $like = '', $not_like);
			$config["per_page"] = RAW_COUNT;
			$config["uri_segment"] = 3;

			$this->pagination->initialize($config);
			$config = array();

			$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
			$data["links"] = $this->pagination->create_links();

			$not_like_array = array(
				'status' =>'DELETE'
			);
			$order_by = array(
				'status' => 'DESC',
				'branch_name' => 'ACS',
			);
			$data['branches'] = $this->common_model->getData(
												$column = '', //string
												$id = '', //int
												$table, //string
												$like = '', //array
												$not_like_array, //array
												RAW_COUNT, //int
												$page, //int
												$order_by //array
												);
			$this->load->view('pr/configurations/branches', $data);
		}else{
			if ( ! check_access('create_branches'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('configurations/branches');
				return;
			}
			$table = 'cm_branchms';
			$field = 'branch_name';
			$value = $this->input->post('branch_name');
			if($this->common_model->checkExistance($table, $field, $value)){
				$this->session->set_flashdata('error', 'Duplicate branch name.');
				redirect('configurations/branches');
				return;
			}
			$field = 'shortcode';
			$value = $this->input->post('shortcode');
			if($this->common_model->checkExistance($table, $field, $value)){
				$this->session->set_flashdata('error', 'Duplicate short code.');
				redirect('configurations/branches');
				return;
			}

			if($this->branch_model->add()){
				$this->session->set_flashdata('msg', 'Successfully created the branch.');
				redirect('configurations/branches');
			}else{
				$this->session->set_flashdata('error', 'Something went wrong.');
				redirect('configurations/branches');
				return;
			}
		}
	}

	function confirmBranch($id){
		if ( ! check_access('confirm_branches'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/branches');
			return;
		}
		$id =  $this->encryption->decode($id);
		$status = 'confirm';
		$data_array = array(
			'status' => 'CONFIRMED',
			'confirm_by' => $this->session->userdata('userid'),
			'confirm_date' => date('Y-m-d')
		);
		$table_name = 'cm_branchms';
		$id_column = 'branch_code';
		if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
			$this->session->set_flashdata('msg', ' Successfully confirmed the branch.');
			redirect('configurations/branches');
		}else{
			$this->session->set_flashdata('error', 'Something went wrong.');
			redirect('configurations/branches');
		}
	}

	function getBranchDetails($purpose){
		$id = $this->input->post('id');
		if($branch_data = $this->common_model->getData(
							$column = 'branch_code', 
							$id, 
							$table_name = 'cm_branchms',
							$like = '', 
							$not_like = '', 
							$limit = '', 
							$start = '', 
							$order_by = '',
							$row_data = 'yes'))
		{
			$data['branch_data'] = $branch_data;
			$data['purpose'] = $purpose;
			$this->load->view('pr/configurations/branch_edit',$data);
		}
	}

	function updateBranchDetails(){
		if ( ! check_access('update_branches'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/branches');
			return;
		}

		$update_array = array( 
			'branch_name' => $this->input->post('branch_name'),
			'shortcode' => $this->input->post('shortcode'),
			'short_description' => $this->input->post('short_description'),
			'location_map' => $this->input->post('location_map'),
			'address1' => $this->input->post('address1'),
			'address2' => $this->input->post('address2'),
			'Contact_number' => $this->input->post('Contact_number'),
			'fax' => $this->input->post('fax'),
			'email' => $this->input->post('email'),
			'update_date' => date('Y-m-d h:i:s'),
			'update_by' => $this->session->userdata('userid')
		);

		$id = $this->input->post('branch_code');
		$status = $this->input->post('status');
		$id_column = 'branch_code';
		$table_name = 'cm_branchms';
		
		if($this->common_model->updateData($update_array, $id_column,$id,$table_name)){
			$this->session->set_flashdata('msg', 'Successfully updated the branch.');
			redirect('configurations/branches');
		}else{
			$this->session->set_flashdata('error', 'Unable to update the branch.');
			redirect('configurations/branches');
		}
	}

	function deleteBranch(){
		if ( ! check_access('delete_branches'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/branches');
			return;
		}
		$id = $this->input->post('id');
		$table_name = 'cm_branchms';
		$id_column = 'branch_code';
		$data_array = array(
			'status' => 'DELETE'
		);
		if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
			echo '1';
		}else{
			echo '0';
		}	
	}

	function searchBranches(){
		if ( ! check_access('view_branches'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/branches');
			return;
		}
		
		$data['search_string'] = $search_string = $this->input->post('search_string');
		if($search_string != ''){
			$this->session->set_userdata('search_string',$search_string);
		}
		$search_string = $this->session->userdata('search_string');
		$table_name = 'cm_branchms';
		$like = array(
			'branch_name' => $search_string,
			'shortcode' => $search_string,
			'short_description' => $search_string,
			'address1' => $search_string,
			'address2' => $search_string,
			'Contact_number' => $search_string,
			'fax' => $search_string,
			'email' => $search_string
		);
		$not_like = '';

		$config = array();
		$config["base_url"] = base_url() . "configurations/searchBranches";
		$config["total_rows"] = $this->common_model->getSearchCount($table_name, $like, $not_like);

		$config["per_page"] = RAW_COUNT;
		$config["uri_segment"] = 3;

		$this->pagination->initialize($config);

		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;

		$data["links"] = $this->pagination->create_links();
		$data['branches'] = $this->common_model->getData(
							$column = '', //string
							$id = '', //int
							$table_name, //string
							$like, //array
							$not_like = '', //array
							RAW_COUNT, //int
							$page, //int
							$order_by = '' //array
						);
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Branches';
		$this->load->view('pr/configurations/branches', $data);
	}

	function activateBranch(){
		if ( ! check_access('reactivate_branches'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/branches');
			return;
		}
		$id = $this->input->post('id');
		$table_name = 'cm_branchms';
		$id_column = 'branch_code';
		$data_array = array(
			'status' => 'CONFIRMED'
		);
		if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
			echo '1';
		}else{
			echo '0';
		}	
	}

	function expenseTypes(){
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Expense Types';
		$data['search_string'] = '';
		if(!$_POST){
			if ( ! check_access('view_expense_type'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('home');
				return;
			}
			$table = 'pr_config_expensetype';
			$not_like = array(
				'status' => 'DELETE'
			);
			$config["base_url"] = base_url() . "configurations/expenseTypes";
			$config["total_rows"] = $this->common_model->getSearchCount( $table, $like = '', $not_like);
			$config["per_page"] = RAW_COUNT;
			$config["uri_segment"] = 3;

			$this->pagination->initialize($config);
			$config = array();

			$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
			$data["links"] = $this->pagination->create_links();

			$not_like_array = array(
				'status' =>'DELETE'
			);
			$order_by = array(
				'status' => 'DESC',
				'expense_name' => 'ACS',
			);
			$data['expensetypes'] = $this->common_model->getData(
												$column = '', //string
												$id = '', //int
												$table, //string
												$like = '', //array
												$not_like_array, //array
												RAW_COUNT, //int
												$page, //int
												$order_by //array
												);
			$this->load->view('pr/configurations/expense_types', $data);
		}else{
			if ( ! check_access('create_expense_type'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('configurations/expenseTypes');
				return;
			}
			$table = 'pr_config_expensetype';
			$field = 'expense_name';
			$value = $this->input->post('expense_name');
			if($this->common_model->checkExistance($table, $field, $value)){
				$this->session->set_flashdata('error', 'Duplicate expense name.');
				redirect('configurations/expenseTypes');
				return;
			}
			$data_array = array(
				'expense_name' => $this->input->post('expense_name'),
				'ledger_id' => $this->input->post('ledger_id'),
				'recurring_type' => $this->input->post('recurring_type'),
				'enable_notification' => $this->input->post('enable_notification'),
				'notification_period' => $this->input->post('notification_period'),
				'create_date' =>date('Y-m-d'),
				'create_by' => $this->session->userdata('userid'),
			);
			$table = 'pr_config_expensetype';
			if($this->common_model->insertData($data_array, $table)){
				$this->session->set_flashdata('msg', 'Successfully created the expense type.');
				redirect('configurations/expenseTypes');
			}else{
				$this->session->set_flashdata('error', 'Something went wrong.');
				redirect('configurations/expenseTypes');
				return;
			}
		}
	}

	function confirmExpenseType($id){
		if ( ! check_access('confirm_expense_type'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/expenseTypes');
			return;
		}
		$id =  $this->encryption->decode($id);
		$status = 'confirm';
		$data_array = array(
			'status' => 'CONFIRMED',
			'confirm_by' => $this->session->userdata('userid'),
			'confirm_date' => date('Y-m-d')
		);
		$table_name = 'pr_config_expensetype';
		$id_column = 'expense_id';
		if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
			$this->session->set_flashdata('msg', ' Successfully confirmed the expense type.');
			redirect('configurations/expenseTypes');
		}else{
			$this->session->set_flashdata('error', 'Something went wrong.');
			redirect('configurations/expenseTypes');
		}
	}

	function deleteExpenseType(){
		if ( ! check_access('delete_expense_type'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/expenseTypes');
			return;
		}
		$id = $this->input->post('id');
		$expense_type = $this->configuration->getExpenseTypeById($id);
		if($expense_type->status != 'CONFIRMED'){
			if($this->configuration->deleteExpenseType($id)){
				echo '1';
			}else{
				echo '0';
			}
		}else{
			$table_name = 'pr_config_expensetype';
			$id_column = 'expense_id';
			$data_array = array(
				'status' => 'DELETE'
			);
			if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
				echo '1';
			}else{
				echo '0';
			}
		}	
	}
		
	function getExpenseTypeDetails($purpose){
		$id = $this->input->post('id');
		if($expense_type_data = $this->common_model->getData(
							$column = 'expense_id', 
							$id, 
							$table_name = 'pr_config_expensetype',
							$like = '', 
							$not_like = '', 
							$limit = '', 
							$start = '', 
							$order_by = '',
							$row_data = 'yes'))
		{
			$data['expense_type_data'] = $expense_type_data;
			$data['purpose'] = $purpose;
			$this->load->view('pr/configurations/expense_type_edit',$data);
		}
	}

	function updateExpenseType(){
		if ( ! check_access('edit_expense_type'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/expenseTypes');
			return;
		}

		$update_array = array( 
			'expense_name' => $this->input->post('expense_name'),
			'ledger_id' => $this->input->post('ledger_id'),
			'recurring_type' => $this->input->post('recurring_type'),
			'enable_notification' => $this->input->post('enable_notification'),
			'notification_period' => $this->input->post('notification_period'),
			'update_date' =>date('Y-m-d h:i:s'),
			'update_by' => $this->session->userdata('userid')
		);

		$id = $this->input->post('expense_id');
		$status = $this->input->post('status');
		$id_column = 'expense_id';
		$table_name = 'pr_config_expensetype';
		
		if($this->common_model->updateData($update_array, $id_column,$id,$table_name)){
			$this->session->set_flashdata('msg', 'Successfully updated the expense type.');
			redirect('configurations/expenseTypes');
		}else{
			$this->session->set_flashdata('error', 'Unable to update the expense type.');
			redirect('configurations/expenseTypes');
		}
	}

	function searchExpenseTypes(){
		if ( ! check_access('view_expense_type'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/expenseTypes');
			return;
		}
		
		$data['search_string'] = $search_string = $this->input->post('search_string');
		if($search_string != ''){
			$this->session->set_userdata('search_string',$search_string);
		}
		$search_string = $this->session->userdata('search_string');
		$table_name = 'pr_config_expensetype';
		$like = array(
			'expense_name' => $search_string,
			'recurring_type' => $search_string,
			'notification_period' => $search_string
		);
		$not_like = '';

		$config = array();
		$config["base_url"] = base_url() . "configurations/searchExpenseTypes";
		$config["total_rows"] = $this->common_model->getSearchCount($table_name, $like, $not_like);

		$config["per_page"] = RAW_COUNT;
		$config["uri_segment"] = 3;

		$this->pagination->initialize($config);

		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;

		$data["links"] = $this->pagination->create_links();
		$data['expensetypes'] = $this->common_model->getData(
							$column = '', //string
							$id = '', //int
							$table_name, //string
							$like, //array
							$not_like = '', //array
							RAW_COUNT, //int
							$page, //int
							$order_by = '' //array
						);
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Expense Types';
		$this->load->view('pr/configurations/expense_types', $data);
	}

	function activateExpenseType(){
		if ( ! check_access('reactivate_expense_type'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/expenseTypes');
			return;
		}
		$id = $this->input->post('id');
		$table_name = 'pr_config_expensetype';
		$id_column = 'expense_id';
		$data_array = array(
			'status' => 'CONFIRMED'
		);
		if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
			echo '1';
		}else{
			echo '0';
		}	
	}

	function menuOrder(){
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Arrange Menu';
		if(!$_POST){
			if ( ! check_access('arrange_menu'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('home');
				return;
			}
			$data['main_menus'] = $this->accesshelper_model->get_all_mainmenus();
			$this->load->view('pr/configurations/menu_order', $data);
		}else{
			$id = $this->input->post('id');
			$value = $this->input->post('value');
			$type = $this->input->post('type');
			if($type == 'main'){
				$table_name = 'cm_menu_main';
				$id_column = 'main_id';
			}
			if($type == 'sub'){
				$table_name = 'cm_menu_sub';
				$id_column = 'sub_id';
			}
			$update_array = array(
				'order_key' => $value
			);
			$this->common_model->updateData($update_array, $id_column,$id,$table_name);
		}
	}

	function propertyTypes(){
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Property Types';
		$data['search_string'] = '';
		if(!$_POST){
			if ( ! check_access('view_property_types'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('home');
				return;
			}
			$table = 'pr_config_proptype';
			$not_like = array(
				'status' => 'DELETE'
			);
			$config["base_url"] = base_url() . "configurations/propertyTypes";
			$config["total_rows"] = $this->common_model->getSearchCount( $table, $like = '', $not_like);
			$config["per_page"] = RAW_COUNT;
			$config["uri_segment"] = 3;

			$this->pagination->initialize($config);
			$config = array();

			$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
			$data["links"] = $this->pagination->create_links();

			$not_like_array = array(
				'status' =>'DELETE'
			);
			$order_by = array(
				'status' => 'DESC',
				'proptype_name' => 'ACS',
			);
			$data['propertytypes'] = $this->common_model->getData(
												$column = '', //string
												$id = '', //int
												$table, //string
												$like = '', //array
												$not_like_array, //array
												RAW_COUNT, //int
												$page, //int
												$order_by //array
												);
			$this->load->view('pr/configurations/property_types', $data);
		}else{
			if ( ! check_access('create_property_types'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('configurations/propertyTypes');
				return;
			}
			$table = 'pr_config_proptype';
			$field = 'proptype_name';
			$value = $this->input->post('proptype_name');
			if($this->common_model->checkExistance($table, $field, $value)){
				$this->session->set_flashdata('error', 'Duplicate property type name.');
				redirect('configurations/propertyTypes');
				return;
			}
			$file_name = '';
			if (isset($_FILES['fileUpload']['name']) && $_FILES['fileUpload']['error'] == 0) {
				$config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
				$config['allowed_types'] = 'jpg|png'; 
				$config['max_size']      = 1024;
				$this->load->library('upload', $config);
				if ( $this->upload->do_upload('fileUpload')) {
					$uploadedImage = $this->upload->data();
					$file_name = $uploadedImage['file_name'];
					$source_path = $path. $file_name;
					resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
				}else{
					$this->session->set_flashdata('error', 'Property type image size is larger than 1MB.');
					redirect('configurations/propertyTypes');
					return;
				}
			}

			$data_array = array(
				'proptype_name' => $this->input->post('proptype_name'),
				'proptype_description' => $this->input->post('proptype_description'),
				'has_units' => $this->input->post('has_units'),
				'main_image' => $file_name,
				'create_date' =>date('Y-m-d'),
				'create_by' => $this->session->userdata('userid'),
			);
			$table = 'pr_config_proptype';
			if($this->common_model->insertData($data_array, $table)){
				$this->session->set_flashdata('msg', 'Successfully created the property type.');
				redirect('configurations/propertyTypes');
			}else{
				$this->session->set_flashdata('error', 'Something went wrong.');
				redirect('configurations/propertyTypes');
				return;
			}
		}
	}

	function confirmPropertyType($id){
		if ( ! check_access('confirm_property_types'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/propertyTypes');
			return;
		}
		$id =  $this->encryption->decode($id);
		$status = 'confirm';
		$data_array = array(
			'status' => 'CONFIRMED',
			'confirm_by' => $this->session->userdata('userid'),
			'confirm_date' => date('Y-m-d')
		);
		$table_name = 'pr_config_proptype';
		$id_column = 'proptype_id';
		if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
			$this->session->set_flashdata('msg', ' Successfully confirmed the property type.');
			redirect('configurations/propertyTypes');
		}else{
			$this->session->set_flashdata('error', 'Something went wrong.');
			redirect('configurations/propertyTypes');
		}
	}

	function deletePropertyType(){
		if ( ! check_access('delete_property_types'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/propertyTypes');
			return;
		}
		$id = $this->input->post('id');
		$table_name = 'pr_config_proptype';
		$id_column = 'proptype_id';

		$property_type = $this->common_model->getData(
				$column = $id_column, //string
				$id = $id, //int
				$table_name, //string
				$like = '', //array
				$not_like_array = '', //array
				$row_count = '', //int
				$page = '', //int
				$order_by = '', //array
				$raw = 'yes'
			);
		
		if($property_type->status == 'CONFIRMED'){
			$data_array = array(
				'status' => 'DELETE'
			);
			if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
				echo '1';
			}else{
				echo '0';
			}
		}else{
			if($this->common_model->deleteData($id_column, $id, $table_name)){
				echo '1';
			}else{
				echo '0';
			}
		}	
	}

	function getPropertyTypeDetails($purpose){
		$id = $this->input->post('id');
		if($property_type_data = $this->common_model->getData(
							$column = 'proptype_id', 
							$id, 
							$table_name = 'pr_config_proptype',
							$like = '', 
							$not_like = '', 
							$limit = '', 
							$start = '', 
							$order_by = '',
							$row_data = 'yes'))
		{
			$data['property_type_data'] = $property_type_data;
			$data['purpose'] = $purpose;
			$this->load->view('pr/configurations/property_type_edit',$data);
		}
	}

	function updatePropertyType(){
		if ( ! check_access('edit_property_types'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/propertyTypes');
			return;
		}

		$update_array = array( 
			'proptype_name' => $this->input->post('proptype_name'),
			'proptype_description' => $this->input->post('proptype_description'),
			'has_units' => $this->input->post('has_units'),
			'update_date' =>date('Y-m-d h:i:s'),
			'update_by' => $this->session->userdata('userid')
		);

		if (isset($_FILES['fileUpload']['name']) && $_FILES['fileUpload']['error'] == 0) {
			$config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
			$config['allowed_types'] = 'jpg|png'; 
			$config['max_size']      = 2048;
			$this->load->library('upload', $config);
			if ( $this->upload->do_upload('fileUpload')) {
				unlink($path.$this->input->post('old-image')); //remove old file
				$uploadedImage = $this->upload->data();
				$file_name = $uploadedImage['file_name'];
				$source_path = $path. $file_name;
				resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
				$update_array['main_image'] = $file_name;
			}
		}

		$id = $this->input->post('proptype_id');
		$status = $this->input->post('status');
		$id_column = 'proptype_id';
		$table_name = 'pr_config_proptype';
		
		if($this->common_model->updateData($update_array, $id_column,$id,$table_name)){
			$this->session->set_flashdata('msg', 'Successfully updated the property type.');
			redirect('configurations/propertyTypes');
		}else{
			$this->session->set_flashdata('error', 'Unable to update the property type.');
			redirect('configurations/propertyTypes');
		}
	}

	function searchPropertyTypes(){
		if ( ! check_access('view_property_types'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/propertyTypes');
			return;
		}
		
		$data['search_string'] = $search_string = $this->input->post('search_string');
		if($search_string != ''){
			$this->session->set_userdata('search_string',$search_string);
		}
		$search_string = $this->session->userdata('search_string');
		$table_name = 'pr_config_proptype';
		$like = array(
			'proptype_name' => $search_string,
			'proptype_description' => $search_string
		);
		$not_like = '';

		$config = array();
		$config["base_url"] = base_url() . "configurations/searchPropertyTypes";
		$config["total_rows"] = $this->common_model->getSearchCount($table_name, $like, $not_like);

		$config["per_page"] = RAW_COUNT;
		$config["uri_segment"] = 3;

		$this->pagination->initialize($config);

		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;

		$data["links"] = $this->pagination->create_links();
		$data['propertytypes'] = $this->common_model->getData(
							$column = '', //string
							$id = '', //int
							$table_name, //string
							$like, //array
							$not_like = '', //array
							RAW_COUNT, //int
							$page, //int
							$order_by = '' //array
						);
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Property Types';
		$this->load->view('pr/configurations/property_types', $data);
	}

	function activatePropertyType(){
		if ( ! check_access('reactivate_property_types'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/propertyTypes');
			return;
		}
		$id = $this->input->post('id');
		$table_name = 'pr_config_proptype';
		$id_column = 'proptype_id';
		$data_array = array(
			'status' => 'CONFIRMED'
		);
		if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
			echo '1';
		}else{
			echo '0';
		}	
	}

	function documentTypes(){
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Document Types';
		$data['search_string'] = '';
		if(!$_POST){
			if ( ! check_access('view_document_types'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('home');
				return;
			}
			$table = 'pr_config_doctype';
			$config["base_url"] = base_url() . "configurations/documentTypes";
			$config["total_rows"] = $this->common_model->getSearchCount( $table, $like = '', $not_like = '');
			$config["per_page"] = RAW_COUNT;
			$config["uri_segment"] = 3;

			$this->pagination->initialize($config);
			$config = array();

			$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
			$data["links"] = $this->pagination->create_links();
			$order_by = array(
				'doctype_name' => 'ACS',
			);
			$data['documenttypes'] = $this->common_model->getData(
												$column = '', //string
												$id = '', //int
												$table, //string
												$like = '', //array
												$not_like_array = '', //array
												RAW_COUNT, //int
												$page, //int
												$order_by, //array
												$row_data = '' //yes
												);
			$this->load->view('pr/configurations/document_types', $data);
		}else{
			if ( ! check_access('create_document_types'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('configurations/documentTypes');
				return;
			}
			$table = 'pr_config_doctype';
			$field = 'doctype_name';
			$value = $this->input->post('doctype_name');
			if($this->common_model->checkExistance($table, $field, $value)){
				$this->session->set_flashdata('error', 'Duplicate document type name.');
				redirect('configurations/documentTypes');
				return;
			}

			$data_array = array(
				'doctype_name' => $this->input->post('doctype_name'),
				'doc_categery' => $this->input->post('doc_categery'),
			);
			if($this->common_model->insertData($data_array, $table)){
				$this->session->set_flashdata('msg', 'Successfully created the document type.');
				redirect('configurations/documentTypes');
			}else{
				$this->session->set_flashdata('error', 'Something went wrong.');
				redirect('configurations/documentTypes');
				return;
			}
		}
	}

	function deleteDocumentTypes(){
		if ( ! check_access('delete_document_types'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/documentTypes');
			return;
		}
		$id = $this->input->post('id');
		$table_name = 'pr_config_doctype';
		$id_column = 'doctype_id';
		if($this->common_model->deleteData($id_column, $id, $table_name)){
			echo '1';
		}else{
			echo '0';
		}	
	}

	function RenteePayTypes(){
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Rentee Payment Types';
		$data['search_string'] = '';
		if(!$_POST){
			if ( ! check_access('view_rentee_pay_types'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('home');
				return;
			}
			$table = 'pr_config_renteepaytype';
			$not_like = array(
				'status' => 'DELETE'
			);
			$config["base_url"] = base_url() . "configurations/RenteePayTypes";
			$config["total_rows"] = $this->common_model->getSearchCount( $table, $like = '', $not_like);
			$config["per_page"] = RAW_COUNT;
			$config["uri_segment"] = 3;

			$this->pagination->initialize($config);
			$config = array();

			$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
			$data["links"] = $this->pagination->create_links();

			$not_like_array = array(
				'status' =>'DELETE'
			);
			$order_by = array(
				'status' => 'DESC',
				'paytype_name' => 'ACS',
			);
			$list = array('3','4','5','6','7','8');
            $data['ledger_groups'] = $this->ledger_model->getLedgerGroups($list);
			$data['paytypes'] = $this->common_model->getData(
												$column = '', //string
												$id = '', //int
												$table, //string
												$like = '', //array
												$not_like_array, //array
												RAW_COUNT, //int
												$page, //int
												$order_by //array
												);
			$this->load->view('pr/configurations/rentee_pay_types', $data);
		}else{
			if ( ! check_access('create_rentee_pay_types'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('configurations/RenteePayTypes');
				return;
			}
			$table = 'pr_config_renteepaytype';
			$field = 'paytype_name';
			$value = $this->input->post('paytype_name');
			if($this->common_model->checkExistance($table, $field, $value)){
				$this->session->set_flashdata('error', 'Duplicate payment type name.');
				redirect('configurations/RenteePayTypes');
				return;
			}

			$data_array = array(
				'paytype_name' => $this->input->post('paytype_name'),
				'ledger_id' => $this->input->post('ledger_id'),
				'late_payement_rate' =>  $this->input->post('late_payement_rate'),
				'grace_period' =>  $this->input->post('grace_period'),
				'late_payledger' =>  $this->input->post('late_payledger'),
				'receivable_ledger' =>  $this->input->post('receivable_ledger'),
				'recurring_type' =>  $this->input->post('recurring_type'),
				'enable_notification' =>  $this->input->post('enable_notification'),
				'notification_period' =>  $this->input->post('notification_period'),
				'create_date' =>date('Y-m-d'),
				'create_by' => $this->session->userdata('userid'),
			);
			$table = 'pr_config_renteepaytype';
			if($this->common_model->insertData($data_array, $table)){
				$this->session->set_flashdata('msg', 'Successfully created the rentee payment type.');
				redirect('configurations/RenteePayTypes');
			}else{
				$this->session->set_flashdata('error', 'Something went wrong.');
				redirect('configurations/RenteePayTypes');
				return;
			}
		}
	}

	function confirmRenteePayTypes($id){
		if ( ! check_access('confirm_rentee_pay_types'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/RenteePayTypes');
			return;
		}
		$id =  $this->encryption->decode($id);
		$data_array = array(
			'status' => 'CONFIRMED',
			'confirm_by' => $this->session->userdata('userid'),
			'confirm_date' => date('Y-m-d')
		);
		$table_name = 'pr_config_renteepaytype';
		$id_column = 'paytype_id';
		if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
			$this->session->set_flashdata('msg', ' Successfully confirmed the rentee payment type.');
			redirect('configurations/RenteePayTypes');
		}else{
			$this->session->set_flashdata('error', 'Something went wrong.');
			redirect('configurations/RenteePayTypes');
		}
	}

	function getRenteePayTypesDetails($purpose){
		$id = $this->input->post('id');
		if($payment_type_data = $this->common_model->getData(
							$column = 'paytype_id', 
							$id, 
							$table_name = 'pr_config_renteepaytype',
							$like = '', 
							$not_like = '', 
							$limit = '', 
							$start = '', 
							$order_by = '',
							$row_data = 'yes'))
		{
			$data['payment_type_data'] = $payment_type_data;
			$data['purpose'] = $purpose;
			$this->load->view('pr/configurations/rentee_pay_type_edit',$data);
		}
	}

	function updateRenteePayTypes(){
		if ( ! check_access('edit_rentee_pay_types'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/RenteePayTypes');
			return;
		}

		$id = $this->input->post('paytype_id');
		//get Paytype
		$payment_type_data = $this->common_model->getData(
			$column = 'paytype_id', 
			$id, 
			$table_name = 'pr_config_renteepaytype',
			$like = '', 
			$not_like = '', 
			$limit = '', 
			$start = '', 
			$order_by = '',
			$row_data = 'yes');
		
		if($payment_type_data->lock == '1'){
			$update_array = array( 
				'paytype_name' => $this->input->post('paytype_name'),
				'late_payement_rate' =>  $this->input->post('late_payement_rate'),
				'grace_period' =>  $this->input->post('grace_period'),
				'enable_notification' =>  $this->input->post('enable_notification'),
				'notification_period' =>  $this->input->post('notification_period'),
				'update_date' =>date('Y-m-d h:i:s'),
				'update_by' => $this->session->userdata('userid')
			);
		}else{
			$update_array = array( 
				'paytype_name' => $this->input->post('paytype_name'),
				'ledger_id' => $this->input->post('ledger_id'),
				'late_payement_rate' =>  $this->input->post('late_payement_rate'),
				'grace_period' =>  $this->input->post('grace_period'),
				'late_payledger' =>  $this->input->post('late_payledger'),
				'receivable_ledger' =>  $this->input->post('receivable_ledger'),
				'recurring_type' =>  $this->input->post('recurring_type'),
				'enable_notification' =>  $this->input->post('enable_notification'),
				'notification_period' =>  $this->input->post('notification_period'),
				'update_date' =>date('Y-m-d h:i:s'),
				'update_by' => $this->session->userdata('userid')
			);
		}
		
		$status = $this->input->post('status');
		$id_column = 'paytype_id';
		$table_name = 'pr_config_renteepaytype';
		
		if($this->common_model->updateData($update_array, $id_column,$id,$table_name)){
			$this->session->set_flashdata('msg', 'Successfully updated the payment type.');
			redirect('configurations/RenteePayTypes');
		}else{
			$this->session->set_flashdata('error', 'Unable to update the payment type.');
			redirect('configurations/RenteePayTypes');
		}
	}

	function deleteRenteePayTypes(){
		if ( ! check_access('delete_rentee_pay_types'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/RenteePayTypes');
			return;
		}
		$id = $this->input->post('id');
		$table_name = 'pr_config_renteepaytype';
		$id_column = 'paytype_id';
		$data_array = array(
			'status' => 'DELETE'
		);
		if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
			echo '1';
		}else{
			echo '0';
		}		
	}

	function searchRenteePayTypes(){
		if ( ! check_access('view_property_types'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/RenteePayTypes');
			return;
		}
		
		$data['search_string'] = $search_string = $this->input->post('search_string');
		if($search_string != ''){
			$this->session->set_userdata('search_string',$search_string);
		}
		$search_string = $this->session->userdata('search_string');
		$table_name = 'pr_config_renteepaytype';
		$like = array(
			'paytype_name' => $search_string,
		);
		$not_like = '';

		$config = array();
		$config["base_url"] = base_url() . "configurations/searchRenteePayTypes";
		$config["total_rows"] = $this->common_model->getSearchCount($table_name, $like, $not_like);

		$config["per_page"] = RAW_COUNT;
		$config["uri_segment"] = 3;

		$this->pagination->initialize($config);

		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;

		$data["links"] = $this->pagination->create_links();
		$data['paytypes'] = $this->common_model->getData(
							$column = '', //string
							$id = '', //int
							$table_name, //string
							$like, //array
							$not_like = '', //array
							RAW_COUNT, //int
							$page, //int
							$order_by = '' //array
						);
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Rentee Pay Types';
		$this->load->view('pr/configurations/rentee_pay_types', $data);
	}

	function activateRenteePayTypes(){
		if ( ! check_access('reactivate_rentee_pay_types'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/RenteePayTypes');
			return;
		}
		$id = $this->input->post('id');
		$table_name = 'pr_config_renteepaytype';
		$id_column = 'paytype_id';
		$data_array = array(
			'status' => 'CONFIRMED'
		);
		if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
			echo '1';
		}else{
			echo '0';
		}	
	}

	function paymentNotifications(){
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Payment Notifications';
		$data['search_string'] = '';
		if(!$_POST){
			if ( ! check_access('view_payment_notifications'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('home');
				return;
			}
			$table = 'pr_config_notification';
			$config["base_url"] = base_url() . "configurations/paymentNotifications";
			$config["total_rows"] = $this->common_model->getSearchCount( $table, $like = '', $not_like = '');
			$config["per_page"] = RAW_COUNT;
			$config["uri_segment"] = 3;

			$this->pagination->initialize($config);
			$config = array();

			$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
			$data["links"] = $this->pagination->create_links();
			$data['notifications'] = $this->configuration->getPaymentNotifications(RAW_COUNT, $page);
			$table_name = 'pr_config_renteepaytype';
			$data['payment_types'] = $this->configuration->getUnusedPaymentTypes();
			$this->load->view('pr/configurations/payment_notifications', $data);
		}else{
			if ( ! check_access('create_payment_notifications'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('configurations/paymentNotifications');
				return;
			}
			$data_array = array(
				'paytype_id' => $this->input->post('paytype_id'),
			);
			foreach($this->input->post('notifications') as $type){
				$data_array[$type] = '1';
			}
			$table = 'pr_config_notification';
			if($this->common_model->insertData($data_array, $table)){
				$this->session->set_flashdata('msg', 'Successfully created the payment notifications.');
				redirect('configurations/paymentNotifications');
			}else{
				$this->session->set_flashdata('error', 'Something went wrong.');
				redirect('configurations/paymentNotifications');
				return;
			}
		}
	}

	function updatePaymentNotifications()
	{
		if ( ! check_access('edit_payment_notifications'))
		{
			return;
		}
		$ischecked = $this->input->post('ischecked');
		$table_name =  $this->input->post('table');
		$id_column =  'id';
		$entryid =  $this->input->post('entryid');
		$field =  $this->input->post('field');
		$data_array = array(
			$field 		=> $ischecked,
		);
		if($ischecked!='' && $table_name != '' && $field!=''){
			$this->common_model->updateData($data_array, $id_column,$entryid,$table_name);
		}
	}

	function searchPaymentNotifications(){
		if ( ! check_access('view_payment_notifications'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/paymentNotifications');
			return;
		}
		
		$data['search_string'] = $search_string = $this->input->post('search_string');
		if($search_string != ''){
			$this->session->set_userdata('search_string',$search_string);
		}
		$search_string = $this->session->userdata('search_string');
		$table_name = 'pr_config_proptype';
		$like = array(
			'proptype_name' => $search_string,
			'proptype_description' => $search_string
		);
		$not_like = '';

		$config = array();
		$config["base_url"] = base_url() . "configurations/searchPropertyTypes";
		$config["total_rows"] = $this->common_model->getSearchCount($table_name, $like, $not_like);

		$config["per_page"] = RAW_COUNT;
		$config["uri_segment"] = 3;

		$this->pagination->initialize($config);

		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;

		$data["links"] = $this->pagination->create_links();
		$data['propertytypes'] = $this->common_model->getData(
							$column = '', //string
							$id = '', //int
							$table_name, //string
							$like, //array
							$not_like = '', //array
							RAW_COUNT, //int
							$page, //int
							$order_by = '' //array
						);
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Property Types';
		$this->load->view('pr/configurations/property_types', $data);
	}

	function accountingMethod(){
		if($_POST){
			if ( ! check_access('update_default_bank'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('configurations/accountingMethod');
				return;
			}
			if($this->configuration->updateDefaultLedger()){
				$this->session->set_flashdata('msg', 'Successfully updated the accounting method.');
			}else{
				$this->session->set_flashdata('error', 'Something went wrong.');
			}
			redirect('configurations/accountingMethod');
		}
		if ( ! check_access('view_accounting_method'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('home');
			return;
		}
		$data['method'] = $this->configuration->getAccountingMethod();
		$data['bank_accounts'] = $this->ledger_model->get_all_ac_ledgers_tomake_payment();
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Accounting Method';
		$this->load->view('pr/configurations/accounting_method', $data);
	}

	function taskCategories(){
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Task Categories';
		$data['search_string'] = '';
		if(!$_POST){
			if ( ! check_access('view_task_categories'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('home');
				return;
			}
			$table = 'pr_config_task';
			$not_like = array(
				'status' => 'DELETE'
			);
			$config["base_url"] = base_url() . "configurations/taskCategories";
			$config["total_rows"] = $this->common_model->getSearchCount( $table, $like = '', $not_like);
			$config["per_page"] = RAW_COUNT;
			$config["uri_segment"] = 3;

			$this->pagination->initialize($config);
			$config = array();

			$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
			$data["links"] = $this->pagination->create_links();

			$not_like_array = array(
				'status' =>'DELETE'
			);
			$order_by = array(
				'status' => 'DESC',
				'task_name' => 'ACS',
			);
			$data['categories'] = $this->common_model->getData(
												$column = '', //string
												$id = '', //int
												$table, //string
												$like = '', //array
												$not_like_array, //array
												RAW_COUNT, //int
												$page, //int
												$order_by //array
												);
			$this->load->view('pr/configurations/task_categories', $data);
		}else{
			if ( ! check_access('add_task_categories'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('configurations/taskCategories');
				return;
			}
			$table = 'pr_config_task';
			$task_name = $this->input->post('task_name');
			$like_array = array(
				'task_name' => $task_name
			);
			$check_exists = $this->common_model->getData(
								$column = 'task_name', 
								$id = '', //int
								$table, //string
								$like_array, //array
								$not_like = '', //array
								$per_page = '', //int
								$page = '', //int
								$order_by = '' //array
							);
			if($check_exists){
				$this->session->set_flashdata('error', 'Duplicate category name.');
				redirect('configurations/taskCategories');
				return;
			}
			$insert_array = array(
				'task_name' => $task_name,
				'create_date' => date('Y-m-d'),
				'create_by' => $this->session->userdata('userid')
			);
			if($this->common_model->insertData($insert_array, $table)){
				$this->session->set_flashdata('msg', 'Successfully created the task category.');
				redirect('configurations/taskCategories');
			}else{
				$this->session->set_flashdata('error', 'Something went wrong.');
				redirect('configurations/taskCategories');
				return;
			}
			
			$this->load->view('pr/configurations/task_categories', $data);
		}
	}

	function searchCategories(){
		if ( ! check_access('view_task_categories'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/taskCategories');
			return;
		}
		
		$data['search_string'] = $search_string = $this->input->post('search_string');
		if($search_string != ''){
			$this->session->set_userdata('search_string',$search_string);
		}
		$search_string = $this->session->userdata('search_string');
		$column = 'task_name';
		$table_name = 'pr_config_task';
		$like = array(
			$column => $search_string
		);
		$not_like = '';

		$config = array();
		$config["base_url"] = base_url() . "configurations/searchCategories";
		$config["total_rows"] = $this->common_model->getSearchCount($table_name, $like, $not_like);

		$config["per_page"] = RAW_COUNT;
		$config["uri_segment"] = 3;

		$this->pagination->initialize($config);

		$page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;

		$data["links"] = $this->pagination->create_links();
		$data['categories'] = $this->common_model->getData(
							$column, //string
							$id = '', //int
							$table_name, //string
							$like, //array
							$not_like = '', //array
							RAW_COUNT, //int
							$page, //int
							$order_by = '' //array
						);
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Task Categories';
		$this->load->view('pr/configurations/task_categories', $data);
	}

	function confirmCategory($id){
		if ( ! check_access('confirm_task_categories'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/taskCategories');
			return;
		}
		$id =  $this->encryption->decode($id);
		$status = 'confirm';
		$data_array = array(
			'status' => 'CONFIRMED',
			'confirm_by' => $this->session->userdata('userid'),
			'confirm_date' => date('Y-m-d')
		);
		$table_name = 'pr_config_task';
		$id_column = 'task_id';
		if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
			$this->session->set_flashdata('msg', ' Successfully confirmed the task category.');
			redirect('configurations/taskCategories');
		}else{
			$this->session->set_flashdata('error', 'Something went wrong.');
			redirect('configurations/taskCategories');
		}
	}

	function deleteCategory(){
		if ( ! check_access('delete_task_categories'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/taskCategories');
			return;
		}
		$id = $this->input->post('id');
		$table_name = 'pr_config_task';
		$id_column = 'task_id';
		$data_array = array(
			'status' => 'DELETE'
		);
		if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
			echo '1';
		}else{
			echo '0';
		}	
	}

	function activateCategory(){
		if ( ! check_access('reactivate_task_categories'))
		{
			$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
			redirect('configurations/taskCategories');
			return;
		}
		$id = $this->input->post('id');
		$table_name = 'pr_config_task';
		$id_column = 'task_id';
		$data_array = array(
			'status' => 'CONFIRMED'
		);
		if($this->common_model->updateData($data_array, $id_column,$id,$table_name)){
			echo '1';
		}else{
			echo '0';
		}	
	}

  	function updateMaximumImages(){
		$data['menu_name'] = 'Configurations';
		$data['submenu_name'] = 'Maximum Images';
		$data['search_string'] = '';
		if(!$_POST){
			if ( ! check_access('update_maximum_images')){
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('home');
			}else{
				$data['settings'] = $this->configuration->get_config_systemsettings();
				$this->load->view('pr/configurations/maximum_images', $data);
			}
		}else{
			if ( ! check_access('update_maximum_images'))
			{
				$this->session->set_flashdata('error', 'You do not have permission to perform this action.');
				redirect('configurations/updateMaximumImages');
				return;
			}
			$data_array = array(
				'max_prop_images' => $this->input->post('max_prop_images'),
				'max_unit_images' => $this->input->post('max_unit_images'),
			);

			$system_settings = $this->configuration->get_config_systemsettings();

			$table = 'pr_config_systemsettings';
			if($this->common_model->updateData($data_array, 'id',$system_settings->id,$table)){
				$this->session->set_flashdata('msg', 'Successfully Updated the No of Maximum Images.');
				redirect('configurations/updateMaximumImages');
			}else{
				$this->session->set_flashdata('error', 'Something went wrong.');
				redirect('configurations/updateMaximumImages');
				return;
			}
		}
	}
}
