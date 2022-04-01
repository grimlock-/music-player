import * as Config from './config.js';
import * as Queue from './queue.js';
import * as Player from './playback.js';
import * as Cache from './cache.js';
import * as Views from './views.js';
import * as Util from './util.js';


//DOM root node for template instance
let QuickSearch,
QuickSearchResults,
PlayPause,
CurrentView,
CurrentSection,
ChangingViews = false;
const API = location.href + "api/";

function XhrErrorCheck(response, errMsg1 = "", errMsg2 = "")
{
	if(response.status != 200)
	{
		if(errMsg1)
			Util.DisplayError(errMsg1);
		return null;
	}
	
	let obj = JSON.parse(response.responseText);
	if(obj.error_message)
	{
		if(errMsg2)
			Util.DisplayError(errMsg2 + obj.error_message);
		return null;
	}

	return obj;
}
function SetView(view, section = "default")
{
	if(!view || ChangingViews)
		return;

	ChangingViews = true;
	let viewObj = Views.Get(CurrentView);
	if(viewObj.Out)
		viewObj.Out();
	viewObj = Views.Get(view);
	if(!viewObj.initialized)
		viewObj.Init();
	else
		viewObj.Draw();

	CurrentView = view;
	localStorage.setItem("last_view", view);
	RefreshViewIndicator();
	let instClasses = document.getElementById("instance").classList;
	while(instClasses.length > 0)
		instClasses.remove(instClasses[0]);
	instClasses.add(view);
	
	ChangingViews = false;
}
function RefreshViewIndicator()
{
	//Deactivate buttons
	for(let button of document.querySelectorAll(".active[data-view]"))
	{
		button.classList.remove("active");
		button.addEventListener("click", _setview);
	}
	//Activate category button for current view
	if(CurrentView == Config.Get("default_view"))
		return;
	for(let button of document.querySelectorAll("#categories > *[data-view=" + CurrentView + "]"))
	{
		button.classList.add("active");
		button.removeEventListener("click", _setview);
	}
}

//Start
Config.Init();
QuickSearch = document.getElementById("quicksearch");
QuickSearchResults = document.getElementById("quicksearch_results");
PlayPause = document.getElementById("playpause");

if(Config.Get("initial_view") === "default")
{
	SetView(Config.Get("default_view"));
}
else if(Config.Get("initial_view") === "restore")
{
	const lastSection = localStorage.getItem("last_section");
	const lastView = localStorage.getItem("last_view");
	if(lastView === null)
	{
		console.warn("No previous view to restore");
		SetView(Config.Get("default_view"));
	}
	else
	{
		SetView(lastView, lastSection);
	}
}
else
{
	throw "Unknown initial_view value: " + Config.Get("initial_view");
}

//Queue
Queue.LoadFromStorage();
function _queueClick(e)
{
	let id, i, t = e.target;
	while(t)
	{
		if(t.dataset.index)
		{
			i = Number(t.dataset.index);
			id = t.dataset.songid;
			break;
		}
		t = t.parentElement;
	}

	if(id)
	{
		console.log("Click on queue item index: " + i + ",id:" + id);
		if(Player.IsLoaded(id))
		{
			if(Player.GetState() == Player.PlayerState.Playing)
			{
				console.log("TODO - The player should be able to start a song while already playing one without issue");
				return;
			}
			Queue.SetActiveIndex(i);
			Player.BeginPlayback();
		}
		else
		{
			Player.Load(id);
		}
	}
}
document.getElementById("queue").addEventListener("click", _queueClick);

//Listeners
function _setview(e)
{
	//TODO - get previous section for this view
	SetView(this.dataset.view);
}
for(let button of document.querySelectorAll("*[data-view]"))
{
	let def = Config.Get("default_view");
	let v = button.dataset.view;
	if(v != CurrentView || v == def)
		button.addEventListener("click", _setview);
	else if(button.dataset.view != def)
		button.classList.add("active");
}

function _quicksearch(e)
{
	let ele = this;
	if(this.value.length < 2)
	{
		QuickSearchResults.innerHTML = "";
		return;
	}
	let val = this.value;
	let xhr = new XMLHttpRequest();
	xhr.addEventListener("load", function() {
		let data = XhrErrorCheck(this,
			`Quicksearch server error (${this.status})`,
			`Quicksearch server error: `
		);

		if(document.activeElement === ele)
			Cache.SetSearchResults(val, response);
		if(ele.value == val)
			SetQuickSearchResults(response);
	});
	xhr.open("GET", API + "quicksearch.php?query=" + encodeURIComponent(val));
	xhr.send();
}
function _qsblur(e)
{
	QuickSearchResults.innerHTML = "";
}
function _qsfocus(e)
{
	if(Config.Get("quicksearch.show_results_on_focus"))
	{
		QuickSearchResults.classList.remove("hidden");
		let results = Cache.GetSearchResults();
		if(results)
			SetQuickSearchResults(results);
	}
}
function SetQuickSearchResults(results)
{
	if(results.songs.length == 0 &&
		results.videos.length == 0 &&
		results.albums.length == 0 &&
		results.artists.length == 0)
	{
		QuickSearchResults.innerHTML = "No results";
	}
	else
	{
		let wrapper;
		for(let item of results.songs)
		{
			if(!Cache.GetSongInfo(item.id))
				Cache.SetSongInfo(item.id, item);
			wrapper = document.createElement("div");
			if(item.art == "song")
				wrapper.innerHTML = "<img src='thumbnails/songs/"+item.id+"'>";
			console.log(item.id + ": " + item.type);
		}
		for(let item of results.videos)
		{
			wrapper = document.createElement("div");
		}
		for(let item of results.albums)
		{
			wrapper = document.createElement("div");
		}
		for(let item of results.artists)
		{
			wrapper = document.createElement("div");
		}
	}
}
QuickSearch.addEventListener("input", _quicksearch);
QuickSearch.addEventListener("blur", _qsblur);
QuickSearch.addEventListener("focus", _qsfocus);

//document.addEventListener("pagehide", function(e) {
	//Queue.Save();
//});
PlayPause.addEventListener("click", function(e) {
	switch(Player.GetState())
	{
		case Player.PlayerState.Idle:
			Player.PlayFromIdle();
		break;

		case Player.PlayerState.Paused:
			Player.Resume();
		break;

		case Player.PlayerState.Playing:
			Player.Pause();
		break;

		case Player.PlayerState.Loading:
			//do nothing
		break;
	}
});
document.getElementById("volume_slider").addEventListener("input", function(e){
	let num = Number(this.value);
	Player.SetGain(num);
});
document.getElementById("seekbar").addEventListener("input", function(e){
	console.log("seek to " + this.value);
});
document.getElementById("clear").addEventListener("click", function(e){
	Queue.Clear();
});
document.getElementById("next").addEventListener("click", function(e){
	Player.NextSong();
});
document.getElementById("stop").addEventListener("click", function(e){
	Player.Stop();
});
document.getElementById("loop").addEventListener("click", function(e){
	switch(Player.Looping)
	{
		case "":
			Player.LoopTrack();
			this.innerHTML = "Loop: track";
		break;

		case "track":
			Player.LoopQueue();
			this.innerHTML = "Loop: queue";
		break;

		case "queue":
			Player.LoopOff();
			this.innerHTML = "Loop: off";
		break;

		default:
			console.log("Unknown looping state: " + Player.Looping);
		break;
	}
});
document.getElementById("divider").addEventListener("mousedown", function(e) {
	console.log("divider mousedown");
});
