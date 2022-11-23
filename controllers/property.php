<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Property extends CI_Controller {

	 function __construct() {
        parent::__construct();
		$this->is_logged_in();
		$this->load->model("pr/configuration_model",'configuration');
		$this->load->model("accesshelper_model");
		$this->load->model("pr/common_model");
		$this->load->model("branch_model");
        $this->load->model("pr/property_model");
		$this->load->library("pagination");
    }
	
	function index()
	{   
        if ( ! check_access('view_property'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        $data['menu_name'] = 'Rental';
		$data['submenu_name'] = 'Properties';

        //Pagination Config
 
        $config["base_url"] = base_url() . "pr/property/index";
        $config["total_rows"] = $this->common_model->getSearchCount( "pr_rent_propertymain", $like = '', $not_like = '');
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 4;
        
        $this->pagination->initialize($config);
        $config = array();
        
        $page = ($this->uri->segment(4)) ? $this->uri->segment(4) : 0;
        $data["links"] = $this->pagination->create_links();
        //End of Pagination Config

        $data['property_list'] = $this->property_model->getPropertyData(RAW_COUNT,$page);

        //initialize search_details
        $properties = $this->common_model->getData('', '', 'pr_rent_propertymain', '', '', '', '', '');
        $properties_array = array();
        foreach($properties as $property){
            $properties_array[$property->property_id]['id'] = $property->property_id;
            $properties_array[$property->property_id]['name'] = $property->property_name;
        }
        $data['properties'] = $properties_array;

        $propertyTypes = $this->common_model->getData('', '', 'pr_config_proptype', '', '', '', '', '');
        $propertyTypes_array = array();
        foreach($propertyTypes as $propertyType){
            $propertyTypes_array[$propertyType->proptype_id]['id'] = $propertyType->proptype_id;
            $propertyTypes_array[$propertyType->proptype_id]['name'] = $propertyType->proptype_name;
        }
        $data['propertyTypes'] = $propertyTypes_array;

        $this->load->view('pr/rental/property/property_main.php',$data);
        return;

	}

    function addProperty()
    {
        if ( ! check_access('add_property'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }

        $data['menu_name'] = 'Rental';
		$data['submenu_name'] = 'Properties';
        $like = array( 'status' => 'CONFIRMED' );
        $data['property_types'] = $this->common_model->getData('status', 'CONFIRMED', 'pr_config_proptype', $like, '', '', '', '');
        $data['branch_list'] = $this->common_model->getData('*', '', 'cm_branchms', '', '', '', '', '');
        $data['user_list'] = $this->common_model->getData('status','A','hr_empmastr','','','','','');
        $data['check_officer_list'] = $this->common_model->get_privilage_officer_list('check_property');
        $data['confirm_officer_list'] = $this->common_model->get_privilage_officer_list('confirm_property');
        $this->load->view('pr/rental/property/add_property.php',$data);
        return;
    }

    function addUnit()
    {
        $data['counter']=$counter=$this->uri->segment(2);
        $this->load->view('pr/rental/property/add_unit.php',$data);
    }

    function submitProperty()
    {
        //get_configuraion_settings
        $config_settings = $this->configuration->get_config_systemsettings();   

        //Genrate Property Code
        $max_id = $this->property_model->get_max_property_id();
        if(!$max_id)
            $max_id = 1;
        
        $property_code = 'PR'.date('Y').date('m').($max_id+1);

        $action = checkApproveLevel('pr_rent_propertymain',"PENDING");
        $status = "PENDING";
        $confirm_officer = '';
        $check_officer = '';
        if($action == "CHECK")
        {
            $confirm_officer = $this->input->post('confirm_officer');
            $check_officer = $this->input->post('check_officer');
        }
        elseif($action == "CONFIRM")
        {
            $confirm_officer = $this->input->post('confirm_officer');
        }
        else
        {
            $status = $action;
        }

        $property_type_data = $this->common_model->getData('proptype_id', $this->input->post('property_type'), 'pr_config_proptype', '', '', '', '', '', 'yes');
        

        if($property_type_data->has_units == "1")
            $no_of_units = $this->input->post('unitcount');
        else
            $no_of_units = 1;

        $property_type =  $this->input->post('property_type');
        $property_name = $this->input->post('property_name');
        $address1 = $this->input->post('address1');
        $address2 = $this->input->post('address2');
        $city = $this->input->post('city');
        $branch = $this->input->post('branch');
        $description = $this->input->post('description');
        $state = $this->input->post('state');
        $country = $this->input->post('country');
        $manager = $this->input->post('manager');
        $email = $this->input->post('email');
        $fax = $this->input->post('fax');
        $tel = $this->input->post('tel');
        $mobile = $this->input->post('mobile');
        $web_address = $this->input->post('web_address');
        $location_map = $this->input->post('location_map');
        $tot_parkings = $this->input->post('tot_parkings');

        //Upload Process of Main Image
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
                redirect('pr/property');
                return;
            }
        }
        //End of Main Image Upload Process


        $property_data = array(
            'property_code'=>$property_code,
            'property_name'=>$property_name,
            'unit_count'=> $no_of_units,
            'prop_address1'=>$address1,
            'prop_adress2'=>$address2,
            'city'=>$city,
            'property_type'=>$property_type,
            'mobile'=>$mobile,
            'tel'=>$tel,
            'fax'=>$fax,
            'email'=>$email,
            'country'=>$country,
            'state'=>$state,
            'branch_code'=>$branch,
            'property_details'=>$description,
            'web_address'=>$web_address,
            'create_by'=>$this->session->userdata('username'),
            'create_date'=>date("Y-m-d"),
            'owner_type'=>'COMPANY',
            'manager'=>$manager,
            'location_map'=>$location_map,
            'status'=> $action,
            'check_by'=>$check_officer,
            'confirm_by'=>$confirm_officer,
            'main_img'=>$file_name,
            'parkings'=>$tot_parkings,

        );

        $pr_id = $this->common_model->insertData($property_data,'pr_rent_propertymain');

        //Upload Process of Other Images

		//now we move files from temp folder to unique folder
		if ($this->input->post('file_array')){
            $image_count = 1;

            $path = 'uploads/'.getUniqueID().'/'; //common helper function

			$files = explode(',', $this->input->post('file_array')); //create an array from files
			
			//move files
			foreach($files as $raw){
                if($config_settings->max_prop_images < $image_count)
                    break;
				if (file_exists($path)){
                    if (file_exists('uploads/temp/'.$raw)) {
                        rename('./uploads/temp/'.$raw, './uploads/'.getUniqueID().'/'.$raw);
                    }
                    if (file_exists('uploads/temp/thumbnail/'.$raw)) {
                        rename('./uploads/temp/thumbnail/'.$raw, './uploads/'.getUniqueID().'/thumbnail/'.$raw);
                    }
					$this->property_model->add_property_images($pr_id,$raw);
				}

                $image_count++;
			}
		}
        //End of Upload Process

        if($property_type_data->has_units == "1")
        {
            $count = 1;
            while($count <= $no_of_units)
            {
                $unit_no = $this->input->post('unit'.$count);
                $unit_name = $this->input->post('unitname'.$count);
                $floors = $this->input->post('floors'.$count);
                $parkings = $this->input->post('parkings'.$count);
                $bedrooms = $this->input->post('rooms'.$count);
                $bathrooms = $this->input->post('baths'.$count);
                $sqft = $this->input->post('sqft'.$count);

                //Upload Process of Unit Main Image
                $file_name = '';
                if (isset($_FILES['fileUploadUnit'.$count]['name']) && $_FILES['fileUploadUnit'.$count]['error'] == 0) {
                    $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                    $config['allowed_types'] = 'jpg|png'; 
                    $config['max_size']      = 1024;
                    $this->load->library('upload', $config);
                    if ( $this->upload->do_upload('fileUploadUnit'.$count)) {
                        $uploadedImage = $this->upload->data();
                        $file_name = $uploadedImage['file_name'];
                        $source_path = $path. $file_name;
                        resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                    }else{
                        $this->session->set_flashdata('error', 'Unit image size is larger than 1MB.');
                        redirect('pr/property');
                        return;
                    }
                }
                //End of Unit Main Image Upload Process
    
                $unit_data = array(
                    'unit_name'=>$unit_name,
                    'numof_floors'=>$floors,
                    'numof_parkinspace'=>$parkings,
                   'property_id'=>$pr_id,
                    'numof_bedrooms'=>$bedrooms,
                    'numof_bathrooms'=>$bathrooms,
                    'squir_feet'=>$sqft,
                    'unit_main_img'=>$file_name
                );
    
                $unit_id = $this->common_model->insertData($unit_data,'pr_rent_propertyunit');

                //Upload Process of Unit Other Images
                if ($this->input->post('file_array_unit'.$count)){
                    $image_count = 1;
                    $path = 'uploads/'.getUniqueID().'/'; //common helper function

                    $files = explode(',', $this->input->post('file_array_unit'.$count)); //create an array from files
                    
                    //move files
                    foreach($files as $raw){

                        if($config_settings->max_unit_images < $image_count)
                            break;

                        if (file_exists($path)){
                            if (file_exists('uploads/temp/'.$raw)) {
                                rename('./uploads/temp/'.$raw, './uploads/'.getUniqueID().'/'.$raw);
                            }
                            if (file_exists('uploads/temp/thumbnail/'.$raw)) {
                                rename('./uploads/temp/thumbnail/'.$raw, './uploads/'.getUniqueID().'/thumbnail/'.$raw);
                            }
                            $this->property_model->add_unit_images($unit_id,$raw);
                        }

                        $image_count++;
                    }
                }
                //End of Upload Process
    
                $count += 1;
            }
        }
        else
        {   
            $unit_name = $this->input->post('unitname0');
            $floors = $this->input->post('floors0');
            $parkings = $this->input->post('parkings0');
            $bedrooms = $this->input->post('rooms0');
            $bathrooms = $this->input->post('baths0');
            $sqft = $this->input->post('sqft0');

            //Upload Process of Unit Main Image
            $file_name = '';
            if (isset($_FILES['fileUploadUnit0']['name']) && $_FILES['fileUploadUnit0']['error'] == 0) {
                $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                $config['allowed_types'] = 'jpg|png'; 
                $config['max_size']      = 1024;
                $this->load->library('upload', $config);
                if ( $this->upload->do_upload('fileUploadUnit0')) {
                    $uploadedImage = $this->upload->data();
                    $file_name = $uploadedImage['file_name'];
                    $source_path = $path. $file_name;
                    resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                }else{
                     $this->session->set_flashdata('error', 'Unit image size is larger than 1MB.');
                    redirect('pr/property');
                    return;
                }
            }
            //End of Unit Main Image Upload Process           

            $unit_data = array(
                'unit_name'=>$unit_name,
                'numof_floors'=>$floors,
                'numof_parkinspace'=>$parkings,
                'property_id'=>$pr_id,
                'numof_bedrooms'=>$bedrooms,
                'numof_bathrooms'=>$bathrooms,
                'squir_feet'=>$sqft,
                'unit_main_img'=>$file_name
            );

            $unit_id = $this->common_model->insertData($unit_data,'pr_rent_propertyunit');

             //Upload Process of Unit Other Images
           
             if ($this->input->post('file_array_unit0')){

                $image_count = 1;
                
                $path = 'uploads/'.getUniqueID().'/'; //common helper function

                $files = explode(',', $this->input->post('file_array_unit0')); //create an array from files
                
                //move files
                foreach($files as $raw){
                    if($config_settings->max_unit_images < $image_count)
                        break;
                    if (file_exists($path)){
                        if (file_exists('uploads/temp/'.$raw)) {
                            rename('./uploads/temp/'.$raw, './uploads/'.getUniqueID().'/'.$raw);
                        }
                        if (file_exists('uploads/temp/thumbnail/'.$raw)) {
                            rename('./uploads/temp/thumbnail/'.$raw, './uploads/'.getUniqueID().'/thumbnail/'.$raw);
                        }
                        $this->property_model->add_unit_images($unit_id,$raw);
                    }

                    $image_count++;
                }
            }
            //End of Upload Process
        }

        if($action == "CHECK")
        {
            $this->common_model->add_notification_officer('pr_rent_propertymain','Property need to check','pr/property/',$pr_id,$check_officer);
        }
        elseif($action == "CONFIRM")
        {
            $this->common_model->add_notification_officer('pr_rent_propertymain','Property need to confirm','pr/property/',$pr_id,$confirm_officer);
        }
      
        redirect('property');
        return;
    }

    function get_property_details($purpose)
    {
        if ( ! check_access('view_property'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        $data['menu_name'] = 'Rental';
		$data['submenu_name'] = 'Properties';
        $data['purpose'] = $purpose;
        $unit_images = null;

        $property_id = $this->input->post('id');

        $data['property_types'] = $this->common_model->getData('*', '', 'pr_config_proptype', '', '', '', '', '');
        $data['property_data'] = $property_data = $this->common_model->getData('property_id',$property_id,'pr_rent_propertymain','','','','','','yes');
        $data['property_unit'] = $proerty_unit = $this->common_model->getData('property_id',$property_id,'pr_rent_propertyunit','','','','','');
        $data['branch_list'] = $this->common_model->getData('*', '', 'cm_branchms', '', '', '', '', '');
        $data['user_list'] = $this->common_model->getData('status','A','hr_empmastr','','','','','');
        $data['current_prop_type'] = $this->common_model->getData('proptype_id',$property_data->property_type,'pr_config_proptype','','','','','','yes');
        $data['check_officer_list'] = $this->common_model->get_privilage_officer_list('check_property');
        $data['confirm_officer_list'] = $this->common_model->get_privilage_officer_list('confirm_property');
        $data['property_images'] = $this->common_model->getData('proprety_id',$property_id,'pr_rent_propertyimages','','','','','');
        if($proerty_unit)
        {
            foreach($proerty_unit as $row)
            {
                $unit_images[$row->unit_id] = $this->common_model->getData('unit_id',$row->unit_id,'pr_rent_propertyunit_images','','','','','');
            }
        }
        
        $data['unit_images'] = $unit_images;

        $this->load->view('pr/rental/property/property_edit.php',$data);
        
    }

    function editProperty()
    {
        if ( ! check_access('update_property'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        $data['menu_name'] = 'Rental';
		$data['submenu_name'] = 'Properties';

        //get_configuraion_settings
        $config_settings = $this->configuration->get_config_systemsettings();   

        $property_id = $this->input->post('id');

        $property_type_data = $this->common_model->getData('proptype_id', $this->input->post('property_type'), 'pr_config_proptype', '', '', '', '', '', 'yes');

        $action = checkApproveLevel('pr_rent_propertymain',"PENDING");
        $confirm_officer = '';
        $check_officer = '';
        if($action == "CHECK")
        {
            $confirm_officer = $this->input->post('confirm_officer');
            $check_officer = $this->input->post('check_officer');
        }
        elseif($action == "CONFIRM")
        {   
            $confirm_officer = $this->input->post('confirm_officer');
        }
       

        if($property_type_data->has_units == "1")
            $no_of_units = $this->input->post('unitcount');
        else
            $no_of_units = 1;

        $property_type = $this->input->post('property_type');
        $property_name = $this->input->post('property_name');
        $address1 = $this->input->post('address1');
        $address2 = $this->input->post('address2');
        $city = $this->input->post('city');
        $branch = $this->input->post('branch');
        $description = $this->input->post('description');
        $state = $this->input->post('state');
        $country = $this->input->post('country');
        $manager = $this->input->post('manager');
        $email = $this->input->post('email');
        $fax = $this->input->post('fax');
        $tel = $this->input->post('tel');
        $mobile = $this->input->post('mobile');
        $web_address = $this->input->post('web_address');
        $location_map = $this->input->post('location_map');
        $tot_parkings = $this->input->post('tot_parkings');

        //Upload Process of Main Image
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
                redirect('pr/property');
                return;
            }

            $update_property = array(
                'property_name'=>$property_name,
                'unit_count'=> $no_of_units,
                'prop_address1'=>$address1,
                'prop_adress2'=>$address2,
                'city'=>$city,
                'property_type'=>$property_type,
                'mobile'=>$mobile,
                'tel'=>$tel,
                'fax'=>$fax,
                'email'=>$email,
                'country'=>$country,
                'state'=>$state,
                'branch_code'=>$branch,
                'property_details'=>$description,
                'web_address'=>$web_address,
                'create_by'=>$this->session->userdata('username'),
                'create_date'=>date("Y-m-d"),
                'owner_type'=>'COMPANY',
                'manager'=>$manager,
                'location_map'=>$location_map,
                'main_img'=>$file_name,
                'parkings'=>$tot_parkings,
                'check_by'=>$check_officer,
                'confirm_by'=>$confirm_officer,
            );
        }
        else
        {
            $update_property = array(
                'property_name'=>$property_name,
                'unit_count'=> $no_of_units,
                'prop_address1'=>$address1,
                'prop_adress2'=>$address2,
                'city'=>$city,
                'property_type'=>$property_type,
                'mobile'=>$mobile,
                'tel'=>$tel,
                'fax'=>$fax,
                'email'=>$email,
                'country'=>$country,
                'state'=>$state,
                'branch_code'=>$branch,
                'property_details'=>$description,
                'web_address'=>$web_address,
                'create_by'=>$this->session->userdata('username'),
                'create_date'=>date("Y-m-d"),
                'owner_type'=>'COMPANY',
                'manager'=>$manager,
                'location_map'=>$location_map,
                'parkings'=>$tot_parkings,
                'check_by'=>$check_officer,
                'confirm_by'=>$confirm_officer,
            );
        }
        //End of Main Image Upload Process

        $this->common_model->updateData($update_property,'property_id',$property_id,'pr_rent_propertymain');

        //Remove Current Images
        $current_property_images = $this->common_model->getData('proprety_id',$property_id,'pr_rent_propertyimages','','','','','');
        if($current_property_images)
        {
            foreach($current_property_images as $curr_img)
            {
                if($this->input->post('remove_prop_images'.$curr_img->image_id))
                {   
                    $path = 'uploads/'.getUniqueID().'/'.$curr_img->image_name; //common helper function
                    unlink($path);
                    $this->common_model->deleteData('image_id',$curr_img->image_id,'pr_rent_propertyimages');

                }
            }
        }

        $current_image_count = $this->common_model->getSearchCount('pr_rent_propertyimages',array('proprety_id'=>$property_id));
        $balance_images = $config_settings->max_prop_images -  $current_image_count;

        //Upload Process of Other Images

		//now we move files from temp folder to propoerty folder
		if ($this->input->post('file_array')){
            $image_count = 1;
            $path = 'uploads/'.getUniqueID().'/'; //common helper function

			$files = explode(',', $this->input->post('file_array')); //create an array from files
			
			//move files
			foreach($files as $raw){
                if($balance_images < $image_count)
                    break;
				if (file_exists($path)){
                    if (file_exists('uploads/temp/'.$raw)) {
                        rename('./uploads/temp/'.$raw, './uploads/'.getUniqueID().'/'.$raw);
                    }
                    if (file_exists('uploads/temp/thumbnail/'.$raw)) {
                        rename('./uploads/temp/thumbnail/'.$raw, './uploads/'.getUniqueID().'/thumbnail/'.$raw);
                    }
					$this->property_model->add_property_images($property_id,$raw);

                    $image_count++;
				}
			}
		}
        //End of Upload Process

        $remove_count = 0;
        if($property_type_data->has_units == "1")
        {   
            
            $count = 1;
            while($count <= $no_of_units)
            {   
                $unit_id = $this->input->post('unit_id'.$count);
                $unit_no = $this->input->post('unit'.$count);
                $unit_name = $this->input->post('unitname'.$count);
                $floors = $this->input->post('floors'.$count);
                $parkings = $this->input->post('parkings'.$count);
                $bedrooms = $this->input->post('rooms'.$count);
                $bathrooms = $this->input->post('baths'.$count);
                $sqft = $this->input->post('sqft'.$count);

                //Upload Process of Unit Main Image
                $file_name = '';
                if (isset($_FILES['fileUploadUnit'.$count]['name']) && $_FILES['fileUploadUnit'.$count]['error'] == 0) {
                    $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                    $config['allowed_types'] = 'jpg|png'; 
                    $config['max_size']      = 1024;
                    $this->load->library('upload', $config);
                    if ( $this->upload->do_upload('fileUploadUnit'.$count)) {
                        $uploadedImage = $this->upload->data();
                        $file_name = $uploadedImage['file_name'];
                        $source_path = $path. $file_name;
                        resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                    }else{
                        $this->session->set_flashdata('error', 'Unit image size is larger than 1MB.');
                        redirect('pr/property');
                        return;
                    }

                    $unit_data = array(
                        'property_id'=>$property_id,
                        'unit_name'=>$unit_name,
                        'numof_floors'=>$floors,
                        'numof_parkinspace'=>$parkings,
                        'numof_bedrooms'=>$bedrooms,
                        'numof_bathrooms'=>$bathrooms,
                        'squir_feet'=>$sqft,
                        'unit_main_img'=>$file_name
                    );
                }
                else
                {
                    $unit_data = array(
                        'property_id'=>$property_id,
                        'unit_name'=>$unit_name,
                        'numof_floors'=>$floors,
                        'numof_parkinspace'=>$parkings,
                        'numof_bedrooms'=>$bedrooms,
                        'numof_bathrooms'=>$bathrooms,
                        'squir_feet'=>$sqft,
                    );
                }
                //End of Unit Main Image Upload Process

               

                if($unit_id)
                {
                    if($this->input->post('reomveUnit'.$count))
                    {   
                        $this->common_model->deleteData('unit_id',$this->input->post('reomveUnit'.$count),'pr_rent_propertyunit');
                        $remove_count++;
                    }
                    else
                        $this->common_model->updateData($unit_data,'unit_id',$unit_id,'pr_rent_propertyunit');
                }
                else
                    $this->common_model->insertData($unit_data,'pr_rent_propertyunit');

                 //Remove process of Unit Other Images
                 $current_unit_images = $this->common_model->getData('unit_id',$unit_id,'pr_rent_propertyunit_images','','','','','');
                 if($current_unit_images)
                 {
                     foreach($current_unit_images as $curr_uni_img)
                     {
                         if($this->input->post('remove_unit_images'.$count.'_'.$curr_uni_img->id))
                         {   
                             $path = 'uploads/'.getUniqueID().'/'.$curr_uni_img->image_name; //common helper function
                             unlink($path);
                             $this->common_model->deleteData('id',$curr_uni_img->id,'pr_rent_propertyunit_images');
         
                         }
                     }
                 }
         
                 $current_unit_image_count = $this->common_model->getSearchCount('pr_rent_propertyunit_images',array('unit_id'=>$unit_id));
                 $balance_images = $config_settings->max_unit_images -  $current_unit_image_count;

                 //Upload Process of Unit Other Images
                 if ($this->input->post('file_array_unit'.$count)){
                    $image_count = 1;
                    $path = 'uploads/'.getUniqueID().'/'; //common helper function

                    $files = explode(',', $this->input->post('file_array_unit'.$count)); //create an array from files
                    
                    //move files
                    foreach($files as $raw){
                        if($balance_images < $image_count)
                            break;
                        if (file_exists($path)){
                            if (file_exists('uploads/temp/'.$raw)) {
                                rename('./uploads/temp/'.$raw, './uploads/'.getUniqueID().'/'.$raw);
                            }
                            if (file_exists('uploads/temp/thumbnail/'.$raw)) {
                                rename('./uploads/temp/thumbnail/'.$raw, './uploads/'.getUniqueID().'/thumbnail/'.$raw);
                            }
                            $this->property_model->add_unit_images($unit_id,$raw);

                            $image_count++;
                        }
                    }
                }
                //End of Upload Process

                $count += 1;
            }
        }
        else
        {
            $unit_id = $this->input->post('unit_id0');
            $unit_no = $this->input->post('unit0');
            $unit_name = $this->input->post('unitname0');
            $floors = $this->input->post('floors0');
            $parkings = $this->input->post('parkings0');
            $bedrooms = $this->input->post('rooms0');
            $bathrooms = $this->input->post('baths0');
            $sqft = $this->input->post('sqft0');

            //Upload Process of Unit Main Image
            $file_name = '';
            if (isset($_FILES['fileUploadUnit0']['name']) && $_FILES['fileUploadUnit0']['error'] == 0) {
                $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                $config['allowed_types'] = 'jpg|png'; 
                $config['max_size']      = 1024;
                $this->load->library('upload', $config);
                if ( $this->upload->do_upload('fileUploadUnit0')) {
                    $uploadedImage = $this->upload->data();
                    $file_name = $uploadedImage['file_name'];
                    $source_path = $path. $file_name;
                    resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                }else{
                     $this->session->set_flashdata('error', 'Unit image size is larger than 1MB.');
                    redirect('pr/property');
                    return;
                }

                $unit_data = array(
                    'property_id'=>$property_id,
                    'unit_name'=>$unit_name,
                    'numof_floors'=>$floors,
                    'numof_parkinspace'=>$parkings,
                    'numof_bedrooms'=>$bedrooms,
                    'numof_bathrooms'=>$bathrooms,
                    'squir_feet'=>$sqft,
                    'unit_main_img'=>$file_name
                );
            }
            else
            {
                $unit_data = array(
                    'property_id'=>$property_id,
                    'unit_name'=>$unit_name,
                    'numof_floors'=>$floors,
                    'numof_parkinspace'=>$parkings,
                    'numof_bedrooms'=>$bedrooms,
                    'numof_bathrooms'=>$bathrooms,
                    'squir_feet'=>$sqft,
                );
            }
            //End of Unit Main Image Upload Process  


            if($unit_id)
            {
                if($this->input->post('reomveUnit0'))
                {   
                    $this->common_model->deleteData('unit_id',$this->input->post('reomveUnit0'),'pr_rent_propertyunit');
                    $remove_count = 1;
                }
                else
                    $this->common_model->updateData($unit_data,'unit_id',$unit_id,'pr_rent_propertyunit');
            }
            else
                $this->common_model->insertData($unit_data,'pr_rent_propertyunit');

            //Remove process of Unit Other Images
            $current_unit_images = $this->common_model->getData('unit_id',$unit_id,'pr_rent_propertyunit_images','','','','','');
            if($current_unit_images)
            {
                foreach($current_unit_images as $curr_uni_img)
                {
                    if($this->input->post('remove_unit_images0_'.$curr_uni_img->id))
                    {   
                        $path = 'uploads/'.getUniqueID().'/'.$curr_uni_img->image_name; //common helper function
                        unlink($path);
                        $this->common_model->deleteData('id',$curr_uni_img->id,'pr_rent_propertyunit_images');
    
                    }
                }
            }
    
            $current_unit_image_count = $this->common_model->getSearchCount('pr_rent_propertyunit_images',array('unit_id'=>$unit_id));
            $balance_images = $config_settings->max_unit_images -  $current_unit_image_count;
            
            //Upload Process of Unit Other Images
             if ($this->input->post('file_array_unit0')){
                $image_count = 1;
                
                $path = 'uploads/'.getUniqueID().'/'; //common helper function

                $files = explode(',', $this->input->post('file_array_unit0')); //create an array from files
                
                //move files
                foreach($files as $raw){
                    if($balance_images < $image_count)
                        break;
                    if (file_exists($path)){
                        if (file_exists('uploads/temp/'.$raw)) {
                            rename('./uploads/temp/'.$raw, './uploads/'.getUniqueID().'/'.$raw);
                        }
                        if (file_exists('uploads/temp/thumbnail/'.$raw)) {
                            rename('./uploads/temp/thumbnail/'.$raw, './uploads/'.getUniqueID().'/thumbnail/'.$raw);
                        }
                        $this->property_model->add_unit_images($unit_id,$raw);

                        $image_count++;
                    }
                }
            }
            //End of Upload Process
        }

        $update_unit_count = array('unit_count'=>($no_of_units-$remove_count));
        $this->common_model->updateData($update_unit_count,'property_id',$property_id,'pr_rent_propertymain');

        $this->common_model->delete_notification('pr_rent_propertymain',$property_id);
        if($action == "CHECK")
        {   
            $this->common_model->add_notification_officer('pr_rent_propertymain','Property need to check','pr/property/',$property_id,$check_officer);
        }
        elseif($action == "CONFIRM")
        {
            $this->common_model->add_notification_officer('pr_rent_propertymain','Property need to confirm','pr/property/',$property_id,$confirm_officer);
        }

        redirect('property');
        return;

    }

    function unitImagesUpload()
    {
        $config['upload_path']          = './uploads/';
        $config['allowed_types']        = 'gif|jpg|png';
        $config['max_size']             = 100;
        $config['max_width']            = 1024;
        $config['max_height']           = 768;

        $this->load->library('upload', $config);

        if ( ! $this->upload->do_upload('file'))
        {
            $error = array('error' => $this->upload->display_errors());

            echo $error;
        }
        else
        {
            $data = array('upload_data' => $this->upload->data());

            echo 'ok';
        }

        // $data = [];
   
        // $count = count($_FILES['file']['name']);
      
        // for($i=0;$i<$count;$i++){
      
        //   if(!empty($_FILES['file']['name'][$i])){
      
        //     $_FILES['file']['name'] = $_FILES['file']['name'][$i];
        //     $_FILES['file']['type'] = $_FILES['file']['type'][$i];
        //     $_FILES['file']['tmp_name'] = $_FILES['file']['tmp_name'][$i];
        //     $_FILES['file']['error'] = $_FILES['file']['error'][$i];
        //     $_FILES['file']['size'] = $_FILES['file']['size'][$i];
    
        //     $config['upload_path'] = './uploads/'; 
        //     $config['allowed_types'] = 'jpg|jpeg|png|gif';
        //     $config['max_size'] = '5000';
        //     $config['file_name'] = $_FILES['file']['name'][$i];
     
        //     $this->load->library('upload',$config); 
      
        //     if($this->upload->do_upload('file')){
        //       $uploadData = $this->upload->data();
        //       $filename = $uploadData['file_name'];
     
        //       $data['totalFiles'][] = $filename;
        //     }
        //   }
     
        // }
     
        // echo"Ok"; 

        
    }

    function createPropertyTypeAJAX()
	{  
        $postdata = $_POST;
		$table = 'pr_config_proptype';
		$field = 'proptype_name';
		$value = $postdata['proptype_name'];
		if($this->common_model->checkExistance($table, $field, $value)){
			$this->session->set_flashdata('error', 'Duplicate property type name.');
			redirect('pr/property/addProperty');
			return;
		}
		$file_name = '';
		if (isset($_FILES['fileUploadp']['name']) && $_FILES['fileUploadp']['error'] == 0) {
			$config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
			$config['allowed_types'] = 'jpg|png'; 
			$config['max_size']      = 2048;
			$this->load->library('upload', $config);
			if ( $this->upload->do_upload('fileUploadp')) {
				$uploadedImage = $this->upload->data();
				$file_name = $uploadedImage['file_name'];
				$source_path = $path. $file_name;
				resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
			}
		}

		$data_array = array(
			'proptype_name' => $postdata['proptype_name'],
			'proptype_description' => $postdata['proptype_description'],
			'has_units' => $postdata['has_units'],
			'main_image' => $file_name,
			'create_date' =>date('Y-m-d'),
			'create_by' => $this->session->userdata('userid'),
            'status' => 'CONFIRMED'
		);
		$table = 'pr_config_proptype';
		if($returned_data = $this->common_model->insertDataReturn($data_array, $table,'proptype_id')){
			echo json_encode($returned_data);
		}
	}

    function check_property()
    {   
        if ( ! check_access('check_property'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        $data['menu_name'] = 'Rental';
		$data['submenu_name'] = 'Properties';

        //get_configuraion_settings
        $config_settings = $this->configuration->get_config_systemsettings();   

        $property_id = $this->input->post('id');
        $confirm_officer = $this->input->post('confirm_officer');

        $property_type_data = $this->common_model->getData('proptype_id', $this->input->post('property_type'), 'pr_config_proptype', '', '', '', '', '', 'yes');

        if($property_type_data->has_units == "1")
            $no_of_units = $this->input->post('unitcount');
        else
            $no_of_units = 1;

        $property_type = $this->input->post('property_type');
        $property_name = $this->input->post('property_name');
        $address1 = $this->input->post('address1');
        $address2 = $this->input->post('address2');
        $city = $this->input->post('city');
        $branch = $this->input->post('branch');
        $description = $this->input->post('description');
        $state = $this->input->post('state');
        $country = $this->input->post('country');
        $manager = $this->input->post('manager');
        $email = $this->input->post('email');
        $fax = $this->input->post('fax');
        $tel = $this->input->post('tel');
        $mobile = $this->input->post('mobile');
        $web_address = $this->input->post('web_address');
        $location_map = $this->input->post('location_map');
        $tot_parkings = $this->input->post('tot_parkings');

        //Upload Process of Main Image
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
                redirect('pr/property');
                return;
            }

            $update_property = array(
                'property_name'=>$property_name,
                'unit_count'=> $no_of_units,
                'prop_address1'=>$address1,
                'prop_adress2'=>$address2,
                'city'=>$city,
                'property_type'=>$property_type,
                'mobile'=>$mobile,
                'tel'=>$tel,
                'fax'=>$fax,
                'email'=>$email,
                'country'=>$country,
                'state'=>$state,
                'branch_code'=>$branch,
                'property_details'=>$description,
                'web_address'=>$web_address,
                'create_by'=>$this->session->userdata('username'),
                'create_date'=>date("Y-m-d"),
                'owner_type'=>'COMPANY',
                'manager'=>$manager,
                'location_map'=>$location_map,
                'main_img'=>$file_name,
                'parkings'=>$tot_parkings,
                'status'=>'CONFIRM',
                'check_by'=>$this->session->userdata("userid"),
                'check_date'=>date("Y-m-d"),
                'confirm_by'=>$confirm_officer,
            );
        }
        else
        {
            $update_property = array(
                'property_name'=>$property_name,
                'unit_count'=> $no_of_units,
                'prop_address1'=>$address1,
                'prop_adress2'=>$address2,
                'city'=>$city,
                'property_type'=>$property_type,
                'mobile'=>$mobile,
                'tel'=>$tel,
                'fax'=>$fax,
                'email'=>$email,
                'country'=>$country,
                'state'=>$state,
                'branch_code'=>$branch,
                'property_details'=>$description,
                'web_address'=>$web_address,
                'create_by'=>$this->session->userdata('username'),
                'create_date'=>date("Y-m-d"),
                'owner_type'=>'COMPANY',
                'manager'=>$manager,
                'location_map'=>$location_map,
                'parkings'=>$tot_parkings,
                'status'=>'CONFIRM',
                'check_by'=>$this->session->userdata("userid"),
                'check_date'=>date("Y-m-d"),
                'confirm_by'=>$confirm_officer,
            );
        }
        //End of Main Image Upload Process

   
        $this->common_model->updateData($update_property,'property_id',$property_id,'pr_rent_propertymain');

         //Remove Current Images
         $current_property_images = $this->common_model->getData('proprety_id',$property_id,'pr_rent_propertyimages','','','','','');
         if($current_property_images)
         {
             foreach($current_property_images as $curr_img)
             {
                 if($this->input->post('remove_prop_images'.$curr_img->image_id))
                 {   
                     $path = 'uploads/'.getUniqueID().'/'.$curr_img->image_name; //common helper function
                     unlink($path);
                     $this->common_model->deleteData('image_id',$curr_img->image_id,'pr_rent_propertyimages');
 
                 }
             }
         }
 
         $current_image_count = $this->common_model->getSearchCount('pr_rent_propertyimages',array('proprety_id'=>$property_id));
         $balance_images = $config_settings->max_prop_images -  $current_image_count;
 
         //Upload Process of Other Images
 
         //now we move files from temp folder to propoerty folder
         if ($this->input->post('file_array')){
             $image_count = 1;
             $path = 'uploads/'.getUniqueID().'/'; //common helper function
 
             $files = explode(',', $this->input->post('file_array')); //create an array from files
             
             //move files
             foreach($files as $raw){
                 if($balance_images < $image_count)
                     break;
                 if (file_exists($path)){
                     if (file_exists('uploads/temp/'.$raw)) {
                         rename('./uploads/temp/'.$raw, './uploads/'.getUniqueID().'/'.$raw);
                     }
                     if (file_exists('uploads/temp/thumbnail/'.$raw)) {
                         rename('./uploads/temp/thumbnail/'.$raw, './uploads/'.getUniqueID().'/thumbnail/'.$raw);
                     }
                     $this->property_model->add_property_images($property_id,$raw);
 
                     $image_count++;
                 }
             }
         }
         //End of Upload Process

        $remove_count = 0;
        if($property_type_data->has_units == "1")
        {   
            
            $count = 1;
            while($count <= $no_of_units)
            {   
                $unit_id = $this->input->post('unit_id'.$count);
                $unit_no = $this->input->post('unit'.$count);
                $unit_name = $this->input->post('unitname'.$count);
                $floors = $this->input->post('floors'.$count);
                $parkings = $this->input->post('parkings'.$count);
                $bedrooms = $this->input->post('rooms'.$count);
                $bathrooms = $this->input->post('baths'.$count);
                $sqft = $this->input->post('sqft'.$count);

                //Upload Process of Unit Main Image
                $file_name = '';
                if (isset($_FILES['fileUploadUnit'.$count]['name']) && $_FILES['fileUploadUnit'.$count]['error'] == 0) {
                    $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                    $config['allowed_types'] = 'jpg|png'; 
                    $config['max_size']      = 1024;
                    $this->load->library('upload', $config);
                    if ( $this->upload->do_upload('fileUploadUnit'.$count)) {
                        $uploadedImage = $this->upload->data();
                        $file_name = $uploadedImage['file_name'];
                        $source_path = $path. $file_name;
                        resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                    }else{
                        $this->session->set_flashdata('error', 'Unit image size is larger than 1MB.');
                        redirect('pr/property');
                        return;
                    }

                    $unit_data = array(
                        'property_id'=>$property_id,
                        'unit_name'=>$unit_name,
                        'numof_floors'=>$floors,
                        'numof_parkinspace'=>$parkings,
                        'numof_bedrooms'=>$bedrooms,
                        'numof_bathrooms'=>$bathrooms,
                        'squir_feet'=>$sqft,
                        'unit_main_img'=>$file_name
                    );
                }
                else
                {
                    $unit_data = array(
                        'property_id'=>$property_id,
                        'unit_name'=>$unit_name,
                        'numof_floors'=>$floors,
                        'numof_parkinspace'=>$parkings,
                        'numof_bedrooms'=>$bedrooms,
                        'numof_bathrooms'=>$bathrooms,
                        'squir_feet'=>$sqft,
                    );
                }
                //End of Unit Main Image Upload Process

                if($unit_id)
                {
                    if($this->input->post('reomveUnit'.$count))
                    {   
                        $this->common_model->deleteData('unit_id',$this->input->post('reomveUnit'.$count),'pr_rent_propertyunit');
                        $remove_count++;
                    }
                    else
                        $this->common_model->updateData($unit_data,'unit_id',$unit_id,'pr_rent_propertyunit');
                }
                else
                    $this->common_model->insertData($unit_data,'pr_rent_propertyunit');

                
               //Remove process of Unit Other Images
               $current_unit_images = $this->common_model->getData('unit_id',$unit_id,'pr_rent_propertyunit_images','','','','','');
               if($current_unit_images)
               {
                   foreach($current_unit_images as $curr_uni_img)
                   {
                       if($this->input->post('remove_unit_images'.$count.'_'.$curr_uni_img->id))
                       {   
                           $path = 'uploads/'.getUniqueID().'/'.$curr_uni_img->image_name; //common helper function
                           unlink($path);
                           $this->common_model->deleteData('id',$curr_uni_img->id,'pr_rent_propertyunit_images');
       
                       }
                   }
               }
       
               $current_unit_image_count = $this->common_model->getSearchCount('pr_rent_propertyunit_images',array('unit_id'=>$unit_id));
               $balance_images = $config_settings->max_unit_images -  $current_unit_image_count;

               //Upload Process of Unit Other Images
               if ($this->input->post('file_array_unit'.$count)){
                  $image_count = 1;
                  $path = 'uploads/'.getUniqueID().'/'; //common helper function

                  $files = explode(',', $this->input->post('file_array_unit'.$count)); //create an array from files
                  
                  //move files
                  foreach($files as $raw){
                      if($balance_images < $image_count)
                          break;
                      if (file_exists($path)){
                          if (file_exists('uploads/temp/'.$raw)) {
                              rename('./uploads/temp/'.$raw, './uploads/'.getUniqueID().'/'.$raw);
                          }
                          if (file_exists('uploads/temp/thumbnail/'.$raw)) {
                              rename('./uploads/temp/thumbnail/'.$raw, './uploads/'.getUniqueID().'/thumbnail/'.$raw);
                          }
                          $this->property_model->add_unit_images($unit_id,$raw);

                          $image_count++;
                      }
                  }
              }
              //End of Upload Process

                $count += 1;
            }
        }
        else
        {
            $unit_id = $this->input->post('unit_id0');
            $unit_no = $this->input->post('unit0');
            $unit_name = $this->input->post('unitname0');
            $floors = $this->input->post('floors0');
            $parkings = $this->input->post('parkings0');
            $bedrooms = $this->input->post('rooms0');
            $bathrooms = $this->input->post('baths0');
            $sqft = $this->input->post('sqft0');

            //Upload Process of Unit Main Image
            $file_name = '';
            if (isset($_FILES['fileUploadUnit0']['name']) && $_FILES['fileUploadUnit0']['error'] == 0) {
                $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                $config['allowed_types'] = 'jpg|png'; 
                $config['max_size']      = 1024;
                $this->load->library('upload', $config);
                if ( $this->upload->do_upload('fileUploadUnit0')) {
                    $uploadedImage = $this->upload->data();
                    $file_name = $uploadedImage['file_name'];
                    $source_path = $path. $file_name;
                    resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                }else{
                     $this->session->set_flashdata('error', 'Unit image size is larger than 1MB.');
                    redirect('pr/property');
                    return;
                }

                $unit_data = array(
                    'property_id'=>$property_id,
                    'unit_name'=>$unit_name,
                    'numof_floors'=>$floors,
                    'numof_parkinspace'=>$parkings,
                    'numof_bedrooms'=>$bedrooms,
                    'numof_bathrooms'=>$bathrooms,
                    'squir_feet'=>$sqft,
                    'unit_main_img'=>$file_name
                );
            }
            else
            {
                $unit_data = array(
                    'property_id'=>$property_id,
                    'unit_name'=>$unit_name,
                    'numof_floors'=>$floors,
                    'numof_parkinspace'=>$parkings,
                    'numof_bedrooms'=>$bedrooms,
                    'numof_bathrooms'=>$bathrooms,
                    'squir_feet'=>$sqft,
                );
            }
            //End of Unit Main Image Upload Process  

            if($unit_id)
            {
                if($this->input->post('reomveUnit0'))
                {   
                    $this->common_model->deleteData('unit_id',$this->input->post('reomveUnit0'),'pr_rent_propertyunit');
                    $remove_count = 1;
                }
                else
                    $this->common_model->updateData($unit_data,'unit_id',$unit_id,'pr_rent_propertyunit');
            }
            else
                $this->common_model->insertData($unit_data,'pr_rent_propertyunit');

             //Remove process of Unit Other Images
             $current_unit_images = $this->common_model->getData('unit_id',$unit_id,'pr_rent_propertyunit_images','','','','','');
             if($current_unit_images)
             {
                 foreach($current_unit_images as $curr_uni_img)
                 {
                     if($this->input->post('remove_unit_images0_'.$curr_uni_img->id))
                     {   
                         $path = 'uploads/'.getUniqueID().'/'.$curr_uni_img->image_name; //common helper function
                         unlink($path);
                         $this->common_model->deleteData('id',$curr_uni_img->id,'pr_rent_propertyunit_images');
     
                     }
                 }
             }
     
             $current_unit_image_count = $this->common_model->getSearchCount('pr_rent_propertyunit_images',array('unit_id'=>$unit_id));
             $balance_images = $config_settings->max_unit_images -  $current_unit_image_count;
             
             //Upload Process of Unit Other Images
              if ($this->input->post('file_array_unit0')){
                 $image_count = 1;
                 
                 $path = 'uploads/'.getUniqueID().'/'; //common helper function
 
                 $files = explode(',', $this->input->post('file_array_unit0')); //create an array from files
                 
                 //move files
                 foreach($files as $raw){
                     if($balance_images < $image_count)
                         break;
                     if (file_exists($path)){
                         if (file_exists('uploads/temp/'.$raw)) {
                             rename('./uploads/temp/'.$raw, './uploads/'.getUniqueID().'/'.$raw);
                         }
                         if (file_exists('uploads/temp/thumbnail/'.$raw)) {
                             rename('./uploads/temp/thumbnail/'.$raw, './uploads/'.getUniqueID().'/thumbnail/'.$raw);
                         }
                         $this->property_model->add_unit_images($unit_id,$raw);
 
                         $image_count++;
                     }
                 }
             }
             //End of Upload Process
        }

        $update_unit_count = array('unit_count'=>($no_of_units-$remove_count));
        $this->common_model->updateData($update_unit_count,'property_id',$property_id,'pr_rent_propertymain');

        $this->common_model->update_notification('pr_rent_propertymain',$property_id,"READ");
        $this->common_model->add_notification_officer('pr_rent_propertymain','Property need to confirm','pr/property/',$property_id,$confirm_officer);
        $this->session->set_flashdata("msg","Property Successfully Checked!");

        redirect('property');
        return;
    }

    function confirm_property()
    {   
        if ( ! check_access('confirm_property'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        $data['menu_name'] = 'Rental';
		$data['submenu_name'] = 'Properties';

        //get_configuraion_settings
        $config_settings = $this->configuration->get_config_systemsettings();   

        $property_id = $this->input->post('id');

        $property_type_data = $this->common_model->getData('proptype_id', $this->input->post('property_type'), 'pr_config_proptype', '', '', '', '', '', 'yes');

        if($property_type_data->has_units == "1")
            $no_of_units = $this->input->post('unitcount');
        else
            $no_of_units = 1;

        $property_type = $this->input->post('property_type');
        $property_name = $this->input->post('property_name');
        $address1 = $this->input->post('address1');
        $address2 = $this->input->post('address2');
        $city = $this->input->post('city');
        $branch = $this->input->post('branch');
        $description = $this->input->post('description');
        $state = $this->input->post('state');
        $country = $this->input->post('country');
        $manager = $this->input->post('manager');
        $email = $this->input->post('email');
        $fax = $this->input->post('fax');
        $tel = $this->input->post('tel');
        $mobile = $this->input->post('mobile');
        $web_address = $this->input->post('web_address');
        $location_map = $this->input->post('location_map');
        $tot_parkings = $this->input->post('tot_parkings');

        //Upload Process of Main Image
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
                redirect('pr/property');
                return;
            }

            $update_property = array(
                'property_name'=>$property_name,
                'unit_count'=> $no_of_units,
                'prop_address1'=>$address1,
                'prop_adress2'=>$address2,
                'city'=>$city,
                'property_type'=>$property_type,
                'mobile'=>$mobile,
                'tel'=>$tel,
                'fax'=>$fax,
                'email'=>$email,
                'country'=>$country,
                'state'=>$state,
                'branch_code'=>$branch,
                'property_details'=>$description,
                'web_address'=>$web_address,
                'create_by'=>$this->session->userdata('username'),
                'create_date'=>date("Y-m-d"),
                'owner_type'=>'COMPANY',
                'manager'=>$manager,
                'location_map'=>$location_map,
                'main_img'=>$file_name,
                'parkings'=>$tot_parkings,
                'status'=>'CONFIRMED',
                'confirm_by'=>$this->session->userdata("userid"),
                'confirm_date'=>date("Y-m-d"),
    
            );
        }
        else
        {
            $update_property = array(
                'property_name'=>$property_name,
                'unit_count'=> $no_of_units,
                'prop_address1'=>$address1,
                'prop_adress2'=>$address2,
                'city'=>$city,
                'property_type'=>$property_type,
                'mobile'=>$mobile,
                'tel'=>$tel,
                'fax'=>$fax,
                'email'=>$email,
                'country'=>$country,
                'state'=>$state,
                'branch_code'=>$branch,
                'property_details'=>$description,
                'web_address'=>$web_address,
                'create_by'=>$this->session->userdata('username'),
                'create_date'=>date("Y-m-d"),
                'owner_type'=>'COMPANY',
                'manager'=>$manager,
                'location_map'=>$location_map,
                'parkings'=>$tot_parkings,
                'status'=>'CONFIRMED',
                'confirm_by'=>$this->session->userdata("userid"),
                'confirm_date'=>date("Y-m-d"),
    
            );
        }
        //End of Main Image Upload Process

   
        $this->common_model->updateData($update_property,'property_id',$property_id,'pr_rent_propertymain');

        //Remove Current Images
        $current_property_images = $this->common_model->getData('proprety_id',$property_id,'pr_rent_propertyimages','','','','','');
        if($current_property_images)
        {
            foreach($current_property_images as $curr_img)
            {
                if($this->input->post('remove_prop_images'.$curr_img->image_id))
                {   
                    $path = 'uploads/'.getUniqueID().'/'.$curr_img->image_name; //common helper function
                    unlink($path);
                    $this->common_model->deleteData('image_id',$curr_img->image_id,'pr_rent_propertyimages');

                }
            }
        }

        $current_image_count = $this->common_model->getSearchCount('pr_rent_propertyimages',array('proprety_id'=>$property_id));
        $balance_images = $config_settings->max_prop_images -  $current_image_count;

        //Upload Process of Other Images

		//now we move files from temp folder to propoerty folder
		if ($this->input->post('file_array')){
            $image_count = 1;
            $path = 'uploads/'.getUniqueID().'/'; //common helper function

			$files = explode(',', $this->input->post('file_array')); //create an array from files
			
			//move files
			foreach($files as $raw){
                if($balance_images < $image_count)
                    break;
				if (file_exists($path)){
                    if (file_exists('uploads/temp/'.$raw)) {
                        rename('./uploads/temp/'.$raw, './uploads/'.getUniqueID().'/'.$raw);
                    }
                    if (file_exists('uploads/temp/thumbnail/'.$raw)) {
                        rename('./uploads/temp/thumbnail/'.$raw, './uploads/'.getUniqueID().'/thumbnail/'.$raw);
                    }
					$this->property_model->add_property_images($property_id,$raw);

                    $image_count++;
				}
			}
		}
        //End of Upload Process

        $remove_count = 0;
        if($property_type_data->has_units == "1")
        {   
            
            $count = 1;
            while($count <= $no_of_units)
            {   
                $unit_id = $this->input->post('unit_id'.$count);
                $unit_no = $this->input->post('unit'.$count);
                $unit_name = $this->input->post('unitname'.$count);
                $floors = $this->input->post('floors'.$count);
                $parkings = $this->input->post('parkings'.$count);
                $bedrooms = $this->input->post('rooms'.$count);
                $bathrooms = $this->input->post('baths'.$count);
                $sqft = $this->input->post('sqft'.$count);

                //Upload Process of Unit Main Image
                $file_name = '';
                if (isset($_FILES['fileUploadUnit'.$count]['name']) && $_FILES['fileUploadUnit'.$count]['error'] == 0) {
                    $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                    $config['allowed_types'] = 'jpg|png'; 
                    $config['max_size']      = 1024;
                    $this->load->library('upload', $config);
                    if ( $this->upload->do_upload('fileUploadUnit'.$count)) {
                        $uploadedImage = $this->upload->data();
                        $file_name = $uploadedImage['file_name'];
                        $source_path = $path. $file_name;
                        resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                    }else{
                        $this->session->set_flashdata('error', 'Unit image size is larger than 1MB.');
                        redirect('pr/property');
                        return;
                    }

                    $unit_data = array(
                        'property_id'=>$property_id,
                        'unit_name'=>$unit_name,
                        'numof_floors'=>$floors,
                        'numof_parkinspace'=>$parkings,
                        'numof_bedrooms'=>$bedrooms,
                        'numof_bathrooms'=>$bathrooms,
                        'squir_feet'=>$sqft,
                        'unit_main_img'=>$file_name
                    );
                }
                else
                {
                    $unit_data = array(
                        'property_id'=>$property_id,
                        'unit_name'=>$unit_name,
                        'numof_floors'=>$floors,
                        'numof_parkinspace'=>$parkings,
                        'numof_bedrooms'=>$bedrooms,
                        'numof_bathrooms'=>$bathrooms,
                        'squir_feet'=>$sqft,
                    );
                }
                //End of Unit Main Image Upload Process


                if($unit_id)
                {
                    if($this->input->post('reomveUnit'.$count))
                    {   
                        $this->common_model->deleteData('unit_id',$this->input->post('reomveUnit'.$count),'pr_rent_propertyunit');
                        $remove_count++;
                    }
                    else
                        $this->common_model->updateData($unit_data,'unit_id',$unit_id,'pr_rent_propertyunit');
                }
                else
                    $this->common_model->insertData($unit_data,'pr_rent_propertyunit');

                
              //Remove process of Unit Other Images
              $current_unit_images = $this->common_model->getData('unit_id',$unit_id,'pr_rent_propertyunit_images','','','','','');
              if($current_unit_images)
              {
                  foreach($current_unit_images as $curr_uni_img)
                  {
                      if($this->input->post('remove_unit_images'.$count.'_'.$curr_uni_img->id))
                      {   
                          $path = 'uploads/'.getUniqueID().'/'.$curr_uni_img->image_name; //common helper function
                          unlink($path);
                          $this->common_model->deleteData('id',$curr_uni_img->id,'pr_rent_propertyunit_images');
      
                      }
                  }
              }
      
              $current_unit_image_count = $this->common_model->getSearchCount('pr_rent_propertyunit_images',array('unit_id'=>$unit_id));
              $balance_images = $config_settings->max_unit_images -  $current_unit_image_count;

              //Upload Process of Unit Other Images
              if ($this->input->post('file_array_unit'.$count)){
                 $image_count = 1;
                 $path = 'uploads/'.getUniqueID().'/'; //common helper function

                 $files = explode(',', $this->input->post('file_array_unit'.$count)); //create an array from files
                 
                 //move files
                 foreach($files as $raw){
                     if($balance_images < $image_count)
                         break;
                     if (file_exists($path)){
                         if (file_exists('uploads/temp/'.$raw)) {
                             rename('./uploads/temp/'.$raw, './uploads/'.getUniqueID().'/'.$raw);
                         }
                         if (file_exists('uploads/temp/thumbnail/'.$raw)) {
                             rename('./uploads/temp/thumbnail/'.$raw, './uploads/'.getUniqueID().'/thumbnail/'.$raw);
                         }
                         $this->property_model->add_unit_images($unit_id,$raw);

                         $image_count++;
                     }
                 }
             }
             //End of Upload Process
                $count += 1;
            }
        }
        else
        {
            $unit_id = $this->input->post('unit_id0');
            $unit_no = $this->input->post('unit0');
            $unit_name = $this->input->post('unitname0');
            $floors = $this->input->post('floors0');
            $parkings = $this->input->post('parkings0');
            $bedrooms = $this->input->post('rooms0');
            $bathrooms = $this->input->post('baths0');
            $sqft = $this->input->post('sqft0');

            //Upload Process of Unit Main Image
            $file_name = '';
            if (isset($_FILES['fileUploadUnit0']['name']) && $_FILES['fileUploadUnit0']['error'] == 0) {
                $config['upload_path'] = $path = './uploads/'.getUniqueID().'/'; //common helper function
                $config['allowed_types'] = 'jpg|png'; 
                $config['max_size']      = 1024;
                $this->load->library('upload', $config);
                if ( $this->upload->do_upload('fileUploadUnit0')) {
                    $uploadedImage = $this->upload->data();
                    $file_name = $uploadedImage['file_name'];
                    $source_path = $path. $file_name;
                    resizeImageMain($uploadedImage['file_name'],$source_path); //common helper function
                }else{
                     $this->session->set_flashdata('error', 'Unit image size is larger than 1MB.');
                    redirect('pr/property');
                    return;
                }

                $unit_data = array(
                    'property_id'=>$property_id,
                    'unit_name'=>$unit_name,
                    'numof_floors'=>$floors,
                    'numof_parkinspace'=>$parkings,
                    'numof_bedrooms'=>$bedrooms,
                    'numof_bathrooms'=>$bathrooms,
                    'squir_feet'=>$sqft,
                    'unit_main_img'=>$file_name
                );
            }
            else
            {
                $unit_data = array(
                    'property_id'=>$property_id,
                    'unit_name'=>$unit_name,
                    'numof_floors'=>$floors,
                    'numof_parkinspace'=>$parkings,
                    'numof_bedrooms'=>$bedrooms,
                    'numof_bathrooms'=>$bathrooms,
                    'squir_feet'=>$sqft,
                );
            }
            //End of Unit Main Image Upload Process  



            if($unit_id)
            {
                if($this->input->post('reomveUnit0'))
                {   
                    $this->common_model->deleteData('unit_id',$this->input->post('reomveUnit0'),'pr_rent_propertyunit');
                    $remove_count = 1;
                }
                else
                    $this->common_model->updateData($unit_data,'unit_id',$unit_id,'pr_rent_propertyunit');
            }
            else
                $this->common_model->insertData($unit_data,'pr_rent_propertyunit');

             //Remove process of Unit Other Images
             $current_unit_images = $this->common_model->getData('unit_id',$unit_id,'pr_rent_propertyunit_images','','','','','');
             if($current_unit_images)
             {
                 foreach($current_unit_images as $curr_uni_img)
                 {
                     if($this->input->post('remove_unit_images0_'.$curr_uni_img->id))
                     {   
                         $path = 'uploads/'.getUniqueID().'/'.$curr_uni_img->image_name; //common helper function
                         unlink($path);
                         $this->common_model->deleteData('id',$curr_uni_img->id,'pr_rent_propertyunit_images');
     
                     }
                 }
             }
     
             $current_unit_image_count = $this->common_model->getSearchCount('pr_rent_propertyunit_images',array('unit_id'=>$unit_id));
             $balance_images = $config_settings->max_unit_images -  $current_unit_image_count;
             
             //Upload Process of Unit Other Images
              if ($this->input->post('file_array_unit0')){
                 $image_count = 1;
                 
                 $path = 'uploads/'.getUniqueID().'/'; //common helper function
 
                 $files = explode(',', $this->input->post('file_array_unit0')); //create an array from files
                 
                 //move files
                 foreach($files as $raw){
                     if($balance_images < $image_count)
                         break;
                     if (file_exists($path)){
                         if (file_exists('uploads/temp/'.$raw)) {
                             rename('./uploads/temp/'.$raw, './uploads/'.getUniqueID().'/'.$raw);
                         }
                         if (file_exists('uploads/temp/thumbnail/'.$raw)) {
                             rename('./uploads/temp/thumbnail/'.$raw, './uploads/'.getUniqueID().'/thumbnail/'.$raw);
                         }
                         $this->property_model->add_unit_images($unit_id,$raw);
 
                         $image_count++;
                     }
                 }
             }
             //End of Upload Process
        }

        $update_unit_count = array('unit_count'=>($no_of_units-$remove_count));
        $this->common_model->updateData($update_unit_count,'property_id',$property_id,'pr_rent_propertymain');

        $this->common_model->update_notification('pr_rent_propertymain',$property_id,"READ");
        $this->session->set_flashdata("msg","Property Successfully Confirmed!");

        redirect('property');
        return;
    }

    function delete_property()
    {
        $pr_id = $this->input->post('id');
        $this->common_model->deleteData("property_id",$pr_id,"pr_rent_propertymain");
        $this->common_model->deleteData("property_id",$pr_id,"pr_rent_propertyunit");
        $this->session->set_flashdata("msg","Property Successfully Deleted!");

        echo '1';
    }

    function listings()
    {
        if ( ! check_access('view_listings'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }

        //Pagination Config
        $not_like = array(
            'status' => 'USED'
        );

        $config["base_url"] = base_url() . "pr/property/listings/";
		$config["total_rows"] = $this->common_model->getSearchCount( "pr_rent_listing", $like = '', $not_like);
		$config["per_page"] = RAW_COUNT;
		$config["uri_segment"] = 4;

		$this->pagination->initialize($config);
		$config = array();

		$page = ($this->uri->segment(4)) ? $this->uri->segment(4) : 0;
		$data["links"] = $this->pagination->create_links();
        //End of Pagination Config

        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Listings';

        $data['listed_units'] = $this->property_model->getAllListedUnits(RAW_COUNT,$page);

        //Initialize search details
        $propertyTypes = $this->common_model->getData('', '', 'pr_config_proptype', '', '', '', '', '');
        $propertyTypes_array = array();
        foreach($propertyTypes as $propertyType){
            $propertyTypes_array[$propertyType->proptype_id]['id'] = $propertyType->proptype_id;
            $propertyTypes_array[$propertyType->proptype_id]['name'] = $propertyType->proptype_name;
        }
        $data['propertyTypes'] = $propertyTypes_array;

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

        $this->load->view('pr/listings/listing_main.php',$data);
        return;
    }

    function list_unit()
    {   
        if ( ! check_access('add_listings'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }

        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Listings';

        $data['unit_id'] = $unit_id = $this->uri->segment(4);

        $data['unit_details'] = $this->property_model->getUnitbyId($unit_id);
        $data['unit_images'] =  $this->common_model->getData('unit_id',$unit_id,'pr_rent_propertyunit_images','','','','','');
        $data['user_list'] = $this->common_model->getData('status','A','hr_empmastr','','','','','');
        $data['agents'] = $this->common_model->getData('','','pr_agent','','','','','');
        $data['check_officer_list'] = $this->common_model->get_privilage_officer_list('check_listing');
        $data['confirm_officer_list'] = $this->common_model->get_privilage_officer_list('confirm_listing');

        $this->load->view('pr/listings/list_unit.php',$data);
        return;
        
    }

    function unlisted_units()
    {
        if ( ! check_access('add_listings'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }

        //Pagination Config
        $not_like = array(
            'status' => 'LISTED',
        );

        $config["base_url"] = base_url() . "pr/property/unlisted_units/";
		$config["total_rows"] = $this->common_model->getSearchCount( "pr_rent_propertyunit", $like = '', $not_like);
		$config["per_page"] = RAW_COUNT;
		$config["uri_segment"] = 4;

		$this->pagination->initialize($config);
		$config = array();

		$page = ($this->uri->segment(4)) ? $this->uri->segment(4) : 0;
		$data["links"] = $this->pagination->create_links();

        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Listings';
        //End of Pagination Config

        $data['unlisted_units'] = $this->property_model->getAllAvailableUnits(RAW_COUNT,$page);

        //Initialize search details
        $propertyTypes = $this->common_model->getData('', '', 'pr_config_proptype', '', '', '', '', '');
        $propertyTypes_array = array();
        foreach($propertyTypes as $propertyType){
            $propertyTypes_array[$propertyType->proptype_id]['id'] = $propertyType->proptype_id;
            $propertyTypes_array[$propertyType->proptype_id]['name'] = $propertyType->proptype_name;
        }
        $data['propertyTypes'] = $propertyTypes_array;

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

        $this->load->view('pr/listings/unlisted_main.php',$data);
        return;
    }

    function addListing()
    {
        if ( ! check_access('add_listings'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }

        $action = checkApproveLevel('pr_rent_listing',"PENDING");
        $status = "PENDING";
        $confirm_officer = '';
        $check_officer = '';
        if($action == "CHECK")
        {
            $confirm_officer = $this->input->post('confirm_officer');
            $check_officer = $this->input->post('check_officer');
        }
        elseif($action == "CONFIRM")
        {
            $confirm_officer = $this->input->post('confirm_officer');
        }
        else
        {
            $status = $action;
        }

        //Get Data
        $unit_id = $this->input->post("id");
        $rent_price = $this->input->post("rent");
        $available_from = $this->input->post("available");
        $agent_id = $this->input->post("contact");
        $deposit = $this->input->post("deposit");

        $data = array(
            'unit_id'=>$unit_id,
            'rent_price'=>$rent_price,
            'deposit'=>$deposit,
            'available_from'=>$available_from,
            'agent_id'=>$agent_id,
            'create_by'=>$this->session->userdata("userid"),
            'create_date'=>date('Y-m-d'),
            'status'=>$status,
            'confirm_by'=>$confirm_officer,
            'check_by'=>$check_officer

        );

        $id = $this->common_model->insertData($data,"pr_rent_listing");
        if($id)
        {   
            $update = array("status"=>"LISTED");
            $this->common_model->updateData($update,"unit_id",$unit_id,"pr_rent_propertyunit");
            $this->session->set_flashdata('msg', 'Listing Succsessfully.');
        }           
        else
            $this->session->set_flashdata('error', 'Something went wrong please try again!');

        if($action == "CHECK")
        {
            $this->common_model->add_notification_officer('pr_rent_listing','Listing need to check','pr/property/listings',$id,$check_officer);
        }
        elseif($action == "CONFIRM")
        {
            $this->common_model->add_notification_officer('pr_rent_listing','Listing need to confirm','pr/property/listings',$id,$confirm_officer);
        }
        
        redirect('listings');
    }

    function get_listing_details($val)
    {
        if ( ! check_access('view_listings'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }

        $data['list_id'] = $list_id = $this->input->post("id");
        $data['purpose'] = $val;

        $data['list_data'] = $list_data = $this->property_model->get_listing_data_by_id($list_id);
        $data['unit_images'] =  $this->common_model->getData('unit_id',$list_data->unit_id,'pr_rent_propertyunit_images','','','','','');
        $data['user_list'] = $this->common_model->getData('','','pr_agent','','','','','');
        $data['check_officer_list'] = $this->common_model->get_privilage_officer_list('check_listing');
        $data['confirm_officer_list'] = $this->common_model->get_privilage_officer_list('confirm_listing');

        $this->load->view('pr/listings/listing_edit.php',$data);
        return;
    }

    function updateListing()
    {
        if ( ! check_access('update_listings'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
         //Get Data
         $list_id = $this->input->post("id");
         $rent_price = $this->input->post("rent");
         $available_from = $this->input->post("available");
         $agent_id = $this->input->post("contact");
         $deposit = $this->input->post("deposit");

         $action = checkApproveLevel('pr_rent_listing',"PENDING");
         $confirm_officer = '';
         $check_officer = '';
         if($action == "CHECK")
         {
             $confirm_officer = $this->input->post('confirm_officer');
             $check_officer = $this->input->post('check_officer');
         }
         elseif($action == "CONFIRM")
         {   
             $confirm_officer = $this->input->post('confirm_officer');
         }
 
         $data = array(
             'rent_price'=>$rent_price,
             'available_from'=>$available_from,
             'agent_id'=>$agent_id,
             'deposit'=>$deposit,
 
         );

         $result = $this->common_model->updateData($data,"list_id",$list_id,"pr_rent_listing");
         if($result)  
             $this->session->set_flashdata('msg', 'Listing Succsessfully Updated!.');      
         else
             $this->session->set_flashdata('error', 'Something went wrong please try again!');

        $this->common_model->delete_notification('pr_rent_listing',$list_id);
        if($action == "CHECK")
        {
            $this->common_model->add_notification_officer('pr_rent_listing','Listing need to check','pr/property/listings',$list_id,$check_officer);
        }
        elseif($action == "CONFIRM")
        {
            $this->common_model->add_notification_officer('pr_rent_listing','Listing need to confirm','pr/property/listings',$list_id,$confirm_officer);
        }
         
         redirect('listings');
 
    }

    function check_listing()
    {
        if ( ! check_access('check_listing'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
         //Get Data
         $list_id = $this->input->post("id");
         $rent_price = $this->input->post("rent");
         $available_from = $this->input->post("available");
         $agent_id = $this->input->post("contact");
         $deposit = $this->input->post("deposit");
        
         $confirm_officer = $this->input->post('confirm_officer');

         $data = array(
             'rent_price'=>$rent_price,
             'available_from'=>$available_from,
             'agent_id'=>$agent_id,
             'deposit'=>$deposit,
             'check_by'=>$this->session->userdata("userid"),
             'check_date'=>date("Y-m-d"),
             'status'=>'CHECK',
             'confirm_by'=>$confirm_officer
 
         );

         $result = $this->common_model->updateData($data,"list_id",$list_id,"pr_rent_listing");
         if($result)  
             $this->session->set_flashdata('msg', 'Listing Succsessfully Checked!.');      
         else
             $this->session->set_flashdata('error', 'Something went wrong please try again!');
        
        $this->common_model->update_notification('pr_rent_listing',$list_id,"READ");
        $this->common_model->add_notification_officer('pr_rent_listing','Listing need to confirm','pr/property/listings',$list_id,$confirm_officer);
         
         redirect('listings');
    }

    function confirm_listing()
    {
        if ( ! check_access('confirm_listing'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
         //Get Data
         $list_id = $this->input->post("id");
         $rent_price = $this->input->post("rent");
         $available_from = $this->input->post("available");
         $agent_id = $this->input->post("contact");
         $deposit = $this->input->post("deposit");
 
         $data = array(
             'rent_price'=>$rent_price,
             'available_from'=>$available_from,
             'agent_id'=>$agent_id,
             'deposit'=>$deposit,
             'confirm_by'=>$this->session->userdata("userid"),
             'confirm_date'=>date("Y-m-d"),
             'status'=>'CONFIRMED'
 
         );

         $result = $this->common_model->updateData($data,"list_id",$list_id,"pr_rent_listing");
         if($result)  
             $this->session->set_flashdata('msg', 'Listing Succsessfully Confirmed!.');      
         else
             $this->session->set_flashdata('error', 'Something went wrong please try again!');
         
        $this->common_model->update_notification('pr_rent_listing',$list_id,"READ");

         redirect('listings');
    }

    function unlistUnit()
    {   
        if ( ! check_access('unlist_unit'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }

        $list_id = $this->input->post('id');
        $data = array("status"=>"UNLISTED");
        $listData = $this->common_model->getData("list_id",$list_id,"pr_rent_listing",'','','','','','yes');
        $this->common_model->updateData($data,"list_id",$list_id,"pr_rent_listing");
        $data = array("status"=>"PENDING");
        $this->common_model->updateData($data,"unit_id",$listData->unit_id,"pr_rent_propertyunit");
        $this->session->set_flashdata("msg","Unlisted Successfully!");

        echo '1';
    }

    function viewListing()
    {
        if ( ! check_access('view_listings'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }

        $data['list_id'] = $list_id  = $this->input->post("id");
        
        $data['list_data'] = $this->property_model->get_listing_data_by_id($list_id);

        $this->load->view('pr/listings/listing_view.php',$data);
        return;
    }

    function printListing()
    {
        $data['list_id'] = $list_id  = $this->uri->segment(4);
        
        $data['list_data'] = $this->property_model->get_listing_data_by_id($list_id);

        $this->load->view('pr/listings/listing_print.php',$data);
        return;
    }

    function checkPropertyTypehasUnits(){
        $id = $this->input->post('id');
        if($this->property_model->checkPropertyTypehasUnits($id)){
            echo '1';
        }else{
            echo '0';
        }
    }

    function release_unit()
    {
        if ( ! check_access('add_listings'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }

        //Pagination Config
        $like = array(
            'status' => 'USED'
        );

        $config["base_url"] = base_url() . "pr/property/release_unit/";
		$config["total_rows"] = $this->common_model->getSearchCount( "pr_rent_listing", $like, $not_like ='');
		$config["per_page"] = RAW_COUNT;
		$config["uri_segment"] = 4;

		$this->pagination->initialize($config);
		$config = array();

		$page = ($this->uri->segment(4)) ? $this->uri->segment(4) : 0;
		$data["links"] = $this->pagination->create_links();
        //End of Pagination Config

        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Listings';

        $data['release_units'] = $this->property_model->getUnitsForRelease(RAW_COUNT,$page);

        $properties = $this->property_model->getUnitsForRelease();
        $properties_array = array();
        if($properties)
        {
            foreach($properties as $property){
                $properties_array[$property->property_id]['id'] = $property->property_id;
                $properties_array[$property->property_id]['name'] = $property->property_name;
                $properties_array[$property->property_id]['units'][] = array(
                    'uid' => $property->unit_id,
                    'uname' => $property->unit_name
                );
            }
        }
        $data['properties'] = $properties_array;
        $this->load->view('pr/listings/release_unit.php',$data);
        return;
    }

    function releaseUnit()
    {
        $agreement_id = $this->input->post('id');
        $result = $this->property_model->releaseUnit($agreement_id);
        if($result)
            $this->session->set_flashdata('msg', 'Unit Released.');
        else
            $this->session->set_flashdata('error', 'Something Went Wrong Please Try Again.');
    }

    function searchReleaseUnit(){
        if ( ! check_access('view_listings'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Listings';
        $data['property'] = $agreement_code = $this->input->post('property');
        if($agreement_code == ''){
            $this->session->set_flashdata('error', 'You need to fill at least one field.');
            redirect('pr/property/release_unit/');
            return;
        }
        $data['release_units'] = $this->property_model->getSearchedReleaseUnits();
        $properties = $this->property_model->getUnitsForRelease();
        $properties_array = array();
        if($properties)
        {
            foreach($properties as $property){
                $properties_array[$property->property_id]['id'] = $property->property_id;
                $properties_array[$property->property_id]['name'] = $property->property_name;
                $properties_array[$property->property_id]['units'][] = array(
                    'uid' => $property->unit_id,
                    'uname' => $property->unit_name
                );
            }
        }
        $data['properties'] = $properties_array;
        $this->load->view('pr/listings/search_releaseUnit.php',$data);
        return;
    }

    function searchProperties(){
        if ( ! check_access('view_property'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        $data['menu_name'] = 'Rental';
		$data['submenu_name'] = 'Properties';
        $data['units_search'] = $units_search = $this->input->post('units_search');
        $data['property'] = $property = $this->input->post('property');
        $data['propertyType'] = $propertyType = $this->input->post('propertyType');
        if($units_search == '' && $property == '' && $propertyType == ''){
            $this->session->set_flashdata('error', 'You need to fill at least one field.');
            redirect('pr/property/');
            return;
        }
        $data['property_list'] = $this->property_model->searchPropertyData();

         //initialize search_details
         $properties = $this->common_model->getData('', '', 'pr_rent_propertymain', '', '', '', '', '');
         $properties_array = array();
         foreach($properties as $property){
             $properties_array[$property->property_id]['id'] = $property->property_id;
             $properties_array[$property->property_id]['name'] = $property->property_name;
         }
         $data['properties'] = $properties_array;
 
         $propertyTypes = $this->common_model->getData('', '', 'pr_config_proptype', '', '', '', '', '');
         $propertyTypes_array = array();
         foreach($propertyTypes as $propertyType){
             $propertyTypes_array[$propertyType->proptype_id]['id'] = $propertyType->proptype_id;
             $propertyTypes_array[$propertyType->proptype_id]['name'] = $propertyType->proptype_name;
         }
         $data['propertyTypes'] = $propertyTypes_array;

        $this->load->view('pr/rental/property/search_properties.php',$data);
        return;
    }

    function searchListings()
    {
        if ( ! check_access('view_listings'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Listings';
        $data['availableDate'] = $availableDate = $this->input->post('availableDate');
        $data['property'] = $property = $this->input->post('property');
        $data['propertyType'] = $propertyType = $this->input->post('propertyType');
        if($availableDate == '' && $property == '' && $propertyType == ''){
            $this->session->set_flashdata('error', 'You need to fill at least one field.');
            redirect('pr/property/listings');
            return;
        }
        $data['listed_units'] = $this->property_model->searchListedUnits();

        //Initialize search details
        $propertyTypes = $this->common_model->getData('', '', 'pr_config_proptype', '', '', '', '', '');
        $propertyTypes_array = array();
        foreach($propertyTypes as $propertyType){
            $propertyTypes_array[$propertyType->proptype_id]['id'] = $propertyType->proptype_id;
            $propertyTypes_array[$propertyType->proptype_id]['name'] = $propertyType->proptype_name;
        }
        $data['propertyTypes'] = $propertyTypes_array;

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

        $this->load->view('pr/listings/search_listings.php',$data);
        return;
    }

    function searchUnlisted()
    {
        if ( ! check_access('view_listings'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Listings';
        $data['property'] = $property = $this->input->post('property');
        $data['propertyType'] = $propertyType = $this->input->post('propertyType');
        if($property == '' && $propertyType == ''){
            $this->session->set_flashdata('error', 'You need to fill at least one field.');
            redirect('pr/property/unlisted_units');
            return;
        }
        $data['unlisted_units'] = $this->property_model->searchAvailableUnits();
        //Initialize search details
        $propertyTypes = $this->common_model->getData('', '', 'pr_config_proptype', '', '', '', '', '');
        $propertyTypes_array = array();
        foreach($propertyTypes as $propertyType){
            $propertyTypes_array[$propertyType->proptype_id]['id'] = $propertyType->proptype_id;
            $propertyTypes_array[$propertyType->proptype_id]['name'] = $propertyType->proptype_name;
        }
        $data['propertyTypes'] = $propertyTypes_array;

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

        $this->load->view('pr/listings/unlisted_search.php',$data);
        return;
    }

    function unit_summery($list_id)
    {   
        $data['menu_name'] = 'Leasing';
		$data['submenu_name'] = 'Listings';
        $data['list_data'] = $list_data = $this->property_model->get_listing_data_by_id($list_id);
        $data['unit_images'] =  $this->common_model->getData('unit_id',$list_data->unit_id,'pr_rent_propertyunit_images','','','','','');
        $this->load->view('pr/rental/property/unit_summery.php',$data);
    }
    
    
}
