const defaultSettings =
{
	title: "Moozik",
	initial_view: "default",
	default_view: "timeline",
	theme: "theme-one",
	lazy_loading: true,
	queue: {
		//Remove songs from queue when they finish playing
		remove_after_playback: false,
		//When selecting an item in an instance to play, add it to the end of the queue, even if it is already present in the queue
		always_append: true,
		show_load_progress: true,
		show_queue_info: true
	},
	playback: {
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
		unload_all_songs_on_stop: true
	},
	views: {
		timeline: {
			default_grouping: "month",
			next_chunk_scroll_percent: 80,
			//When true, songs with no import date show up at the end of the timeline
			show_songs_with_no_date: false
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
	},
	spotlight: {
		keep_in_view: true
	},
	dev: {
		show_ids: false
	}
};
//enums aren't being used, I'm just listing the values here until they're pulled out to their own .js
const Constraints =
{
	initial_view: {
		type: "enum",
		o: ["default", "restore"]
	},
	default_view: {
		type: "enum",
		o: []
	},
	theme: {
		type: "enum",
		o: ["theme-one"]
	},
	views: {
		timeline: {
			default_grouping: {
				type: "enum",
				o: ["day", "year", "month"]
			},
			next_chunk_scroll_percent: {
				type: "number",
				min: 20,
				max: 100
			}
		},
		random: {
			default_song_count: {
				type: "number",
				min: 1
			},
			default_video_count: {
				type: "number",
				min: 1
			},
			default_album_count: {
				type: "number",
				min: 1
			}
		}
	},
	quicksearch: {
		max_item_count_per_category: {
			type: "number",
			min: 1,
			max: 20
		}
	}
};
let settings = {};

export function Init()
{
	if(!LoadFromStorage())
		settings = JSON.parse(JSON.stringify(defaultSettings));

	RenderMarkup(settings, document.getElementById("config"), "");

	//TODO - add help icon for window title format during playback
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

let headerLevel = 3;
function RenderMarkup(obj, container, settingsPath)
{
	if(settingsPath.length > 0 && settingsPath != "views")
	{
		let header = document.createElement("h"+headerLevel);
		header.innerHTML = settingsPath.substring(settingsPath.lastIndexOf(".")+1);
		container.appendChild(header);
	}
	for(let prop in obj)
	{
		let div = document.createElement("div");
		div.id = "config_" + prop;
		switch(typeof obj[prop])
		{
			case "string":
				div.innerHTML = StringMarkup(prop, obj[prop], settingsPath + prop);
			break;

			case "number":
				div.innerHTML = NumberMarkup(prop, obj[prop], settingsPath + prop);
			break;

			case "boolean":
				div.innerHTML = BoolMarkup(prop, obj[prop], settingsPath + prop);
			break;

			case "object":
				container.appendChild(document.createElement("br"));
				let newPath = (settingsPath.length > 0 ? settingsPath + "." + prop : prop);
				headerLevel++;
				RenderMarkup(obj[prop], div, newPath);
			break;

			default:
			break;
		}
		container.appendChild(div);
	}
	headerLevel--;
}
function StringMarkup(label, placeholder, settingsPath)
{
	let c = GetConstraints(settingsPath);
	/*if(c && c.type == "enum")
		return EnumMarkup(label, placeholder, settingsPath);*/

	let ret = label.replaceAll("_", " ") + ": ";
	ret += "<input type=\"text\" placeholder=\"" + placeholder + "\" name=\"" + settingsPath + "\" />";
	return ret;
}
function EnumMarkup(label, defaultValue, settingsPath)
{
	return "ENUM (" + settingsPath + ")";
}
function NumberMarkup(label, placeholder, settingsPath)
{
	let c = GetConstraints(settingsPath);
	let ret = label.replaceAll("_", " ") + ": ";
	ret += "<input type=\"number\" placeholder=\"" + placeholder + "\" name=\"" + settingsPath + "\" ";
	if(c)
	{
		if(c.min)
			ret += "min=\"" + c.min + "\" ";
		if(c.max)
			ret += "max=\"" + c.max + "\" ";
	}
	ret += "/>";
	return ret;
}
function BoolMarkup(label, defaultValue, settingsPath)
{
	let ret = label.replaceAll("_", " ") + ": ";
	ret += "<input type=\"radio\" name=\"" + settingsPath + "\" value=\"true\"";
	if(defaultValue)
		ret += " checked";
	ret += " ><label for=\"" + settingsPath + "\">True</label>";

	ret += "<input type=\"radio\" name=\"" + settingsPath + "\" value=\"false\"";
	if(!defaultValue)
		ret += " checked";
	ret += " ><label for=\"" + settingsPath + "\">False</label>";
	return ret;
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
	return true;
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

function GetConstraints(path)
{
	let ret = null;
	let obj = Constraints;
	for(let key of path.split('.'))
	{
		if(obj.hasOwnProperty(key))
			obj = obj[key];
		else
			return null;
	}
	return null;
}

