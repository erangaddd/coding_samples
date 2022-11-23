<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Rental_model extends CI_Model {

    function __construct() {
		$this->load->model('entry_model');
        parent::__construct();
    }

	function processInitialPayments($agreement_id){
		//get all charges
		$this->db->select('pr_rent_charges.*,pr_config_renteepaytype.recurring_type,pr_config_renteepaytype.paytype_name,pr_config_renteepaytype.ledger_id,pr_config_renteepaytype.receivable_ledger');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
		$this->db->where('agreement_id',$agreement_id);
		$query = $this->db->get('pr_rent_charges');
		if($query->num_rows() > 0){
			$charges = $query->result();
			//get agreement data
			$this->db->select('pr_rent_leaseagreement.*');
			$this->db->where('agreement_id',$agreement_id);
			$query = $this->db->get('pr_rent_leaseagreement');
			if($query->num_rows() > 0){
				$agreement = $query->row();
				foreach($charges as $charge){
					$start_date = new DateTime($charge->first_due_date);
					$datetime = new DateTime('tomorrow');
					$end_date = new DateTime($datetime->format('Y-m-d'));
					if($agreement->rent_cycle == 'One Time'){ //If rent cycle is one time we take whole period to avoid payment cycles
						if($charge->include_rent == 'yes'){
							$tomorrow = new DateTime('tomorrow');
							$tomorrow = $tomorrow->format('Y-m-d H:i:s');
							$now = strtotime($tomorrow);
							$begin_date = strtotime($charge->first_due_date);
							$datediff = $now - $begin_date;
							$period = '+'.round($datediff / (60 * 60 * 24)).' days';
						}else{
							$period = recurringPeriods($charge->recurring_type); //common helper
						}
					}else{
						if($charge->include_rent == 'yes'){
							$period = recurringPeriods($agreement->rent_cycle); //common helper
						}else{
							$period = recurringPeriods($charge->recurring_type); //common helper
						}
					}
					$interval = DateInterval::createFromDateString($period);
					$period = new DatePeriod($start_date, $interval, $end_date);
					
					if($charge->description != ''){
						$description = $charge->description;
					}else{
						$description = $charge->paytype_name;
					}

					//add pay_type credits
					$insert_data = array(
						'agreement_id' => $agreement_id,
						'charge_id' => $charge->charge_id
					);
					$this->db->insert('pr_rent_charges_credits', $insert_data);

					//if recurring run the loop for each charge within the period from start date to today
					if($charge->occurrence == 'recurring'){
						$charge_amount = 0;
						$x = 1;
						foreach ($period as $dt) {
							if($x == 1){
								$charge_amount = $charge->default_amount;
							}else{
								$charge_amount = '';
							}

							if($charge_amount == '' || $charge_amount == 0){
								$status = 'PENDING';
							}else{
								$status = 'CONFIRMED';
							}
							
							if($dt->format("Y-m-d") <= date('Y-m-d')){
								if($charge->amount_type == 'fixed'){
									$payment_array = array(
										'charge_id' => $charge->charge_id,
										'agreement_id' => $agreement_id,
										'bill_amount' => $charge->default_amount,
										'due_date' => $dt->format("Y-m-d"),
										'description' => $description,
										'status' => 'CONFIRMED',
										'generate_by' => $this->session->userdata('userid'),
										'generate_date' => date('Y-m-d'),
										'generate_type' => 'system'
									);
									$entry_type_id = '4';
									$entry_number = $this->entry_model->next_entry_number($entry_type_id);
									$this->db->trans_start();
									$insert_data = array(
										'number' => $entry_number,
										'date' => date('Y-m-d'),
										'narration' => $charge->description.' receivable for the agreement '.$agreement->agreement_code,
										'entry_type' => $entry_type_id,
										'dr_total' => $charge->default_amount,
										'cr_total' => $charge->default_amount, 
										'module' => 'P',
										'pid' => '0',
										'uid' => $agreement->unit_id,
										'agreement_id'=>$agreement_id,
										'create_date' => date("Y-m-d"),
										'status' => 'CONFIRMED'
									);
									$this->db->insert('ac_entries', $insert_data);
									$entry_id = $this->db->insert_id();
									$this->db->trans_complete();
									if( $this->db->trans_status() === FALSE ){
										$this->db->trans_rollback();
									}else{
										if(get_accounting_mothod()=='Cash')//pr/account_helper function
											$crledger=get_unrealized_incomeaccount();//pr/account_helper function
										else
											$crledger=$charge->ledger_id;
										
										$insert_ledger_data = array(
											'entry_id' => $entry_id,
											'ledger_id' => $charge->receivable_ledger,
											'amount' => $charge->default_amount,
											'dc' => 'D',
										);
										$this->db->insert('ac_entry_items', $insert_ledger_data);
										
										$insert_ledger_data = array(
											'entry_id' => $entry_id,
											'ledger_id' => $crledger,
											'amount' => $charge->default_amount,
											'dc' => 'C',
										);
										$this->db->insert('ac_entry_items', $insert_ledger_data);
										
										$this->db->trans_complete();
										if( $this->db->trans_status() === FALSE ){
											$this->db->trans_rollback();
										}else{
											$this->db->trans_commit();
										}
									}
								}else{
									$payment_array = array(
										'charge_id' => $charge->charge_id,
										'agreement_id' => $agreement_id,
										'bill_amount' => $charge_amount,
										'due_date' => $dt->format("Y-m-d"),
										'description' => $description,
										'status' => $status,
										'generate_by' => $this->session->userdata('userid'),
										'generate_date' => date('Y-m-d'),
										'generate_type' => 'system'
									);
									if($x == 1){
										$entry_type_id = '4';
										$entry_number = $this->entry_model->next_entry_number($entry_type_id);
										$this->db->trans_start();
										if($charge->default_amount > 0){
											$insert_data = array(
												'number' => $entry_number,
												'date' => date('Y-m-d'),
												'narration' => $charge->description. ' receivable for the agreement '.$agreement->agreement_code,
												'entry_type' => $entry_type_id,
												'dr_total' => $charge->default_amount,
												'cr_total' => $charge->default_amount, 
												'module' => 'P',
												'pid' => '0',
												'uid' => $agreement->unit_id,
												'agreement_id'=>$agreement_id,
												'create_date' => date("Y-m-d"),
												'status' => 'CONFIRMED'
											);
											$this->db->insert('ac_entries', $insert_data);
											$entry_id = $this->db->insert_id();
										}
										$this->db->trans_complete();
										if( $this->db->trans_status() === FALSE ){
											$this->db->trans_rollback();
										}else{
											if(get_accounting_mothod()=='Cash')//pr/account_helper function
												$crledger=get_unrealized_incomeaccount();//pr/account_helper function
											else
												$crledger=$charge->ledger_id;

											if($charge->default_amount > 0){
												$insert_ledger_data = array(
													'entry_id' => $entry_id,
													'ledger_id' => $charge->receivable_ledger,
													'amount' => $charge->default_amount,
													'dc' => 'D',
												);
												$this->db->insert('ac_entry_items', $insert_ledger_data);
												
												$insert_ledger_data = array(
													'entry_id' => $entry_id,
													'ledger_id' => $crledger,
													'amount' => $charge->default_amount,
													'dc' => 'C',
												);
												$this->db->insert('ac_entry_items', $insert_ledger_data);
											}
											
											$this->db->trans_complete();
											if( $this->db->trans_status() === FALSE ){
												$this->db->trans_rollback();
											}else{
												$this->db->trans_commit();
											}
										}
									}
								}
								$this->db->insert('pr_rent_charges_bills',$payment_array);
							}
							$x++;
						}
					}else{
						if($charge->default_amount == 0){
							$status = 'PENDING';
						}else{
							$status = 'CONFIRMED';
						}

						$payment_array = array(
							'charge_id' => $charge->charge_id,
							'agreement_id' => $agreement_id,
							'bill_amount' => $charge->default_amount,
							'due_date' => $charge->first_due_date,
							'description' => $description,
							'status' => $status,
							'generate_by' => $this->session->userdata('userid'),
							'generate_date' => date('Y-m-d'),
							'generate_type' => 'system'
						);
						$this->db->insert('pr_rent_charges_bills',$payment_array);
						
						$entry_type_id = '4';
						$entry_number = $this->entry_model->next_entry_number($entry_type_id);
						$this->db->trans_start();
						if($charge->default_amount > 0){
							$insert_data = array(
								'number' => $entry_number,
								'date' => date('Y-m-d'),
								'narration' => $charge->description.' receivable for the agreement '.$agreement->agreement_code,
								'entry_type' => $entry_type_id,
								'dr_total' => $charge->default_amount,
								'cr_total' => $charge->default_amount, 
								'module' => 'P',
								'pid' => '0',
								'uid' => $agreement->unit_id,
								'create_date' => date("Y-m-d"),
								'status' => 'CONFIRMED'
							);
							$this->db->insert('ac_entries', $insert_data);
							$entry_id = $this->db->insert_id();
						}
						$this->db->trans_complete();
						if( $this->db->trans_status() === FALSE ){
							$this->db->trans_rollback();
						}else{
							
							if(get_accounting_mothod()=='Cash')//pr/account_helper function
								$crledger=get_unrealized_incomeaccount();//pr/account_helper function
							else
								$crledger=$charge->ledger_id;
							if($charge->default_amount > 0){
								$insert_ledger_data = array(
									'entry_id' => $entry_id,
									'ledger_id' => $charge->receivable_ledger,
									'amount' => $charge->default_amount,
									'dc' => 'D',
								);
								$this->db->insert('ac_entry_items', $insert_ledger_data);
								
								$insert_ledger_data = array(
									'entry_id' => $entry_id,
									'ledger_id' =>$crledger,
									'amount' => $charge->default_amount,
									'dc' => 'C',
								);
								$this->db->insert('ac_entry_items', $insert_ledger_data);
							}
							
							$this->db->trans_complete();
							if( $this->db->trans_status() === FALSE ){
								$this->db->trans_rollback();
							}else{
								$this->db->trans_commit();
							}
						}
					}
					
				}
				//Add maintenance charge
				$rent_data = array(
					'paytype_id' => '4',
					'agreement_id' => $agreement_id,
					'include_rent' => 'no',
					'default_amount' => '0',
					'amount_type' => 'variable',
					'occurrence' => 'onetime',
					'first_due_date' => '',
					'description' => 'Repair and maintenance charges',
				);
				$this->db->insert('pr_rent_charges',$rent_data);
				$data_array = array(
					'status' => 'MOVED-IN'
				);
				$this->db->where('agreement_id',$agreement_id);
				$this->db->update('pr_rent_leaseagreement',$data_array);
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}

	function getPendingIncome($page,$limit){
		$this->db->select('pr_rent_charges_bills.*,pr_rent_leaseagreement.list_id,pr_rent_leaseagreement.agreement_code');
		$this->db->join('pr_rent_leaseagreement','pr_rent_leaseagreement.agreement_id = pr_rent_charges_bills.agreement_id');
		$this->db->where('pr_rent_charges_bills.confirm_date',NULL);
		$this->db->limit($limit,$page);
		$query = $this->db->get('pr_rent_charges_bills');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function countPendingIncome(){
		$this->db->select('pr_rent_charges_bills.*');
		$this->db->where('pr_rent_charges_bills.confirm_date',NULL);
		$query = $this->db->get('pr_rent_charges_bills');
		if($query->num_rows() > 0){
			return $query->num_rows();
		}else{
			return false;
		}
	}

	function getChargesByAgreementID($agreement_id){
		$this->db->select('pr_rent_charges_bills.*,pr_rent_leaseagreement.list_id,pr_rent_leaseagreement.agreement_code');
		$this->db->join('pr_rent_leaseagreement','pr_rent_leaseagreement.agreement_id = pr_rent_charges_bills.agreement_id');
		$this->db->where('pr_rent_charges_bills.agreement_id',$agreement_id);
		$query = $this->db->get('pr_rent_charges_bills');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function getChargeBillsbyChargeID($charge_id, $agreement_id){
		$this->db->select('pr_rent_charges_bills.*,pr_rent_leaseagreement.list_id,pr_rent_leaseagreement.agreement_code');
		$this->db->join('pr_rent_leaseagreement','pr_rent_leaseagreement.agreement_id = pr_rent_charges_bills.agreement_id');
		$this->db->where('pr_rent_charges_bills.agreement_id',$agreement_id);
		$this->db->where('pr_rent_charges_bills.charge_id',$charge_id);
		$this->db->where('pr_rent_charges_bills.status','CONFIRMED');
		$this->db->order_by('pr_rent_charges_bills.due_date','ASC');
		$query = $this->db->get('pr_rent_charges_bills');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function getChargeTypesByAgreementID($agreement_id){
		$this->db->select('*');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
		$this->db->where('pr_rent_charges.agreement_id',$agreement_id);
		$query = $this->db->get('pr_rent_charges');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function getPaidChargeAmount($bill_id){
		$this->db->select('SUM(di_amount) as di_amount, SUM(credit_amount) as credit_amount, SUM(receipt_amount) as receipt_amount');
		$this->db->where('bill_id',$bill_id);
		$this->db->group_by('bill_id');
		$query = $this->db->get('pr_rent_charges_paydata');
		if($query->num_rows() > 0){
			$pay_data = $query->row();
			$total_di_paid = $pay_data->di_amount - $pay_data->credit_amount;
			$total_paid = $pay_data->receipt_amount - $total_di_paid;
			return $total_paid;
		}else{
			return '0';
		}
	}

	function getPaidDiAmount($bill_id){
		$this->db->select('SUM(di_amount) as di_amount, SUM(di_waived) as di_waived');
		$this->db->where('bill_id',$bill_id);
		$query = $this->db->get('pr_rent_charges_paydata');
		if($query->num_rows() > 0){
			$di_data =  $query->row();
			$di_paid = $di_data->di_amount + $di_data->di_waived;
			return $di_paid;
		}else{
			return '0';
		}
	}

	function getCreditsUsedDi($bill_id){
		$this->db->select('*');
		$this->db->where('bill_id',$bill_id);
		$query = $this->db->get('pr_rent_charges_paydata');
		if($query->num_rows() > 0){
			$credit_used = 0;
			$pay_data =  $query->result();
			foreach($pay_data as $row){
				if($row->credit_amount >= $row->di_amount){
					$credit_used = $credit_used + $row->di_amount;
				}
				if($row->credit_amount < $row->di_amount){
					$credit_used = $credit_used + $row->credit_amount;
				}
			}
			return $credit_used;
		}else{
			return '0';
		}
	}

	function getRenteeChargetypeCredits($agreement_id,$charge_id){
		$this->db->select('amount');
		$this->db->where('agreement_id',$agreement_id);
		$this->db->where('charge_id',$charge_id);
		$query = $this->db->get('pr_rent_charges_credits');
		if($query->num_rows() > 0){
			return $query->row()->amount;
		}else{
			return '0';
		}
	}

	function updateBillInterest($bill_id,$interest){
		$data = array( 'delay_interest' => $interest);
		$this->db->where('bill_id',$bill_id);
		$this->db->update('pr_rent_charges_bills',$data);
	}

	function getBillsbyChargeID($agreement_id,$charge_id){
		$this->db->select('pr_rent_charges_bills.*,pr_rent_charges.charge_id,pr_rent_leaseagreement.list_id,pr_rent_leaseagreement.agreement_code');
		$this->db->join('pr_rent_leaseagreement','pr_rent_leaseagreement.agreement_id = pr_rent_charges_bills.agreement_id');
		$this->db->join('pr_rent_charges','pr_rent_charges.charge_id = pr_rent_charges_bills.charge_id');
		$this->db->where('pr_rent_charges_bills.agreement_id',$agreement_id);
		$this->db->where('pr_rent_charges.charge_id',$charge_id);
		$this->db->order_by('pr_rent_charges_bills.due_date','ASC');
		$query = $this->db->get('pr_rent_charges_bills');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function addChargePayments($charge_types){
		$agreement_id = $this->input->post('agreement_id');
		$this->load->model("pr/agreement_model");
		$application = $this->agreement_model->getApplicationByAgreementID($agreement_id);
		$income_data = array(
			'agreement_id' => $agreement_id,
			'branch_code' => $this->session->userdata('branchid'),
			'cus_code' => $application->cus_code,
			'income_type' => 'Rental charges',
			'amount' => $this->input->post('total'),
			'income_date' => $this->input->post('date')
		);
		$this->db->trans_start();
		$this->db->insert('pr_incomedata',$income_data);
		$income_id = $this->db->insert_id();
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			foreach($charge_types as $charge_type){
				
				$total_payment = $this->input->post('current_payment'.$charge_type->charge_id);
				$total_amount = 0;
				$interest_total = 0; //total interest paid using cash

				if($total_payment != ''){
					//add any credit to total payment
					$credit_balance = $this->getRenteeChargetypeCredits($agreement_id,$charge_type->charge_id);
					$total_amount = $total_payment + $credit_balance;
					//get charge bills
					$this->db->select('pr_rent_charges_bills.*,pr_config_renteepaytype.*');
					$this->db->join('pr_rent_charges','pr_rent_charges.charge_id = pr_rent_charges_bills.charge_id');
					$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
					$this->db->where('pr_rent_charges.charge_id',$charge_type->charge_id);
					$this->db->where('pr_rent_charges_bills.agreement_id',$agreement_id);
					$this->db->order_by('pr_rent_charges_bills.due_date','ASC');
					$query = $this->db->get('pr_rent_charges_bills');
					if($query->num_rows() > 0){
						$bills =  $query->result();
						//first we deduct interest
						foreach($bills as $bill){
							$di_waived = $this->input->post('int_waived'.$bill->bill_id);
							// if($bill->delay_interest <= $credit_balance && $bill->delay_interest != '0'){
							// 	$pay_data = array(
							// 		'charge_id' => $bill->charge_id,
							// 		'agreement_id' => $agreement_id,
							// 		'bill_id' => $bill->bill_id,
							// 		'pay_amount' => $bill->delay_interest,
							// 		'receipt_amount' => '',
							// 		'credit_amount' => $bill->delay_interest,
							// 		'di_amount' => $bill->delay_interest,
							// 		'di_waived' => $di_waived,
							// 		'pay_date' => $this->input->post('date'),
							// 		'income_id' => $income_id,
							// 		'pay_status' => 'CONFIRMED',
							// 		'confirm_by' => $this->session->userdata('userid'),
							// 		'confirm_date' => date('Y-m-d')
							// 	);
							// 	$this->db->insert('pr_rent_charges_paydata',$pay_data);
							// 	$credit_balance = $credit_balance - $bill->delay_interest;
							// 	$total_amount = $total_amount - $bill->delay_interest;
							// 	$di_waived = 0;
							// }else if($bill->delay_interest > $credit_balance && $bill->delay_interest <= $total_amount && $bill->delay_interest != '0'){
							// 	$pay_data = array(
							// 		'charge_id' => $bill->charge_id,
							// 		'agreement_id' => $agreement_id,
							// 		'bill_id' => $bill->bill_id,
							// 		'pay_amount' => $bill->delay_interest,
							// 		'receipt_amount' => $bill->delay_interest - $credit_balance,
							// 		'credit_amount' => $credit_balance,
							// 		'di_amount' => $bill->delay_interest,
							// 		'di_waived' => $di_waived,
							// 		'pay_date' => $this->input->post('date'),
							// 		'income_id' => $income_id,
							// 		'pay_status' => 'CONFIRMED',
							// 		'confirm_by' => $this->session->userdata('userid'),
							// 		'confirm_date' => date('Y-m-d')
							// 	);
							// 	$this->db->insert('pr_rent_charges_paydata',$pay_data);

							// 	$total_amount = $total_amount - $bill->delay_interest;
							// 	$interest_total = $interest_total + ($bill->delay_interest - $credit_balance);
							// 	$credit_balance = 0;
							// }else if($total_amount > 0 && $bill->delay_interest != '0'){
							// 	$pay_data = array(
							// 		'charge_id' => $bill->charge_id,
							// 		'agreement_id' => $agreement_id,
							// 		'bill_id' => $bill->bill_id,
							// 		'pay_amount' => $total_amount,
							// 		'receipt_amount' => $total_amount - $credit_balance,
							// 		'credit_amount' => $credit_balance,
							// 		'di_amount' => $total_amount,
							// 		'di_waived' => $di_waived,
							// 		'pay_date' => $this->input->post('date'),
							// 		'income_id' => $income_id,
							// 		'pay_status' => 'CONFIRMED',
							// 		'confirm_by' => $this->session->userdata('userid'),
							// 		'confirm_date' => date('Y-m-d')
							// 	);
							// 	$this->db->insert('pr_rent_charges_paydata',$pay_data);
							// 	$interest_total = $interest_total + ($total_amount - $credit_balance);
							// 	$total_amount = $total_amount - $credit_balance;
							// 	$credit_balance = 0;
							// 	$total_amount = 0;
							// }
							$pay_amount = 0;
							if($total_amount > 0 && $bill->delay_interest != '0'){
								if($total_amount > $bill->delay_interest){
									$pay_amount = $bill->delay_interest;
								}else{
									$pay_amount = $total_amount;
								}
								
								$pay_data = array(
									'charge_id' => $bill->charge_id,
									'agreement_id' => $agreement_id,
									'bill_id' => $bill->bill_id,
									'pay_amount' => $pay_amount,
									'receipt_amount' => $pay_amount,
									'credit_amount' => '',
									'di_amount' => $pay_amount,
									'di_waived' => $di_waived,
									'pay_date' => $this->input->post('date'),
									'income_id' => $income_id,
									'pay_status' => 'CONFIRMED',
									'confirm_by' => $this->session->userdata('userid'),
									'confirm_date' => date('Y-m-d')
								);
								$this->db->insert('pr_rent_charges_paydata',$pay_data);
								$interest_total = $interest_total + $pay_amount;
								$total_amount = $total_amount - $pay_amount;
							}
						}
						
						//We deduct payment if there is a balance after paying DI
						if($total_amount > 0){
							foreach($bills as $bill){
								$old_payments = $this->getPaidChargeAmount($bill->bill_id);
								$bill_balance = $bill->bill_amount - $old_payments;
								if($bill->bill_amount > $old_payments && $bill->bill_amount != '0' && $total_amount != '0'){
									if($total_amount > $bill_balance){
										$pay_amount = $bill_balance;
									}else{
										$pay_amount = $total_amount;
									}
									if($pay_amount){
										$pay_data = array(
											'charge_id' => $bill->charge_id,
											'agreement_id' => $agreement_id,
											'bill_id' => $bill->bill_id,
											'pay_amount' => $pay_amount,
											'receipt_amount' => $pay_amount - $credit_balance,
											'credit_amount' => $credit_balance,
											'di_amount' => '',
											'pay_date' => $this->input->post('date'),
											'income_id' => $income_id,
											'pay_status' => 'CONFIRMED',
											'confirm_by' => $this->session->userdata('userid'),
											'confirm_date' => date('Y-m-d')
										);
										$this->db->insert('pr_rent_charges_paydata',$pay_data);
									}
									
									$entry_data = array(
										'income_id' => $income_id,
										'ledger_id' => $charge_type->receivable_ledger,
										'dc_type' => 'C',
										'amount' => $pay_amount - $credit_balance
									);
									$this->db->insert('pr_income_entrydata',$entry_data);

									$total_amount = $total_amount - $pay_amount;
									$credit_balance = 0;
								}
							}
						}
					}
					if($interest_total > 0){
						$entry_data = array(
							'income_id' => $income_id,
							'ledger_id' => $charge_type->late_payledger,
							'dc_type' => 'C',
							'amount' => $interest_total
						);
						$this->db->insert('pr_income_entrydata',$entry_data);
					}
	
					//Update credit table
					if($total_amount > 0){
						$credit_data = array('amount' => $total_amount);
						$entry_data = array(
							'income_id' => $income_id,
							'ledger_id' => $charge_type->receivable_ledger,
							'dc_type' => 'C',
							'amount' =>  $total_amount
						);
						$this->db->insert('pr_income_entrydata',$entry_data);
	
						$entry_data = array(
							'income_id' => $income_id,
							'charge_id' => $charge_type->charge_id,
							'amount' => $total_amount
						);
						$this->db->insert('pr_rent_bill_credits',$entry_data);
						
					}else{
						$credit_data = array('amount' => '0');
					}
					$this->db->where('charge_id',$charge_type->charge_id);
					$this->db->where('agreement_id',$agreement_id);
					$this->db->update('pr_rent_charges_credits',$credit_data);
				}
			}
			
			//Debit Entry
			$entry_data = array(
				'income_id' => $income_id,
				'ledger_id' => '1',
				'dc_type' => 'D',
				'amount' => $this->input->post('total')
			);
			$this->db->insert('pr_income_entrydata',$entry_data);
			return $income_id;
		}
	}

	function getCreditIncomeIDChargeID($income_id,$charge_id){
		$this->db->select('pr_rent_bill_credits.amount');
		$this->db->where('pr_rent_bill_credits.charge_id',$charge_id);
		$this->db->where('pr_rent_bill_credits.income_id',$income_id);
		$query = $this->db->get('pr_rent_bill_credits');
		if($query->num_rows() > 0){
			return $query->row()->amount;
		}else{
			return false;
		}
	}

	function getBillToUpdate($agreement_id){
		$this->db->select('pr_rent_charges_bills.bill_id,pr_rent_charges_bills.due_date,pr_rent_charges_bills.description,pr_rent_charges_bills.bill_amount,pr_config_renteepaytype.paytype_name,pr_rent_charges_bills.bill_number');
		$this->db->join('pr_rent_charges','pr_rent_charges.charge_id = pr_rent_charges_bills.charge_id');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
		$this->db->where('pr_rent_charges_bills.agreement_id',$agreement_id);
		$this->db->where('pr_rent_charges.amount_type','variable');
		$this->db->where('pr_rent_charges_bills.status','PENDING');
		$query = $this->db->get('pr_rent_charges_bills');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function updateBillAmount($bill_id, $amount, $bill_number){
		$data = array( 
			'bill_amount' => $amount,
			'bill_number' => $bill_number,
			'status' => 'CONFIRM'
		);
		$this->db->trans_start();
		$this->db->where('bill_id',$bill_id);
		$this->db->update('pr_rent_charges_bills',$data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function countBillByAgreement(){
		$this->db->select('pr_rent_charges_bills.*');
		$this->db->join('pr_rent_charges','pr_rent_charges.charge_id = pr_rent_charges_bills.charge_id');
		$this->db->where('pr_rent_charges_bills.agreement_id',$this->session->userdata('bill_agreement_id'));
		if($this->session->userdata('bill_paytype_id')){
			$this->db->where('pr_rent_charges.paytype_id',$this->session->userdata('bill_paytype_id'));
		}
		if($this->session->userdata('bill_status')){
			$this->db->where('pr_rent_charges_bills.status',$this->session->userdata('bill_status'));
		}
		$query = $this->db->get('pr_rent_charges_bills');
		if($query->num_rows() > 0){
			return $query->num_rows();
		}else{
			return false;
		}
	}

	function getBillByAgreement($page,$limit){
		$this->db->select('pr_rent_charges_bills.*,pr_rent_charges.description as charge_desc, pr_config_renteepaytype.paytype_name,pr_rent_leaseagreement.agreement_code,pr_rent_leaseagreement.list_id');
		$this->db->join('pr_rent_charges','pr_rent_charges.charge_id = pr_rent_charges_bills.charge_id');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
		$this->db->join('pr_rent_leaseagreement','pr_rent_leaseagreement.agreement_id = pr_rent_charges_bills.agreement_id');
		$this->db->where('pr_rent_charges_bills.agreement_id',$this->session->userdata('bill_agreement_id'));
		if($this->session->userdata('bill_paytype_id')){
			$this->db->where('pr_rent_charges.paytype_id',$this->session->userdata('bill_paytype_id'));
		}
		if($this->session->userdata('bill_status')){
			$this->db->where('pr_rent_charges_bills.status',$this->session->userdata('bill_status'));
		}
		$this->db->order_by('pr_rent_charges_bills.due_date','DESC');
		$this->db->limit($limit,$page);
		$query = $this->db->get('pr_rent_charges_bills');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function changeBillStatus($bill_id, $status){
		$data = array( 
			'status' => $status
		);
		$this->db->trans_start();
		$this->db->where('bill_id',$bill_id);
		$this->db->update('pr_rent_charges_bills',$data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			if($status=='CONFIRMED')
			{
				$this->update_charge_bill_entries($bill_id);// added by udani
			}
			$this->db->trans_commit();
			return true;
		}
	}

	function getAllBillstoConfirm($page, $limit){
		$this->db->select('pr_rent_charges_bills.*,pr_rent_charges.description as charge_desc, pr_config_renteepaytype.paytype_name,pr_rent_leaseagreement.agreement_code,pr_rent_leaseagreement.list_id');
		$this->db->join('pr_rent_charges','pr_rent_charges.charge_id = pr_rent_charges_bills.charge_id');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
		$this->db->join('pr_rent_leaseagreement','pr_rent_leaseagreement.agreement_id = pr_rent_charges_bills.agreement_id');
		$this->db->where('pr_rent_charges_bills.status', 'CONFIRM');
		$this->db->order_by('pr_rent_charges_bills.bill_id','DESC');
		$this->db->limit($limit,$page);
		$query = $this->db->get('pr_rent_charges_bills');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function countAllBillstoConfirm(){
		$this->db->select('pr_rent_charges_bills.*');
		$this->db->join('pr_rent_charges','pr_rent_charges.charge_id = pr_rent_charges_bills.charge_id');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
		$this->db->where('pr_rent_charges_bills.status', 'CONFIRM');
		$this->db->order_by('pr_rent_charges_bills.due_date','DESC');
		$query = $this->db->get('pr_rent_charges_bills');
		if($query->num_rows() > 0){
			return $query->num_rows();
		}else{
			return false;
		}
	}
	
	// functions added by udani for make accounting entries on bill confirmation
	
	function update_charge_bill_entries($bill_id){
		
		
		$method=get_accounting_mothod();// pr account helper_function
		$crledger=$unincome_ledger=get_unrealized_incomeaccount();// praccount hleper funcion
		
		$this->db->select('pr_rent_charges_bills.*,pr_config_renteepaytype.ledger_id,pr_config_renteepaytype.receivable_ledger,pr_config_renteepaytype.recurring_type,pr_config_renteepaytype.paytype_name,pr_rent_leaseagreement.agreement_code,pr_rent_charges.description,,pr_rent_leaseagreement.unit_id');
		$this->db->join('pr_rent_charges','pr_rent_charges.charge_id = pr_rent_charges_bills.charge_id');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
		$this->db->join('pr_rent_leaseagreement','pr_rent_leaseagreement.agreement_id = pr_rent_charges.agreement_id');
		$this->db->where('pr_rent_charges_bills.bill_id', $bill_id);
		$query = $this->db->get('pr_rent_charges_bills');
		if($query->num_rows() > 0){
			$chargeraw= $query->row() ;
			if($chargeraw->bill_amount>0)
			{
				$charge_amount=$chargeraw->bill_amount;
				$date=date('Y-m-d');
				if($method!='Cash')
					$crledger=$chargeraw->ledger_id;
				$crlist[0]['ledgerid']=$crledger;
				$crlist[0]['amount']=$crtot=$chargeraw->bill_amount;
				$drlist[0]['ledgerid']=$chargeraw->receivable_ledger;
				$drlist[0]['amount']=$drtot=$chargeraw->bill_amount;
				$narration = $chargeraw->agreement_code.'-'.$chargeraw->paytype_name.' '.$chargeraw->description.' Bill Creation Entry   '  ;
								
				$int_entry=jurnal_entry_pr($crlist,$drlist,$crtot,$drtot,$date,$narration,0,$chargeraw->unit_id,$chargeraw->agreement_id); // pr account helper function
				$incomeldger=$chargeraw->ledger_id;
				$narration_pass= $chargeraw->agreement_code.'-'.$chargeraw->paytype_name.' '.$chargeraw->description;
				allocate_credit_payments($bill_id,$chargeraw->charge_id,$chargeraw->agreement_id,$charge_amount,$method,$incomeldger,$unincome_ledger,$date,$narration_pass,$chargeraw->unit_id);// pr account helper function
			}
		}else{
			return false;
		}
	}

	function getAllChargestoConfirm($page, $limit){
		$this->db->select('pr_rent_charges.*,pr_config_renteepaytype.paytype_name,pr_rent_leaseagreement.agreement_code,pr_rent_leaseagreement.list_id');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
		$this->db->join('pr_rent_leaseagreement','pr_rent_leaseagreement.agreement_id = pr_rent_charges.agreement_id');
		$this->db->where('pr_rent_charges.status !=', 'CONFIRMED');
		$this->db->order_by('pr_rent_charges.charge_id','DESC');
		$this->db->limit($limit,$page);
		$query = $this->db->get('pr_rent_charges');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function countAllChargestoConfirm(){
		$this->db->select('pr_rent_charges.*');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
		$this->db->join('pr_rent_leaseagreement','pr_rent_leaseagreement.agreement_id = pr_rent_charges.agreement_id');
		$this->db->where('pr_rent_charges.status !=', 'CONFIRMED');
		$this->db->order_by('pr_rent_charges.first_due_date','DESC');
		$query = $this->db->get('pr_rent_charges');
		if($query->num_rows() > 0){
			return $query->num_rows();
		}else{
			return false;
		}
	}

	function countChargesByAgreement(){
		$this->db->select('pr_rent_charges.*,pr_config_renteepaytype.paytype_name,pr_rent_leaseagreement.agreement_code,pr_rent_leaseagreement.list_id');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
		$this->db->join('pr_rent_leaseagreement','pr_rent_leaseagreement.agreement_id = pr_rent_charges.agreement_id');
		$this->db->where('pr_rent_charges.agreement_id',$this->session->userdata('charge_agreement_id'));
		if($this->session->userdata('charge_paytype_id')){
			$this->db->where('pr_rent_charges.paytype_id',$this->session->userdata('charge_paytype_id'));
		}
		if($this->session->userdata('charge_status')){
			$this->db->where('pr_rent_charges.status',$this->session->userdata('charge_status'));
		}
		$this->db->order_by('pr_rent_charges.first_due_date','DESC');
		$query = $this->db->get('pr_rent_charges');
		if($query->num_rows() > 0){
			return $query->num_rows();
		}else{
			return false;
		}
	}

	function getCharegsByAgreement($page,$limit){
		$this->db->select('pr_rent_charges.*,pr_config_renteepaytype.paytype_name,pr_rent_leaseagreement.agreement_code,pr_rent_leaseagreement.list_id');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
		$this->db->join('pr_rent_leaseagreement','pr_rent_leaseagreement.agreement_id = pr_rent_charges.agreement_id');
		$this->db->where('pr_rent_charges.agreement_id',$this->session->userdata('charge_agreement_id'));
		if($this->session->userdata('charge_paytype_id')){
			$this->db->where('pr_rent_charges.paytype_id',$this->session->userdata('charge_paytype_id'));
		}
		if($this->session->userdata('charge_status')){
			$this->db->where('pr_rent_charges.status',$this->session->userdata('charge_status'));
		}
		$this->db->order_by('pr_rent_charges.first_due_date','DESC');
		$this->db->limit($limit,$page);
		$query = $this->db->get('pr_rent_charges');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function addCharges(){
		$agreement_id = $this->input->post('agreement_id');
		$table = 'pr_rent_charges';
        $next_action = checkApproveLevel($table,'PENDING'); //common helper
		$charge_data = array(
			'paytype_id' => $this->input->post('paytype_id'),
			'agreement_id' => $agreement_id,
			'include_rent' => 'no',
			'default_amount' => $this->input->post('charge_amount'),
			'amount_type' => $this->input->post('charge_amount_type'),
			'occurrence' => $this->input->post('charge_occurrence'),
			'first_due_date' => $this->input->post('charge_due_date'),
			'description' => $this->input->post('charge_description'),
			'status' => $next_action
		);
		$this->db->trans_start();
		$this->db->insert('pr_rent_charges',$charge_data);
		$this->db->trans_complete();
		$charge_id = $this->db->insert_id();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			if($next_action == 'CONFIRMED'){
				$this->processBillsForCharge($charge_id, $agreement_id);
			}
			//add pay_type credits
			$insert_data = array(
				'agreement_id' => $agreement_id,
				'charge_id' => $charge_id
			);
			$this->db->trans_start();
			$this->db->insert('pr_rent_charges_credits', $insert_data);
			$this->db->trans_complete();
			$this->db->trans_commit();
			return true;
		}
	}

	function changeStatus($status, $charge_id){
		$table = 'pr_rent_charges';
		$next_action = checkApproveLevel($table,$status); //common helper
		if($status == 'CHECK'){
			$data_array = array(
				'check_by' => $this->session->userdata('userid'),
				'check_date' => date('Y-m-d'),
				'status' => $next_action
			);
		}
		if($status == 'CONFIRM'){
			$data_array = array(
				'confirm_by' => $this->session->userdata('userid'),
				'confirm_date' => date('Y-m-d'),
				'status' => $next_action
			);
		}
		if($status == 'HOLD'){
			$data_array = array(
				'status' => 'HOLDED'
			);
		}
		if($status == 'RESUME'){
			$data_array = array(
				'status' => 'CONFIRMED'
			);
		}
		$this->db->where('charge_id',$charge_id);
		$this->db->trans_start();
		$this->db->update('pr_rent_charges',$data_array);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			if($next_action == 'CONFIRMED'){
				$this->db->select('pr_rent_charges.agreement_id');
				$this->db->where('pr_rent_charges.charge_id',$charge_id);
				$query = $this->db->get('pr_rent_charges');
				if($query->num_rows() > 0){
					$agreement_id =  $query->row()->agreement_id;
					$this->processBillsForCharge($charge_id, $agreement_id);
				}else{
					$this->db->trans_rollback();
					return false;
				}
			}
			$this->db->trans_commit();
			return true;
		}
	}

	function processBillsForCharge($charge_id, $agreement_id){
		$this->db->select('pr_rent_charges.*,pr_config_renteepaytype.recurring_type,pr_config_renteepaytype.paytype_name,pr_config_renteepaytype.ledger_id,pr_config_renteepaytype.receivable_ledger');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
		$this->db->where('charge_id',$charge_id);
		$query = $this->db->get('pr_rent_charges');
		if($query->num_rows() > 0){
			$charge = $query->row();

			$this->db->select('pr_rent_leaseagreement.*');
			$this->db->where('agreement_id',$agreement_id);
			$query = $this->db->get('pr_rent_leaseagreement');
			if($query->num_rows() > 0){
				$agreement = $query->row();
			}

			$start_date = new DateTime($charge->first_due_date);
			$datetime = new DateTime('tomorrow');
			$end_date = new DateTime($datetime->format('Y-m-d'));

			$period = recurringPeriods($charge->recurring_type); //common helper
			$interval = DateInterval::createFromDateString($period);
			$period = new DatePeriod($start_date, $interval, $end_date);
			
			if($charge->description != ''){
				$description = $charge->description;
			}else{
				$description = $charge->paytype_name;
			}

			//if recurring run the loop for each charge within the period from start date to today
			if($charge->occurrence == 'recurring'){
				$charge_amount = 0;
				$x = 1;
				foreach ($period as $dt) {
					if($x == 1){
						$charge_amount = $charge->default_amount;
					}else{
						$charge_amount = '';
					}

					if($charge_amount == '' || $charge_amount == 0){
						$status = 'PENDING';
					}else{
						$status = 'CONFIRMED';
					}
					
					if($dt->format("Y-m-d") <= date('Y-m-d')){
						if($charge->amount_type == 'fixed'){
							$payment_array = array(
								'charge_id' => $charge->charge_id,
								'agreement_id' => $agreement_id,
								'bill_amount' => $charge->default_amount,
								'due_date' => $dt->format("Y-m-d"),
								'description' => $description,
								'status' => 'CONFIRMED',
								'generate_by' => $this->session->userdata('userid'),
								'generate_date' => date('Y-m-d'),
								'generate_type' => 'system'
							);
							$entry_type_id = '4';
							$entry_number = $this->entry_model->next_entry_number($entry_type_id);
							$this->db->trans_start();
							$insert_data = array(
								'number' => $entry_number,
								'date' => date('Y-m-d'),
								'narration' => $charge->description.' receivable for the agreement '.$agreement->agreement_code,
								'entry_type' => $entry_type_id,
								'dr_total' => $charge->default_amount,
								'cr_total' => $charge->default_amount, 
								'module' => 'P',
								'pid' => '0',
								'uid' => $agreement->unit_id,
								'agreement_id'=>$agreement_id,
								'create_date' => date("Y-m-d"),
								'status' => 'CONFIRMED'
							);
							$this->db->insert('ac_entries', $insert_data);
							$entry_id = $this->db->insert_id();
							$this->db->trans_complete();
							if( $this->db->trans_status() === FALSE ){
								$this->db->trans_rollback();
							}else{
								if(get_accounting_mothod()=='Cash')//pr/account_helper function
									$crledger=get_unrealized_incomeaccount();//pr/account_helper function
								else
									$crledger=$charge->ledger_id;
								
								$insert_ledger_data = array(
									'entry_id' => $entry_id,
									'ledger_id' => $charge->receivable_ledger,
									'amount' => $charge->default_amount,
									'dc' => 'D',
								);
								$this->db->insert('ac_entry_items', $insert_ledger_data);
								
								$insert_ledger_data = array(
									'entry_id' => $entry_id,
									'ledger_id' => $crledger,
									'amount' => $charge->default_amount,
									'dc' => 'C',
								);
								$this->db->insert('ac_entry_items', $insert_ledger_data);
								
								$this->db->trans_complete();
								if( $this->db->trans_status() === FALSE ){
									$this->db->trans_rollback();
								}else{
									$this->db->trans_commit();
								}
							}
						}else{
							$payment_array = array(
								'charge_id' => $charge->charge_id,
								'agreement_id' => $agreement_id,
								'bill_amount' => $charge_amount,
								'due_date' => $dt->format("Y-m-d"),
								'description' => $description,
								'status' => $status,
								'generate_by' => $this->session->userdata('userid'),
								'generate_date' => date('Y-m-d'),
								'generate_type' => 'system'
							);
							if($x == 1){
								$entry_type_id = '4';
								$entry_number = $this->entry_model->next_entry_number($entry_type_id);
								$this->db->trans_start();
								if($charge->default_amount > 0){
									$insert_data = array(
										'number' => $entry_number,
										'date' => date('Y-m-d'),
										'narration' => $charge->description. ' receivable for the agreement '.$agreement->agreement_code,
										'entry_type' => $entry_type_id,
										'dr_total' => $charge->default_amount,
										'cr_total' => $charge->default_amount, 
										'module' => 'P',
										'pid' => '0',
										'uid' => $agreement->unit_id,
										'agreement_id'=>$agreement_id,
										'create_date' => date("Y-m-d"),
										'status' => 'CONFIRMED'
									);
									$this->db->insert('ac_entries', $insert_data);
									$entry_id = $this->db->insert_id();
								}
								$this->db->trans_complete();
								if( $this->db->trans_status() === FALSE ){
									$this->db->trans_rollback();
								}else{
									if(get_accounting_mothod()=='Cash')//pr/account_helper function
										$crledger=get_unrealized_incomeaccount();//pr/account_helper function
									else
										$crledger=$charge->ledger_id;

									if($charge->default_amount > 0){
										$insert_ledger_data = array(
											'entry_id' => $entry_id,
											'ledger_id' => $charge->receivable_ledger,
											'amount' => $charge->default_amount,
											'dc' => 'D',
										);
										$this->db->insert('ac_entry_items', $insert_ledger_data);
										
										$insert_ledger_data = array(
											'entry_id' => $entry_id,
											'ledger_id' => $crledger,
											'amount' => $charge->default_amount,
											'dc' => 'C',
										);
										$this->db->insert('ac_entry_items', $insert_ledger_data);
									}
									
									$this->db->trans_complete();
									if( $this->db->trans_status() === FALSE ){
										$this->db->trans_rollback();
									}else{
										$this->db->trans_commit();
									}
								}
							}
						}
						$this->db->insert('pr_rent_charges_bills',$payment_array);
					}
					$x++;
				}
			}else{
				if($charge->default_amount == 0){
					$status = 'PENDING';
				}else{
					$status = 'CONFIRMED';
				}

				$payment_array = array(
					'charge_id' => $charge->charge_id,
					'agreement_id' => $agreement_id,
					'bill_amount' => $charge->default_amount,
					'due_date' => $charge->first_due_date,
					'description' => $description,
					'status' => $status,
					'generate_by' => $this->session->userdata('userid'),
					'generate_date' => date('Y-m-d'),
					'generate_type' => 'system'
				);
				$this->db->insert('pr_rent_charges_bills',$payment_array);
				
				$entry_type_id = '4';
				$entry_number = $this->entry_model->next_entry_number($entry_type_id);
				$this->db->trans_start();
				if($charge->default_amount > 0){
					$insert_data = array(
						'number' => $entry_number,
						'date' => date('Y-m-d'),
						'narration' => $charge->description.' receivable for the agreement '.$agreement->agreement_code,
						'entry_type' => $entry_type_id,
						'dr_total' => $charge->default_amount,
						'cr_total' => $charge->default_amount, 
						'module' => 'P',
						'pid' => '0',
						'uid' => $agreement->unit_id,
						'create_date' => date("Y-m-d"),
						'status' => 'CONFIRMED'
					);
					$this->db->insert('ac_entries', $insert_data);
					$entry_id = $this->db->insert_id();
				}
				$this->db->trans_complete();
				if( $this->db->trans_status() === FALSE ){
					$this->db->trans_rollback();
				}else{
					
					if(get_accounting_mothod()=='Cash')//pr/account_helper function
						$crledger=get_unrealized_incomeaccount();//pr/account_helper function
					else
						$crledger=$charge->ledger_id;
					if($charge->default_amount > 0){
						$insert_ledger_data = array(
							'entry_id' => $entry_id,
							'ledger_id' => $charge->receivable_ledger,
							'amount' => $charge->default_amount,
							'dc' => 'D',
						);
						$this->db->insert('ac_entry_items', $insert_ledger_data);
						
						$insert_ledger_data = array(
							'entry_id' => $entry_id,
							'ledger_id' =>$crledger,
							'amount' => $charge->default_amount,
							'dc' => 'C',
						);
						$this->db->insert('ac_entry_items', $insert_ledger_data);
					}
					
					$this->db->trans_complete();
					if( $this->db->trans_status() === FALSE ){
						$this->db->trans_rollback();
					}else{
						$this->db->trans_commit();
					}
				}
			}
			return true;
		}else{
			return false;
		}
		
	}

	function checkChargeStatus($charge_id){
		$this->db->select('pr_rent_charges.status');
		$this->db->where('pr_rent_charges.charge_id',$charge_id);
		$query = $this->db->get('pr_rent_charges');
		if($query->num_rows() > 0){
			$charge_status = $query->row()->status;
			if($charge_status == 'CONFIRMED'){
				return true;
			}else{
				return false;
			}
		}else{
			return false;
		}
	}

	function editCharge($charge_id){
		$data_array = array(
			'agreement_id' => $this->input->post('agreement_id'),
			'paytype_id' => $this->input->post('paytype_id'),
			'default_amount' => $this->input->post('charge_amount'),
			'amount_type' => $this->input->post('charge_amount_type'),
			'occurrence' => $this->input->post('charge_occurrence'),
			'first_due_date' => $this->input->post('charge_due_date'),
			'description' => $this->input->post('charge_description'),
			'update_by' => $this->session->userdata('userid'),
			'update_date' => date('Y-m-d')
		);
		$this->db->where('charge_id',$charge_id);
		$this->db->trans_start();
		$this->db->update('pr_rent_charges',$data_array);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function deleteCharge($charge_id){
		$this->db->where('charge_id',$charge_id);
		$this->db->trans_start();
		$this->db->delete('pr_rent_charges');
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function getPendingChargeUpdates($page,$limit){
		$this->db->select('pr_rent_charges_update.*,pr_rent_charges.description,pr_config_renteepaytype.paytype_name,pr_rent_leaseagreement.agreement_code,pr_rent_leaseagreement.list_id');
		$this->db->join('pr_rent_charges','pr_rent_charges.charge_id = pr_rent_charges_update.charge_id');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
		$this->db->join('pr_rent_leaseagreement','pr_rent_leaseagreement.agreement_id = pr_rent_charges.agreement_id');
		$this->db->order_by('pr_rent_charges_update.update_id','DESC');
		$this->db->where('pr_rent_charges_update.status','CONFIRM');
		$this->db->limit($limit,$page);
		$query = $this->db->get('pr_rent_charges_update');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function countPendingChargeUpdates(){
		$this->db->select('pr_rent_charges_update.*,pr_rent_charges.description,pr_config_renteepaytype.paytype_name,pr_rent_leaseagreement.agreement_code,pr_rent_leaseagreement.list_id');
		$this->db->join('pr_rent_charges','pr_rent_charges.charge_id = pr_rent_charges_update.charge_id');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
		$this->db->join('pr_rent_leaseagreement','pr_rent_leaseagreement.agreement_id = pr_rent_charges.agreement_id');
		$this->db->where('pr_rent_charges_update.status','CONFIRM');
		$query = $this->db->get('pr_rent_charges_update');
		if($query->num_rows() > 0){
			return $query->num_rows();
		}else{
			return false;
		}
	}

	function getUpdateChargesByAgreementID($agreement_id){
		$this->db->select('pr_rent_charges.*,pr_config_renteepaytype.paytype_name');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
		$this->db->where('pr_rent_charges.agreement_id',$agreement_id);
		$this->db->where('pr_rent_charges.paytype_id !=','2');
		$query = $this->db->get('pr_rent_charges');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function getChargeByID($charge_id){
		$this->db->select('pr_rent_charges.*');
		$this->db->where('pr_rent_charges.charge_id',$charge_id);
		$query = $this->db->get('pr_rent_charges');
		if($query->num_rows() > 0){
			return $query->row();
		}else{
			return false;
		}
	}

	function updateCharges(){
		$this->db->where('charge_id',$this->input->post('charge_id'));
		$this->db->where('status','CONFIRM');
		$this->db->delete('pr_rent_charges_update');

		$data_array = array(
			'charge_id' => $this->input->post('charge_id'),
			'previous_amount' => $this->input->post('default_amount'),
			'new_amount' => $this->input->post('new_amount'),
			'update_by' => $this->session->userdata('userid'),
			'update_date' => date('Y-m-d')
		);
		$this->db->trans_start();
		$this->db->insert('pr_rent_charges_update',$data_array);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function rejectChargeAmountUpdate($update_id){
		$this->db->where('update_id',$update_id);
		$this->db->trans_start();
		$this->db->delete('pr_rent_charges_update');
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function confirmChargeAmountUpdate($update_id){
		$this->db->select('pr_rent_charges_update.*');
		$this->db->where('pr_rent_charges_update.update_id',$update_id);
		$query = $this->db->get('pr_rent_charges_update');
		if($query->num_rows() > 0){
			$update_data = $query->row();
			$data_array = array(
				'default_amount' => $update_data->new_amount
			);
			$this->db->where('charge_id',$update_data->charge_id);
			$this->db->trans_start();
			$this->db->update('pr_rent_charges',$data_array);
			$this->db->trans_complete();
			if ($this->db->trans_status() === FALSE) {
				$this->db->trans_rollback();
				return false;
			} else {
				$update_array = array(
					'status' => 'CONFIRMED',
					'confirm_by' => $this->session->userdata('userid'),
					'confirm_date' => date('Y-m-d')
				);
				$this->db->where('update_id',$update_id);
				$this->db->update('pr_rent_charges_update',$update_array);
				$this->db->trans_commit();
				return true;
			}
		}else{
			return false;
		}
	}

	function getRevisions(){
		$this->db->select('pr_rent_charges_update.*');
		$this->db->where('pr_rent_charges_update.status','CONFIRMED');
		$this->db->where('pr_rent_charges_update.charge_id',$this->input->post('charge_id'));
		$query = $this->db->get('pr_rent_charges_update');
		if($query->num_rows() > 0){
			return $query->result();
		}else{
			return false;
		}
	}

	function addBills(){
		$data_array = array(
			'charge_id' => $this->input->post('charge_id'),
			'agreement_id' => $this->input->post('agreement_id'),
			'bill_number' => $this->input->post('bill_number'),
			'bill_amount' => $this->input->post('bill_amount'),
			'due_date' => $this->input->post('due_date'),
			'description' => $this->input->post('bill_description'),
			'generate_by' => $this->session->userdata('userid'),
			'generate_date' => date('Y-m-d'),
			'generate_type' => 'manual',
			'status' => 'CONFIRM'
		);
		$this->db->trans_start();
		$this->db->insert('pr_rent_charges_bills',$data_array);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function editBills($bill_id){
		$data_array = array(
			'charge_id' => $this->input->post('charge_id'),
			'agreement_id' => $this->input->post('agreement_id'),
			'bill_number' => $this->input->post('bill_number'),
			'bill_amount' => $this->input->post('bill_amount'),
			'due_date' => $this->input->post('due_date'),
			'description' => $this->input->post('bill_description'),
			'generate_by' => $this->session->userdata('userid'),
			'generate_date' => date('Y-m-d'),
		);
		$this->db->trans_start();
		$this->db->where('bill_id',$bill_id);
		$this->db->update('pr_rent_charges_bills',$data_array);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function deleteBills(){
		$this->db->where('bill_id',$this->input->post('id'));
		$this->db->trans_start();
		$this->db->delete('pr_rent_charges_bills');
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function getChargeByAgreementId($agreement_id, $payType_id){
		$this->db->select('pr_rent_charges.*,pr_config_renteepaytype.*');
		$this->db->join('pr_config_renteepaytype','pr_config_renteepaytype.paytype_id = pr_rent_charges.paytype_id');
		$this->db->where('pr_rent_charges.paytype_id',$payType_id);
		$this->db->where('pr_rent_charges.agreement_id',$agreement_id);
		$query = $this->db->get('pr_rent_charges');
		if($query->num_rows() > 0){
			return $query->row();
		}else{
			return false;
		}
	}

	function getBillById($bill_id){
		$this->db->select('pr_rent_charges_bills.*');
		$this->db->where('pr_rent_charges_bills.bill_id',$bill_id);
		$query = $this->db->get('pr_rent_charges_bills');
		if($query->num_rows() > 0){
			return $query->row();
		}else{
			return false;
		}
	}
}