import {AddView,LoadTemplate} from './views.js';
import * as Enums from './enums.js';
import * as Util from './util.js';

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
			let albumDiv = Util.MakeAlbumTile(album);
			$(albumDiv, "img").removeEventListener("click", Util._collectionClick);
			$(albumDiv, "img").addEventListener("click", this._albumClick);
			//albumDiv.dataset.albumid = album.id;
			albumDiv.classList.add("tile_med", "collection");

			let addBtn = $(albumDiv, "input");
			addBtn.onclick = Util._appendCollection;

			container.appendChild(albumDiv);
		}
		this.ApplyFilters();
	},
	types: [],
	buckets: [],
	albums: [],
	generateLetterBuckets: Util.GenerateLetterBuckets,
	ApplyFilters: function()
	{
		let filteredOut = 0;
		for(let album of this.albums)
		{
			if($("#instance input[name=\"" + album.type + "\"]").checked)
			{
				$("#instance *[data-albumid=\"" + album.id + "\"]").classList.remove("hidden");
			}
			else
			{
				++filteredOut;
				let ele = $("#instance *[data-albumid=\"" + album.id + "\"]");
				if(!ele.classList.contains("hidden"))
					ele.classList.add("hidden");
			}
		}
		if(filteredOut)
			$("#filter_notice").innerHTML = filteredOut + " album" + (filteredOut == 1 ? "":"s") + " filtered out";
		else
			$("#filter_notice").innerHTML = "";
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
			$("#albums").innerHTML = "";
			this.albums = data;
			this.Apply(data);
		}).bind(this));
	},
	_albumClick: function(e) {
		console.log("TODO - Get album songs if you can't find any");
	}
}

AddView(Albums, Enums.Views.ALBUMS);
