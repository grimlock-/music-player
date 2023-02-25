import {AddView,LoadTemplate} from './views.js';
import * as Enums from './enums.js';
import * as Util from './util.js';

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
		LoadTemplate("#artist_template");

		$("#instance a").addEventListener("click", function(e){SetView("artists");});
		$("#name").innerHTML = Util.EscHtml(this.info.name);

		if(this.info.aliases)
			$("#aliases").innerHTML = Util.EscHtml(this.info.aliases);
		else
			$("#aliases").classList.add("hidden");

		if(this.info.countries && this.info.locations)
		$("#info").innerHTML = "Countries: " + (Util.EscHtml(this.info.countries) || "N/A") +
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
				$("#links").appendChild(ele);
				$("#links").appendChild(make("br"));
			}
		}

		if(this.info.description)
			Util.RenderMarkdown(this.info.description, $("#description"));
		else
			$("#description").classList.add("hidden");

		$("#albums").innerHTML = "Put album tiles here with heders for types";

		if(this.songs.length == 0)
		{
			$("#artist_songs").innerHTML = "No songs from this artist";
		}
		else
		{
			for(let s of this.songs)
			{
				let ele = make("div", s.title);
				ele.dataset.songid = s.id;
				ele.addEventListener("click", Util._addSong);
				$("#artist_songs").appendChild(ele);
			}
		}
	},
	info: null,
	songs: null
}

AddView(Artist, Enums.Views.ARTIST);
