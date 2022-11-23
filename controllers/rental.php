<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

class Rental extends CI_Controller {

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
    }
	
	function moveIn(){
        $agreement_id = $this->encryption->decode($this->uri->segment('3'));
        if($this->rental_model->processInitialPayments($agreement_id)){
            $this->session->set_flashdata('msg', 'Successfully moved in.');
            redirect('leasing/agreements');
        }else{
            $this->session->set_flashdata('error', 'Unable to move in.');
            redirect('leasing/agreements');
        }
    }

    function chargeIncome(){
        if ( ! check_access('add_rentee_payments'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        //get properties with moved in agreements
        $data['properties'] = $this->property_model->getRentPropertyUnits();
		$data['menu_name'] = 'Rental';
        $data['submenu_name'] = 'Rentee Payments';
        $this->load->view('pr/rental/income/payments', $data);
    }

    function updateBills(){
        if ( ! check_access('update_rentee_bills'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('rental/chargeBills');
            return;
        }
        if($_POST){
            $bill_id = $this->input->post('bill_id');
            $amount = $this->input->post('amount');
            $bill_number = $this->input->post('bill_number');
            if($this->rental_model->updateBillAmount($bill_id, $amount, $bill_number)){
                $this->common_model->add_notification('pr_rent_charges_bills','Confirm rentee bill','rental/chargeBills',$bill_id,'confirm_rentee_bills');
                echo 'Updated';
                exit;
            }
        }
        //get properties with moved in agreements
        $data['properties'] = $this->property_model->getRentPropertyUnits();
		$data['menu_name'] = 'Rental';
        $data['submenu_name'] = 'Rentee Bills';
        $this->load->view('pr/rental/income/update_bills', $data);
    }

    function changeBillStatus(){
        $status = $this->uri->segment('3');
        $bill_id = $this->encryption->decode($this->uri->segment('4'));
        if($this->rental_model->changeBillStatus($bill_id, $status)){
            if($status == 'CONFIRMED'){
                $this->session->set_flashdata('msg', 'Successfully confirmed the bill.');
            }else{
                $this->session->set_flashdata('error', 'Successfully rejected the bill.');
            }
            redirect('rental/chargeBills');
        }else{
            $this->session->set_flashdata('error', 'Something when wrong.');
            redirect('rental/chargeBills');
        }
    }

    function chargeBills(){
        if ( ! check_access('view_rentee_bills'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        
        $this->session->unset_userdata('bill_agreement_id');
        $this->session->unset_userdata('bill_paytype_id');
        $this->session->unset_userdata('bill_status');
        $data['agreement_id'] = '';
        $data['paytype_id'] = '';
        $data['status'] = '';
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
        $config["base_url"] = base_url() . "rental/chargeBills";
        $config["total_rows"] = $count = $this->rental_model->countAllBillstoConfirm();
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        $this->pagination->initialize($config);
        $config = array();
        $data["links"] = $this->pagination->create_links();
        $data['bills'] =  $this->rental_model->getAllBillstoConfirm($page,RAW_COUNT);
        
        //get properties with moved in agreements
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
        $data['properties'] = $this->property_model->getRentPropertyUnits();
		$data['menu_name'] = 'Rental';
        $data['submenu_name'] = 'Rentee Bills';
        $this->load->view('pr/rental/income/bills', $data);
    }

    function searchChargeBills(){
        if($_POST){
            $data['agreement_id'] = $agreement_id = $this->input->post('agreement_id');
            $data['paytype_id'] = $paytype_id = $this->input->post('paytype_id');
            $data['status'] = $status = $this->input->post('status');
            $search_data = array(
                'bill_agreement_id'  => $agreement_id,
                'bill_paytype_id'     => $paytype_id,
                'bill_status' => $status
            );
            $this->session->set_userdata($search_data);
        }  
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
        $config["base_url"] = base_url() . "rental/searchChargeBills";
        $config["total_rows"] = $count = $this->rental_model->countBillByAgreement();
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        $this->pagination->initialize($config);
        $config = array();
        $data["links"] = $this->pagination->create_links();
        $data['bills'] = $this->rental_model->getBillByAgreement($page,RAW_COUNT);
        $data['properties'] = $this->property_model->getRentPropertyUnits();
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
        $data['submenu_name'] = 'Rentee Bills';
        $this->load->view('pr/rental/income/bills', $data);
    }

    function getBillToUpdate(){
        $agreement_id = $this->input->post('agreement_id');
        if($bills = $this->rental_model->getBillToUpdate($agreement_id)){
            $count = 0;
            foreach($bills as $bill){
                if(!$this->income_model->getBillPaidTotal($bill->bill_id)){
                    $count++;
                }
            }
            if($count > 0){
                echo '<table class="table">
                        <thead>
                        <tr>
                            <th>Bill Type/ Description</th>
                            <th>Due Date</th>
                            <th>Bill Number</th>
                            <th class="text-right">Bill Total ('.get_currency_symbol().')</th>
                            <th></th>
                        </tr>
                        </thead>
                        <tbody>';
                foreach($bills as $bill){
                    if(!$this->income_model->getBillPaidTotal($bill->bill_id)){
                        echo '<tr>
                                <td>'.$bill->paytype_name.'/ '.$bill->description.'<br><span class="success" id="success'.$bill->bill_id.'"></span></td>
                                <td>'.$bill->due_date.'</td>
                                <td><input type="text" class="form-control" id="bill_number'.$bill->bill_id.'" value="'.$bill->bill_number.'"</td>
                                <td><input type="text" class="form-control number-separator text-right" id="amount'.$bill->bill_id.'" value="'.$bill->bill_amount.'"</td>
                                <td><button type="button" id="update'.$bill->bill_id.'" class="btn btn-success" onClick=updateBillAmount("'.$bill->bill_id.'")> Update </button></td>
                            </tr>';
                    }
                }
                echo '</tbody></table>';
            }
        }
    }

    function income(){
        if ( ! check_access('add_income'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        $config["base_url"] = base_url() . "rental/income";
        $config["total_rows"] = $count = $this->rental_model->countPendingIncome();
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        $this->pagination->initialize($config);
        $config = array();
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
        $data["links"] = $this->pagination->create_links();
        $data['incomes'] = $this->rental_model->getPendingIncome($page,RAW_COUNT);
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
		$data['menu_name'] = 'Rental';
        $data['submenu_name'] = 'Income';
        $this->load->view('pr/rental/income/add', $data);
    }

    function getChargesByAgreementID(){
        $agreement_id = $this->input->post('agreement_id');
        $data['date'] = $this->input->post('date');
        $table = 'pr_rent_charges';
        $data['charge_types'] = $this->rental_model->getChargeTypesByAgreementID($agreement_id);
        //$data['charges'] = $this->rental_model->getChargesByAgreementID($agreement_id);
        $data['agreement_id'] = $agreement_id;
        $this->load->view('pr/rental/income/pending_payments', $data);
    }

    function addChargePayments(){
        if ( ! check_access('add_rentee_payments'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        if((int)$this->input->post('total') < 1){
            $this->session->set_flashdata('error', 'At least one field need to be filled.');
            redirect('rental/chargeIncome');
        }
        $agreement_id = $this->input->post('agreement_id');
        $charge_types = $this->rental_model->getChargeTypesByAgreementID($agreement_id);
        if($income_id = $this->rental_model->addChargePayments($charge_types)){
            $income_id = $this->encryption->encode($income_id);
            if ( check_access('create_receipts'))
            {
                $this->session->set_userdata('org_url', 'rental/chargeIncome');
                redirect('accounts/addReceipt/'.$income_id);
            }else{
                $this->session->set_flashdata('msg', 'Successfully entered the rentee payments.');
                redirect('rental/chargeIncome');
            }
        }else{
            $this->session->set_flashdata('error', 'Something went wrong.');
            redirect('rental/chargeIncome');
        }
    }

    function updateBillInterest(){
        $bill_id = $this->input->post('bill_id');
        $interest =  $this->input->post('interest');
        $this->rental_model->updateBillInterest($bill_id,$interest);
    }

    function getChargesTotalbyPayID(){
        $agreement_id = $this->input->post('agreement_id');
        $charge_id =  $this->input->post('charge_id');
        $bills = $this->rental_model->getBillsbyChargeID($agreement_id,$charge_id);
        $total = 0;
        foreach($bills as $bill){
            $payment = getPaidChargeAmount($bill->bill_id); //custom helper
            $balance = $bill->bill_amount - $payment;
            $total = $total + $balance;
            if($bill->bill_amount > $payment){
                $total = $total + $bill->delay_interest;
            }
        }
        echo $total;
    }

    function chargeTypes(){
        if ( ! check_access('view_rentee_charge'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('home');
            return;
        }
        $this->session->unset_userdata('charge_agreement_id');
        $this->session->unset_userdata('charge_paytype_id');
        $this->session->unset_userdata('charge_status');
        $data['agreement_id'] = '';
        $data['paytype_id'] = '';
        $data['status'] = '';
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
        $config["base_url"] = base_url() . "rental/chargeTypes";
        $config["total_rows"] = $count = $this->rental_model->countAllChargestoConfirm();
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        $this->pagination->initialize($config);
        $config = array();
        $data["links"] = $this->pagination->create_links();
        $data['charges'] =  $this->rental_model->getAllChargestoConfirm($page,RAW_COUNT);
        
        //get properties with moved in agreements
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
        $data['properties'] = $this->property_model->getRentPropertyUnits();
		$data['menu_name'] = 'Rental';
        $data['submenu_name'] = 'Rentee Charges';
        $this->load->view('pr/rental/charges/index', $data);
    }

    function searchCharges(){
        if($_POST){
            $data['agreement_id'] = $agreement_id = $this->input->post('agreement_id');
            $data['paytype_id'] = $paytype_id = $this->input->post('paytype_id');
            $data['status'] = $status = $this->input->post('status');
            $search_data = array(
                'charge_agreement_id'  => $agreement_id,
                'charge_paytype_id'     => $paytype_id,
                'charge_status' => $status
            );
            $this->session->set_userdata($search_data);
        }  
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
        $config["base_url"] = base_url() . "rental/searchCharges";
        $config["total_rows"] = $count = $this->rental_model->countChargesByAgreement();
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        $this->pagination->initialize($config);
        $config = array();
        $data["links"] = $this->pagination->create_links();
        $data['charges'] = $this->rental_model->getCharegsByAgreement($page,RAW_COUNT);
        $data['properties'] = $this->property_model->getRentPropertyUnits();
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
        $data['submenu_name'] = 'Rentee Charges';
        $this->load->view('pr/rental/charges/index', $data);
    }

    function addCharges(){
        if ( ! check_access('add_rentee_charge'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('rental/chargeTypes');
            return;
        }
        if($_POST){
            if($this->rental_model->addCharges()){
                $this->session->set_flashdata('msg', 'Successfully added the rentee charge.');
                redirect('rental/addCharges');
            }else{
                $this->session->set_flashdata('error', 'Unable to add the rentee charge.');
                redirect('rental/addCharges');
            }
        }
        //get properties with moved in agreements
        $data['properties'] = $this->property_model->getRentPropertyUnitsMovedIn();
        $table = 'pr_config_renteepaytype';
        $not_like = array('paytype_id' => '2');
        $not_in = 
        $data['pay_types'] = $this->common_model->getData(
            $column = '', 
            $id = '', 
            $table, 
            $like = '', 
            $not_like, 
            $row_count = '', 
            $page= '', 
            $order_by = '',
            $row_data = '',
            $not_in_row = 'status',
            $not_in = 'PENDING'
        );
		$data['menu_name'] = 'Rental';
        $data['submenu_name'] = 'Rentee Charges';
        $this->load->view('pr/rental/charges/add_charge', $data);
    }

    function changeStatus($status, $charge_id){
        $charge_id = $this->encryption->decode($charge_id);
        if($status == 'CHECK'){
            $state = 'checked';
            if ( ! check_access('check_rentee_charge'))
            {
                $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
                redirect('rental/chargeTypes');
                return;
            }
        }
        if($status == 'CONFIRM'){
            $state = 'confirmed';
            if ( ! check_access('confirm_rentee_charge'))
            {
                $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
                redirect('rental/chargeTypes');
                return;
            }
        }
        if($status == 'HOLD'){
            $state = 'holded';
            if ( ! check_access('hold_rentee_charge'))
            {
                $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
                redirect('rental/chargeTypes');
                return;
            }
        }
        if($status == 'RESUME'){
            $state = 'resumed';
            if ( ! check_access('resume_rentee_charge'))
            {
                $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
                redirect('rental/chargeTypes');
                return;
            }
        }
        if($this->rental_model->changeStatus($status, $charge_id)){
            $this->session->set_flashdata('msg', 'Successfully '.$state.' the rentee charge.');
            redirect('rental/chargeTypes');
        }else{
            $this->session->set_flashdata('error', 'Unable to '.strtolower($status).' the rentee charge.');
            redirect('rental/chargeTypes');
        }
    }

    function editCharge($charge_id){
        if ( ! check_access('edit_rentee_charge'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('rental/chargeTypes');
            return;
        }
        $charge_id = $this->encryption->decode($charge_id);
        //check status of the charge
        if ($this->rental_model->checkChargeStatus($charge_id))
        {
            $this->session->set_flashdata('error', 'You cannot edit a confirmed charge.');
            redirect('rental/chargeTypes');
            return;
        }

        if($_POST){
            if($this->rental_model->editCharge($charge_id)){
                $this->session->set_flashdata('msg', 'Successfully updated the rentee charge.');
                redirect('rental/chargeTypes');
            }else{
                $this->session->set_flashdata('error', 'Unable to update the rentee charge.');
                redirect('rental/chargeTypes');
            }
        }

        //get properties with moved in agreements
        $data['properties'] = $this->property_model->getRentPropertyUnits();
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
        $table = 'pr_rent_charges';
        $data['charge'] = $this->common_model->getData(
            $column = 'charge_id', 
            $id = $charge_id, 
            $table, 
            $like = '', 
            $not_like= '', 
            $row_count = '', 
            $page= '', 
            $order_by = '',
            $row_data = 'yes'
        );
		$data['menu_name'] = 'Rental';
        $data['submenu_name'] = 'Rentee Charges';
        $this->load->view('pr/rental/charges/edit_charge', $data);
    }

    function deleteCharge(){
        if ( ! check_access('delete_rentee_charge'))
        {
            echo 'You do not have permission to perform this action.';
            exit;
        }
        $charge_id = $this->input->post('id');
        //check status of the charge
        if ($this->rental_model->checkChargeStatus($charge_id))
        {
            echo 'You cannot delete a confirmed charge.';
            exit;
        }
        if($this->rental_model->deleteCharge($charge_id)){
            echo '1';
            exit;
        }else{
            echo 'Unable to delete the rentee charge.';
            exit;
        }
    }

    function updateCharges(){
        if ( ! check_access('update_confirmed_charges'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('rental/chargeTypes');
            return;
        }
        if($_POST){
            if($this->rental_model->updateCharges()){
                $this->session->set_flashdata('msg', 'Successfully updated the charge amount.');
                redirect('rental/updateCharges');
            }else{
                $this->session->set_flashdata('error', 'Unable to update the charge amount.');
                redirect('rental/updateCharges');
            }
        }
        
        $page = ($this->uri->segment(3)) ? $this->uri->segment(3) : 0;
        $config["base_url"] = base_url() . "rental/updateCharges";
        $config["total_rows"] = $count = $this->rental_model->countPendingChargeUpdates();
        $config["per_page"] = RAW_COUNT;
        $config["uri_segment"] = 3;
        $this->pagination->initialize($config);
        $config = array();
        $data["links"] = $this->pagination->create_links();
        $data['charge_updates'] =  $this->rental_model->getPendingChargeUpdates($page,RAW_COUNT);

        $data['properties'] = $this->property_model->getRentPropertyUnits();
		$data['menu_name'] = 'Rental';
        $data['submenu_name'] = 'Rentee Charges';
        $this->load->view('pr/rental/charges/update', $data);
    }

    function getUpdateChargesByAgreementID(){
        $agreement_id = $this->input->post('agreement_id');
        if($charges = $this->rental_model->getUpdateChargesByAgreementID($agreement_id)){
            foreach($charges as $charge){
                echo '<option value="'.$charge->charge_id.'">'.$charge->paytype_name.' - '.$charge->description.'</option>';
            }
        }else{
            exit;
        }
    }

    function getCurrentAmount(){
        $charge_id = $this->input->post('charge_id');
        $charge = $this->rental_model->getChargeByID($charge_id);
        echo $charge->default_amount;
    }

    function changeChargeUpdateStatus($status, $update_id){
        $update_id = $this->encryption->decode($update_id);
        if($status == 'REJECT'){
            if ( ! check_access('reject_charge_update'))
            {
                $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
                redirect('rental/updateCharges');
                return;
            }
            if($this->rental_model->rejectChargeAmountUpdate($update_id)){
                $this->session->set_flashdata('msg', 'Successfully rejected the charge amount update.');
                redirect('rental/updateCharges');
            }else{
                $this->session->set_flashdata('error', 'Unable to reject the charge amount update.');
                redirect('rental/updateCharges');
            }
        }else{
            if ( ! check_access('confirm_charge_update'))
            {
                $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
                redirect('rental/updateCharges');
                return;
            }
            if($this->rental_model->confirmChargeAmountUpdate($update_id)){
                $this->session->set_flashdata('msg', 'Successfully confirmed the charge amount update.');
                redirect('rental/updateCharges');
            }else{
                $this->session->set_flashdata('error', 'Unable to confirm the charge amount update.');
                redirect('rental/updateCharges');
            }
        }
    }

    function getRevisions(){
        if($revisions = $this->rental_model->getRevisions()){
            foreach($revisions as $revision){
                echo '<tr>';
                echo '<td class="text-right">'.number_format($revision->previous_amount,2).'</td>';
                echo '<td class="text-right">'.number_format($revision->new_amount,2).'</td>';
                echo '<td>'.getUsernamebyID($revision->update_by).'</td>';//custom helper
                echo '<td>'.$revision->update_date.'</td>';
                echo '<td>'.getUsernamebyID($revision->confirm_by).'</td>';//custom helper
                echo '<td>'.$revision->confirm_date.'</td>';
                echo '</tr>';
            }
        }else{
            echo '<tr><td colspan="6">No revisions available.</td></tr>';
        }
    }

    function addBills(){
        if ( ! check_access('add_rentee_bills'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('rental/chargeBills');
            return;
        }
        if($_POST){
            if($this->rental_model->addBills()){
                $this->session->set_flashdata('msg', 'Successfully added the rentee bill.');
                redirect('rental/addBills');
            }else{
                $this->session->set_flashdata('error', 'Unable to add the rentee bill.');
                redirect('rental/addBills');
            }
        }
        //get properties with moved in agreements
        $data['properties'] = $this->property_model->getRentPropertyUnits();
		$data['menu_name'] = 'Rental';
        $data['submenu_name'] = 'Rentee Bills';
        $this->load->view('pr/rental/income/add_bill', $data);
    }

    function editBills($bill_id){
        if ( ! check_access('edit_rentee_bills'))
        {
            $this->session->set_flashdata('error', 'You do not have permission to perform this action.');
            redirect('rental/chargeBills');
            return;
        }
        $bill_id = $this->encryption->decode($bill_id);
        if($_POST){
            if($this->rental_model->editBills($bill_id)){
                $this->session->set_flashdata('msg', 'Successfully edited the rentee bill.');
                redirect('rental/chargeBills');
            }else{
                $this->session->set_flashdata('error', 'Unable to edit the rentee bill.');
                redirect('rental/chargeBills');
            }
        }
        //get properties with moved in agreements
        $data['properties'] = $this->property_model->getRentPropertyUnits();
        $table = 'pr_rent_charges_bills';
        $data['bill'] = $bill= $this->common_model->getData(
                                    $column = 'bill_id', 
                                    $id = $bill_id, 
                                    $table, 
                                    $like = '', 
                                    $not_like = '', 
                                    $row_count = '', 
                                    $page= '', 
                                    $order_by = '',
                                    $row_data = 'yes'
                                );
        $data['charges'] = $this->rental_model->getUpdateChargesByAgreementID($bill->agreement_id);                       
		$data['menu_name'] = 'Rental';
        $data['submenu_name'] = 'Rentee Bills';
        $this->load->view('pr/rental/income/edit_bill', $data);
    }

    function deleteBill(){
        if ( ! check_access('delete_rentee_bills'))
        {
            echo 'You do not have permission to perform this action.';
            exit;
        }
        if($this->rental_model->deleteBills()){
            echo '1';
            exit;
        }else{
            echo 'Unable to delete the rentee bill.';
            exit;
        }
    }
}
