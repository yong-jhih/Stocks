<?php
include('inside_header.php');
?>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script type="text/javascript">
    google.charts.load('current', {
        'packages': ['corechart']
    });
</script>
<h1>EC</h1>
<div class="row input-group">
    <div class="col-md-12">
        <div style="display: flex; align-items: center;">
            <button onclick="google.charts.setOnLoadCallback(drawVisualization)" class="btn btn-primary" style="margin-left: 10px;">三大法人買賣超</button>
            <p style="color:red; margin: 0 0 0 10px;">顯示60日內資料</p>
        </div>
    </div>
</div>
<div id="chart_div" style="width: 1080px; height: 900;"></div>
<script>
    function drawVisualization() {
        Swal.fire({
            icon: 'success',
            title: '成功',
            text: '資料生成中請稍候',
            // timer: 36000,
            showConfirmButton: false
        });
        $.ajax({
            type: "post",
            url: 'getMain.php',
            data: {
                "period": 60,
            },
            success: function(e) {
                Swal.close();
                let b = [
                    ['date', 'Percentage', 'TWII']
                ];
                Object.entries(JSON.parse(e)).forEach(([key, value]) => {
                    b.push([new Date(key), value['percentage'], parseFloat(value['TWII'])])
                });
                var data = google.visualization.arrayToDataTable(b);

                let options = {
                    series: { // 設置圖表系列，將兩個資料系列 (Percentage, TWII) 分別指定到不同的 Y 軸
                        0: {
                            targetAxisIndex: 0, // Percentage 系列使用左側 Y 軸 (索引 0)
                            type: 'bars', // 類型為柱狀圖
                            color: '#4285F4' // 設定柱狀圖顏色
                        },
                        1: {
                            targetAxisIndex: 1, // TWII 系列使用右側 Y 軸 (索引 1)
                            type: 'line', // 類型為折線圖
                            color: '#EA4335' // 設定折線圖顏色
                        }
                    },
                    vAxes: { // 設置兩個垂直軸的屬性
                        0: { // Y 軸 1 (左側 Y 軸, 索引 0)
                            title: '百分比', // Y 軸標題
                            format: '#,##0.00%', // 數值格式化為百分比
                        },
                        1: { // Y 軸 2 (右側 Y 軸, 索引 1)
                            title: '數值 (TWII)', // Y 軸標題
                            minValue: 22000, // 數值範圍最小值
                            maxValue: 28000, // 數值範圍最大值
                            format: 'decimal', // 數值格式為純數字
                            gridlines: { // 在這邊可以加入網格線設定，讓視覺效果更清晰
                                count: 5 // 設定網格線數量
                            }
                        }
                    },
                    hAxis: { // 設置 X 軸 (水平軸) 的屬性
                        title: '日期', // X 軸標題
                        format: 'MM-dd' // 日期格式
                    },
                    title: '散戶多空比', // 設定圖表標題
                    legend: { // 啟用圖例 (legend)
                        position: 'bottom' // 將圖例放在底部
                    },
                    tooltip: { // 設定工具提示 (tooltip)
                        isHtml: true, // 啟用 HTML 格式的工具提示
                        trigger: 'focus' // 觸發方式為滑鼠移到資料點上
                    },
                    animation: { // 設定動畫效果 (可選)
                        duration: 1000,
                        easing: 'out',
                        startup: true
                    },
                    width: 1100,
                    height: 600
                };

                var chart = new google.visualization.ComboChart(document.getElementById('chart_div'));
                chart.draw(data, options);
            },
            error: function(e) {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: '錯誤',
                    text: JSON.parse(e).message,
                });
            }
        });
    }
</script>