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
 *         Called before the initialization of another view
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
		case "artist":
			return Artist;

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
		let b = document.createElement("button");
		b.innerHTML = elementText;
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
			this.Draw();
			this.initialized = true;
		}).bind(this))
		.catch(err => Util.DisplayError("Error initializing Import Timeline view: " + err.message));
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
		Instance.querySelector(".close").addEventListener("click", this._hideCollection);
		if(!this.mode)
			this.SetMode(Config.Get("views.timeline.default_grouping") || "day");
		this.Apply(this.songs);
		if(!this.size)
			this.SetSize("medium");
		if(this.lastScrollPosition > 0)
			Instance.scrollTo(0, this.lastScrollPosition);
		if(Config.Get("theme") == "theme-one")
			Instance.addEventListener("scroll", this._scrollDelegate);
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
					newNode.dataset.id = item.id;
					newNode.addEventListener("click", function(){
						if(Config.Get("queue.always_append") === true || Queue.IndexOf(this.dataset.id) == -1)
							Queue.AddSong(this.dataset.id);
					});
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
					i.addEventListener("click", this._showCollection);
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
		Instance.removeEventListener("scroll", this._scrollDelegate);
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
		//FIXME - the full art isn't always going to be jpg
		albumInner.innerHTML = "<input type=\"image\" src=\"img/add.png\" alt=\"Add album to queue\" class=\"add hidden\" /><a href=\"" + location.href + "art/albums/" + albumObj.id + ".jpg\" target=\"_blank\" class=\"albumImageLink hidden\"><img src=\"img/link.png\"></a>";
		albumDiv.onmouseenter = _albumMouseenter;
		albumDiv.onmouseleave = _albumMouseleave;
		let i = document.createElement("img");
		i.classList.add("cover");
		i.addEventListener("error", _albumArtError);
		i.addEventListener("click", this._showCollection);
		i.setAttribute("width", 200);
		i.setAttribute("height", 200);
		//FIXME - have to get thumbnail format from server
		i.setAttribute("src", location.href + "thumbnails/albums/" + albumObj.id + "_400.jpg");
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
		this.lastScrollPosition = Instance.scrollTop;
		if(!Instance.scrollTopMax)
		{
			if(!this.requestingNextChunk)
				this.GetNextChunk();
		}
		else
		{
			let trigger = Config.Get("views.timeline.next_chunk_scroll_percent");
			if(trigger > 1)
				trigger = trigger / 100;
			if(Instance.scrollTop / Instance.scrollTopMax >= trigger && !this.requestingNextChunk)
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
				let newText = document.createElement("h3");
				newText.innerHTML = "End of timeline reached";
				document.getElementById("items").appendChild(newText);
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
				if(this.mode == mode)
					return;
			break;

			default:
				return;
		}
		this.mode = mode;
		for(let btn of Instance.querySelectorAll("#group_options button.active"))
			btn.classList.remove("active");
		let btn = Instance.querySelector("#"+mode);
		btn.classList.add("active");

		if(this.initialized)
		{
			document.getElementById("items").innerHTML = "";
			this.Apply(this.songs);
		}

		if(this.initialized)
			this.ScrollListener();
	},
	SetSize: function(size) {
		if(this.size == size)
			return;

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
	},
	_showCollection: function() {
		let root = GetCollectionRoot(this);
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
			container.appendChild(ele);
		}
		spot.classList.remove("hidden");
	},
	_hideCollection: function() {
		document.getElementById("collection_spotlight").classList.add("hidden");
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
		let letterHolder = document.getElementById("group_buttons");
		this.generateLetterBuckets(this.buckets, letterHolder);
	},
	Apply: function(data) {
		data.sort(Util.SortAlbumsByName_Asc);
		let container = document.getElementById("artists");
		for(let artist of data)
		{
			let i = artist.name.indexOf("|");
			if(i == -1)
				i = artist.name.length;
			let primaryName = artist.name.substring(0, i);
			let ele = document.createElement("div");
			ele.innerHTML = "<h2>" + primaryName + "</h2>";
			container.appendChild(ele);
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
		data.sort(Util.SortAlbumsByName_Asc);
		let container = document.getElementById("albums");
		for(let album of data)
		{
			let albumDiv = document.createElement("div");
			albumDiv.dataset.albumid = album.id;
			albumDiv.classList.add("tile_med");
			let albumInner = document.createElement("div");
			albumInner.style.position = "relative";
			albumInner.style.height = "auto";
			albumInner.innerHTML = "<input type=\"image\" src=\"img/add.png\" alt=\"Add album to queue\" class=\"add hidden\" /><a href=\"" + location.href + "art/albums/" + album.id + ".jpg\" target=\"_blank\" class=\"albumImageLink hidden\"><img src=\"img/link.png\"></a>";
			albumDiv.onmouseenter = _albumMouseenter;
			albumDiv.onmouseleave = _albumMouseleave;
			let i = document.createElement("img");
			i.addEventListener("error", _albumArtError);
			i.setAttribute("width", 200);
			i.setAttribute("height", 200);
			//FIXME - have to get thumbnail format from server
			i.setAttribute("src", location.href + "thumbnails/albums/" + album.id + "_400.jpg");
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
			newNode.dataset.id = song.id;
			newNode.dataset.type = "song";
			newNode.addEventListener("click", function(){
				if(Config.Get("queue.always_append") === true || Queue.IndexOf(this.dataset.id) == -1)
					Queue.AddSong(this.dataset.id);
			});
			container.appendChild(newNode);
		}
	},
	DrawVideos: function(items) {
		console.error("Random videos not implemented");
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
			albumInner.innerHTML = "<input type=\"image\" src=\"img/add.png\" alt=\"Add album to queue\" class=\"add hidden\" /><a href=\"" + location.href + "art/albums/" + album.id + ".jpg\" target=\"_blank\" class=\"albumImageLink hidden\"><img src=\"img/link.png\"></a>";
			albumDiv.onmouseenter = _albumMouseenter;
			albumDiv.onmouseleave = _albumMouseleave;
			let i = document.createElement("img");
			i.addEventListener("error", _albumArtError);
			i.setAttribute("width", 200);
			i.setAttribute("height", 200);
			//FIXME - have to get thumbnail format from server
			i.setAttribute("src", location.href + "thumbnails/albums/" + album.id + "_400.jpg");
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

let Artist = {
	initialized: false,
	Init: async function() {
	},
	Draw: function() {
	},
	Appy: function(data) {
	}
}
