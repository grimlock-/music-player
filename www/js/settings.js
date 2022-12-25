import * as Config from './config.js';
import * as Util from './util.js';
import * as Library from './library.js';

let Container = document.getElementById("settings");
let Audio = document.querySelector("#settings audio");
let temp_config = null;

function _bgClick(e) { Hide(); }

function Hide()
{
	temp_config = null;
	if(!Audio.paused)
	{
		Audio.src = "";
		Audio.load();
	}

	$("#settings .background").removeEventListener("click", _bgClick);
	$("#settings .close").removeEventListener("click", _bgClick);
	Container.classList.add("hidden");
}

export function Show()
{
	temp_config = JSON.parse(Config.GetJSON());
	$("#settings .background").addEventListener("click", _bgClick);
	$("#settings .close").addEventListener("click", _bgClick);
	Container.classList.remove("hidden");
	Library.get_info();
	Library.request_import_status();
	Library.request_thumbs_status();
}

function Apply()
{
}

document.getElementById("settings-set-audio").addEventListener("click", function(e) {
	let id = $("#song-test").value;
	if(id == "")
	{
		Audio.src = "";
		Audio.load();
	}
	else if(id && Audio.src.indexOf(encodeURIComponent(id)) == -1)
	{
		Audio.src = location.href + "api/song.php?id=" + encodeURIComponent(id);
		Audio.load();
		Audio.play();
	}
});
document.getElementById("settings-btn").addEventListener("click", Show);

