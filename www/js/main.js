import * as Config from './config.js';
import * as Queue from './queue.js';
import * as Player from './playback.js';
import * as Util from './util.js';
import * as Enums from './enums.js';
import "./settings.js";
import "./view_timeline.js";
import "./view_random.js";
import "./view_artists.js";
import "./view_artist.js";
import "./view_albums.js";
import "./spotlight.js";
import "./quicksearch.js";


//DOM root node for template instance
let PlayPause;

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

//Start
Config.Init();
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
