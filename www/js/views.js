/**
 * Views are any objects with these properties:
 *     *initialized
 *         Boolean flag. Value determines if Init() or Draw() is used to
 *         instantiate view
 *     *Init()
 *         Handles initialization, only called once. Some views will have
 *         unchanging properties like the letter buckets for artists and albums,
 *         this is where the data for those is established
 *     *Draw()
 *         Instantiates the view's template. Some will replace the current view
 *         by clearing the #instance element, others will bring up modals.
 *     *Out()
 *         Called before initializing another view if the current one needs to do cleanup
 *
 * There's also some conventions
 *     *Apply()
 *         Not called from external code, but a convention used for views that
 *         depend on data from an endpoint. This is used to redraw elements
 *         related to that data without redrawing the entire view.
 *     *_underscore prefix
 *         Functions starting with an underscore are event listeners that don't
 *         use bind() to change their "this" reference. All other functions
 *         have "this" referencing their view object
 */

import * as Cache from './cache.js';
import * as Config from './config.js';
import * as Queue from './queue.js';
import * as Util from './util.js';

let Instance = document.getElementById("instance");
let Months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
let Views = [];

export function Get(name)
{
	switch(name.toLowerCase())
	{
		case "artists":
			return Artists;
		case "artist":
			return Artist;
		case "albums":
			return Albums;
		case "songs":
			return Songs;
		case "genres":
			return Genres;
		case "real_timeline":
			return RealTimeline;
		case "favorites":
			return Favorites;
		case "random":
			return Random;
		case "playlists":
			return Playlists;
		case "videos":
			return Videos;

		default:
			return Timeline;
		break;
	}
}

const API = location.href + "api/";

function Clear()
{
	while(Instance.firstChild)
		Instance.removeChild(Instance.firstChild);
}
function LoadTemplate(template, section = "default")
{
	console.log(`Loading template ${template} | section: ${section}`);
	
	let template_obj = document.querySelector(template);
	for(let ele of template_obj.content.children)
	{
		Instance.appendChild(ele.cloneNode(true));
	}
}
function GenerateLetterBuckets(buckets, container)
{
	let numFlag = false;
	let specialFlag = false;
	for(let l of buckets)
	{
		let elementText = l;
		let asNum = Number(l);
		if(Number.isNaN(asNum))
		{
			if(Util.IsSpecialChar(l))
			{
				if(specialFlag)
					continue;

				specialFlag = true;
				elementText = "@";
			}
		}
		else
		{
			if(numFlag)
				continue;

			numFlag = true;
			elementText = "#";
		}
		let b = make("button", elementText);
		b.addEventListener("click", this.GetBucketData.bind(this));
		if(elementText == "#")
		{
			if(container.firstChild)
				container.insertBefore(b, container.firstChild);
			else
				container.appendChild(b);
		}
		else if(elementText == "@")
		{
			if(container.firstChild)
			{
				if(container.firstChild.innerHTML == "#")
					container.insertBefore(b, container.firstChild.nextSibling);
				else
					container.insertBefore(b, container.firstChild);
			}
			else
			{
				container.appendChild(b);
			}
		}
		else if(elementText == "The")
		{
			let lastPrior = null;
			for(let i = container.children.length-1; i >= 0; --i)
			{
				let comp = elementText.localeCompare(container.children[i].innerHTML);
				if(comp < 0)
					lastPrior = container.children[i];
				else if(comp > 0)
					break;
			}
			if(lastPrior == null)
				container.appendChild(b);
			else
				container.insertBefore(b, lastPrior);
		}
		else
		{
			container.appendChild(b);
		}
	}
}
function GetCollectionRoot(ele)
{
	let ret = ele;
	while(ret != document.body)
	{
		if(ret.classList.contains("collection"))
			return ret;
		if(ret.id == "collection_spotlight")
			return ret;
		ret = ret.parentElement;
	}
	return null;
}
function _albumMouseenter(e)
{
	let btn = this.querySelector(".add");
	if(btn)
		btn.classList.remove("hidden");
	btn = this.querySelector("a");
	if(btn)
		btn.classList.remove("hidden");
}
function _albumMouseleave(e)
{
	let btn = this.querySelector(".add");
	if(btn)
		btn.classList.add("hidden");
	btn = this.querySelector("a");
	if(btn)
		btn.classList.add("hidden");
}
function _appendCollection(e)
{
	e.preventDefault();
	let tracks = [];
	let root = GetCollectionRoot(this);
	if(!root)
	{
		Util.DisplayError("Error finding collection root");
		return;
	}
	for(let ele of root.querySelectorAll("*[data-songid]"))
	{
		tracks.push(ele.dataset.songid);
	}
	if(tracks.length > 0)
	{
		Queue.AddSongs(...tracks);
	}
}
function _albumArtError(error)
{
	let albumUrl = location.href + "img/album.png";
	if(this.src != albumUrl)
		this.src = albumUrl;
}
function _addSong()
{
	if(Config.Get("queue.always_append") === true || Queue.IndexOf(this.dataset.songid) == -1)
		Queue.AddSong(this.dataset.songid);
}
let spotlight_element = null;
function _showCollection(e)
{
	if(spotlight_element === this)
		return;
	spotlight_element = this;
	let root = GetCollectionRoot(spotlight_element);
	let spot = document.getElementById("collection_spotlight");
	let container = document.getElementById("collection_items");
	container.innerHTML = "";
	spot.querySelector("img").src = root.querySelector(".cover").src;
	if(root.querySelector("*[data-title]"))
	{
		let t = root.querySelector("*[data-title]").dataset.title;
		let i = t.indexOf("|");
		if(i != -1)
		{
			let title = t.substring(0, i);
			spot.querySelector(".collection_title").innerHTML = title;
			let aa = spot.querySelector(".album_aliases");
			aa.classList.remove("hidden");
			aa.innerHTML = t.substring(i+1);
		}
		else
		{
			spot.querySelector(".collection_title").innerHTML = t;
			let aa = spot.querySelector(".album_aliases");
			if(!aa.classList.contains("hidden"))
				aa.classList.add("hidden");
		}
	}
	for(let song of root.querySelectorAll("*[data-songid]"))
	{
		let id = song.dataset.songid;
		let data = Cache.GetSongInfo(id);
		if(!data)
			continue;
		let text = "";
		let ele = document.createElement("div");
		ele.dataset.songid = id;
		if(data.track_number)
			text += data.track_number + " - ";
		text += data.title + " - " + Util.StoMS(data.duration);
		ele.innerHTML = text;
		ele.addEventListener("click", _addSong);
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
function _hideCollection(e)
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
function _downloadCollection(e)
{
	let ids = [];
	let root = GetCollectionRoot(this);
	for(let ele of $$(root, "*[data-songid]"))
	{
		ids.push(ele.dataset.songid);
	}

	let title = $(root, "*[data-title]");
	if(title)
	{
		title = title.dataset.title;
	}
	else
	{
		title = $(root, ".collection_title");
		if(title)
			title = title.innerHTML;
		else
			title = "Untitled Collection";
	}

	let ele = make("a");
	ele.href = API + "download.php?type=song&id=" + encodeURIComponent(ids.join(','));
	ele.setAttribute("download", title + ".zip");
	ele.click();
	return false;
}
document.getElementById("collection_spotlight").querySelector(".close").addEventListener("click", _hideCollection);
document.getElementById("collection_spotlight").querySelector(".download").addEventListener("click", _downloadCollection);
document.getElementById("main_panel").addEventListener("scroll", UpdateSpotlightPosition);



let Timeline = {
	initialized: false,
	songs: null,
	lastDate: null,
	mode: "",
	size: "",
	requestingNextChunk: false,
	lastScrollPosition: 0,
	Init: async function() {
		this._scrollDelegate = this.ScrollListener.bind(this);
		await fetch(API + "import_timeline.php")
		.then(response => response.json())
		.then((function(data){
			if(data.error_message)
			{
				Util.DisplayError(data.error_message);
				return;
			}
			this.songs = data.songs;
			this.lastDate = data.last_date;
		}).bind(this))
		.catch(err => Util.DisplayError("Error initializing Import Timeline view: " + err.message));
		this.Draw();
		this.initialized = true;
	},
	Draw: function() {
		Clear();
		LoadTemplate("#timeline_template");
		Instance.querySelector("#day").addEventListener("click", this.SetMode.bind(this, "day"));
		Instance.querySelector("#year").addEventListener("click", this.SetMode.bind(this, "year"));
		Instance.querySelector("#month").addEventListener("click", this.SetMode.bind(this, "month"));
		Instance.querySelector("#large").addEventListener("click", this.SetSize.bind(this, "large"));
		Instance.querySelector("#medium").addEventListener("click", this.SetSize.bind(this, "medium"));
		Instance.querySelector("#small").addEventListener("click", this.SetSize.bind(this, "small"));
		this.SetMode(this.mode || Config.Get("views.timeline.default_grouping") || "day");
		//this.Apply(this.songs);
		this.SetSize(this.size || "medium");
		if(this.lastScrollPosition > 0)
			Instance.scrollTo(0, this.lastScrollPosition);
		if(Config.Get("theme") == "theme-one")
			$("#main_panel").addEventListener("scroll", this._scrollDelegate);
	},
	Apply: function(data) {
		let groups;
		let container = document.getElementById("items");
		switch(this.mode)
		{
			case "day":
				groups = Util.GroupSongsByDate(data);
			break;
			case "year":
				groups = Util.GroupSongsByYear(data);
			break;
			case "month":
				groups = Util.GroupSongsByMonth(data);
			break;
		}
		//Iterate through groupings
		let keys = Object.keys(groups).sort(Util.SortDates_Desc);
		for(let date of keys)
		{
			let wrapper = document.createElement("div");
			let innerWrapper;
			let d = new Date(date);
			let header = document.createElement("h3");
			if(date.substring(0,4) == "0000")
			{
				innerWrapper = container.querySelector("#nodate");
				if(!innerWrapper)
				{
					innerWrapper = document.createElement("div");
					innerWrapper.id = "nodate";
					header.innerHTML = "No import date set";
					wrapper.appendChild(header);
					wrapper.appendChild(innerWrapper);
				}
			}
			else
			{
				let id;
				switch(this.mode)
				{
					case "day":
						id = "_"+date;
						innerWrapper = null;
						header.innerHTML = Months[d.getUTCMonth()] + " " + d.getUTCDate() + ", " + d.getUTCFullYear();
					break;
					case "year":
						id = d.getUTCFullYear();
						innerWrapper = container.querySelector("#_"+id);
						header.innerHTML = d.getUTCFullYear();
					break;
					case "month":
						id = d.getUTCFullYear() + "-" + d.getUTCMonth();
						innerWrapper = container.querySelector("#_"+id);
						header.innerHTML = Months[d.getUTCMonth()] + " " + d.getUTCFullYear();
					break;
				}
				if(!innerWrapper)
				{
					innerWrapper = document.createElement("div");
					innerWrapper.id = "_"+id;
					innerWrapper.classList.add("tile_collection");
					wrapper.appendChild(header);
					wrapper.appendChild(innerWrapper);
				}
			}

			//Sort by track number
			groups[date].sort(Util.SortSongsByTrackNumber_Asc);
			//Sort by album name
			groups[date].sort(Util.SortSongsByAlbumName_Asc);

			let alb = {"id": "", "name": "", "songs": []};
			let artists = {};
			for(let item of groups[date])
			{
				if(!Cache.GetSongInfo(item.id))
					Cache.SetSongInfo(item.id, item);

				if(item.album_id)
				{
					if(item.album_id != alb.id)
					{
						this.RenderAlbum(alb, innerWrapper);
						alb.id = item.album_id;
						alb.name = item.album;
					}
					alb.songs.push(item);
				}
				else
				{
					if(alb.id)
						this.RenderAlbum(alb, innerWrapper);

					if(!item.artists)
						item.artists = "No Artist";
					if(!artists[item.artists])
						artists[item.artists] = [];
					artists[item.artists].push(item);
				}
			}
			if(alb.id)
				this.RenderAlbum(alb, innerWrapper);
			for(let artist of Object.keys(artists))
			{
				if(artists[artist].length == 1)
				{
					let item = artists[artist][0];
					let newNode = document.createElement("div");
					newNode.innerHTML = item.title + " | " + item.artists + " | " + item.genre + " | " + Util.StoMS(item.duration);
					newNode.dataset.songid = item.id;
					newNode.addEventListener("click", _addSong);
					innerWrapper.appendChild(newNode);
				}
				else if(artists[artist].length > 1)
				{
					let newNode = document.createElement("div");
					switch(this.size)
					{
						case "small":
							newNode.classList.add("tile_sm", "collection");
						break;
						case "medium":
							newNode.classList.add("tile_med", "collection");
						break;
						case "large":
							newNode.classList.add("tile_lg", "collection");
						break;
						default:
							newNode.classList.add("tile_med", "collection");
						break;
					}
					let innerNode = document.createElement("div");
					innerNode.style.position = "relative";
					innerNode.style.height = "auto";
					//newNode.innerHTML = "<strong>" + artist + "</strong>: " + artists[artist].length + " songs";
					innerNode.innerHTML = "<input type=\"image\" src=\"img/add.png\" alt=\"Add songs to queue\" class=\"add hidden\" /></div>";
					newNode.onmouseenter = _albumMouseenter;
					newNode.onmouseleave = _albumMouseleave;
					let i = document.createElement("img");
					i.classList.add("cover");
					i.addEventListener("click", _showCollection);
					i.setAttribute("width", 200);
					i.setAttribute("height", 200);
					if(artist == "No Artist")
						i.src = "img/noartist.png";
					else
						i.src = "img/artist_fallback.png";
					innerNode.appendChild(i);
					newNode.appendChild(innerNode);

					let addButton = newNode.querySelector("input");
					addButton.onclick = _appendCollection;

					let songs = document.createElement("div");
					songs.classList.add("hidden");
					//title
					let title = document.createElement("div");
					title.dataset.title = artist;
					songs.appendChild(title);
					//songs
					for(let song of artists[artist])
					{
						let newEle = document.createElement("span");
						newEle.dataset.songid = song.id;
						newEle.innerHTML = song.title;
						songs.appendChild(newEle);
					}
					newNode.appendChild(songs);

					innerWrapper.appendChild(newNode);
				}
			}

			container.append(wrapper);
		}
	},
	Out: function() {
		$("#main_panel").removeEventListener("scroll", this._scrollDelegate);
	},
	RenderAlbum: function(albumObj, container) {
		if(!albumObj.id)
			return;
		let albumDiv = document.createElement("div");
		switch(this.size)
		{
			case "small":
				albumDiv.classList.add("tile_sm", "collection");
			break;
			case "medium":
				albumDiv.classList.add("tile_med", "collection");
			break;
			case "large":
				albumDiv.classList.add("tile_lg", "collection");
			break;
			default:
				albumDiv.classList.add("tile_med", "collection");
			break;
		}
		let albumInner = document.createElement("div");
		albumInner.style.position = "relative";
		albumInner.style.height = "auto";
		albumInner.innerHTML = "<input type=\"image\" src=\"img/add.png\" alt=\"Add album to queue\" class=\"add hidden\" /><a href=\"" + location.href + album_thumbnail_path + albumObj.id + "." + thumbnail_format + "\" target=\"_blank\" class=\"albumImageLink hidden\"><img src=\"img/link.png\"></a>";
		albumDiv.onmouseenter = _albumMouseenter;
		albumDiv.onmouseleave = _albumMouseleave;
		let i = document.createElement("img");
		i.classList.add("cover");
		i.addEventListener("error", _albumArtError);
		i.addEventListener("click", _showCollection);
		i.setAttribute("width", 200);
		i.setAttribute("height", 200);
		i.setAttribute("src", location.href + album_thumbnail_path + albumObj.id + "_400." + thumbnail_format);
		i.setAttribute("alt", albumObj.name);
		albumInner.appendChild(i);
		albumDiv.appendChild(albumInner);


		let aaBtn = albumDiv.querySelector("input");
		aaBtn.onclick = _appendCollection;

		let songs = document.createElement("div");
		songs.classList.add("hidden");
		//add album stuff
		let title = document.createElement("div");
		title.dataset.title = albumObj.name;
		songs.appendChild(title);
		//add songs
		for(let t of albumObj.songs)
		{
			let newEle = document.createElement("span");
			newEle.dataset.songid = t.id;
			newEle.innerHTML = t.title;
			songs.appendChild(newEle);
		}
		albumDiv.appendChild(songs);

		container.appendChild(albumDiv);
		albumObj.id = "";
		albumObj.name = "";
		albumObj.songs = [];
	},
	_scrollDelegate: null,
	ScrollListener: function() {
		let mp = $("#main_panel");
		this.lastScrollPosition = mp.scrollTop;
		if(!mp.scrollTopMax)
		{
			if(!this.requestingNextChunk)
				this.GetNextChunk();
		}
		else
		{
			let trigger = Config.Get("views.timeline.next_chunk_scroll_percent");
			if(trigger > 1)
				trigger = trigger / 100;
			if(mp.scrollTop / mp.scrollTopMax >= trigger && !this.requestingNextChunk)
				this.GetNextChunk();
		}
	},
	GetNextChunk: async function() {
		this.requestingNextChunk = true;

		await fetch(API + "import_timeline.php?date=" + this.lastDate)
		.then(response => response.json())
		.then((function(data){
			if(data.error_message)
			{
				Util.DisplayError(data.error_message);
				return;
			}
			if(data.songs.length == 0)
			{
				console.log("Reached end of import timline");
				document.getElementById("items").appendChild(make("h3", "End of timeline reached"));
				Instance.removeEventListener("scroll", this._scrollDelegate);
				return;
			}
			this.songs = this.songs.concat(data.songs);
			this.lastDate = data.last_date;
			this.Apply(data.songs);
			this.requestingNextChunk = false;
		}).bind(this))
		.catch(err => Util.DisplayError("Error getting Timeline data: " + err.message));
	},
	SetMode: function(mode) {
		switch(mode)
		{
			case "day":
			case "year":
			case "month":
				this.mode = mode;
			break;
		}
		for(let btn of Instance.querySelectorAll("#group_options button.active"))
			btn.classList.remove("active");
		let btn = Instance.querySelector("#"+mode);
		btn.classList.add("active");

		document.getElementById("items").innerHTML = "";
		this.Apply(this.songs);
		//this.ScrollListener();
	},
	SetSize: function(size) {
		let others;
		this.size = size;
		for(let btn of Instance.querySelectorAll("#size_buttons button.active"))
			btn.classList.remove("active");
		let btn = Instance.querySelector("#"+size);
		btn.classList.add("active");
		switch(size)
		{
			case "small":
				for(let item of Instance.querySelectorAll(".tile_med"))
					item.classList.replace("tile_med", "tile_sm");
				for(let item of Instance.querySelectorAll(".tile_lg"))
					item.classList.replace("tile_lg", "tile_sm");
			break;
			case "medium":
				for(let item of Instance.querySelectorAll(".tile_sm"))
					item.classList.replace("tile_sm", "tile_med");
				for(let item of Instance.querySelectorAll(".tile_lg"))
					item.classList.replace("tile_lg", "tile_med");
			break;
			case "large":
				for(let item of Instance.querySelectorAll(".tile_sm"))
					item.classList.replace("tile_sm", "tile_lg");
				for(let item of Instance.querySelectorAll(".tile_med"))
					item.classList.replace("tile_med", "tile_lg");
			break;

			default:
				return;
		}
		if(this.initialized)
			this.ScrollListener();
	}
}

let Artists = {
	initialized: false,
	Init: async function() {
		await fetch(API + "buckets.php?type=artists")
		.then(response => response.json())
		.then((function(data){
			if(data.error_message)
			{
				Util.DisplayError(data.error_message);
				return;
			}
			this.buckets = data.buckets;
			this.Draw();
			this.initialized = true;
		}).bind(this))
		.catch(err => Util.DisplayError("Error initializing Artists view: " + err.message));
	},
	Draw: function() {
		Clear();
		LoadTemplate("#artists_template");

		//"Letter" buttons
		this.generateLetterBuckets(this.buckets, $("#group_buttons"));
		if(this.artists !== null)
			this.Apply(this.artists);
	},
	Apply: function(data) {
		data.sort(Util.SortArtistsByTitle_Asc);
		for(let artist of data)
		{
			let i = artist.name.indexOf("|");
			if(i == -1)
				i = artist.name.length;
			let primaryName = artist.name.substring(0, i);
			let ele = make("div", "<h2><a data-id='" + artist.id + "'>" + primaryName + "</a></h2>");
			ele.querySelector("a").addEventListener("click", function(e){SetView("artist", this.dataset.id)});
			$("#artists").appendChild(ele);
		}
	},
	artists: null,
	buckets: null,
	generateLetterBuckets: GenerateLetterBuckets,
	GetBucketData: function(e) {
		fetch(API + "artists.php?char=" + encodeURIComponent(e.target.innerHTML))
		.then(response => response.json())
		.then((function(data){
			Instance.querySelector("#artists").innerHTML = "";
			this.artists = data;
			this.Apply(data);
		}).bind(this));
	}
}


let Artist = {
	initialized: false,
	Init: async function(artist_id) {
		await fetch(API + "songs.php?artist=" + artist_id)
		.then(response => response.json())
		.then((function(data){
			if(data.error_message)
			{
				Util.DisplayError(data.error_message);
				return;
			}
			this.songs = data;
			for(let s of data)
			{
				if(!Cache.GetSongInfo(s.id))
					Cache.SetSongInfo(s.id, s);
			}
		}).bind(this))
		.catch(err => Util.DisplayError("Error initializing Artists view: " + err.message));
		await fetch(API + "artist_info.php?id=" + artist_id)
		.then(response => response.json())
		.then((function(data){
			if(data.error_message)
			{
				Util.DisplayError(data.error_message);
				return;
			}
			this.info = data;
			this.Draw();
		}).bind(this))
		.catch(err => Util.DisplayError("Error initializing Artists view: " + err.message));
	},
	Draw: function() {
		Clear();
		LoadTemplate("#artist_template");

		Instance.querySelector("a").addEventListener("click", function(e){SetView("artists");});
		Instance.querySelector("#name").innerHTML = Util.EscHtml(this.info.name);

		if(this.info.aliases)
			Instance.querySelector("#aliases").innerHTML = Util.EscHtml(this.info.aliases);
		else
			Instance.querySelector("#aliases").classList.add("hidden");

		$(Instance, "#info").innerHTML = "Countries: " + (Util.EscHtml(this.info.countries) || "N/A") +
							" | Locations: " + (Util.EscHtml(this.info.locations) || "N/A");

		if(this.info.external_links)
		{
			for(let link of this.info.external_links.split('|'))
			{
				let ele = make("a");
				ele.href = link;
				if(ele.hostname.substring(0, 4) == "www.")
					ele.innerHTML = ele.hostname.substring(4);
				else
					ele.innerHTML = ele.hostname;
				$(Instance, "#links").appendChild(ele);
				$(Instance, "#links").appendChild(make("br"));
			}
		}

		if(this.info.description)
			Util.RenderMarkdown(this.info.description, $(Instance, "#description"));
		else
			$(Instance, "#description").classList.add("hidden");

		$(Instance, "#albums").innerHTML = "Put album tiles here with heders for types";

		if(this.songs.length == 0)
		{
			$(Instance, "#artist_songs").innerHTML = "No songs from this artist";
		}
		else
		{
			for(let s of this.songs)
			{
				let ele = make("div", s.title);
				ele.dataset.songid = s.id;
				ele.addEventListener("click", _addSong);
				$("#artist_songs").appendChild(ele);
			}
		}
	},
	info: null,
	songs: null
}

let Albums = {
	initialized: false,
	Init: async function() {
		let url = API + "buckets.php?type=albums";
		if(Config.Get("views.albums.separate_the_bucket"))
			url += "&separate_the=true";
		if(Config.Get("views.albums.allow_initial_punctuation"))
			url += "&initial_punct=true";
		await fetch(url)
		.then(response => response.json())
		.then((function(data){
			if(data.error_message)
			{
				Util.DisplayError(data.error_message);
				return;
			}
			this.types = data.types;
			this.buckets = data.buckets;
			this.Draw();
			this.initialized = true;
		}).bind(this))
		.catch(err => Util.DisplayError("Error initializing Albums view: " + err.message));
	},
	Draw: function() {
		Clear();
		LoadTemplate("#albums_template");

		//Checkboxes
		for(let type of this.types)
		{
			let l = document.createElement("label");
			l.innerHTML = "<input type=\"checkbox\" name=\"" + type + "\" checked>" + type + "</input>";
			l.firstChild.addEventListener("change", this.CheckboxToggled.bind(this));
			document.getElementById("album_type_filter").appendChild(l);
		}

		//"Letter" buttons
		let letterHolder = document.getElementById("group_buttons");
		this.generateLetterBuckets(this.buckets, letterHolder);
	},
	Apply: function(data) {
		data.sort(Util.SortAlbumsByTitle_Asc);
		let container = document.getElementById("albums");
		for(let album of data)
		{
			let albumDiv = document.createElement("div");
			albumDiv.dataset.albumid = album.id;
			albumDiv.classList.add("tile_med", "collection");
			albumDiv.addEventListener("click", _showCollection);
			let albumInner = document.createElement("div");
			albumInner.style.position = "relative";
			albumInner.style.height = "auto";
			albumInner.innerHTML = "<input type=\"image\" src=\"img/add.png\" alt=\"Add album to queue\" class=\"add hidden\" /><a href=\"" + location.href + album_thumbnail_path + album.id + "." + thumbnail_format+ "\" target=\"_blank\" class=\"albumImageLink hidden\"><img src=\"img/link.png\"></a>";
			albumDiv.onmouseenter = _albumMouseenter;
			albumDiv.onmouseleave = _albumMouseleave;
			let i = document.createElement("img");
			i.classList.add("cover");
			i.addEventListener("error", _albumArtError);
			i.setAttribute("width", 200);
			i.setAttribute("height", 200);
			i.setAttribute("src", location.href + album_thumbnail_path + album.id + "_400." + thumbnail_format);
			i.setAttribute("alt", album.name);
			albumInner.appendChild(i);
			albumDiv.appendChild(albumInner);

			let addBtn = albumDiv.querySelector("input");
			addBtn.onclick = _appendCollection;

			if(album.songs)
			{
				let songs = document.createElement("div")
				songs.classList.add("hidden");
				for(let t of album.songs)
				{
					let newEle = document.createElement("span");
					newEle.dataset.songid = t.id;
					newEle.innerHTML = t.title;
					songs.appendChild(newEle);
				}
				albumDiv.appendChild(songs);
			}
			else
			{
				//TODO
				console.log("Add click listener to fetch album songs");
			}
			container.appendChild(albumDiv);
		}
		this.ApplyFilters();
	},
	types: [],
	buckets: [],
	albums: [],
	generateLetterBuckets: GenerateLetterBuckets,
	ApplyFilters: function()
	{
		let filteredOut = 0;
		for(let album of this.albums)
		{
			if(Instance.querySelector("input[name=\"" + album.type + "\"]").checked)
			{
				Instance.querySelector("*[data-albumid=\"" + album.id + "\"]").classList.remove("hidden");
			}
			else
			{
				++filteredOut;
				let ele = Instance.querySelector("*[data-albumid=\"" + album.id + "\"]");
				if(!ele.classList.contains("hidden"))
					ele.classList.add("hidden");
			}
		}
		if(filteredOut)
			Instance.querySelector("#filter_notice").innerHTML = filteredOut + " album" + (filteredOut == 1 ? "":"s") + " filtered out";
		else
			Instance.querySelector("#filter_notice").innerHTML = "";
	},
	CheckboxToggled: function(e) {
		this.ApplyFilters();
	},
	GetBucketData: function(e) {
		let url = API + "albums.php?char=" + encodeURIComponent(e.target.innerHTML);
		if(Config.Get("views.albums.allow_initial_punctuation"))
			url += "&initial_punct=true";
		if(Config.Get("views.albums.separate_the_bucket"))
			url += "&separate_the=true";
		fetch(url)
		.then(response => response.json())
		.then((function(data){
			if(data.error_message)
			{
				//FIXME - this isn't displaying an error for some reason
				Util.DisplayError(data.error_message);
				return;
			}
			Instance.querySelector("#albums").innerHTML = "";
			this.albums = data;
			this.Apply(data);
		}).bind(this));
	}
}

let Songs = {
	Init: async function() {
		await fetch(API + "songs.php")
		.then(response => response.json())
		.then((function(data) {
			this.data = data;
			this.Draw();
		}).bind(this))
		.catch(err => Util.DisplayError("Error initializing Songs view: " + err.message));
	},
	Draw: function() {
	},
	data: null
}

let Genres = {
	Init: async function() {
	}
}

let RealTimeline = {
	Init: async function() {
	}
}

let Favorites = {
	Init: async function() {
	}
}

let Random = {
	initialized: false,
	Init: async function() {
		await fetch(API + "random.php")
		.then(response => response.json())
		.then((function(data) {
			this.data = data;
			this.Draw();
			this.initialized = true;
		}).bind(this))
		.catch(err => Util.DisplayError("Error initializing Random view: " + err.message));
	},
	Draw: function() {
		Clear();
		LoadTemplate("#random_template");
		Instance.querySelector("#quantity").placeholder = Config.Get("views.random.default_song_count");

		//Listeners
		Instance.querySelector("select").addEventListener("input", this._typeChange);
		Instance.querySelector("button").addEventListener("click", this.reroll.bind(this));

		if(this.data)
			this.Apply(this.data);
	},
	Apply: function(data) {
		let container = Instance.querySelector("#items");
		switch(data.type)
		{
			case "song":
				this.DrawSongs(data.items);
			break;
			case "video":
				this.DrawVideos(data.items);
			break;
			case "album":
				this.DrawAlbums(data.items);
			break;
			default:
				console.error("Unknown item type: " + data.type);
			break;
		}
	},
	DrawSongs: function(items) {
		let container = Instance.querySelector("#items");
		for(let song of items)
		{
			Cache.SetSongInfo(song.id, song);

			//Html stuff
			let newNode = document.createElement("div");
			newNode.innerHTML = (song.title ? song.title : "<strong>Untitled</strong>") + " | " + (song.genre ? song.genre : "<strong>No Genre</strong>") + " | " + (song.album ? song.album : "<strong>No Album</strong>");
			if(song.artists)
				newNode.innerHTML += " | " + song.artists;
			newNode.innerHTML += " | " + Util.StoMS(song.duration);
			newNode.dataset.songid = song.id;
			newNode.dataset.type = "song";
			newNode.addEventListener("click", _addSong);
			container.appendChild(newNode);
		}
	},
	DrawVideos: function(items) {
		Util.DisplayError("Random videos not implemented");
	},
	DrawAlbums: function(items) {
		let container = document.getElementById("items");
		for(let album of items)
		{
			let albumDiv = document.createElement("div");
			albumDiv.dataset.albumid = album.id;
			albumDiv.classList.add("tile_med");
			let albumInner = document.createElement("div");
			albumInner.style.position = "relative";
			albumInner.style.height = "auto";
			albumInner.innerHTML = "<input type=\"image\" src=\"img/add.png\" alt=\"Add album to queue\" class=\"add hidden\" /><a href=\"" + location.href + "art/albums/" + album.id + "." + thumbnail_format + "\" target=\"_blank\" class=\"albumImageLink hidden\"><img src=\"img/link.png\"></a>";
			albumDiv.onmouseenter = _albumMouseenter;
			albumDiv.onmouseleave = _albumMouseleave;
			let i = document.createElement("img");
			i.addEventListener("error", _albumArtError);
			i.setAttribute("width", 200);
			i.setAttribute("height", 200);
			//FIXME - have to get thumbnail format from server
			i.setAttribute("src", location.href + album_thumbnail_path + album.id + "_400." + thumbnail_format);
			i.setAttribute("alt", album.name);
			albumInner.appendChild(i);
			albumDiv.appendChild(albumInner);

			let addBtn = albumDiv.querySelector("input");
			addBtn.onclick = _appendCollection;

			if(album.songs)
			{
				let songs = document.createElement("div")
				songs.classList.add("hidden");
				for(let t of album.songs)
				{
					if(!Cache.GetSongInfo(t.id))
						Cache.SetSongInfo(t.id, t);
					let newEle = document.createElement("span");
					newEle.dataset.songid = t.id;
					newEle.innerHTML = t.title;
					songs.appendChild(newEle);
				}
				albumDiv.appendChild(songs);
			}
			else
			{
				console.log("Add click listener to fetch album songs");
			}

			container.appendChild(albumDiv);
		}
	},
	data: null,
	reroll: function() {
		Instance.querySelector("#items").innerHTML = "";
		fetch(API + "random.php" + this.makeGetString())
		.then(response => response.json())
		.then((function(data) {
			this.data = data;
			this.Apply(data);
		}).bind(this))
		.catch(err => Util.DisplayError("Error getting random items: " + err.message));
	},
	makeGetString: function() {
		let type = document.querySelector("select").value;
		let qt = document.querySelector("#quantity").value;
		if(type == "song" && qt.length == 0)
			return "";
		if(qt.length == 0)
			qt = Config.Get("views.random.default_"+type+"_count");
		return "?type=" + type + "&count=" + Number(qt) + "&resolve=1";
	},
	_typeChange: function(e) {
		Instance.querySelector("#quantity").placeholder = Config.Get("views.random.default_" + e.target.value + "_count");
	}
};

let Playlists = {
	initialized: false,
	Init: async function() {
	},
	Draw: function() {
	},
	Appy: function(data) {
	}
};

let Videos = {
	initialized: false,
	Init: async function() {
	},
	Draw: function() {
	},
	Appy: function(data) {
	}
}
