<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Parking_model extends CI_Model {

    function __construct() {
        parent::__construct();
    }

	function getParkingSpaces($property_id,$unit_id	){
		$this->db->select('*');
		$this->db->where('property_id',$property_id);
		$this->db->where('unit_id',$unit_id);
        $query = $this->db->get('pr_rent_parking');
        if($query->num_rows() > 0)
            return $query->result();
        else
            return false;
	}

	function addParkingSpaces($property_id, $unit_id, $rego, $slot_number){
		$data = array(
			'property_id' => $property_id,
			'unit_id' => $unit_id,
			'rego' => $rego,
			'slot_number' => $slot_number
		);
		$this->db->trans_start();
		$this->db->insert('pr_rent_parking', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function checkRemaining($property_id, $unit_id){
		$this->db->select('*');
		$this->db->where('property_id',$property_id);
        $query = $this->db->get('pr_rent_propertymain');
        if($query->num_rows() > 0){
            $parking_spaces = $query->row()->parkings;
			if($parking_spaces == '0'){
				return true;
			}
			$used_spaces = 0;
			$this->db->where('property_id',$property_id);
			$used_spaces =  $this->db->count_all_results('pr_rent_parking');
			if($parking_spaces <= $used_spaces){
				return true;
			}else{
				return false;
			}
		}else
            return false;
	}

	function updateParkingSpace($parking_id, $rego){
		$data = array(
			'rego' => $rego
		);
		$this->db->trans_start();
		$this->db->where('id', $parking_id);
		$this->db->update('pr_rent_parking', $data);
		$this->db->trans_complete();
		if ($this->db->trans_status() === FALSE) {
			$this->db->trans_rollback();
			return false;
		} else {
			$this->db->trans_commit();
			return true;
		}
	}

	function deleteParkingSpace($parking_id){
		$this->db->where('id', $parking_id);
		$this->db->delete('pr_rent_parking');
		return ($this->db->affected_rows() != 1) ? false : true;
	}

	function checkDuplicates($rego){
		$this->db->select('pr_rent_propertymain.property_name, pr_rent_propertyunit.unit_name,pr_rent_parking.slot_number');
		$this->db->join('pr_rent_propertymain','pr_rent_propertymain.property_id = pr_rent_parking.property_id');
		$this->db->join('pr_rent_propertyunit','pr_rent_propertyunit.unit_id = pr_rent_parking.unit_id');
		$this->db->where('pr_rent_parking.rego',$rego);
        $query = $this->db->get('pr_rent_parking');
        if($query->num_rows() > 0)
            return $query->row();
        else
            return false;
	}

	function checkSlot($property_id, $unit_id, $slot_number){
		$this->db->select('pr_rent_parking.rego');
		$this->db->join('pr_rent_propertymain','pr_rent_propertymain.property_id = pr_rent_parking.property_id');
		$this->db->join('pr_rent_propertyunit','pr_rent_propertyunit.unit_id = pr_rent_parking.unit_id');
		$this->db->where('pr_rent_parking.property_id',$property_id);
		$this->db->where('pr_rent_parking.slot_number',$slot_number);
        $query = $this->db->get('pr_rent_parking');
        if($query->num_rows() > 0)
            return $query->row();
        else
            return false;
	}
}