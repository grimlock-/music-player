let QuickSearch = document.getElementById("quicksearch");
let QuickSearchResults = document.getElementById("quicksearch_results");

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
		let data = Util.XhrErrorCheck(this,
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
