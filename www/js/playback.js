/**
 * Logic for
 *     playing tracks
 *     tracking position/index in queue
 *     buffering next track
 */
import * as Cache from './cache.js';
import * as Queue from './queue.js';
import * as Config from './config.js';
import * as Util from './util.js';

const API = location.href + "api/";

let Context = new AudioContext({latencyHint: "playback"});
let AudioEle = new Audio();
let MasterGain = Context.createGain();
//Gain nodes are apparently always created at full volume
SetGain(document.getElementById("volume_slider").value);
MasterGain.connect(Context.destination);
let PlayCache = {};
export const PlayerState = {
	"Idle": 0,
	"Loading": 1,
	"Paused": 2,
	"Playing": 3,
	"Stopping": 4
}
let State;
export function GetState()
{
	return State;
}
function SetState(state)
{
	switch(state)
	{
		case PlayerState.Idle:
			State = state;
			document.getElementById("playpause").classList.remove("pause", "loading");
			document.getElementById("playpause").classList.add("play");

			/*if(navigator.mediaSession)
			{
				navigator.mediaSession.playbackState = "none";
				navigator.mediaSession.setActionHandler("play", PlayFromIdle);
				navigator.mediaSession.setActionHandler("pause", null);
				navigator.mediaSession.setActionHandler("stop", null);
				navigator.mediaSession.setActionHandler("nexttrack", null);
			}*/
		break;

		case PlayerState.Loading:
			State = state;
			document.getElementById("playpause").classList.remove("play", "pause");
			document.getElementById("playpause").classList.add("loading");

			/*if(navigator.mediaSession)
			{
				navigator.mediaSession.playbackState = "none";
				navigator.mediaSession.setActionHandler("play", null);
				navigator.mediaSession.setActionHandler("pause", null);
				navigator.mediaSession.setActionHandler("stop", Stop);
				navigator.mediaSession.setActionHandler("nexttrack", null);
			}*/
		break;

		case PlayerState.Paused:
			State = state;
			document.getElementById("playpause").classList.remove("pause", "loading");
			document.getElementById("playpause").classList.add("play");

			/*if(navigator.mediaSession)
			{
				navigator.mediaSession.playbackState = "paused";
				navigator.mediaSession.setActionHandler("play", Resume);
				navigator.mediaSession.setActionHandler("pause", null);
				navigator.mediaSession.setActionHandler("stop", Stop);
				navigator.mediaSession.setActionHandler("nexttrack", null);
			}*/
		break;

		case PlayerState.Playing:
			State = state;
			document.getElementById("playpause").classList.remove("play", "loading");
			document.getElementById("playpause").classList.add("pause");
			if(!Monitor)
				Monitor = setInterval(Heartbeat, 800);

			/*if(navigator.mediaSession)
			{
				navigator.mediaSession.playbackState = "playing";
				navigator.mediaSession.setActionHandler("play", null);
				navigator.mediaSession.setActionHandler("pause", Pause);
				navigator.mediaSession.setActionHandler("stop", Stop);
				navigator.mediaSession.setActionHandler("nexttrack", NextSong);
			}*/
		break;

		case PlayerState.Stopping:
			State = state;
			document.getElementById("playpause").classList.remove("pause", "loading");
			document.getElementById("playpause").classList.remove("play");
			if(Monitor)
			{
				clearInterval(Monitor);
				Monitor = 0;
			}

			/*if(navigator.mediaSession)
			{
				navigator.mediaSession.playbackState = "none";
				navigator.mediaSession.setActionHandler("play", null);
				navigator.mediaSession.setActionHandler("pause", null);
				navigator.mediaSession.setActionHandler("stop", null);
				navigator.mediaSession.setActionHandler("nexttrack", null);
			}*/
		break;

		default:
			console.error("Invalid player state: " + state);
		break;
	}
}
SetState(PlayerState.Idle);

export const Loop = {
	"Off": 0,
	"Track": 1,
	"Queue": 2
}
export let Looping = Loop.Off; //"", "track", "queue"
let CurrentSong = "";
let CurrentNode = null;
let CurrentlyLoading = null;
let LoadingXhr = null;
let Monitor = 0;
//During playback, holds Date.now() when started. When paused, holds delta between that and pause time
let PlaybackStart = -1;
let ActiveQueueIndex = 0;
export function SetActiveIndex(i)
{
	ActiveQueueIndex = i;
}

export function PlayFromIdle()
{
	let id = Queue.Get(ActiveQueueIndex);
	if(id === null)
		return;
	if(IsLoaded(id))
		BeginPlayback();
	else
		Load(id, true);
}
/*if(navigator.mediaSession)
{
	console.log("Browser supports Media Session");
	navigator.mediaSession.setActionHandler("play", PlayFromIdle);
}
else
{
	console.log("No Media Session");
}*/

export function BeginPlayback()
{
	let id = Queue.Get(ActiveQueueIndex);
	if(!id || !IsLoaded(id))
	{
		console.error("Cannot play, \"" + id + "\" has not been loaded");
		return;
	}

	CurrentNode = PlayCache[id].node;
	PlayCache[id].node = null;
	CurrentNode.connect(MasterGain);
	if(Looping == Loop.Track)
		CurrentNode.loop = true;
	CurrentNode.start();

	SetCurrentSong(id);
	PlaybackStart = Date.now();
	SetState(PlayerState.Playing);

	//Media Session
	//Media session stuff will not work because browsers only give audio focus
	//to pages that use standard <audio> and <video> elements for playback, not
	//Web audio contexts. There's a proposal for an Audio Focus API to remedy this.
	//FIXME - fix me in 5-10 years when this shit gets sorted out
	// https://github.com/WICG/audio-focus/blob/main/explainer.md
	// https://bugs.chromium.org/p/chromium/issues/detail?id=944538
	//FIXME - alternatively, you can apparently play any audio clip through an
	//audio element to kick-start the mediasession. I may do that with a silent clip
	//if I *really* want this feature
	/*if(navigator.mediaSession)
	{
		let info = Cache.GetSongInfo(id);
		let obj = {
			title: info.titles || "Untitled",
			artist: info.artists || "Anonymous",
			album: info.album || "-"
		}
		if(info.art == "album")
		{
			console.log("song uses album art");
			let url = location.href + "thumbnails/albums/" + info.album;
			obj.artwork = [
				{ src: url+"_400.jpg", sizes: "400x400", type: "image/jpeg" },
				{ src: url+"_700.jpg", sizes: "700x700", type: "image/jpeg" },
				{ src: url+"_1000.jpg", sizes: "1000x1000", type: "image/jpeg" }
				//{ src: url+".jpg", sizes: "1000x1000", type: "image/jpeg" }
			];
		}
		else if(info.art == "song")
		{
			console.log("song has own art");
			let url = location.href + "thumbnails/songs/" + info.id;
			obj.artwork = [
				{ src: url+"_400.jpg", sizes: "400x400", type: "image/jpeg" },
				{ src: url+"_700.jpg", sizes: "700x700", type: "image/jpeg" },
				{ src: url+"_1000.jpg", sizes: "1000x1000", type: "image/jpeg" }
				//{ src: url+".jpg", sizes: "1000x1000", type: "image/jpeg" }
			];
		}
		else if(Config.Get("playback.media_session_art_fallback"))
		{
			obj.artwork = [
				{ src: location.href+"img/song_fallback.png", sizes: "400x400", type: "image/jpeg" }
			]
		}
		navigator.mediaSession.metadata = new MediaMetadata(obj);
		navigator.mediaSession.setPositionState({
			playbackRate: 1.0,
			duration: info.duration,
			position: (Date.now() - PlaybackStart) / 1000
		});
	}*/
	
	/*if(Notification)
	{
		Notification.requestPermission().then(function(p){
			if(p === "granted")
			{
				//See config option dom.webnotifications.allowinsecure in firefox to allow notifications on plain HTTP
				//https://developer.mozilla.org/en-US/docs/Web/API/notification
			}
		});
	}*/
}
function _playbackEnded(e)
{
	if(State == PlayerState.Paused)
		return;

	console.log("Playback finished");
	this.disconnect();
	if(CurrentNode === this)
		CurrentNode = null;

	if(ShouldUnload(CurrentSong))
	{
		console.log("Unloading");
		Unload(CurrentSong);
	}
	else
	{
		console.log("NOT Unloading");
		let newNode = Context.createBufferSource();
		newNode.buffer = PlayCache[CurrentSong].buffer;
		newNode.onended = _playbackEnded;
		PlayCache[CurrentSong].node = newNode;
	}

	if(State == PlayerState.Stopping)
	{
		if(Config.Get("cache.unload_all_songs_on_stop"))
		{
			for(let id of Object.keys(PlayCache))
				Unload(id);
		}
		PlaybackStart = -1;
		SetCurrentSong("");
		SetState(PlayerState.Idle);
	}
	else if(Queue.Count() == 0)
	{
		if(Config.Get("queue.remove_after_playback"))
		{
			Queue.Remove(ActiveQueueIndex);
			if(Queue.IndexOf(CurrentSong) == -1 && Cache.GetSongInfo(CurrentSong) !== null)
				Unload(CurrentSong);
		}

		PlaybackStart = -1;
		SetCurrentSong("");
		Stop();
	}
	else
	{
		if(Config.Get("queue.remove_after_playback"))
		{
			Queue.Remove(ActiveQueueIndex);
			if(Queue.IndexOf(CurrentSong) == -1 && Cache.GetSongInfo(CurrentSong) !== null)
				Unload(CurrentSong);
		}

		let next = Queue.NextSong(ActiveQueueIndex, Looping == Loop.Queue);
		if(next === null)
		{
			Stop();
		}
		else
		{
			ActiveQueueIndex = (ActiveQueueIndex+1) % Queue.Count();
			if(IsLoaded(next))
				BeginPlayback();
			else
				Load(next, true);
		}

	}
}
function ShouldUnload(CurrentSong)
{
	if(!Config.Get("cache.unload_all_songs_on_stop") && State == PlayerState.Stopping)
		return false;

	let looping = (Looping == Loop.Queue);
	if(Queue.NextSong(ActiveQueueIndex, looping) === CurrentSong)
		return false;

	if(!Config.Get("cache.decache_after_playback") && !Config.Get("queue.remove_after_playback"))
		return false;

	return true;
}
//Update seek bar each tick, preload next tracks, schedule next track playback when current one is near the end
function Heartbeat()
{
	let next;
	switch(State)
	{
		case PlayerState.Playing:
			UpdateTrackTime();
			if(navigator.mediaSession)
			{
				let pos = (Date.now() - PlaybackStart) / 1000;
				if(pos > Cache.GetSongInfo(CurrentSong).duration)
					pos = Cache.GetSongInfo(CurrentSong).duration;
				navigator.mediaSession.setPositionState({
					playbackRate: 1.0,
					duration: Cache.GetSongInfo(CurrentSong).duration,
					position: pos
				});
			}
			next = NextSongToLoad();
			if(next)
				Load(next);
		break;

		case PlayerState.Paused:
			next = NextSongToLoad();
			if(next)
				Load(next);
		break;

		case PlayerState.Loading:
		case PlayerState.Idle:
			//nothing
		break;

		default:
			console.error("Unknown player state: " + State);
		break;
	}
}
function UpdateTrackTime()
{
	let dtime = Date.now() - PlaybackStart;
	let dur = Cache.GetSongInfo(CurrentSong).duration;
	if(dur >= 3600)
		document.getElementById("tracktime").innerHTML = Util.StoHMS(Math.floor(dtime/1000) % dur);
	else
		document.getElementById("tracktime").innerHTML = Util.StoMS(Math.floor(dtime/1000) % dur);
	document.getElementById("seekbar").value = Math.floor(dtime/1000) % dur;
}
function NextSongToLoad()
{
	if(CurrentlyLoading !== null)
		return null;


	let precachedCount = 0;
	let set;
	if(Looping != Loop.Queue)
		set = Queue.GetRange(ActiveQueueIndex);
	else
		set = Queue.GetAllShifted(ActiveQueueIndex);

	for(let i = 1; i < set.length; ++i)
	{
		let id = set[i];
		if(IsLoaded(id))
		{
			if(Cache.GetSongInfo(id).duration >= Config.Get("cache.precache_min_len"))
				precachedCount++;
			if(precachedCount >= Config.Get("cache.precache_count"))
				return null;
		}
		else
		{
			return id;
		}
	}
	return null;
}

export function Load(id, playAfterLoading = false)
{
	if(IsLoaded(id) || CurrentlyLoading !== null)
		return;

	if(playAfterLoading)
		SetState(PlayerState.Loading);

	CurrentlyLoading = id;

	function requestFail(e)
	{
		if(e.message)
			console.error("Error loading song: " + e.message);
		document.querySelectorAll("#queue *[data-songid=\"" + id + "\"] .progress").forEach((ele) => { ele.classList.remove("complete"); ele.style.width=""; });
		LoadingXhr = null;
		CurrentlyLoading = null;
		if(navigator.mediaSession)
			navigator.mediaSession.setActionHandler("play", null);
	}
	let xhr = new XMLHttpRequest();
	xhr.addEventListener("load", function(e) {
		if(this.status != 200)
			return;
		if(this.responseType != "arraybuffer")
		{
			console.log("Not an arraybuffer: " + this.responseType);
			return;
		}
		Context.decodeAudioData(this.response).then(function(buffer) {
			LoadingXhr = null;
			CurrentlyLoading = null;
			let sourceNode = Context.createBufferSource();
			sourceNode.buffer = buffer;
			sourceNode.onended = _playbackEnded;
			PlayCache[id] = {"buffer": buffer, "node": sourceNode};
			document.querySelectorAll("#queue *[data-songid=\"" + id + "\"] .progress").forEach(ele => ele.classList.add("complete"));
			if(playAfterLoading)
			{
				if(State != PlayerState.Playing)
					BeginPlayback();
			}
		})
		.catch(requestFail);
	});
	xhr.addEventListener("progress", function(e) {
		let percent = Math.floor((e.loaded / e.total) * 100);
		//console.log("XHR progress: " + e.loaded + " of " + e.total + " bytes | " + percent + "%");
		document.querySelectorAll("#queue *[data-songid=\"" + id + "\"] .progress").forEach(ele => ele.style.width = percent + "%");
	});
	xhr.addEventListener("abort", requestFail);
	xhr.addEventListener("error", requestFail);
	xhr.addEventListener("timeout", requestFail);
	xhr.open("GET", location.href+"api/song.php?id=" + id);
	xhr.responseType = "arraybuffer";
	xhr.send();
	LoadingXhr = xhr;
}

function SetCurrentSong(id)
{
	CurrentSong = id;
	if(id === "")
	{
		document.getElementById("seekbar").max = 10;
		document.getElementById("tracktime").innerHTML = "--:--";
		document.getElementById("tracklen").innerHTML = "--:--";
		document.title = Config.Get("title");
	}
	else
	{
		let len = Math.ceil(Cache.GetSongInfo(id).duration);
		if(len >= 3600)
		{
			document.getElementById("tracktime").innerHTML = Util.StoHMS(0);
			document.getElementById("tracklen").innerHTML = Util.StoHMS(len);
		}
		else
		{
			document.getElementById("tracktime").innerHTML = Util.StoMS(0);
			document.getElementById("tracklen").innerHTML = Util.StoMS(len);
		}
		document.getElementById("seekbar").max = Math.floor(len);
		document.getElementById("seekbar").value = 0;

		let info = Cache.GetSongInfo(id);
		if(Config.Get("playback.set_title_to_track"))
		{
			//TODO - use playback.title_format option
			document.title = info.title + " - " + info.artists;
		}
		console.log("%c" + info.title + " - " + info.artists, "color: brown;");
	}
}

export function IsLoaded(id)
{
	if(PlayCache.hasOwnProperty(id))
		return true;
	else
		return false;
}

export function Unload(id)
{
	if(!IsLoaded(id))
		return;

	delete PlayCache[id];
	document.querySelectorAll("#queue *[data-songid=\"" + id + "\"] .progress").forEach((ele) => { ele.classList.remove("complete"); ele.style.width=""; });
}

export function Resume()
{
	CurrentNode = PlayCache[CurrentSong].node;
	PlayCache[CurrentSong].node = null;
	CurrentNode.connect(MasterGain);
	if(Looping == Loop.Track)
		CurrentNode.loop = true;
	CurrentNode.start(Context.currentTime, PlaybackStart/1000);

	PlaybackStart = Date.now() - PlaybackStart;

	SetState(PlayerState.Playing);
}

export function Pause()
{
	SetState(PlayerState.Paused);
	CurrentNode.stop();
	let newNode = Context.createBufferSource();
	newNode.buffer = PlayCache[CurrentSong].buffer;
	newNode.onended = _playbackEnded;
	PlayCache[CurrentSong].node = newNode;
	let len = newNode.buffer.duration * 1000;
	let songTime = Date.now() - PlaybackStart;
	PlaybackStart = songTime % len;
	//document.getElementById("playpause").innerHTML = "play";
	if(navigator.mediaSession)
	{
		navigator.mediaSession.setActionHandler("pause", null);
		navigator.mediaSession.setActionHandler("nexttrack", null);
	}
}

export function Stop()
{
	SetState(PlayerState.Stopping);
	if(LoadingXhr)
		LoadingXhr.abort();
	if(CurrentNode)
		CurrentNode.stop();
	else
		SetState(PlayerState.Idle);
}

export function SetGain(value)
{
	MasterGain.gain.setValueAtTime(value, Context.currentTime);
}

export function NextSong()
{
	if(CurrentNode)
		CurrentNode.stop();
}

export function LoopTrack()
{
	Looping = Loop.Track;
	if(CurrentNode)
		CurrentNode.loop = true;
	$("#loop").classList.remove("loop-all", "loop-off");
	$("#loop").classList.add("loop-single");
}

export function LoopQueue()
{
	Looping = Loop.Queue;
	if(CurrentNode && CurrentNode.loop)
		CurrentNode.loop = false;
	$("#loop").classList.remove("loop-off", "loop-single");
	$("#loop").classList.add("loop-all");
}

export function LoopOff()
{
	Looping = Loop.Off;
	if(CurrentNode && CurrentNode.loop)
		CurrentNode.loop = false;
	$("#loop").classList.remove("loop-all", "loop-single");
	$("#loop").classList.add("loop-off");
}
