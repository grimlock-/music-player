import * as Config from './config.js';
import * as Queue from './queue.js';
import * as Player from './playback.js';
import * as Cache from './cache.js';
import * as Views from './views.js';
import * as Util from './util.js';
import * as Settings from './settings.js';
import * as Enums from './enums.js';


//DOM root node for template instance
let QuickSearch,
QuickSearchResults,
PlayPause,
CurrentView,
CurrentSection,
ChangingViews = false;
const API = location.href.substring(0, location.href.indexOf('#')) + "api/";

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
window.make = function(element, content)
{
	let ele = document.createElement(element);
	if(content)
		ele.innerHTML = content;
	return ele;
}
window.$ = function(first, second)
{
	if(first instanceof HTMLElement)
		return first.querySelector(second);
	else
		return document.querySelector(first);
}
window.$$ = function(first, second)
{
	if(first instanceof HTMLElement)
		return first.querySelectorAll(second);
	else
		return document.querySelectorAll(first);
}
window.SetView = function(view, section = "default")
{
	if(!view || ChangingViews)
		return;

	ChangingViews = true;
	let viewObj = Views.Get(CurrentView || Config.Get("default_view"));
	if(viewObj.initialized && viewObj.Out)
		viewObj.Out();
	viewObj = Views.Get(view);
	if(!viewObj.initialized)
		viewObj.Init(section);
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
	for(let button of $$(".active[data-view]"))
	{
		button.classList.remove("active");
		button.addEventListener("click", _setview);
	}
	//Activate category button for current view
	if(CurrentView == Config.Get("default_view"))
		return;
	for(let button of $$("#categories > *[data-view=" + CurrentView + "]"))
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

if(Config.Get("initial_view") == Enums.InitialView.DEFAULT)
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
		if(Player.GetState() == Player.PlayerState.Playing)
		{
			console.log("TODO - The player should be able to start a song while already playing one without issue");
			return;
		}
		Player.SetActiveIndex(i);
		Player.BeginPlayback(id);
	}
}
document.getElementById("queue").addEventListener("click", _queueClick);

//Listeners
function _setview(e)
{
	//TODO - get previous section for this view
	SetView(this.dataset.view);
}
for(let button of $$("*[data-view]"))
{
	let def = Config.Get("default_view");
	let v = button.dataset.view;
	if(v != CurrentView || v == def)
		button.addEventListener("click", _setview);
	else if(button.dataset.view != def)
		button.classList.add("active");
}

//Quicksearch
function _quicksearch(e)
{
	let ele = this;
	if(this.value.length == 0)
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
			Cache.SetSearchResults(val, data);
		if(ele.value == val)
		{
			QuickSearchResults.classList.remove("hidden");
			SetQuickSearchResults(data);
		}
	});
	let limit = Config.Get("quicksearch.max_item_count_per_category");
	xhr.open("GET", API + "quicksearch.php?query=" + encodeURIComponent(val) + "&limit=" + limit);
	xhr.send();
}
function _qsblur(e)
{
	QuickSearchResults.innerHTML = "";
}
function _qsfocus(e)
{
	if(Config.Get("quicksearch.show_results_on_focus") && this.value.length)
	{
		QuickSearchResults.classList.remove("hidden");
		let results = Cache.GetSearchResults(this.value);
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
		return;
	}

	QuickSearchResults.innerHTML = "";
	let order = Config.Get("quicksearch.type_order").split(",");
	let wrapper;
	for(let set of order)
	{
		//TODO - display aliases
		//TODO - display artist flags
		//TODO - display album art
		if(set == "songs")
		{
			for(let item of results.songs)
			{
				wrapper = document.createElement("div");
				if(!Cache.GetSongInfo(item.id))
					Cache.SetSongInfo(item.id, item);
				wrapper.innerHTML += Util.EscHtml(item.title);
				QuickSearchResults.appendChild(wrapper);
			}
		}
		else if(set == "albums")
		{
			for(let item of results.albums)
			{
				wrapper = document.createElement("div");
				wrapper.innerHTML += Util.EscHtml(item.title);
				QuickSearchResults.appendChild(wrapper);
			}
		}
		else if(set == "videos")
		{
			for(let item of results.videos)
			{
				wrapper = document.createElement("div");
				if(!Cache.GetVideoInfo(item.id))
					Cache.SetVideoInfo(item.id, item);
				wrapper.innerHTML += Util.EscHtml(item.titles);
				QuickSearchResults.appendChild(wrapper);
			}
		}
		else if(set == "artists")
		{
			for(let item of results.artists)
			{
				wrapper = document.createElement("div");
				wrapper.innerHTML += Util.EscHtml(item.name);
				QuickSearchResults.appendChild(wrapper);
			}
		}
	}
}
QuickSearch.addEventListener("input", _quicksearch);
QuickSearch.addEventListener("blur", _qsblur);
QuickSearch.addEventListener("focus", _qsfocus);

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
	Player.SetActiveIndex(0);
	Queue.Clear();
});
document.getElementById("next").addEventListener("click", function(e){
	Player.NextSong();
});
document.getElementById("stop").addEventListener("click", function(e){
	switch(Player.GetState())
	{
		case Player.PlayerState.Playing:
		case Player.PlayerState.Paused:
		case Player.PlayerState.Loading:
			Player.Stop();
		break;

		default:
			//do nothing
		break;
	}
});
document.getElementById("loop").addEventListener("click", function(e){
	switch(Player.Looping)
	{
		case Player.Loop.Off:
			Player.LoopTrack();
		break;

		case Player.Loop.Track:
			Player.LoopQueue();
		break;

		case Player.Loop.Queue:
			Player.LoopOff();
		break;
	}
});
$("#shuffle").addEventListener("click", function(e){
	Queue.Shuffle();
});


$("#divider").addEventListener("mousedown", function(e) {
	document.body.classList.add("noselect");
	document.body.addEventListener("mousemove", _resizeQueue);
	document.body.addEventListener("mouseup", _stopResizing);
});
function _resizeQueue(e)
{
	let w_str = getComputedStyle($("#content")).getPropertyValue("--queue-width");
	let w = Number(w_str.substring(0, w_str.indexOf("px")));

	let new_w = Util.Clamp(w + e.movementX, 100, 900) + "px";
	$("#content").style.setProperty("--queue-width", new_w);
	localStorage.setItem("queue_width", new_w)
}
function _stopResizing(e)
{
	document.body.classList.remove("noselect");
	document.body.removeEventListener("mousemove", _resizeQueue);
	document.body.removeEventListener("mouseup", _stopResizing);
}
let lastWidth = localStorage.getItem("queue_width");
if(lastWidth)
	$("#content").style.setProperty("--queue-width", lastWidth);


