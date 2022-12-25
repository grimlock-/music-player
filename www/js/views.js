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
import * as Enums from './enums.js';

let Instance = document.getElementById("instance");
let Months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
let Views = [];

export function Get(name)
{
	switch(name)
	{
		case Enums.Views.ARTISTS:
			return Artists;
		case Enums.Views.ARTIST:
			return Artist;
		case Enums.Views.ALBUMS:
			return Albums;
		case Enums.Views.SONGS:
			return Songs;
		case Enums.Views.GENRES:
			return Genres;
		case Enums.Views.REAL_TIMELINE:
			return RealTimeline;
		case Enums.Views.FAVORITES:
			return Favorites;
		case Enums.Views.RANDOM:
			return Random;
		case Enums.Views.PLAYLISTS:
			return Playlists;
		case Enums.Views.VIDEOS:
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
	
	let template_obj = $(template);
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
	let btn = $(this, ".add");
	if(btn)
		btn.classList.remove("hidden");
	btn = $(this, "a");
	if(btn)
		btn.classList.remove("hidden");
}
function _albumMouseleave(e)
{
	let btn = $(this, ".add");
	if(btn)
		btn.classList.add("hidden");
	btn = $(this, "a");
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
	for(let ele of $$(root, "*[data-songid]"))
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
function _collectionClick(e)
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
	let root = GetCollectionRoot(spotlight_element);
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

	//TODO - Change this around so there's an immediate indication the download is going
	let ele = make("a");
	ele.href = API + "download.php?type=song&id=" + encodeURIComponent(ids.join(','));
	ele.setAttribute("download", title + ".zip");
	ele.click();
	return false;
}
document.getElementById("collection_spotlight").querySelector(".close").addEventListener("click", HideCollection);
document.getElementById("collection_spotlight").querySelector(".download").addEventListener("click", _downloadCollection);
document.getElementById("main_panel").addEventListener("scroll", UpdateSpotlightPosition);

function MakeAlbumTile(album)
{
	let albumDiv = make("div");
	albumDiv.dataset.albumid = album.id;
	if(album.title.indexOf(";") == -1)
	{
		albumDiv.dataset.title = album.title;
	}
	else
	{
		albumDiv.dataset.title = album.title.substring(0, album.title.indexOf(";"));
		albumDiv.dataset.aliases = album.title.substring(album.title.indexOf(";")+1);
	}
	let albumInner = make("div");
	albumInner.style.position = "relative";
	albumInner.style.height = "auto";
	albumInner.innerHTML = "<input type=\"image\" src=\"img/plus.svg\" width=\"40px\" height=\"40px\" alt=\"Add album to queue\" class=\"add hidden\" /><a href=\"" + location.href + album_art_path + album.id + "." + thumbnail_format + "\" target=\"_blank\" class=\"albumImageLink hidden\"><img src=\"img/external-link.svg\" width=\"25px\" height=\"25px\"></a>";
	albumDiv.onmouseenter = _albumMouseenter;
	albumDiv.onmouseleave = _albumMouseleave;
	let i = make("img");
	i.classList.add("cover");
	i.addEventListener("error", _albumArtError);
	i.addEventListener("click", _collectionClick);
	i.setAttribute("width", 200);
	i.setAttribute("height", 200);
	if(Config.Get("lazy_loading"))
		i.loading = "lazy";
	i.setAttribute("src", location.href + album_thumbnail_path + album.id + "_400." + thumbnail_format);
	i.setAttribute("alt", album.title);
	albumInner.appendChild(i);
	albumDiv.appendChild(albumInner);

	if(album.songs)
	{
		let songs = make("div")
		songs.classList.add("hidden");
		for(let t of album.songs)
		{
			if(!Cache.GetSongInfo(t.id))
				Cache.SetSongInfo(t.id, t);
			let newEle = make("span");
			newEle.dataset.songid = t.id;
			newEle.innerHTML = t.title;
			songs.appendChild(newEle);
		}
		albumDiv.appendChild(songs);
	}

	return albumDiv;
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
		}).bind(this))
		.catch(err => Util.DisplayError("Error initializing Import Timeline view: " + err.message));
		this.Draw();
		this.initialized = true;
	},
	Draw: function() {
		Clear();
		LoadTemplate("#timeline_template");
		$(Instance, "#day").addEventListener("click", this.SetMode.bind(this, "day"));
		$(Instance, "#year").addEventListener("click", this.SetMode.bind(this, "year"));
		$(Instance, "#month").addEventListener("click", this.SetMode.bind(this, "month"));
		$(Instance, "#large").addEventListener("click", this.SetSize.bind(this, "large"));
		$(Instance, "#medium").addEventListener("click", this.SetSize.bind(this, "medium"));
		$(Instance, "#small").addEventListener("click", this.SetSize.bind(this, "small"));
		this.SetMode(this.mode || Config.Get("views.timeline.default_grouping") || Enums.GroupBy.DAY);
		//this.Apply(this.songs);
		this.SetSize(this.size || "medium");
		if(this.lastScrollPosition > 0)
			Instance.scrollTo(0, this.lastScrollPosition);
		if(Config.Get("theme") == Enums.Themes.THEMEONE)
			$("#main_panel").addEventListener("scroll", this._scrollDelegate);
	},
	Apply: function(data) {
		let groups;
		let container = document.getElementById("items");
		switch(this.mode)
		{
			case Enums.GroupBy.DAY:
				groups = Util.GroupSongsByDate(data);
			break;
			case Enums.GroupBy.YEAR:
				groups = Util.GroupSongsByYear(data);
			break;
			case Enums.GroupBy.MONTH:
				groups = Util.GroupSongsByMonth(data);
			break;
		}
		//Iterate through groupings
		let keys = Object.keys(groups).sort(Util.SortDates_Desc);
		for(let date of keys)
		{
			let wrapper = make("div");
			let innerWrapper;
			let d = new Date(date);
			let header = make("h3");
			if(date.substring(0,4) == "0000")
			{
				if(!Config.Get("views.timeline.show_songs_with_no_date"))
					continue;
				innerWrapper = $(container, "#nodate");
				if(!innerWrapper)
				{
					innerWrapper = make("div");
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
					case Enums.GroupBy.DAY:
						id = "_"+date;
						innerWrapper = null;
						header.innerHTML = Months[d.getUTCMonth()] + " " + d.getUTCDate() + ", " + d.getUTCFullYear();
					break;
					case Enums.GroupBy.YEAR:
						id = d.getUTCFullYear();
						innerWrapper = $(container, "#_"+id);
						header.innerHTML = d.getUTCFullYear();
					break;
					case Enums.GroupBy.MONTH:
						id = d.getUTCFullYear() + "-" + d.getUTCMonth();
						innerWrapper = $(container, "#_"+id);
						header.innerHTML = Months[d.getUTCMonth()] + " " + d.getUTCFullYear();
					break;
				}
				if(!innerWrapper)
				{
					innerWrapper = make("div");
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

			let alb = {"id": "", "title": "", "songs": []};
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
						alb.title = item.album;
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
					let newNode = make("div");
					newNode.innerHTML = item.title + " | " + item.artists + " | " + item.genre + " | " + Util.StoMS(item.duration);
					newNode.dataset.songid = item.id;
					newNode.addEventListener("click", _addSong);
					innerWrapper.appendChild(newNode);
				}
				else if(artists[artist].length > 1)
				{
					let newNode = make("div");
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
					let innerNode = make("div");
					innerNode.style.position = "relative";
					innerNode.style.height = "auto";
					//newNode.innerHTML = "<strong>" + artist + "</strong>: " + artists[artist].length + " songs";
					innerNode.innerHTML = "<input type=\"image\" src=\"img/plus.svg\" width=\"40px\" height=\"40px\" alt=\"Add songs to queue\" class=\"add hidden\" /></div>";
					newNode.onmouseenter = _albumMouseenter;
					newNode.onmouseleave = _albumMouseleave;
					let i = make("img");
					i.classList.add("cover");
					i.addEventListener("click", _collectionClick);
					i.setAttribute("width", 200);
					i.setAttribute("height", 200);
					if(Config.Get("lazy_loading"))
						i.loading = "lazy";
					if(artist == "No Artist")
						i.src = "img/no_artist.png";
					else
						i.src = "img/artist_fallback.png";
					innerNode.appendChild(i);
					newNode.appendChild(innerNode);

					let addButton = $(newNode, "input");
					addButton.onclick = _appendCollection;

					let songs = make("div");
					songs.classList.add("hidden");
					//title
					let title = make("div");
					title.dataset.title = artist;
					songs.appendChild(title);
					//songs
					for(let song of artists[artist])
					{
						let newEle = make("span", song.title);
						newEle.dataset.songid = song.id;
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
		//let albumDiv = make("div");
		let albumDiv = MakeAlbumTile(albumObj);
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

		let aaBtn = $(albumDiv, "input");
		aaBtn.onclick = _appendCollection;

		container.appendChild(albumDiv);
		albumObj.id = "";
		albumObj.title = "";
		albumObj.songs = [];
	},
	_scrollDelegate: null,
	ScrollListener: function() {
		let mp = $("#main_panel");
		this.lastScrollPosition = mp.scrollTop;
		//console.group("scroll event");
		let scrollMax = mp.scrollHeight - mp.clientHeight;
		//console.log("pos: " + mp.scrollTop + " / " + scrollMax + " (Max)");

		let trigger = Config.Get("views.timeline.next_chunk_scroll_percent");
		if(trigger > 1)
			trigger = trigger / 100;
		//console.log("pos: " + (mp.scrollTop/scrollMax) + "% / " + trigger + "%");
		if(mp.scrollTop / scrollMax >= trigger && !this.requestingNextChunk)
			this.GetNextChunk();
		//console.groupEnd();
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
			case Enums.GroupBy.DAY:
			case Enums.GroupBy.MONTH:
			case Enums.GroupBy.YEAR:
				this.mode = mode;
			break;
		}
		for(let btn of $$(Instance, "#group_options button.active"))
			btn.classList.remove("active");
		let btn = $(Instance, "#"+mode);
		btn.classList.add("active");

		document.getElementById("items").innerHTML = "";
		this.Apply(this.songs);
		//this.ScrollListener();
	},
	SetSize: function(size) {
		let others;
		this.size = size;
		for(let btn of $$(Instance, "#size_buttons button.active"))
			btn.classList.remove("active");
		let btn = $(Instance, "#"+size);
		btn.classList.add("active");
		switch(size)
		{
			case "small":
				for(let item of $$(Instance, ".tile_med"))
					item.classList.replace("tile_med", "tile_sm");
				for(let item of $$(Instance, ".tile_lg"))
					item.classList.replace("tile_lg", "tile_sm");
			break;
			case "medium":
				for(let item of $$(Instance, ".tile_sm"))
					item.classList.replace("tile_sm", "tile_med");
				for(let item of $$(Instance, ".tile_lg"))
					item.classList.replace("tile_lg", "tile_med");
			break;
			case "large":
				for(let item of $$(Instance, ".tile_sm"))
					item.classList.replace("tile_sm", "tile_lg");
				for(let item of $$(Instance, ".tile_med"))
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
			$(ele, "a").addEventListener("click", function(e){SetView("artist", this.dataset.id)});
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
			$(Instance, "#artists").innerHTML = "";
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
				Cache.SetSongInfo(s.id, s);
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

		$(Instance, "a").addEventListener("click", function(e){SetView("artists");});
		$(Instance, "#name").innerHTML = Util.EscHtml(this.info.name);

		if(this.info.aliases)
			$(Instance, "#aliases").innerHTML = Util.EscHtml(this.info.aliases);
		else
			$(Instance, "#aliases").classList.add("hidden");

		if(this.info.countries && this.info.locations)
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
			let l = make("label");
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
			//let albumDiv = make("div");
			let albumDiv = MakeAlbumTile(album);
			$(albumDiv, "img").removeEventListener("click", _collectionClick);
			$(albumDiv, "img").addEventListener("click", this._albumClick);
			//albumDiv.dataset.albumid = album.id;
			albumDiv.classList.add("tile_med", "collection");

			let addBtn = $(albumDiv, "input");
			addBtn.onclick = _appendCollection;

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
			if($(Instance, "input[name=\"" + album.type + "\"]").checked)
			{
				$(Instance, "*[data-albumid=\"" + album.id + "\"]").classList.remove("hidden");
			}
			else
			{
				++filteredOut;
				let ele = $(Instance, "*[data-albumid=\"" + album.id + "\"]");
				if(!ele.classList.contains("hidden"))
					ele.classList.add("hidden");
			}
		}
		if(filteredOut)
			$(Instance, "#filter_notice").innerHTML = filteredOut + " album" + (filteredOut == 1 ? "":"s") + " filtered out";
		else
			$(Instance, "#filter_notice").innerHTML = "";
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
			$(Instance, "#albums").innerHTML = "";
			this.albums = data;
			this.Apply(data);
		}).bind(this));
	},
	_albumClick: function(e) {
		console.log("TODO - Get album songs if you can't find any");
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
		$(Instance, "#quantity").placeholder = Config.Get("views.random.default_song_count");

		//Listeners
		$(Instance, "select").addEventListener("input", this._typeChange);
		$(Instance, "button").addEventListener("click", this.reroll.bind(this));

		if(this.data)
			this.Apply(this.data);
	},
	Apply: function(data) {
		let container = $(Instance, "#items");
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
		let container = $(Instance, "#items");
		for(let song of items)
		{
			Cache.SetSongInfo(song.id, song);

			//Html stuff
			let newNode = make("div");
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
			//let albumDiv = make("div");
			let albumDiv = MakeAlbumTile(album);
			albumDiv.classList.add("tile_med");

			let addBtn = $(albumDiv, "input");
			addBtn.onclick = _appendCollection;

			container.appendChild(albumDiv);
		}
	},
	data: null,
	reroll: function() {
		$(Instance, "#items").innerHTML = "";
		fetch(API + "random.php" + this.makeGetString())
		.then(response => response.json())
		.then((function(data) {
			this.data = data;
			this.Apply(data);
		}).bind(this))
		.catch(err => Util.DisplayError("Error getting random items: " + err.message));
	},
	makeGetString: function() {
		let type = $(Instance, "select").value;
		let qt = $(Instance, "#quantity").value;
		if(type == "song" && qt.length == 0)
			return "";
		if(qt.length == 0)
			qt = Config.Get("views.random.default_"+type+"_count");
		return "?type=" + type + "&count=" + Number(qt) + "&resolve=1";
	},
	_typeChange: function(e) {
		$(Instance, "#quantity").placeholder = Config.Get("views.random.default_" + e.target.value + "_count");
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
