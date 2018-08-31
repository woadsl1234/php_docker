<?php

use app\model\school_model;

class area_controller extends Controller
{
    public function action_children()
    {
        $province = (int)request('province', 0, 'get');
        $city = (int)request('city', 0, 'get');
        $area = new area();
        echo json_encode($area->get_children($province, $city));
    }
/*
    public function action_schools()
    {
        $school_list = (new school_model())->get_school_list();
        echo json_encode(['status' => 'success', 'data' => $school_list]);
    }*/

/*    public function action_buildings()
    {
        $school_id = request('school_id', 0);
        $all_buildings = [
            [],
            //杭州电子科技大学
            ["2","3","4南","4北","5南","5北","6南","6北","8","10南","10北","11南","11北","12南","12北","13",
                "14","15","16","18","21","22","27","28","29","30","31","32东","32西","研究生公寓","其他"],
            //浙江传媒学院
            ["L楼","1","12","13","14","16","17","18","19","20","21","S楼A区","S楼B区","S楼C区","S楼D区","其他"],
            //中国计量大学
            ["1东","1西","2东","2西","3南","3北","4东","4西","5南","5北","6东","6西","7南","7北","8东","8西","9南","9北",
                "10南","10北","11南","11北","13","其他"],
            //浙江理工大学
            ["一区1","一区2","一区3南","一区3北","一区4南","一区4北","二区2东","二区2西","二区4南","二区4北","二区5南","二区5北",
                "二区6南","二区6北","二区12","三区1西","三区1东","三区3西","三区3东","三区7","三区8","三区9","三区10","三区11","其他"]
        ];
        echo json_encode(['status' => 'success', 'data' => $all_buildings[$school_id]]);
    }*/
}