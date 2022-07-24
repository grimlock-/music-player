/**
 * FIXME - After moving the active index from here to playback.js, 
 * removing entries from the list will not update the active index
 * accordingly. The best remedy seems to be adding an event for
 * queue modifications, but I want to think on this some more
 */
import * as Config from './config.js';
import * as Cache from './cache.js';
import * as Util from './util.js';

let List = [];
let songs_element = document.getElementById("songs");
let queue_info_element = document.getElementById("queue_info");

export async function LoadFromStorage()
{
	const savedQueue = localStorage.getItem("queue");
	if(savedQueue !== null)
	{
		List = JSON.parse(savedQueue);
		if(List.length > 0)
			await Cache.LoadSongInfo(List);
		redoHtml();
	}
}

function Save()
{
	localStorage.setItem("queue", JSON.stringify(List));
}

/*export function GetActiveId()
{
	return List[ActiveIndex];
}

export function GetActiveIndex()
{
	return ActiveIndex;
}

export function SetActiveIndex(i)
{
	if(i >= 0 && i <= List.length-1)
		ActiveIndex = i;
}*/

export function NextSong(i, loopAround = false)
{
	if(i >= List.length-1)
	{
		if(List.length == 0 || !loopAround)
			return "";
		else
			return List[0];
	}
	return List[i+1];
}

export function AddSong(songId)
{
	if(typeof songId == "string")
	{
		List.push(songId);
		AddSongHtml(songId);
		Save();
	}
	updateQueueInfo();
	Save();
}

export function AddSongs(...songIds)
{
	for(let id of songIds)
	{
		if(typeof id == "string")
		{
			List.push(id);
			AddSongHtml(id);
		}
	}
	updateQueueInfo();
	Save();
}

export function AddSongHtml(id)
{
	let info = Cache.GetSongInfo(id);
	let text = id;
	if(info)
		text = "<span class=\"handle\"></span><span class='track_title'>" + info.title + "</span><span class='track_length'>" + Util.StoMS(info.duration) + "</span><span class='track_artist'>" + info.artists + "</span><span class='track_number'>" + info.track_number + "</span><span class='genre'>" + info.genre + "</span>";

	let newEle = document.createElement("div");
	newEle.dataset.index = songs_element.childNodes.length;
	newEle.dataset.songid = id;

	let progress = document.createElement("div");
	if(Config.Get("queue.show_load_progress"))
		progress.classList.add("progress");
	progress.innerHTML = text;
	newEle.appendChild(progress);
	songs_element.appendChild(newEle);
}

function redoHtml()
{
	let info;
	let time = 0;
	songs_element.innerHTML = "";
	for(let id of List)
	{
		info = Cache.GetSongInfo(id);
		AddSongHtml(id);
		time += Number(info.duration);
	}
	if(time > 3600)
		time = Util.StoHMS(time);
	else
		time = Util.StoMS(time);
	if(Config.Get("queue.show_queue_info"))
		queue_info_element.innerText = "" + List.length + " songs - playtime: " + time;
}

function updateQueueInfo()
{
	let time = GetTotalDuration();
	if(time > 3600)
		time = Util.StoHMS(time);
	else
		time = Util.StoMS(time);
	if(Config.Get("queue.show_queue_info"))
		queue_info_element.innerText = "" + List.length + " songs - playtime: " + time;
}

export function Shuffle()
{
	let temp;
	for(let i = List.length-1; i > 0; --i)
	{
		let r = Math.floor(Math.random() * (i+1));
		temp = List[r];
		List[r] = List[i];
		List[i] = temp;
	}
	Save();
	redoHtml();
}

export function Clear()
{
	List = [];
	songs_element.innerHTML = "";
	updateQueueInfo();
	Save();
}

export function Remove(i)
{
	List = List.splict(i, 1);
	Save();
}

export function GetTotalDuration()
{
	let d = 0;
	for(let id of List)
	{
		let info = Cache.GetSongInfo(id);
		d += Number(info.duration);
	}
	return d;
}

export function IndexOf(id)
{
	return List.indexOf(id);
}

export function Get(index)
{
	if(index >= 0 && index < List.length)
		return List[index];
	else
		return null;
}
export function GetRange(start, end)
{
	return List.slice(start, end);
}
export function GetAll()
{
	return List.slice();
}
export function GetAllShifted(firstInd)
{
	if(firstInd === 0 || List.length <= 1 || firstInd >= List.length)
		return List.slice();
	
	let ret = List.slice();
	let first = ret.splice(0, firstInd);
	return ret.concat(first);
}

export function Count()
{
	return List.length;
}
