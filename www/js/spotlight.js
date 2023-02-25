import * as Config from './config.js';
import * as Cache from './cache.js';
import * as Util from './util.js';

let spotlight_element = null;
export function _collectionClick(e)
{
	if(spotlight_element === this)
	{
		HideCollection();
	}
	else
	{
		spotlight_element = this;
		ShowCollection();
	}
}
function ShowCollection(e)
{
	let root = Util.GetCollectionRoot(spotlight_element);
	let spot = document.getElementById("collection_spotlight");
	let container = document.getElementById("collection_items");
	container.innerHTML = "";
	$(spot, "img").src = $(root, ".cover").src;
	if(root.dataset.title || $(root, "*[data-title]"))
	{
		let t = root.dataset.title || $(root, "*[data-title]").dataset.title;
		$(spot, ".collection_title").innerHTML = t;
	}
	if(root.dataset.aliases)
	{
		let aa = $(spot, ".album_aliases");
		aa.classList.remove("hidden");
		aa.innerHTML = root.dataset.aliases;
	}
	else
	{
		let aa = $(spot, ".album_aliases");
		if(!aa.classList.contains("hidden"))
			aa.classList.add("hidden");
	}

	for(let song of $$(root, "*[data-songid]"))
	{
		let id = song.dataset.songid;
		let data = Cache.GetSongInfo(id);
		if(!data)
			continue;
		let text = "";
		let ele = make("div");
		ele.dataset.songid = id;
		if(data.track_number)
			text += data.track_number + " - ";
		text += data.title + " - " + Util.StoMS(data.duration);
		ele.innerHTML = text;
		ele.addEventListener("click", Util._addSong);
		container.appendChild(ele);
	}
	spot.classList.remove("hidden");

	//Positioning
	let rect = spotlight_element.getBoundingClientRect();
	let spot_rect = spot.getBoundingClientRect();
	let top = rect.y - 100;
	if(Config.Get("spotlight.keep_in_view") && top < 45)
		top = 45;
	else if(Config.Get("spotlight.keep_in_view") && top > visualViewport.height - spot_rect.height)
		top = visualViewport.height - spot_rect.height;
	spot.style.top = top + "px";
	if(visualViewport.width - rect.right >= spot_rect.width + 20)
	{
		spot.style.left = "" + (rect.right + 10) + "px";
	}
	else
	{
		spot.style.left = "" + (rect.x - 10 - spot_rect.width) + "px";
		//TODO - set class on spotlight making stuff right aligned
	}
}
function HideCollection(e)
{
	document.getElementById("collection_spotlight").classList.add("hidden");
	spotlight_element = null;
}
function UpdateSpotlightPosition()
{
	if(spotlight_element === null)
		return;
	let rect = spotlight_element.getBoundingClientRect();
	let spot_rect = $("#collection_spotlight").getBoundingClientRect();
	let top = rect.y - 100;
	if(Config.Get("spotlight.keep_in_view") && top < 45)
		top = 45;
	else if(Config.Get("spotlight.keep_in_view") && top > visualViewport.height - spot_rect.height)
		top = visualViewport.height - spot_rect.height;
	$("#collection_spotlight").style.top = top + "px";
}

document.getElementById("collection_spotlight").querySelector(".close").addEventListener("click", HideCollection);
document.getElementById("collection_spotlight").querySelector(".download").addEventListener("click", Util._downloadCollection);
document.getElementById("main_panel").addEventListener("scroll", UpdateSpotlightPosition);
