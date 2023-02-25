import {AddView,LoadTemplate} from './views.js';
import * as Enums from './enums.js';
import * as Util from './util.js';

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
	generateLetterBuckets: Util.GenerateLetterBuckets,
	GetBucketData: function(e) {
		fetch(API + "artists.php?char=" + encodeURIComponent(e.target.innerHTML))
		.then(response => response.json())
		.then((function(data){
			$("#artists").innerHTML = "";
			this.artists = data;
			this.Apply(data);
		}).bind(this));
	}
}

AddView(Artists, Enums.Views.ARTISTS);
