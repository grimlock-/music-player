import {AddView,LoadTemplate} from './views.js';
import * as Enums from './enums.js';
import * as Util from './util.js';
import * as Config from './config.js';
import * as Cache from './cache.js';

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
		LoadTemplate("#timeline_template");
		$("#day").addEventListener("click", this.SetMode.bind(this, Enums.GroupBy.DAY));
		$("#year").addEventListener("click", this.SetMode.bind(this, Enums.GroupBy.YEAR));
		$("#month").addEventListener("click", this.SetMode.bind(this, Enums.GroupBy.MONTH));
		$("#large").addEventListener("click", this.SetSize.bind(this, "large"));
		$("#medium").addEventListener("click", this.SetSize.bind(this, "medium"));
		$("#small").addEventListener("click", this.SetSize.bind(this, "small"));
		this.SetMode(this.mode || Config.Get("views.timeline.default_grouping") || Enums.GroupBy.DAY);
		this.SetSize(this.size || "medium");
		if(this.lastScrollPosition > 0)
			$("#instance").scrollTo(0, this.lastScrollPosition);
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
						header.innerHTML = Util.Months[d.getUTCMonth()] + " " + d.getUTCDate() + ", " + d.getUTCFullYear();
					break;
					case Enums.GroupBy.YEAR:
						id = d.getUTCFullYear();
						innerWrapper = $(container, "#_"+id);
						header.innerHTML = d.getUTCFullYear();
					break;
					case Enums.GroupBy.MONTH:
						id = d.getUTCFullYear() + "-" + d.getUTCMonth();
						innerWrapper = $(container, "#_"+id);
						header.innerHTML = Util.Months[d.getUTCMonth()] + " " + d.getUTCFullYear();
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
					newNode.addEventListener("click", Util._addSong);
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
					innerNode.innerHTML = "<input type=\"image\" src=\"img/plus.svg\" width=\"40px\" height=\"40px\" alt=\"Add songs to queue\" class=\"add hidden\" /></div>";
					newNode.onmouseenter = Util._tileMouseenter;
					newNode.onmouseleave = Util._tileMouseleave;
					let i = make("img");
					i.classList.add("cover");
					i.addEventListener("click", Util._collectionClick);
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
					addButton.onclick = Util._appendCollection;

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
		let albumDiv = Util.MakeAlbumTile(albumObj);
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
		aaBtn.onclick = Util._appendCollection;

		container.appendChild(albumDiv);
		albumObj.id = "";
		albumObj.title = "";
		albumObj.songs = [];
	},
	_scrollDelegate: null,
	ScrollListener: function() {
		let mp = $("#main_panel");
		this.lastScrollPosition = mp.scrollTop;
		let scrollMax = mp.scrollHeight - mp.clientHeight;

		let trigger = Config.Get("views.timeline.next_chunk_scroll_percent");
		if(trigger > 1)
			trigger = trigger / 100;
		if(mp.scrollTop / scrollMax >= trigger && !this.requestingNextChunk)
			this.GetNextChunk();
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
				$("#instance").removeEventListener("scroll", this._scrollDelegate);
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
		for(let btn of $$("#group_options button.active"))
			btn.classList.remove("active");
		let btn = $("#"+mode);
		btn.classList.add("active");

		document.getElementById("items").innerHTML = "";
		this.Apply(this.songs);
		//this.ScrollListener();
	},
	SetSize: function(size) {
		let others;
		this.size = size;
		for(let btn of $$("#size_buttons button.active"))
			btn.classList.remove("active");
		let btn = $("#"+size);
		btn.classList.add("active");
		switch(size)
		{
			case "small":
				for(let item of $$("#instance .tile_med"))
					item.classList.replace("tile_med", "tile_sm");
				for(let item of $$("#instance .tile_lg"))
					item.classList.replace("tile_lg", "tile_sm");
			break;
			case "medium":
				for(let item of $$("#instance .tile_sm"))
					item.classList.replace("tile_sm", "tile_med");
				for(let item of $$("#instance .tile_lg"))
					item.classList.replace("tile_lg", "tile_med");
			break;
			case "large":
				for(let item of $$("#instance .tile_sm"))
					item.classList.replace("tile_sm", "tile_lg");
				for(let item of $$("#instance .tile_med"))
					item.classList.replace("tile_med", "tile_lg");
			break;

			default:
				return;
		}
		if(this.initialized)
			this.ScrollListener();
	}
}

AddView(Timeline, Enums.Views.TIMELINE);
