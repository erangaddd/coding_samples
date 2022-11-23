<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Rentalreport extends CI_Controller {

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
        $this->load->model("pr/rental_model");
        $this->load->model("income_model");
		$this->load->library("pagination");
        $this->load->model("customer_model");
	$this->load->model("pr/rentalreport_model");
    }
	
	function rental_reports(){
		if ( ! check_access('view_accounting_reports'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
	    $data['properties_data'] = $this->rentalreport_model->getPropertyData();
		$data['agreements'] = $this->property_model->getRentPropertyUnits();
		$table = 'pr_config_renteepaytype';
		$data['pay_types'] = $this->common_model->getData(
                                    $column = '', 
                                    $id = '', 
                                    $table, 
                                    $like = '', 
                                    $not_like = '', 
                                    $row_count = '', 
                                    $page= '', 
                                    $order_by = ''
                                );
		$data['menu_name'] = 'Rental';
        $data['submenu_name'] = 'Rental Reports';
		$this->load->view('pr/rental/reports/rental_reports', $data);
	}

	function paymentreport()
	{
		$data['agreement_id']=$agreement_id=$this->input->post('agreement_id');
		$data['fromdate']=$fromdate=$this->input->post('fromdate');
		$data['todate']=$todate=$this->input->post('todate');
		$data['property_id']=$property_id=$this->input->post('property_id');
		$data['amount']=$amount=$this->input->post('amount');
		$data['paytype_id']=$paytype_id=$this->input->post('paytype_id');
		$data['reportdata'] = $this->rentalreport_model->get_chargepeyments($amount,$fromdate,$todate,$property_id,$agreement_id,$paytype_id);
		$this->load->view('pr/rental/reports/payment_report', $data);
	}

	function parkingReport(){
		$property_id = $this->input->post('property_id');
		$unit_id = $this->input->post('unit_id');
		$rego = $this->input->post('rego');
		$data['property'] = $this->property_model->getPropertybyId($property_id);
		$data['parkings'] = $this->rentalreport_model->getParkingSpacesByProperty($property_id, $unit_id, $rego);
		$this->load->view('pr/rental/reports/parking_spaces', $data);
	}
}
