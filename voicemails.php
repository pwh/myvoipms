<!DOCTYPE html>
<?php
/// Show a page of my voicemail messages at voip.ms
/// This requires a modified copy of the company's
/// SOAP api definition in class.voipms.php, which see.
///
/// Copyright 2019 Paul Hays
/// This program is free software: you can redistribute it and/or modify
/// it under the terms of the GNU General Public License as published by
/// the Free Software Foundation, either version 3 of the License, or
/// (at your option) any later version.
///
/// This program is distributed in the hope that it will be useful,
/// but WITHOUT ANY WARRANTY; without even the implied warranty of
/// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
/// GNU General Public License for more details.
///
/// You should have received a copy of the GNU General Public License
/// along with this program.  If not, see <https://www.gnu.org/licenses/>.
?>
<html>
<head>

<style>
td {text-align: center;}
.bgred {background-color:rgba(255,0,0,0.8);}
.bggreen {background-color:rgba(0,255,0,0.3);}
.bgblue {background-color:rgba(0,0,255,0.3);}
.bggrey {background-color:rgba(192,192,192,0.3);}
.bgyellow {background-color:rgba(255,255,0,0.3);}
.bgcerise {background-color:rgba(255,0,255,0.3);}

/* see https://www.w3schools.com/howto/tryit.asp?filename=tryhow_css_js_dropdown */
.dropbtn {
    background-color: #3498DB;
    color: white;
    padding: 4px;
    /* font-size: 16px; */
    border: none;
    cursor: pointer;
}

.dropbtn:hover, .dropbtn:focus {
    background-color: #2980B9;
}

.dropdown {
    position: relative;
    display: inline-block;
}

.dropdown-content {
    display: none;
    position: absolute;
    background-color: #f1f1f1;
    min-width: 80px;
    overflow: auto;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.8);
    z-index: 1;
}

.dropdown-content button /*a*/ {
	width: 100%;
	border-style: ridge;
    color: black;
    padding: 6px 8px;
    text-decoration: none;
    display: block;
}

.dropdown button:hover {background-color: #ddd;}

.show {display: block;}

</style>
<title>Voicemail messages</title>
</head>

<body>
<?php

/// Remove leading & trailing whitespace from a string element of array
/// $_POST and discard bytes if necessary to limit the size
/// @param string $field name of an element in $_POST
/// @param int $maxsize n of bytes to limit the size of the string
/// @return string trimmed to size, or '' if the array element is unset
/// or empty
function trim_entry( $field, $maxsize ) {
	if( isset( $_POST[$field] ) && (!empty($_POST[$field]) || is_numeric($_POST[$field]))) {
		return( substr( trim((string)($_POST[$field])), 0, $maxsize ));
	}
	else {
		return( '' );
	}
}

require_once("class.voipms.php");

$vmformat = "mp3"; // voicemail message format, "mp3" or "wav"

$voipms = new VoIPms();

// where to look for messages
$mailbox = "1"; // voicemail account name

/// fetch current messages (from all folders, by default)
/// @param $folder optional, name a folder to restrict search
/// @return an array of message objects
function get_messages($folder) {
	global $voipms;
	global $mailbox;

	// get the messages object
	$response = $voipms->getVoicemailMessages($mailbox, $folder);
	if($response['status']!='success') { echo $response['status']; exit; }

	// get array of messages
	$messages = $response['messages'];

	//debug echo '<p>$messages has <pre>'; print_r($messages); echo '</pre></p>'; // debug
	return( $messages);
}

//debug echo '<p>$_POST has <pre>'; print_r($_POST); echo '</pre></p>';

$errmsg = "";
$actmsg = "";
$post_act =			htmlspecialchars(trim_entry('act',        10));
$post_folder =		htmlspecialchars(trim_entry('folder',     10));
$post_message_num =	htmlspecialchars(trim_entry('message_num', 5));
$post_date =		htmlspecialchars(trim_entry('date',       20));

if ($post_act != '') {
	if ($post_folder == '' || (!ctype_digit( $post_message_num )) || $post_date == '') {
    	$errmsg = "The data posted for this page is incomplete:<br/>
		act = $post_act, folder = $post_folder, message number = $post_message_num, date = $post_date";
	}
	else {
		$messages = get_messages($post_folder);
		// find the message object to act upon
		foreach ($messages as $message) {
			//debug echo "comparing " . $message['message_num'] . " with $post_message_num<br/>";
			if ($message['message_num']==$post_message_num)
				break;
		}
		//debug echo "<pre>" . print_r($message) . "</pre><br/>
		//debug 	act = $post_act, folder = $post_folder, message number = $post_message_num, date = $post_date";
		if ( $message === NULL || $message['date'] != $post_date) {
			$errmsg = "Failed to locate message number $post_message_num in folder $post_folder at date $post_date";
		} else {
			//debug echo "<pre>".print_r($message)."</pre>";
			switch ($post_act) {
				case 'listen' :
					$response = $voipms->getVoicemailMessageFile($mailbox, $post_folder, $post_message_num, $vmformat);
					if ($response['status']!='success') {
						$errmsg = $response['status'];
					}
					else {
						$actmsg = 'audio';
						$message=$response['message'];
						//debug  $fp =  fopen("/tmp/track.$vmformat.b64", "wb"); fwrite( $fp, $message['data']); fclose( $fp );
						$message_data_b64 = $message['data'];
					}
					break;

				case 'urgent' :
					$urgent = ($message['urgent']=='no') ? 'yes' : 'no';
					$response = $voipms->markUrgentVoicemailMessage($mailbox, $post_folder, $post_message_num, $urgent );
					if ($response['status']!='success') {
						$errmsg = $response['status'];
					}
					else {
						$actmsg = "message " . $message['date'] . "  " . $message['callerid'] .
							" marked" . (($urgent=='no') ? ' not ' : ' ') . "urgent";
						// voip.ms seems to take a long time to update the data
						sleep(30); /// todo some kind of spinner?
					}
					break;

				case 'delete' :
					$response = $voipms->delMessages($mailbox, $post_folder, $post_message_num);
					if ($response['status']!='success') {
						$errmsg = $response['status'];
					}
					else {
						$actmsg = "message from " . $message['callerid'] . " was deleted";
					} 
					sleep(30); /* ...  need a spinnner ... */
					break;

			}
		}
	}
}

//////// Get voicemail messages, sort by date-time, display them in a table ///////

    $messages = get_messages('' /* all folders */);
    usort ($messages,
        function ($msga, $msgb){
            return strtotime($msgb['date']) - strtotime($msga['date']);
        }
    );

    echo '<h3>Voicemail messages</h3><p>(as of: ' . strftime('%Y-%m-%d %H:%M:%S') .')</p>';
?>
	<!-- table of voicemail messages -->
	<table style="border: 1px solid #336699;">
		<tr>
			<td></td>
			<td>received</td>
			<td>from</td>
			<td>folder</td>
		</tr>
<?php

// make a table row for each message; 1st column provides action
// forms to be posted back to this script
$selfpost = htmlspecialchars($_SERVER["PHP_SELF"]);
foreach ($messages as $message ) {
	$message_num = $message['message_num'];
	$menuid = 'id="myDropdown' . $message_num . '"';
    $bg_colour = ($message['urgent'] == 'yes') ? 'bgred' :
				($message['listened'] == 'yes') ? 'bggrey' :
												'bggreen' ;
?>
		<tr>
			<td>
				<div class="dropdown">
					<button onclick="msgMenu(<?php echo $message_num;?>)" class="dropbtn">&equiv;</button>
					  <div <?php echo $menuid;?> class="dropdown-content">
						<form action="<?php echo $selfpost;?>" method="post">
						<!-- post parameters to identify the current message to the action script -->
						<input name="message_num" value="<?php echo $message_num;?>"       type="hidden">
						<input name="folder"      value="<?php echo $message['folder'];?>" type="hidden">
						<input name="date"        value="<?php echo $message['date'];?>"   type="hidden">
						<!-- display a button for each action -->
						<button name="act" value="listen" type="submit">Listen</button>
						<button name="act" value="urgent" type="submit">Urgent</button>
						<button name="act" value="delete" type="submit">Delete</button>
						</form>
					  </div>
				</div>
			</td>
            <td class="<?php echo $bg_colour ;?>">
                <?php echo $message['date'] ?>
            </td>
            <td class="<?php echo $bg_colour ;?>">
                <?php echo htmlentities($message['callerid']); ?>
            </td>
            <td>
                <?php echo strtolower(htmlentities($message['folder'])); ?>
            </td>
        <tr>
<?php
}
?>
    </table>


<?php

if ($errmsg != '' ) {
	echo "<p><span style='background-color:red'>ERROR: $errmsg</span></p>";
}
elseif ($actmsg == "audio") {
?>
						<br/>
						Message <?php echo $post_date;?>:<br/>
						<audio id="audio_player" controls autoplay>
							<?php echo "<source type=\"audio/$vmformat\" src=\"data:audio/$vmformat;base64,$message_data_b64\">";?>
  							Your browser does not support the audio element (type <?php echo $vmformat;?>).
						</audio>
<?php
}
else {
	echo "<p>$actmsg</p>";
} 
?>

<script>
// When the user clicks on the menu button, toggle between hiding and showing
// the dropdown content for the current row. 
function msgMenu(js_message_num) {
    document.getElementById('myDropdown' + js_message_num).classList.toggle("show");
}

//Close the dropdown if the user clicks outside of it.
window.onclick = function(event) {
  if (!event.target.matches('.dropbtn')) {

    var dropdowns = document.getElementsByClassName("dropdown-content");
    var i;
    for (i = 0; i < dropdowns.length; i++) {
      var openDropdown = dropdowns[i];
      if (openDropdown.classList.contains('show')) {
        openDropdown.classList.remove('show');
      }
    }
  }
}
</script>

</body>
</html>