<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

class Property_model extends CI_Model {

    function __construct() {
        parent::__construct();
    }

    function get_max_property_id()
    {
        $this->db->select('MAX(property_id) as id');
        $query = $this->db->get('pr_rent_propertymain');
        if($query->num_rows() > 0)
            return $query->row()->id;
        else
            return false;
    }

    function getPropertyData($limit,$page)
    {
        $this->db->select('pr_rent_propertymain.*,pr_config_proptype.proptype_name');
        $this->db->join('pr_config_proptype','pr_config_proptype.proptype_id = pr_rent_propertymain.property_type');
        $this->db->order_by('pr_rent_propertymain.property_id','DESC');
        $this->db->limit($limit,$page);
        $query = $this->db->get('pr_rent_propertymain');
        if($query->num_rows() > 0)
            return $query->result();
        else
            return false;
    }

    function getAllProperties(){
        $this->db->select('pr_rent_propertymain.*,pr_config_proptype.proptype_name');
        $this->db->join('pr_config_proptype','pr_config_proptype.proptype_id = pr_rent_propertymain.property_type');
        $this->db->order_by('pr_rent_propertymain.property_id','DESC');
        $query = $this->db->get('pr_rent_propertymain');
        if($query->num_rows() > 0)
            return $query->result();
        else
            return false;
    }

    function getAllPropertyUnits(){
        $this->db->select('pr_rent_propertymain.*,pr_rent_propertyunit.*');
        $this->db->join('pr_rent_propertymain','pr_rent_propertymain.property_id = pr_rent_propertyunit.property_id');
        $this->db->order_by('pr_rent_propertymain.property_id');
        $this->db->order_by('pr_rent_propertyunit.unit_id');
        $query = $this->db->get('pr_rent_propertyunit');
        if($query->num_rows() > 0)
            return $query->result();
        else
            return false;
    }

    // function add_property_images($file_array,$property_id){
    //     $filearray = explode(",", $file_array);
    //       $filecount = count($filearray);
    
    //       for($i=0;$i<$filecount;$i++){
    
    //         $imageinsrtarr = array(
    //           'proprety_id' => $property_id,
    //           'image_name' => $filearray[$i]
    //         );
    
    
    //           $this->db->insert('pr_rent_propertyimages',$imageinsrtarr);
    //         }
    
    //         return true;
    //   }

    //   function add_unit_images($file_array,$unit_id){
    //     $filearray = explode(",", $file_array);
    //       $filecount = count($filearray);
    
    //       for($i=0;$i<$filecount;$i++){
    
    //         $imageinsrtarr = array(
    //           'unit_id' => $unit_id,
    //           'image_name' => $filearray[$i]
    //         );
    
    
    //           $this->db->insert('pr_rent_propertyunit_images',$imageinsrtarr);
    //         }
    
    //         return true;
    //   }

      function getAllAvailableUnits($limit,$page)
      {
        $this->db->select("pr_rent_propertyunit.*,pr_rent_propertymain.*,pr_config_proptype.proptype_name");
        $this->db->join("pr_rent_propertymain","pr_rent_propertymain.property_id = pr_rent_propertyunit.property_id");
        $this->db->join("pr_config_proptype","pr_config_proptype.proptype_id = pr_rent_propertymain.property_type");
        $this->db->where("pr_rent_propertyunit.status","PENDING");
        $this->db->where("pr_rent_propertymain.status","CONFIRMED");
        $this->db->limit($limit,$page);
        $query = $this->db->get("pr_rent_propertyunit");
        if($query->num_rows() > 0)
            return $query->result();
        else
            return false;
      }

      function getAllListedUnits($limit,$page)
      {
        
        $this->db->select("pr_rent_listing.*,pr_rent_listing.create_date as listed_date,pr_rent_listing.status as list_status,pr_rent_propertymain.*,pr_rent_propertyunit.*,pr_config_proptype.proptype_name");
        $this->db->join("pr_rent_propertyunit","pr_rent_propertyunit.unit_id = pr_rent_listing.unit_id");
        $this->db->join("pr_rent_propertymain","pr_rent_propertymain.property_id = pr_rent_propertyunit.property_id");
        $this->db->join("pr_config_proptype","pr_config_proptype.proptype_id = pr_rent_propertymain.property_type");
        $this->db->where("pr_rent_listing.status <>",'USED');
        $this->db->where("pr_rent_listing.status <>",'UNLISTED');
        if($limit){
          $this->db->limit($limit,$page);
        }else{
          $statuses = array('PENDING', 'USED');
          $this->db->where_not_in("pr_rent_listing.status",$statuses);
        }
        $query = $this->db->get("pr_rent_listing");
        if($query->num_rows() > 0)
            return $query->result();
        else
            return false;
      }

      function getAllListedConfirmedUnits()
      {
        
        $this->db->select("pr_rent_listing.*,pr_rent_listing.status as list_status,pr_rent_propertymain.*,pr_rent_propertyunit.*,pr_config_proptype.proptype_name");
        $this->db->join("pr_rent_propertyunit","pr_rent_propertyunit.unit_id = pr_rent_listing.unit_id");
        $this->db->join("pr_rent_propertymain","pr_rent_propertymain.property_id = pr_rent_propertyunit.property_id");
        $this->db->join("pr_config_proptype","pr_config_proptype.proptype_id = pr_rent_propertymain.property_type");
        $this->db->where("pr_rent_listing.status",'CONFIRMED');
        $query = $this->db->get("pr_rent_listing");
        if($query->num_rows() > 0)
            return $query->result();
        else
            return false;
      }

      function getPropertybyId($id){
        $this->db->select('*');
        $this->db->where('property_id',$id);
        $query = $this->db->get('pr_rent_propertymain');
        if($query->num_rows() > 0)
            return $query->row();
        else
            return false;
    }

    function getUnitbyId($id){
        $this->db->select('pr_rent_propertymain.*,pr_rent_propertyunit.*');
        $this->db->join('pr_rent_propertymain','pr_rent_propertymain.property_id = pr_rent_propertyunit.property_id');
        $this->db->where('unit_id',$id);
        $query = $this->db->get('pr_rent_propertyunit');
        if($query->num_rows() > 0)
            return $query->row();
        else
            return false;
    }

    function get_listing_data_by_id($id)
    {
      $this->db->select('pr_rent_listing.*,pr_rent_listing.status as list_status,pr_rent_listing.check_by as list_check,pr_rent_listing.confirm_by as list_confirm,pr_rent_propertymain.prop_address1,pr_rent_propertymain.prop_adress2,pr_rent_propertymain.city,pr_rent_propertymain.property_details,pr_rent_propertyunit.*,pr_rent_propertymain.property_name,pr_rent_propertymain.main_img,pr_agent.title
      ,pr_agent.initial,pr_agent.surname,pr_agent.mobile,pr_agent.email,pr_rent_propertymain.location_map,pr_rent_propertymain.property_id');
      $this->db->join('pr_rent_propertyunit','pr_rent_propertyunit.unit_id = pr_rent_listing.unit_id');
      $this->db->join('pr_rent_propertymain','pr_rent_propertymain.property_id = pr_rent_propertyunit.property_id');
      $this->db->join('pr_agent','pr_agent.agent_id = pr_rent_listing.agent_id','left');
      $this->db->where('pr_rent_listing.list_id',$id);
      $query = $this->db->get('pr_rent_listing');
      if($query->num_rows() > 0)
          return $query->row();
      else
          return false;
    }

    function getMovedInPropertyUnits(){
        $this->db->select("pr_rent_propertyunit.*,pr_rent_propertymain.*,pr_config_proptype.proptype_name,pr_rent_leaseagreement.agreement_id,pr_rent_leaseagreement.agreement_code,cm_customerms.first_name,cm_customerms.last_name,cm_customerms.id_number");
        $this->db->join("pr_rent_propertymain","pr_rent_propertymain.property_id = pr_rent_propertyunit.property_id");
        $this->db->join("pr_config_proptype","pr_config_proptype.proptype_id = pr_rent_propertymain.property_type");
        $this->db->join("pr_rent_leaseagreement","pr_rent_leaseagreement.unit_id = pr_rent_propertyunit.unit_id");
        $this->db->join("pr_rent_application","pr_rent_application.application_id = pr_rent_leaseagreement.application_id");
        $this->db->join("cm_customerms","cm_customerms.cus_code = pr_rent_application.cus_code");
        $this->db->order_by('pr_rent_leaseagreement.agreement_id','DESC');
        $this->db->where("pr_rent_leaseagreement.status","MOVED-IN");
        $query = $this->db->get("pr_rent_propertyunit");
        if($query->num_rows() > 0)
            return $query->result();
        else
            return false;
    }

    function getRentPropertyUnits(){
        $this->db->select("pr_rent_propertyunit.*,pr_rent_propertymain.*,pr_config_proptype.proptype_name,pr_rent_leaseagreement.agreement_id, pr_rent_leaseagreement.list_id,pr_rent_leaseagreement.agreement_code,cm_customerms.first_name,cm_customerms.last_name,cm_customerms.id_number");
        $this->db->join("pr_rent_propertymain","pr_rent_propertymain.property_id = pr_rent_propertyunit.property_id");
        $this->db->join("pr_config_proptype","pr_config_proptype.proptype_id = pr_rent_propertymain.property_type");
        $this->db->join("pr_rent_leaseagreement","pr_rent_leaseagreement.unit_id = pr_rent_propertyunit.unit_id");
        $this->db->join("pr_rent_application","pr_rent_application.application_id = pr_rent_leaseagreement.application_id");
        $this->db->join("cm_customerms","cm_customerms.cus_code = pr_rent_application.cus_code");
        $this->db->order_by('pr_rent_leaseagreement.agreement_id','DESC');
        $this->db->where("pr_rent_leaseagreement.status","MOVED-IN");
        $this->db->or_where("pr_rent_leaseagreement.status","TERMINATED");
        $this->db->or_where("pr_rent_leaseagreement.status","EXPIRED");
        $query = $this->db->get("pr_rent_propertyunit");
        if($query->num_rows() > 0)
            return $query->result();
        else
            return false;
    }

    function getRentPropertyUnitsMovedIn(){
        $this->db->select("pr_rent_propertyunit.*,pr_rent_propertymain.*,pr_config_proptype.proptype_name,pr_rent_leaseagreement.agreement_id, pr_rent_leaseagreement.list_id,pr_rent_leaseagreement.agreement_code,cm_customerms.first_name,cm_customerms.last_name,cm_customerms.id_number");
        $this->db->join("pr_rent_propertymain","pr_rent_propertymain.property_id = pr_rent_propertyunit.property_id");
        $this->db->join("pr_config_proptype","pr_config_proptype.proptype_id = pr_rent_propertymain.property_type");
        $this->db->join("pr_rent_leaseagreement","pr_rent_leaseagreement.unit_id = pr_rent_propertyunit.unit_id");
        $this->db->join("pr_rent_application","pr_rent_application.application_id = pr_rent_leaseagreement.application_id");
        $this->db->join("cm_customerms","cm_customerms.cus_code = pr_rent_application.cus_code");
        $this->db->order_by('pr_rent_leaseagreement.agreement_id','DESC');
        $this->db->where("pr_rent_leaseagreement.status","MOVED-IN");
        $query = $this->db->get("pr_rent_propertyunit");
        if($query->num_rows() > 0)
            return $query->result();
        else
            return false;
    }

    function getUnitsbyPropertyID($pid){
        $this->db->select('pr_rent_propertymain.property_name,pr_rent_propertymain.prop_address1,pr_rent_propertymain.prop_adress2,pr_rent_propertymain.property_details,pr_rent_propertyunit.*');
        $this->db->join('pr_rent_propertymain','pr_rent_propertymain.property_id = pr_rent_propertyunit.property_id');
        $this->db->where('pr_rent_propertymain.property_id',$pid);
        $query = $this->db->get('pr_rent_propertyunit');
        if($query->num_rows() > 0)
            return $query->result();
        else
            return false;
    }

    function checkPropertyTypehasUnits($id){
        $this->db->select('has_units');
        $this->db->where('proptype_id',$id);
        $query = $this->db->get('pr_config_proptype');
        if($query->num_rows() > 0){
            $units =  $query->row()->has_units;
            if($units == '1'){
                return true;
            }else{
                return false;
            }
          }else{
            return false;
        }
    }

    function checkDeletePropertyType($propertyTypeId)
    {
        $this->db->select('*');
        $this->db->where('property_type',$propertyTypeId);
        $query = $this->db->get('pr_rent_propertymain');
        if($query->num_rows() > 0)
            return false;
        else
            return true;
           
    }

    function getUnitsForRelease($limit = '',$page = '')
    {
        $this->db->select("pr_rent_leaseagreement.*,pr_rent_listing.create_date as listed_date,pr_rent_listing.available_from,pr_rent_listing.status as list_status,pr_rent_propertymain.*,pr_rent_propertyunit.*,pr_config_proptype.proptype_name");
        $this->db->join("pr_rent_listing","pr_rent_listing.list_id = pr_rent_leaseagreement.list_id");
        $this->db->join("pr_rent_propertyunit","pr_rent_propertyunit.unit_id = pr_rent_leaseagreement.unit_id");
        $this->db->join("pr_rent_propertymain","pr_rent_propertymain.property_id = pr_rent_leaseagreement.property_id");
        $this->db->join("pr_config_proptype","pr_config_proptype.proptype_id = pr_rent_propertymain.property_type");
        $this->db->where("pr_rent_propertyunit.status",'LISTED');
        $this->db->where("pr_rent_leaseagreement.status","EXPIRED");
        if($limit != '')
          $this->db->limit($limit,$page);
        $query = $this->db->get("pr_rent_leaseagreement");
        if($query->num_rows() > 0)
            return $query->result();
        else
            return false;
    }
   
    function getSearchedReleaseUnits()
    {
        $this->db->select("pr_rent_leaseagreement.*,pr_rent_listing.create_date as listed_date,pr_rent_listing.available_from,pr_rent_listing.status as list_status,pr_rent_propertymain.*,pr_rent_propertyunit.*,pr_config_proptype.proptype_name");
        $this->db->join("pr_rent_listing","pr_rent_listing.list_id = pr_rent_leaseagreement.list_id");
        $this->db->join("pr_rent_propertyunit","pr_rent_propertyunit.unit_id = pr_rent_leaseagreement.unit_id");
        $this->db->join("pr_rent_propertymain","pr_rent_propertymain.property_id = pr_rent_leaseagreement.property_id");
        $this->db->join("pr_config_proptype","pr_config_proptype.proptype_id = pr_rent_propertymain.property_type");
        $this->db->where("pr_rent_propertyunit.status",'LISTED');
        $this->db->where("pr_rent_leaseagreement.status","EXPIRED");
        $this->db->like("pr_rent_leaseagreement.unit_id",$this->input->post('property'));
        $query = $this->db->get("pr_rent_leaseagreement");
        if($query->num_rows() > 0)
            return $query->result();
        else
            return false;
    }


    function releaseUnit($agreement_id)
    { 
        if($agreement_id)
        {     
            $agreemet_data = $this->get_agreement_data_by_id($agreement_id);
            if($agreemet_data)
            {   
                $data = array(
                    "agreement_id"=>$agreement_id,
                    "release_date"=>date('Y-m-d'),
                    "release_by" =>$this->session->userdata('userid'),

                );

                $this->db->insert('pr_rent_releaseunit',$data);
    
                $this->db->where('unit_id',$agreemet_data->unit_id);
                $this->db->update('pr_rent_propertyunit',array('status'=>'PENDING'));

                return true;
            }
            else
                return false;

        }
        else
            return false;
    }

    function get_agreement_data_by_id($agreement_id)
    {
        $this->db->select('*');
        $this->db->where('agreement_id',$agreement_id);
        $query = $this->db->get('pr_rent_leaseagreement');
        if($query->num_rows() > 0)
            return $query->row();
        else
            return false;
    }

    function add_property_images($pr_id,$new_name)
    {
        $upload_array = array('proprety_id'=>$pr_id,'image_name'=>$new_name);
        $this->db->insert('pr_rent_propertyimages',$upload_array);
    }

    function delete_property_images($pr_id)
    {
        $this->db->where('proprety_id',$pr_id);
        $this->db->delete('pr_rent_propertyimages');
    }

    function searchPropertyData()
    {
        $this->db->select('pr_rent_propertymain.*,pr_config_proptype.proptype_name');
        $this->db->join('pr_config_proptype','pr_config_proptype.proptype_id = pr_rent_propertymain.property_type');
        $this->db->like('property_id',$this->input->post('property'));
        $this->db->like('property_type',$this->input->post('propertyType'));
        $this->db->like('unit_count',$this->input->post('units_search'));
        $this->db->order_by('pr_rent_propertymain.property_id','DESC');
        $query = $this->db->get('pr_rent_propertymain');
        if($query->num_rows() > 0)
            return $query->result();
        else
            return false;
    }

    function searchListedUnits()
    {
      $this->db->select("pr_rent_listing.*,pr_rent_listing.status as list_status,pr_rent_propertymain.*,pr_rent_propertyunit.*,pr_config_proptype.proptype_name");
      $this->db->join("pr_rent_propertyunit","pr_rent_propertyunit.unit_id = pr_rent_listing.unit_id");
      $this->db->join("pr_rent_propertymain","pr_rent_propertymain.property_id = pr_rent_propertyunit.property_id");
      $this->db->join("pr_config_proptype","pr_config_proptype.proptype_id = pr_rent_propertymain.property_type");
      $this->db->where("pr_rent_listing.status <>",'USED');
      $this->db->where("pr_rent_listing.status <>",'UNLISTED');
      $this->db->like("pr_rent_listing.available_from",$this->input->post('availableDate'));
      $this->db->like("pr_rent_listing.unit_id",$this->input->post('property'));
      $this->db->like("pr_rent_propertymain.property_type",$this->input->post('propertyType'));
      $query = $this->db->get("pr_rent_listing");
      if($query->num_rows() > 0)
          return $query->result();
      else
          return false;
    }

    function searchAvailableUnits()
    {
      $this->db->select("pr_rent_propertyunit.*,pr_rent_propertymain.*,pr_config_proptype.proptype_name");
      $this->db->join("pr_rent_propertymain","pr_rent_propertymain.property_id = pr_rent_propertyunit.property_id");
      $this->db->join("pr_config_proptype","pr_config_proptype.proptype_id = pr_rent_propertymain.property_type");
      $this->db->where("pr_rent_propertyunit.status","PENDING");
      $this->db->where("pr_rent_propertymain.status","CONFIRMED");
      $this->db->like("pr_rent_propertyunit.unit_id",$this->input->post('property'));
      $this->db->like("pr_rent_propertymain.property_type",$this->input->post('propertyType'));
      $query = $this->db->get("pr_rent_propertyunit");
      if($query->num_rows() > 0)
          return $query->result();
      else
          return false;
    }

    function add_unit_images($unit_id,$file_name){

        $upload_array = array('unit_id'=>$unit_id,'image_name'=>$file_name);
        $this->db->insert('pr_rent_propertyunit_images',$upload_array);
    }

    function delete_unit_images($unit_id){

        $this->db->where('unit_id',$unit_id);
        $this->db->delete('pr_rent_propertyunit_images');
    }

    function check_released($agreement_id)
    {
        $this->db->select('*');
        $this->db->where('agreement_id',$agreement_id);
        $query = $this->db->get('pr_rent_releaseunit');
        if($query->num_rows() > 0)
            return false;
        else
            return true;
    }

    function get_contact_details($agentId)
    {
        $this->db->select('*');
        $this->db->where('agent_id',$agentId);
        $query = $this->db->get('pr_agent');
        if($query->num_rows() > 0)
            return $query->row();
        else
            return false;
    }

}