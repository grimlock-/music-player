const API = location.href + "api/";

let Songs = {};
let Videos = {};
let Views = {};
let SearchResults = {};

export function LoadSongInfo(ids)
{
	let param;
	if(Array.isArray(ids))
		param = ids.join(",");
	else if(typeof ids == "string")
		param = ids;
	else
		return;

	return fetch(API + "song_info.php?ids=" + encodeURI(param))
	.then(function(response){
		if(response.status == 200)
			return response.json();
	}).then(function(info){
		if(info.error_message)
		{
			console.log("Error getting song info: " + info.error_message);
			return;
		}
		for(let i of info)
		{
			if(!Songs[i.id])
				Songs[i.id] = i;
		}
	});
}

export function SetSongInfo(id, obj)
{
	Songs[id] = obj;
}

export function GetSongInfo(id)
{
	if(Songs[id] !== undefined)
		return Songs[id];
	else
		return null;
}

export function SetVideoInfo(id, obj)
{
	Videos[id] = obj;
}

export function GetVideoInfo(id)
{
	if(Videos[id] !== undefined)
		return Videos[id];
	else
		return null;
}

export function SetViewData(id, data)
{
	Views[id] = data;
}

export function GetViewData(id)
{
	if(Views[id] !== undefined)
		return Views[id];
	else
		return null;
}

export function SetSearchResults(key, data)
{
	SearchResults[key] = data;
}

let qsTest = [
	{id: "someidhere"},
	{id: "someidhere"}
];
export function GetSearchResults(key)
{
	if(SearchResults !== undefined)
		return SearchResults[key];
	else
		return null;
}
