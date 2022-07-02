<!DOCTYPE html>
<html>
<head>
	<title>Library directories</title>
	<style>
		.directory {
			color: black;
		}
		.directory_candidate {
			color: grey;
		}
		.no_directories_notice {
			background-color: lightblue;
		}
		#import_button.disabled {
			background-color: #e5e5e5;
			color: grey;
		}
		#thumbnail_button.disabled {
			background-color: #e5e5e5;
			color: grey;
		}
		.warning {
			background-color: yellow;
		}
	</style>
	<script>
		let import_btn, import_running = false;
		let thumbnail_btn, thumbnail_running = false;

		//
		// Simple functions
		//
		function set_notice(notice)
		{
			document.getElementById("notice").textContent = notice;
		}
		function disable_import_button()
		{
			import_btn.removeEventListener("click", request_import);
			import_btn.classList.add("disabled");
		}
		function enable_import_button()
		{
			import_btn.addEventListener("click", request_import);
			import_btn.classList.remove("disabled");
		}
		function disable_thumbnail_button()
		{
			thumbnail_btn.removeEventListener("click", request_thumbs);
			thumbnail_btn.classList.add("disabled");
		}
		function enable_thumbnail_button()
		{
			thumbnail_btn.addEventListener("click", request_thumbs);
			thumbnail_btn.classList.remove("disabled");
		}
		function request_import_status()
		{
			let xhr = new XMLHttpRequest();
			xhr.addEventListener("load", import_status_callback);
			xhr.open("GET", location.href.substring(0, location.href.lastIndexOf("/")+1)+"api/library_scan.php");
			xhr.send();
		}
		function request_thumbs_status()
		{
			let xhr = new XMLHttpRequest();
			xhr.addEventListener("load", thumbs_status_callback);
			xhr.open("GET", location.href.substring(0, location.href.lastIndexOf("/")+1)+"api/thumbnail_generator.php");
			xhr.send();
		}
		//
		// Not simple functions
		//
		function request_add_dirs()
		{
			let new_dirs = document.getElementById("dirs_input").value;
			if(document.getElementsByClassName("no_directories_notice").length)
				document.getElementById("dirs").innerHTML = "";
			for(let dir of new_dirs.split("\n"))
			{
				let new_row = document.createElement("div");
				new_row.className = "directory_candidate";
				new_row.textContent = dir;
				document.getElementById("dirs").appendChild(new_row);
			}

			let xhr = new XMLHttpRequest();
			xhr.addEventListener("load", function() {
				let result = JSON.parse(this.responseText);
				if(result.error_message)
				{
					setTimeout(function(){
						for(let dir of document.getElementsByClassName("directory_candidate"))
							dir.parentNode.removeChild(dir);
					}, 500);
					set_notice(result.error_message);
					setTimeout(() => set_notice(""), 5000);
				}
				else
				{
					let rem = [], change = [];
					for(let dir of document.getElementsByClassName("directory_candidate"))
					{
						if(result.approved_additions.indexOf(dir.textContent) != -1)
							change.push(dir);
						else
							rem.push(dir);
					}
					for(let dir of change)
					{
						dir.classList.replace("directory_candidate", "directory");
					}
					for(let dir of rem)
					{
						dir.parentNode.removeChild(dir);
					}
				}
			});
			xhr.open("POST", location.href.substring(0, location.href.lastIndexOf("/")+1)+"api/library_add.php");
			xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
			xhr.send("dirs="+encodeURI(new_dirs));
		}
		function request_remove_dirs()
		{
			let rem_dirs = document.getElementById("dirs_input").value;
			let xhr = new XMLHttpRequest();
			xhr.addEventListener("load", function() {
				let result = JSON.parse(this.responseText);
				if(result.error_message)
				{
					set_notice(result.error_message);
					setTimeout(() => set_notice(""), 5000);
				}
				else
				{
					for(let removed of result.removed_dirs)
					{
						for(let dir of document.getElementsByClassName("directory"))
						{
							if(dir.textContent == removed)
							{
								dir.parentNode.removeChild(dir);
								break;
							}
						}
					}
					if(!document.getElementsByClassName("directory").length)
						document.getElementById("dirs").innerHTML = "<div class=\"no_directories_notice\">No directories set!</div>";
				}
			});
			xhr.open("POST", location.href.substring(0, location.href.lastIndexOf("/")+1)+"api/library_remove.php");
			xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
			xhr.send("dirs="+encodeURI(document.getElementById("dirs_input").value));
		}
		function request_import()
		{
			disable_import_button();
			set_notice("Starting import");
			setTimeout(() => set_notice(""), 2000);

			let xhr = new XMLHttpRequest();
			xhr.addEventListener("load", function(e){
				let result = JSON.parse(this.responseText);
				if(result.error_message || !result.start_successful)
				{
					set_notice(result.error_message);
					setTimeout(() => set_notice(""), 2000);
				}
				else
				{
					setTimeout(request_import_status, 2000);
				}
			});
			xhr.open("POST", location.href.substring(0, location.href.lastIndexOf("/")+1)+"api/library_scan.php");
			xhr.send();
		}
		function import_status_callback()
		{
			let result = "";
			//try {
				result = JSON.parse(this.responseText);
			/*} catch(e) {
				set_notice("Error parsing status");
				setTimeout(() => set_notice(""), 5000);
				return;
			}*/
			if(result.error_message)
			{
				set_notice(result.error_message);
				setTimeout(() => set_notice(""), 5000);
				if(import_running)
					setTimeout(request_import_status, 2000);
			}
			else if(!result.running_import)
			{
				import_running = false;
				enable_import_button();
				document.querySelector("#status").innerText = "";
			}
			else
			{
				import_running = true;
				disable_import_button();
				document.querySelector("#status").innerText = result.status;
				setTimeout(request_import_status, 2000);
			}
		}
		function request_thumbs()
		{
			disable_thumbnail_button();
			set_notice("Starting thumbnail generation");
			setTimeout(() => set_notice(""), 2000);

			let xhr = new XMLHttpRequest();
			xhr.addEventListener("load", function(e){
				let result = JSON.parse(this.responseText);
				if(result.error_message || !result.start_successful)
				{
					set_notice(result.error_message);
					setTimeout(() => set_notice(""), 2000);
				}
				else
				{
					setTimeout(request_thumbs_status, 2000);
				}
			});
			xhr.open("POST", location.href.substring(0, location.href.lastIndexOf("/")+1)+"api/thumbnail_generator.php");
			xhr.send();
		}
		function thumbs_status_callback()
		{
			let warn_div = document.getElementById("warnings");
			warn_div.innerHTML = "";
			let result = "";
			try {
				result = JSON.parse(this.responseText);
			} catch(e) {
				set_notice("Error parsing thumbnail status");
				setTimeout(() => set_notice(""), 5000);
				return;
			}
			if(result.error_message)
			{
				set_notice(result.error_message);
				setTimeout(() => set_notice(""), 5000);
				if(thumbnail_running)
					setTimeout(request_thumbs_status, 2000);
			}
			else if(result.missing_album_art || result.missing_song_art ||
				result.missing_album_thumbnail || result.missing_song_thumbnail)
			{
				let ele;
				if(result.missing_album_art)
				{
					ele = document.createElement("div");
					ele.innerHTML = "No album art directory";
					ele.classList.add("warning");
					warn_div.appendChild(ele);
				}
				if(result.missing_song_art)
				{
					ele = document.createElement("div");
					ele.innerHTML = "No song art directory";
					ele.classList.add("warning");
					warn_div.appendChild(ele);
				}
				if(result.missing_album_thumbnail)
				{
					ele = document.createElement("div");
					ele.innerHTML = "No album thumbnail directory";
					ele.classList.add("warning");
					warn_div.appendChild(ele);
				}
				if(result.missing_song_thumbnail)
				{
					ele = document.createElement("div");
					ele.innerHTML = "No song thumbnail directory";
					ele.classList.add("warning");
					warn_div.appendChild(ele);
				}
				thumbnail_running = false;
				disable_thumbnail_button();
				setTimeout(request_thumbs_status, 2000);
			}
			else if(!result.generating_thumbs)
			{
				thumbnail_running = false;
				enable_thumbnail_button();
			}
			else
			{
				console.log("Generating thumbnails");
				thumbnail_running = true;
				disable_thumbnail_button();
				setTimeout(request_thumbs_status, 2000);
			}
		}


		document.addEventListener("DOMContentLoaded", function(e)
		{
			import_btn = document.getElementById("import_button");
			thumbnail_btn = document.getElementById("thumbnail_button");
			if(!document.getElementsByClassName("directory").length)
				document.getElementById("dirs").innerHTML = "<div class=\"no_directories_notice\">No directories set!</div>";

			request_import_status();
			request_thumbs_status();
			document.getElementById("add_dirs_button").addEventListener("click", request_add_dirs);
			document.getElementById("remove_dirs_button").addEventListener("click", request_remove_dirs);
		});
	</script>
</head>
<body>
	<div>Directories:</div>
	<div id="dirs">
		<?php
		require("config.php");

		$dirs = file_get_contents("directories.txt");
		if($dirs)
		{
			if(stripos($dirs, "\n") !== false)
			{
				$dirs = explode("\n", $dirs);
				foreach($dirs as $dir)
					echo "<div class=\"directory\">$dir</div>";
			}
			else
			{
				echo "<div class=\"directory\">$dirs</div>";
			}
		}
		?>
	</div>
	<textarea id="dirs_input" ></textarea><button id="add_dirs_button">Add Directories</button><button id="remove_dirs_button">Remove Directories</button>
	<div id="notice"></div>

	<div id="actions">
		<button id="import_button" class="disabled">Import</button>
		<button id="thumbnail_button" class="disabled">Generate thumbnails</button>
	</div>
	<div id="warnings"></div>
	<div id="status"></div>
</body>
</html>
