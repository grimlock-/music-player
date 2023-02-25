import {AddView,LoadTemplate} from './views.js';
import * as Enums from './enums.js';
import * as Util from './util.js';

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
		LoadTemplate("#random_template");
		$("#quantity").placeholder = Config.Get("views.random.default_song_count");

		//Listeners
		$("#instance select").addEventListener("input", this._typeChange);
		$("#instance button").addEventListener("click", this.reroll.bind(this));

		if(this.data)
			this.Apply(this.data);
	},
	Apply: function(data) {
		let container = $("#items");
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
		let container = $("#items");
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
			newNode.addEventListener("click", Util._addSong);
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
			let albumDiv = Util.MakeAlbumTile(album);
			albumDiv.classList.add("tile_med");

			let addBtn = $(albumDiv, "input");
			addBtn.onclick = Util._appendCollection;

			container.appendChild(albumDiv);
		}
	},
	data: null,
	reroll: function() {
		$("#items").innerHTML = "";
		fetch(API + "random.php" + this.makeGetString())
		.then(response => response.json())
		.then((function(data) {
			this.data = data;
			this.Apply(data);
		}).bind(this))
		.catch(err => Util.DisplayError("Error getting random items: " + err.message));
	},
	makeGetString: function() {
		let type = $("#instance select").value;
		let qt = $("#quantity").value;
		if(type == "song" && qt.length == 0)
			return "";
		if(qt.length == 0)
			qt = Config.Get("views.random.default_"+type+"_count");
		return "?type=" + type + "&count=" + Number(qt) + "&resolve=1";
	},
	_typeChange: function(e) {
		$("#quantity").placeholder = Config.Get("views.random.default_" + e.target.value + "_count");
	}
};

AddView(Random, Enums.Views.RANDOM);
