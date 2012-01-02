<?php
require_once('upyun.php');

$config = array(
	'username' => 'jkey',
	'password' => '1234',
	'bucketname' => 'hello',
	'auth_type' => UpYun::SIGNATURE_AUTH,
);



$upyun = new UpYun($config);

// 获取空间使用情况
$usage = $upyun->getBucketUsage();

$path = '/';
if ( isset($_GET['path']) ) {
	$path = $_GET['path'] . '/';
}

if ( isset($_GET['image']) ) {
	$filename = $path . $_GET['image'];

	// 删除文件
	if ( isset($_POST['action']) && $_POST['action']=='delete' ) {
		$result = $upyun->deleteFile($filename);
		if ( $result ) {
			echo '删除成功！';
		} else {
			echo $upyun->error();
		}
		exit;

	// 读取图片
	} else {
		header('Content-type: image/jpeg');
		echo $upyun->readFile( $filename );
		exit;
	}

// 删除目录
} else if ( isset($_POST['action']) && $_POST['action']=='deleteFolder' ) {
	$result = $upyun->rmDir($path);
	if ( $result ) {
		echo '删除成功！';
	} else {
		echo $upyun->error();
	}
	exit;
}

if ( isset($_POST['submit']) ) {
	if ( $_POST['submit']=='Upload' ) {

		// 上传文件
		if ( isset($_FILES['file']) 
			&& $_FILES['file']['error'] == 0
			&& $_FILES['file']['size'] > 0) {

			$filename = $path . $_FILES['file']['name'];
			$uploadFileName = $_FILES['file']['tmp_name'];
			$upyun->upload($filename, $uploadFileName);
		}

	// 创建目录
	} else if ( $_POST['submit']=='CreateFolder' ) {
		if ( !empty($_POST['foldername']) ) {
			$foldername = $path . $_POST['foldername'];
			$upyun->mkDir($foldername);
		}
	}
}

// 读取目录列表
$list = $upyun->readDir($path);

$html = '<ul>';
if ( $list ) {
	foreach ($list as $item) {
		$type = $item->type;
		$name = $item->name;

		if ( $type == 'file' ) {
			$html .= '<li class="file">'.$name;
			$html .= '&nbsp;&nbsp;';
			$html .= '<a href="?path='.$path.'&image='.$name.'">查看</a>';
			$html .= '&nbsp;&nbsp;';
			$html .= '<a href="?path='.$path.'&image='.$name.'">删除</a>';
			$html .= '</li>';

		} else if ( $type == 'folder' ) {
			$html .= '<li class="folder">'.$name;
			$itempath = $path . $name;
			$html .= '&nbsp;&nbsp;';
			$html .= '<a href="?path='.$itempath.'">进入</a>';
			$html .= '&nbsp;&nbsp;';
			$html .= '<a href="?path='.$itempath.'">删除文件夹</a>';
			$html .= '</li>';
		}
	}
}
$html .= '</ul>';
?>
<!DOCTYPE html>
<html lang="zh">
<head>
<meta charset="utf-8">
<script type="text/javascript">
function createXmlHttpRequest()
{
	if ( window.ActiveXObject ) {
		return new ActiveXObject("Microsoft.XMLHTTP");
	} else if ( window.XMLHttpRequest ) {
		return new XMLHttpRequest();
	}
}

function post(url, data)
{
	xmlHttpRequest = createXmlHttpRequest();

	xmlHttpRequest.onreadystatechange = function() {
		if ( xmlHttpRequest.readyState == 4 ) {
			if ( xmlHttpRequest.status == 200 ) {
				alert(xmlHttpRequest.responseText);
				window.location.reload();
			}
		}
	};

	xmlHttpRequest.open('POST', url, true);
	xmlHttpRequest.setRequestHeader('Content-Length', data.length);
	xmlHttpRequest.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	xmlHttpRequest.send(data);
}

window.onload = function() {
	var items = document.getElementsByTagName('li');
	var showarea = document.getElementById('showarea');

	for ( var i=0; i<items.length; i++ ) {
		var item = items[i];
		if ( item.className == 'file' ) {
			(function() {
				var eleShow = item.children[0];
				var eleDelete = item.children[1];

				eleShow.onclick = function(e) {
					showarea.innerHTML = '<img src="'+eleShow.href+'"/>';
					return false;
				};

				eleDelete.onclick = function(e) {
					post(eleDelete.href, 'action=delete');
					return false;
				};
			})();

		} else if ( item.className == 'folder' ) {
			(function() {
				var eleDelete = item.children[1];

				eleDelete.onclick = function(e) {
					post(eleDelete.href, 'action=deleteFolder');
					return false;
				};
			})();
		}
	}
};
</script>
</head>
<body>
	<div>Bucket Usage: <?php echo $usage; ?></div>
	<div>
		<form enctype="multipart/form-data" method='post'>
			<input type="file" name="file" />
			<input type="submit" name="submit" value="Upload"/>
		</form>
		<form method="post">
			<input type="text" name="foldername" />
			<input type="submit" name="submit" value="CreateFolder" />
		</form>
	</div>
	<div>
		<?php echo $html; ?>
	</div>
	<div id="showarea">
	</div>

</body>
</html>
