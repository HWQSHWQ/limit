<?php


    /**
     * @param $where
     * @param $condition
     * @return array
     *
     * 该方法的思想是：
     *  1=>10;2=>13,3=>1,4=>29;5=>12
     *  要显示的第一页  一页有 20条
     *  则 循环
     *  其中 10 - 20 = -10
     *      -10 + 13 = 3 ，则只需要 1 2 天的数据；
     *
     *      若要显示第二页 则
     *      10 - 40 = -30
     *      -30 + 13 = -17
     *      -17 + 1 = -16
     *      -16 + 29 = 13  则需要 1 ，2 ，3 ，4天的数据，但是，第一天-30+20 = -10 < 0 ，则第一天的数据用不上 ;-17+20 = 3 >0 则表示用得到第三天的数据
     *
     *
     */

    function getSimpleData($where,$condition)
    {
    $server_id = $condition['server_id'];
    $page_no   = $condition['page_no'];
    $page_size = $condition['page_size'];
    $count     = 0;
    $day_num   = array();
    if ($condition['start_day'] && $condition['end_day'])
    {
    //            $daylist = Local\Common::getDatelist($condition['start_day'], $condition['end_day']);
    $daylist = [];
    //获取开始日期和结束日期的列表
    $i = $start_day = date('Ymd',strtotime($condition['start_day']));
    $end_day   = date('Ymd',strtotime($condition['end_day']));
    while( $i <= $end_day)
    {
    $daylist[] = $i;
    $i = date('Ymd',strtotime($i)+86400);
    }

    krsort($daylist);
    foreach ($daylist as $day)
    {
    //判断表是否存在
    $isexit = $this->fetchAll("SHOW TABLES LIKE 'gamelog_{$server_id}_{$day}'");
    if (!empty($isexit))
    {
    $sql_count     = "(SELECT count(*) cou FROM gamelog_" . $server_id . "_" . $day . $where . ")";
    $num           = $this->fetch($sql_count);
    $count         += $num['cou'];
    $day_num[$day] = (int)$num['cou'];
    }
    }

    /*****处理数据start 采用累加******/
    foreach ($day_num as $key => $row)
    {
    if ($row == 0)
    {
    unset($day_num[$key]);
    }
    }
    if(count($day_num) == 0)
    {
    exit ('数据为空');
    }

    $table_id = $table_special = array();
    $all_num  = 0;//总人数
    $n        = 0;
    $shownum  = $page_no * $page_size;
    $have     = 0;//是否足够，小于0则不够 大于0 则够
    $exits    = 0;//是否在对应区间呢
    $key_arr  = [];
    $max_day  = max(array_keys($day_num));

    foreach ($day_num as $key => $row) //确定使用了那些区间
    {
    $all_num += (int)$row; //累加
    $n++;
    if ($key == $max_day)    //第一个减
    {
    $have = $row - $shownum;
    }
    else
    {
    $have = $have + $row;
    }
    $exits = $have + $page_size;
    if ($exits > 0) //表示要用该区间的值
    {
    $key_arr[$key] = $exits;
    }
    if ($have >= 0)
    {
    break;
    }
    }

    if(empty($key_arr))
    {
    return array(1,[]);
    }

    $b_num         = 0;//上一天
    $key_config    = array_flip($key_arr);
    $max           = max(array_keys($key_arr));
    $min           = min(array_keys($key_arr));
    $key_arr_unset = $key_arr;
    unset($key_arr_unset[$min]);
    $have_size = array_pop($key_arr_unset);

    if (count($key_arr) == 1)
    {
    $keya            = max(array_keys($key_arr));
    $tmpa            = $day_num[$keya] - $key_arr[$keya];
    $table_id[$keya] = array($keya => $tmpa . ',' . $page_size);
    }
    else
    {
    foreach ($key_arr as $key => $row)
    {
    if ($key == $max)
    {
    if ($row >= $page_size)
    {
    $table_id[$key] = array($key => ($page_no - 1) * $page_size . ',' . $page_size);
    }
    else
    {
    $tmp            = $page_size - $row;
    $prev           = $day_num[$key] - $row;
    $table_id[$key] = array($key => $prev . ',' . $row);
    }
    }else if ($key == $min)
    {
    $c              = $page_size - $have_size;
    $table_id[$key] = array($key => '0,' . $c);
    } else
    {
    $tmp = $tmp - $day_num[$key];
    if ($tmp + $page_size > 0)
    {
    if ($key > $min && $key < $max)
    {
    prev($key_arr);
    $a              = $row;
    $b              = prev($key_arr);
    $table_id[$key] = array($key => '0,' . ($a - $b));
    }
    }
    }
    }
    }

    if ($all_num <= $page_size) //全部显示 一页能显示所有数据
    {
    foreach ($day_num as $key => $row)
    {
    $limit_prev     = 0;
    $table_id[$key] = array($key => $limit_prev . ',' . $row);
    }
    }
    /*****处理数据end 采用累加******/

    krsort($table_id);
    $sql = array();
    foreach ($table_id as $key => $row) {
    foreach ($row as $k => $r) {
    //判断表是否存在
    $isexit = $this->fetchAll("SHOW TABLES LIKE 'gamelog_{$server_id}_{$k}'");
    if (!empty($isexit)) {
    $sql[] = "(SELECT * FROM gamelog_{$server_id}_{$k} {$where}   LIMIT {$r})";
    }
    }
    }

    if (empty($sql)) {
    return array(0, array());
    } else {
    $query    = implode(' UNION ALL ', $sql);
    $arr_stat = $this->fetchAll($query);
    return array($count, $arr_stat);
    }
    }
}
?>