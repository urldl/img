<?php
$base_upload_dir = __DIR__ . '/s/'; // 如存放在当前程序目录下直接/ 即可如果其他目录前后都要/包含，如：/s/

$max_filename_length = 180; // 最大文件名长度

// 获取服务器的最大上传文件大小和内存限制
$max_upload_size = ini_get('upload_max_filesize');
$max_memory_limit = ini_get('memory_limit');

// 生成随机文件名
function generateRandomFileName($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

// 处理文件上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $name = $_FILES['file']['name'];
    $file_tmp = $_FILES['file']['tmp_name'];
    $error = $_FILES['file']['error'];

    // 获取当前年月
    $year_month = date('Y/m');
    $upload_dir = $base_upload_dir . $year_month;

    // 检查并创建目录
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true) && !is_dir($upload_dir)) {
            echo json_encode(['error' => '无法创建目录: ' . $upload_dir]);
            exit;
        }
    }

    // 生成文件扩展名
    $file_extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

    // 生成随机文件名
    $randomFileName = generateRandomFileName() . '.' . $file_extension;

    // 使用相对路径获取目标文件
    $target_file = $upload_dir . '/' . $randomFileName;

    // 检查上传错误
    if ($error !== UPLOAD_ERR_OK) {
        switch ($error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                echo json_encode(['error' => '文件过大，最大允许上传：' . $max_upload_size]);
                exit;

            case UPLOAD_ERR_PARTIAL:
                echo json_encode(['error' => '文件部分上传失败']);
                exit;

            case UPLOAD_ERR_NO_FILE:
                echo json_encode(['error' => '没有文件被上传']);
                exit;

            case UPLOAD_ERR_CANT_WRITE:
                echo json_encode(['error' => '写入失败，无法保存文件']);
                exit;

            case UPLOAD_ERR_EXTENSION:
                echo json_encode(['error' => '文件上传被扩展程序阻止']);
                exit;

            default:
                echo json_encode(['error' => '上传失败，错误代码：' . $error]);
                exit;
        }
    }

    // 检查硬盘空间
    if (disk_free_space($upload_dir) < filesize($file_tmp)) {
        echo json_encode(['error' => '硬盘空间不足，请清理磁盘后再进行操作']);
        exit;
    }

    // 移动文件
    if (move_uploaded_file($file_tmp, $target_file)) {
        $domain = $_SERVER['HTTP_HOST'];
        $relative_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', $upload_dir);
        $download_url = "http://$domain" . rtrim($relative_path, '/') . '/' . rawurlencode($randomFileName);
        echo json_encode(['upload_url' => htmlspecialchars($download_url)]);
    } else {
        echo json_encode(['error' => '写入失败，无法保存文件']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="stylesheet" href="http://cdn.atusu.cn/202410/bootstrap.min.css">
    <script src="http://cdn.atusu.cn/202410/jquery.min.js"></script>
    <title>一个简单的图床</title>
    <style>
        html, body {
            height: 100%;
            margin: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
        }
        .container {
            width: 600px;
            overflow: hidden;
        }
        .file-list {
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 15px;
        }
        .file-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #ccc;
            margin-bottom: 5px;
        }
        .file-row:last-child {
            border-bottom: none;
        }
        .file-name {
            flex-grow: 1;
            width: 200px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .file-size {
            margin-left: 10px;
            color: #666;
            margin-right: 5px; 
        }
        .progress {
            width: 100px;
            font-size: 12px;
            margin-left: 5px;
            display: none;
            border-radius: 5px;
        }
        .btn-download, .btn-copy {
            color: #ffffff;
            width: 50px;
            height: 25px;
            padding: 0;
            font-size: 14px;
            margin-left: 5px;
        }
        .btn-download {
            background-color: #0d6efd;
        }
        .btn-copy {
            background-color: #198754;
        }
        .status-output {
            margin-top: 15px;
            height: 30px;
            padding: 5px;
            overflow: hidden;
            font-weight: 1000;
            font-size: 16px;
        }
        .link-output {
            /*margin-top: 20px;
            border: 1px solid #ccc;*/
            padding: 10px;
            display: none; /* 默认隐藏 */
        }
        .link-output textarea {
            width: 100%;
            height: 100px;
            margin-top: 10px;
            white-space: pre-wrap; /* 自动换行 */
            overflow-wrap: break-word; /* 强制换行 */
        }
        .copy-all-btn-container {
    display: flex;
    justify-content: flex-end; /* 使按钮靠右对齐 */
    margin-top: 10px; /* 添加上边距 */
}
        @media (max-width: 600px) {
            .container {
                width: 100%;
                padding: 0 10px;
            }
            .file-name {
                width: 100px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            .progress-status {
                display: none;
            }
            .btn-download, .btn-copy {
                width: 40px;
                margin-left: 5px;
            }
        }
          .nav {
            display: flex;
            justify-content: center; /* 居中对齐 */
            align-items: center; /* 垂直居中 */
            gap: 20px; /* 链接之间的间距 */
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title text-center" style="font-weight: bold;">一个简单的图床</h4>
                <form id="uploadForm" enctype="multipart/form-data">
                    <div class="mb-3">
                        <label for="files" class="form-label"></label>
                        <input type="file" class="form-control" id="files" name="files[]" multiple accept="image/jpeg, image/png, image/gif, image/webp, image/bmp, image/svg+xml" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100" style="font-weight: bold;">开始上传</button>
                </form>
                <div id="fileList" class="file-list mt-3"></div>
                <div id="statusOutput" class="status-output"></div>
                <div class="link-output">
                    <button class="btn btn-info" id="directLinkBtn">直接连接</button>
                    <button class="btn btn-info" id="htmlCodeBtn">网页代码</button>
                    <button class="btn btn-info" id="forumCodeBtn">论坛代码</button>
                    <textarea id="linkText" readonly></textarea>
                    <div class="copy-all-btn-container">
                        <button class="btn btn-success" id="copyAllBtn">复制所有链接</button>
                    </div>
                </div>
                <h6 id="formatHint" class="card-title text-center">支持格式: JPEG, PNG, GIF, WEBP, BMP, SVG</h6>
                <div class="nav" id="navLinks">
                    <a class="nav-link" href="https://masuc.cn/" target="_blank">MASUC</a>
                    <a class="nav-link" href="https://aliwp.cn/" target="_blank">网盘资源</a>
                    <a class="nav-link" href="https://github.com/urldl/img" target="_blank">github</a>
                </div>
            </div>
        </div>
    </div>
<script>
$(document).ready(function() {
    // 初始化时显示格式提示
    $('#formatHint, #serverWarning, #navLinks').show();

    $('#files').on('change', function() {
        if (this.files.length > 0) {
            $('#formatHint, #serverWarning, #navLinks').hide(); // 隐藏提示信息
        } else {
            $('#formatHint, #serverWarning, #navLinks').show(); // 显示提示信息
        }
    });
});
let uploadedLinks = [];
$(document).ready(function () {
    // 清除输出信息的函数
    function clearOutput() {
        $('#fileList').empty();
        $('#statusOutput').text('当前状态: 等待上传...');
        $('.link-output').hide(); 
        $('#linkText').val(''); 
        uploadedLinks = []; 
    }
    // 点击选择文件框时清除输出信息
    $('#files').on('click', function () {
        clearOutput();
    });
    $('#files').on('change', function () {
        clearOutput();
        var files = this.files;
        // 允许的文件类型
        var validFormats = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg']; 
        var validFiles = Array.from(files).filter(file => validFormats.includes(file.type));
        
        if (validFiles.length === 0) {
            showAlert('请选择有效的图片文件（JPEG, PNG, GIF，WEBP，BMP,SVG）！');
            $('#files').val(''); // 清空文件选择
            return;
        }
        $.each(validFiles, function (index, file) {
            var fileSize = (file.size / (1024 * 1024)).toFixed(2) + ' MB';
            var fileRow = `
                <div class="file-row" id="file-row-${index}">
                    <span class="file-name">${file.name}</span>
                    <span class="file-size">(${fileSize})</span>
                    <span class="progress-status">未上传</span>
                    <div class="progress">
                        <div class="progress-bar progress-bar-striped" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                    </div>
                </div>
            `;
            $('#fileList').append(fileRow);
        });
    });
    $('#uploadForm').on('submit', function (event) {
        event.preventDefault();
        var files = $('#files')[0].files;
        var fileIndex = 0;
        function uploadNextFile() {
            if (fileIndex < files.length) {
                var formData = new FormData();
                formData.append('file', files[fileIndex]);
                var xhr = new XMLHttpRequest();
                xhr.open("POST", '', true);
                var startTime;
                xhr.upload.addEventListener('progress', function (evt) {
                    if (evt.lengthComputable) {
                        if (!startTime) startTime = new Date();
                        var percentComplete = Math.round((evt.loaded / evt.total) * 100);
                        var elapsedTime = (new Date() - startTime) / 1000; 
                        var speed = (evt.loaded / 1024 / 1024) / elapsedTime; 
                        var fileRow = $('#file-row-' + fileIndex);
                        fileRow.find('.progress-status').text(`  ${speed.toFixed(2)} MB/s`);
                        fileRow.find('.progress').show();
                        fileRow.find('.progress-bar').css('width', percentComplete + '%').text(percentComplete + '%');
                        var container = $('#fileList');
                        var targetRow = $('#file-row-' + fileIndex);
                        var targetTop = targetRow[0].offsetTop;
                        var containerHeight = container.height();
                        var rowHeight = targetRow.outerHeight();
                        var scrollTo = targetTop - (containerHeight / 1) + (rowHeight / 2);
                        scrollTo = Math.max(0, Math.min(scrollTo, container[0].scrollHeight - containerHeight));
                        container.stop().animate({ scrollTop: scrollTo }, 200); 
                        if (percentComplete === 100) {
                            setTimeout(function () {
                                if (xhr.readyState !== XMLHttpRequest.DONE) {
                                    fileRow.find('.progress-status').text('处理中请稍等');
                                }
                            }, 100);
                        }
                    }
                }, false);
                xhr.onreadystatechange = function () {
                    if (xhr.readyState === XMLHttpRequest.DONE) {
                        var fileRow = $('#file-row-' + fileIndex);
                        if (xhr.status === 200) {
                            var result = JSON.parse(xhr.responseText);
                            if (result.upload_url) {
                                uploadedLinks.push(result.upload_url); 
                                fileRow.find('.progress-status').text('处理完成');
                                fileRow.find('.progress').hide();
                                var linkHtml = `
                                    <a href="${result.upload_url}" target="_blank" class="btn btn-info btn-download">预览</a>
                                    <button class="btn btn-success btn-copy copyButton" data-url="${result.upload_url}">复制</button>
                                `;
                                fileRow.append(linkHtml);
                                setTimeout(function() {
                                    fileRow.find('.progress-status').text('成功上传');
                                }, 100);
                                showLinkOutput();
                            } else if (result.error) {
                                fileRow.find('.progress-status').text('上传失败: ' + result.error);
                                $('#statusOutput').text('上传中断: ' + result.error);
                                return;
                            }
                        } else {
                            fileRow.find('.progress-status').text('错误信息: ' + xhr.statusText);
                            $('#statusOutput').text('上传中断: ' + xhr.statusText);
                            return;
                        }
                        fileIndex++;
                        uploadNextFile();
                    }
                };
                xhr.send(formData);
            } else {
                $('#statusOutput').text('上传任务操作完成');
                $('#files').val(''); // 清空文件选择
            }
            $('#statusOutput').text(`当前状态: 正在上传 ${files[fileIndex].name}`);
        }
        uploadNextFile();
    });
    function showLinkOutput() {
        if (uploadedLinks.length > 1) {
            $('.link-output').show();
            $('#linkText').val(uploadedLinks.join('\n')); // 显示所有链接
            $('#linkText').scrollTop($('#linkText')[0].scrollHeight);
        }
    }
    function showAlert(message) {
        var tooltip = document.createElement('div');
        tooltip.style.position = 'fixed'; 
        tooltip.style.background = '#ffffff'; 
        tooltip.style.border = '2px solid #0d6efd'; 
        tooltip.style.padding = '10px';
        tooltip.style.zIndex = 1000;
        tooltip.style.left = '50%'; 
        tooltip.style.top = '50%'; 
        tooltip.style.transform = 'translate(-50%, -50%)'; 
        tooltip.innerHTML = '<strong>' + message + '</strong>';
        document.body.appendChild(tooltip);
        setTimeout(function () {
            document.body.removeChild(tooltip);
        }, 3000);
    }
    
       $('#directLinkBtn').on('click', function () {
        $('#linkText').val(uploadedLinks.join('\n'));
    });

    $('#htmlCodeBtn').on('click', function () {
        const htmlCodes = uploadedLinks.map(url => `<img src="${url}">`).join('\n');
        $('#linkText').val(htmlCodes);
    });

    $('#forumCodeBtn').on('click', function () {
        const forumCodes = uploadedLinks.map(url => `[img]${url}[/img]`).join('\n');
        $('#linkText').val(forumCodes);
    });

$('#htmlCodeBtn').on('click', function () {
    const htmlCodes = uploadedLinks.map(url => `<img src="${url}">`).join('\n');
    $('#linkText').val(htmlCodes);
});

$('#htmlCodeBtn').on('click', function () {
    const htmlCodes = uploadedLinks.map(url => `<img src="${url}">`).join('\n');
    $('#linkText').val(htmlCodes);
});

$('#copyAllBtn').on('click', function () {
    var copyText = $('#linkText').val();
    var tempInput = document.createElement('textarea');
    tempInput.value = copyText;
    document.body.appendChild(tempInput);
    tempInput.select();
    document.execCommand("copy");
    document.body.removeChild(tempInput);

    var links = copyText.split('\n');
    var totalLinks = links.length; 
    var displayedLinks = links.slice(0, 10); 
    var ignoredCount = totalLinks > 10 ? totalLinks - 10 : 0; 

    var tooltip = document.createElement('div');
    tooltip.style.position = 'fixed'; 
    tooltip.style.background = '#ffffff'; 
    tooltip.style.border = '2px solid #0d6efd'; 
    tooltip.style.padding = '10px';
    tooltip.style.zIndex = 1000;
    tooltip.style.left = '50%'; 
    tooltip.style.top = '50%'; 
    tooltip.style.transform = 'translate(-50%, -50%)'; 

    tooltip.innerHTML = `<strong>已成功复制所有代码到粘贴板，一共 ${totalLinks} 个链接:</strong><br><code>${displayedLinks.map(link => link.replace(/</g, '&lt;').replace(/>/g, '&gt;')).join('<br>')}</code>`;
    
    if (ignoredCount > 0) {
        tooltip.innerHTML += `<br><em>提示框仅显示10个，其余${ignoredCount} 个链接也已全部复制到粘贴板</em>`;
    }

    document.body.appendChild(tooltip);
    
    setTimeout(function () {
        document.body.removeChild(tooltip);
    }, 2200);
});


    $(document).on('click', '.copyButton', function () {
        var copyText = $(this).data('url');
        var tempInput = document.createElement('input');
        tempInput.value = copyText;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand("copy");
        document.body.removeChild(tempInput);
        var tooltip = document.createElement('div');
        tooltip.style.position = 'fixed'; 
        tooltip.style.background = '#ffffff'; 
        tooltip.style.border = '2px solid #0d6efd'; 
        tooltip.style.padding = '10px';
        tooltip.style.zIndex = 1000;
        tooltip.style.left = '50%'; 
        tooltip.style.top = '50%'; 
        tooltip.style.transform = 'translate(-50%, -50%)'; 
        tooltip.innerHTML = '<strong>链接已成功复制到粘贴板:</strong><br>' + copyText;
        document.body.appendChild(tooltip)
        setTimeout(function () {
            document.body.removeChild(tooltip);
        }, 2000);
    });
});
</script>
 
</body>
</html>
