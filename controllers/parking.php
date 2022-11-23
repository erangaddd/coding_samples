<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Parking extends CI_Controller {

	 function __construct() {
        parent::__construct();
		$this->is_logged_in();
		$this->load->model("pr/common_model");
        $this->load->model("pr/property_model");
        $this->load->model("pr/parking_model");
    }
	
	function index(){
        if ( ! check_access('view_parking_spaces'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        //initialize search_details
        $properties = $this->common_model->getData('', '', 'pr_rent_propertymain', '', '', '', '', '');
        $properties_array = array();
        foreach($properties as $property){
            $properties_array[$property->property_id]['id'] = $property->property_id;
            $properties_array[$property->property_id]['name'] = $property->property_name;
        }
        $data['properties'] = $properties_array;
        $data['menu_name'] = 'Rental';
        $data['submenu_name'] = 'Parking Spaces';
        $this->load->view('pr/parking/index', $data);
    }

    function getUnits(){
        $property_id = $this->input->post('property_id');
        $units = $this->common_model->getData('property_id', $property_id, 'pr_rent_propertyunit', '', '', '', '', '');
        if($units){
            foreach($units as $unit){
                echo '<option value="'.$unit->unit_id.'">'.$unit->unit_name.'</option>';
            }
        }else{
            exit;
        }
    }

    function getParkingSpaces(){
        $property_id = $this->input->post('property_id');
        $unit_id = $this->input->post('unit_id');
        if($parking_spaces =  $this->parking_model->getParkingSpaces($property_id, $unit_id)){
            echo '<hr>';
            foreach($parking_spaces as $parking_space){
                echo '<div class="row">';
                    echo '<div class="col-sm-2 mb-2 mt-3">';
                            echo '<input type="text" placeholder="Slot Number" readonly value="'.$parking_space->slot_number.'" id="slot'.$parking_space->id.'" name="slot'.$parking_space->id.'" class="form-control" />';
                        echo '</div>';    
                    echo '<div class="col-sm-3 mb-2 mt-3">';
                        echo '<input type="text" placeholder="Vehicle Registration" value="'.$parking_space->rego.'" id="parking'.$parking_space->id.'" name="parking'.$parking_space->id.'" class="form-control" />';
                    echo '</div>';
                    echo '<div class="col-sm-4 mt-2 mb-2">';
                        echo '<button type="button"';
                        if(!check_access('update_parking_spaces')){
                            echo ' disabled ';
                        }
                        echo ' class="btn btn-success" id="update'.$parking_space->id.'" onclick=update("'.$parking_space->id.'")>Update</button>';
                        echo '<button type="button"'; 
                        if(!check_access('delete_parking_spaces')){
                            echo ' disabled ';
                        }
                        echo ' onclick=deleteSpace("'.$parking_space->id.'") class="btn btn-danger">Delete</button>';
                    echo '</div>';
                echo '</div>';
            }
        }else{
            echo '<hr>';
            echo 'No parking spaces have been allocated.';
        }

    }

    function addParkingSpace(){
        if ( ! check_access('add_parking_spaces'))
        {
            echo 'You do not have permission to perform this action.';
            exit;
        }
        $property_id = $this->input->post('property_id');
        $unit_id = $this->input->post('unit_id');
        $rego = $this->input->post('rego');
        $slot_number = $this->input->post('slot_number');
        if($this->parking_model->checkRemaining($property_id, $unit_id)){
            echo 'The property has no remaining spaces.';
            exit;
        }
        if($data = $this->parking_model->checkDuplicates($rego)){
            echo 'This number has been assigned to '.$data->property_name.' - '.$data->unit_name.'/'.$data->slot_number.'.';
            exit;
        }
        if($data = $this->parking_model->checkSlot($property_id, $unit_id, $slot_number)){
            echo 'This slot has been assigned to '.$data->rego.'.';
            exit;
        }
        if($this->parking_model->addParkingSpaces($property_id, $unit_id, $rego, $slot_number)){
            echo '1';
            exit;
        }else{
            echo 'Something went wrong.';
            exit;
        }
    }

    function updateParkingSpace(){
        $parking_id = $this->input->post('parking_id');
        $rego = $this->input->post('rego');
        if($data = $this->parking_model->checkDuplicates($rego)){
            echo 'This number has been assigned to '.$data->property_name.' - '.$data->unit_name.'/'.$data->slot_number.'.';
            exit;
        }
        if($this->parking_model->updateParkingSpace($parking_id, $rego)){
            echo '1';
        }else{
            return false;
        }
    }

    function deleteParkingSpace(){
        $parking_id = $this->input->post('parking_id');
        if($this->parking_model->deleteParkingSpace($parking_id)){
            echo '1';
            exit;
        }else{
            echo 'Something went wrong.';
            exit;
        }
    }
}
