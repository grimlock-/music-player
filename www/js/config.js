const defaultSettings =
{
	title: "Moozik",
	initial_view: "default", //default, restore [previous view]
	default_view: "timeline",
	theme: "theme-one",
	sounds: true,
	queue: {
		//Remove songs from queue when they finish playing
		remove_after_playback: false,
		//When selecting an item in an instance to play, add it to the end of the queue, even if it is already present in the queue
		always_append: true,
		show_load_progress: true,
		show_queue_info: true
	},
	playback: {
		fadeout: false,
		fadeout_duration: 3,
		crossfade: false,
		crossfade_fadeout: 1,
		crossfade_fadein: 1,
		set_title_to_track: true,
		title_format: "%title% - %artist%",
		media_session_art_fallback: true
	},
	cache: {
		//Number of items in the queue past whatever is currently playing to load to allow gapless playback
		precache_count: 1,
		//Don't count songs shorter than this length towards the precache count
		precache_min_len: 71,
		//Remove songs from cache after playback
		decache_after_playback: true,
		unload_all_songs_on_stop: false
	},
	views: {
		timeline: {
			default_grouping: "month",
			next_chunk_scroll_percent: 90
		},
		artists: {
			//When getting all items for a letter, include items that have punctuation before the given character
			allow_initial_punctuation: true,
			//Separate items starting with "the" into their own bucket
			separate_the_bucket: false
		},
		albums: {
			//When getting all items for a letter, include items that have punctuation before the given character
			allow_initial_punctuation: true,
			//Separate items starting with "the" into their own bucket
			separate_the_bucket: true
		},
		random: {
			default_song_count: 10,
			default_video_count: 5,
			default_album_count: 3
		}
	},
	quicksearch: {
		//Clear the quicksearch field when adding a quick search result to the queue
		clear_input_on_enqueue: false,
		//When clicking on the search field and giving it focus, immediately show search results instead of waiting for the input to change
		show_results_on_focus: true,
		type_order: "songs,videos,albums,artists",
		max_item_count_per_category: 3
	},
	lastfm: {
		scrobbling_enabled: false,
		api_key: ""
	}
};
const Constraints =
{
	views: {
		timeline: {
			next_chunk_scroll_percent: {
				type: "number",
				minimum: 20,
				maximum: 100
			}
		},
		random: {
			default_song_count: {
				type: "number",
				minimum: 1
			},
			default_video_count: {
				type: "number",
				minimum: 1
			},
			default_album_count: {
				type: "number",
				minimum: 1
			}
		}
	},
	quicksearch: {
		max_item_count_per_category: {
			type: "number",
			minimum: 1,
			maximum: 20
		}
	}
};
let settings = {};

export function Init()
{
	if(!LoadFromStorage())
		settings = JSON.parse(JSON.stringify(defaultSettings));
}
export function LoadFromStorage()
{
	const savedSettings = localStorage.getItem("config");
	if(savedSettings !== null)
	{
		settings = JSON.parse(savedSettings);
		AddMissingFields(defaultSettings, settings);
		return true;
	}
	return false;
}

export function AddMissingFields(ref, current)
{
	for(let prop of Object.keys(ref))
	{
		if(!current.hasOwnProperty(prop))
		{
			if(typeof ref[prop] == "object")
			{
				console.log("[Settings] missing object " + prop);
				current[prop] = {};
				AddMissingFields(ref[prop], current[prop]);
			}
			else
			{
				console.log("[Settings] missing property " + prop);
				current[prop] = ref[prop];
			}
		}
	}
}

export function GetJSON()
{
	return JSON.stringify(settings);
}

export function Apply(sets)
{
	let temp;
	if(typeof sets == "string")
		temp = JSON.parse(sets);
	else
		temp = sets;
	AddMissingFields(defaultSettings, temp);
	if(IsValid(temp))
		settings = temp;
}
function IsValid(tsets)
{
	for(let prop of Object.keys(Constraints))
	{
		
	}
}

export function Save()
{
	localStorage.setItem("config", JSON.stringify(settings));
}

export function Get(str)
{
	let val = settings;
	for(let key of str.split('.'))
	{
		if(!val.hasOwnProperty(key))
		{
			console.error("Invalid config option: " + str + "(" + key + ")");
			return null;
		}
		val = val[key];
	}
	if(typeof val == "object")
		return JSON.parse(JSON.stringify(val));
	else
		return val;
}
