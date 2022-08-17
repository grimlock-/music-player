let Container = document.getElementById("library");
let import_btn = Container.querySelector("#library-import");
let import_running = false;
let thumbnail_btn = Container.querySelector("#library-thumbnail");
let thumbnail_running = false;
let add_btn = Container.querySelector("#library-add");
add_btn.addEventListener("click", request_add_dirs);
let remove_btn = Container.querySelector("#library-remove");
remove_btn.addEventListener("click", request_remove_dirs);

function set_notice(notice)
{
	$(Container, "#library-notice").textContent = notice;
}
function disable_import_button()
{
	import_btn.removeEventListener("click", start_import);
	import_btn.classList.add("disabled");
}
function enable_import_button()
{
	import_btn.addEventListener("click", start_import);
	import_btn.classList.remove("disabled");
}
function disable_thumbnail_button()
{
	thumbnail_btn.removeEventListener("click", start_thumbs);
	thumbnail_btn.classList.add("disabled");
}
function enable_thumbnail_button()
{
	thumbnail_btn.addEventListener("click", start_thumbs);
	thumbnail_btn.classList.remove("disabled");
}
export function request_import_status()
{
	let xhr = new XMLHttpRequest();
	xhr.addEventListener("load", _import_status_callback);
	xhr.open("GET", location.href.substring(0, location.href.lastIndexOf("/")+1)+"api/library_scan.php");
	xhr.send();
}
export function request_thumbs_status()
{
	let xhr = new XMLHttpRequest();
	xhr.addEventListener("load", _thumbs_status_callback);
	xhr.open("GET", location.href.substring(0, location.href.lastIndexOf("/")+1)+"api/thumbnail_generator.php");
	xhr.send();
}
function request_add_dirs()
{
	let new_dirs = $(Container, "#library_directory").value;
	if($$(Container, ".no_directories_notice").length)
		Container.querySelector("#library-directories").innerHTML = "";
	for(let dir of new_dirs.split("\n"))
	{
		let new_row = make("div", dir);
		new_row.className = "directory_candidate";
		Container.querySelector("#library-directories").appendChild(new_row);
	}

	let xhr = new XMLHttpRequest();
	xhr.addEventListener("load", _add_directory_callback);
	xhr.open("POST", location.href.substring(0, location.href.lastIndexOf("/")+1)+"api/library_add.php");
	xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	xhr.send("dirs="+encodeURI(new_dirs));
}
function _add_directory_callback()
{
	let result = JSON.parse(this.responseText);
	if(result.error_message)
	{
		setTimeout(function(){
			for(let dir of $$(Container, ".directory_candidate"))
				dir.parentNode.removeChild(dir);
		}, 500);
		set_notice(result.error_message);
		setTimeout(() => set_notice(""), 5000);
	}
	else
	{
		let rem = [], change = [];
		for(let dir of $$(Container, ".directory_candidate"))
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
}
function request_remove_dirs()
{
	let rem_dirs = $(Container, "#library_directory").value;
	let xhr = new XMLHttpRequest();
	xhr.addEventListener("load", _remove_directory_callback);
	xhr.open("POST", location.href.substring(0, location.href.lastIndexOf("/")+1)+"api/library_remove.php");
	xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
	xhr.send("dirs="+encodeURI(rem_dirs));
}
function _remove_directory_callback()
{
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
			for(let dir of $$(Container, ".directory"))
			{
				if(dir.textContent == removed)
				{
					dir.parentNode.removeChild(dir);
					break;
				}
			}
		}
		if(!$$(Container, ".directory").length)
			$(Container, "#library-directories").innerHTML = "<div class=\"no_directories_notice\">No directories set!</div>";
	}
}
function start_import()
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
function _import_status_callback()
{
	let result = "";
	result = JSON.parse(this.responseText);
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
		$(Container, "#library-status").innerText = "";
	}
	else
	{
		import_running = true;
		disable_import_button();
		$(Container, "#library-status").innerText = result.status;
		setTimeout(request_import_status, 2000);
	}
}
function start_thumbs()
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
function _thumbs_status_callback()
{
	let warn_div = $(Container, "#library-warning");
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
			ele = make("div", "No album art directory");
			ele.classList.add("warning");
			warn_div.appendChild(ele);
		}
		if(result.missing_song_art)
		{
			ele = make("div","No song art directory");
			ele.classList.add("warning");
			warn_div.appendChild(ele);
		}
		if(result.missing_album_thumbnail)
		{
			ele = make("div","No album thumbnail directory");
			ele.classList.add("warning");
			warn_div.appendChild(ele);
		}
		if(result.missing_song_thumbnail)
		{
			ele = make("div","No song thumbnail directory");
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
		thumbnail_running = true;
		disable_thumbnail_button();
		setTimeout(request_thumbs_status, 2000);
	}
}

