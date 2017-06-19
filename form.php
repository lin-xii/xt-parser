<!DOCTYPE html>
<head>
    <meta charset="utf-8">
    <title>xt-parser</title>
</head>
<body>
<?php
$xtfile = $_FILES['xtfile'];
if ($xtfile['error'] > 0) die('Error: upload failed');// name, type, size, tmp_name

$filepath = '/tmp/trace';
$filename = $xtfile['name'];
if (!file_exists($filepath)) {
    mkdir($filepath, 0777);
    chmod($filepath, 0777);
}
// if (file_exists($filepath.'/'.$filename)) die('<b>'.$filename.'</b>'.' already exists.');
if (file_exists($filepath.'/'.$filename)) $filename = date('YmdHis').'.'.$filename;

if (!move_uploaded_file($xtfile['tmp_name'], "$filepath/".$filename)) die("Stored in: $filepath/".'<b>'.$filename.'</b>');

$trace_date = parseTrace($filepath.'/'.$filename);

function parseTrace($file)
{
    $fp = fopen($file, 'r');
    $arrTime = array();
    $arrMemory = array();
    $arrDetail = array();
    $arrKey = 0;
    $lineNum = 0;

    while(!feof($fp)){
        ++$lineNum;
        $row = trim(fgets($fp));
        if (strpos($row, '=>')!==false) {
            continue;
        }
        $arr_now = preg_split('#\s+#', $row);

        if (count($arr_now) > 3 && is_numeric($arr_now[0])) {
            $time_format = $arr_now[0]*1000; //时间消耗, 变成整数, 单位ms
            $memory_format = $arr_now[1]/1024; //内存消耗, 缩小数量级, 单位KB
            $arrTime[] = $time_format;
            $arrMemory[] = $memory_format;

            $tmp = array();
            // if ($arrKey==0) {
            //     $arrDetail[$arrKey-1]['time']=0;
            // }
            $tmp['time'] = $time_format;
            $tmp['consuming'] = $time_format-$arrDetail[$arrKey-1]['time'];
            $tmp['memory'] = $memory_format;
            $tmp['operation'] = $arr_now[2];
            $tmp['location'] = array_pop($arr_now);
            $tmp['function'] = implode(array_slice($arr_now,3));//$arr_now[3]
            $tmp['line'] = $lineNum;
            $arrDetail[$arrKey] = $tmp;
            ++$arrKey;
        }
    }
    unlink($file);
    return array($arrTime, $arrMemory, $arrDetail);
}
?>
    <!-- 为ECharts准备一个具备大小（宽高）的Dom -->
    <div id="main" style="height:1600px"></div>
    <!-- ECharts单文件引入 -->
    <script src="./js/echarts.js"></script>
    <script type="text/javascript">

    // 基于准备好的dom，初始化echarts图表
    var myChart = echarts.init(document.getElementById('main'));
    var xAxisData = [<?=implode(',', range(0, count($trace_date[1])))?>];
    var minTime = <?=min($trace_date[0])?>;
    var maxTime = <?=max($trace_date[0])?>;
    var minMemory = <?=min($trace_date[1])?>;
    var maxMemory = <?=max($trace_date[1])?>;
    var timeDate = [<?=implode(',', array_values($trace_date[0]))?>];
    var memoryData = [<?=implode(',', array_values($trace_date[1]))?>];
    var detailData = <?=json_encode($trace_date[2])?>;

    option = {
        title: {
            text: '耗时/内存--<?=$filename?>',
            subtext: '用于分析xdebug的trace日志',
            x: 'center'
        },
        tooltip : {
            trigger: 'axis',
            axisPointer:{
                show: true,
                type : 'cross',
                lineStyle: {
                    type : 'dashed',
                    width : 1
                }
            },
            formatter : function (params) {
                var timeData = params[0];
                var memeoryData = params[1];
                var arrKey = timeData.axisValue;

                str = '时间: '+detailData[arrKey].time+" ms<br>";
                // if (detailData[parseInt(arrKey)+1] != 'undefined') {str += '耗时: '+detailData[parseInt(arrKey)+1].consuming+" ms<br>";}
                str += '耗时: '+detailData[parseInt(arrKey)+1].consuming+" ms<br>";
                str += '内存: '+detailData[arrKey].memory+" kb<br>";
                str += '函数名: '+detailData[arrKey].function+"<br>";
                str += '函数位置: '+detailData[arrKey].location+"<br>";;
                str += '日志行数: '+detailData[arrKey].line;

                return str;
            }
        },
        legend: {
            data:['耗时','内存'],
            x: 'left'
        },
        toolbox: {
            feature: {
                dataZoom: {
                    yAxisIndex: 'none'
                },
                restore: {},
                saveAsImage: {}
            }
        },
        axisPointer: {
            link: {xAxisIndex: 'all'}
        },
        dataZoom: [
            {
                show: true,
                realtime: true,
                start: 30,
                end: 70,
                xAxisIndex: [0, 1]
            },
            {
                type: 'inside',
                realtime: true,
                start: 30,
                end: 70,
                xAxisIndex: [0, 1]
            }
        ],
        grid: [{
            // left: 10%,
            // right: 50,
            height: '40%'
        }, {
            // left: 50,
            // right: 50,
            top: '55%',
            height: '40%'
        }],
        xAxis : [
            {
                type : 'category',
                boundaryGap : false,
                axisLine: {onZero: true},
                data: xAxisData
            },
            {
                gridIndex: 1,
                type : 'category',
                boundaryGap : false,
                axisLine: {onZero: true},
                data: xAxisData,
                position: 'top'
            }
        ],
        yAxis : [
            {
                name : '耗时(ms)',
                type : 'value',
                min : minTime,
                max : maxTime
            },
            {
                gridIndex: 1,
                name : '内存(kb)',
                type : 'value',
                inverse: true,
                min : minMemory,
                max : maxMemory
            }
        ],
        series : [
            {
                name:'耗时',
                type:'line',
                symbolSize: 8,
                hoverAnimation: false,
                data:timeDate
            },
            {
                name:'内存',
                type:'line',
                xAxisIndex: 1,
                yAxisIndex: 1,
                symbolSize: 8,
                hoverAnimation: false,
                data:memoryData
            }
        ]
    };
    // 为echarts对象加载数据
    myChart.setOption(option);
    </script>
</body>
</html>