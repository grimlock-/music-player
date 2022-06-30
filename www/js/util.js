export function DisplayError(message)
{
	let ele = document.getElementById("messages");
	if(!ele.childCount)
		ele.classList.remove("hidden");
	console.error(message);
	let div = document.createElement("div");
	div.className = "error_message";
	div.innerHTML = message;
	document.getElementById("messages").appendChild(div);
	setTimeout(function(){
		ele.removeChild(div);
		if(!ele.childCount)
			ele.classList.add("hidden");
	}, 5000);
}

export function RandomInt(max)
{
	max = Math.floor(max);
	return Math.floor(Math.random() * max);
}

export function StoMS(seconds)
{
	if(typeof seconds != "number")
		seconds = Number(seconds);
	let min = Math.floor(seconds / 60);
	let sec = seconds % 60;
	if(sec < 10)
		sec = "0" + String(sec);
	return "" + min + ":" + sec;
}

export function StoHMS(seconds)
{
	let h = Math.floor(seconds / 3600);
	seconds -= 3600*h;
	let min = Math.floor(seconds / 60);
	if(min < 10)
		min = "0" + String(min);
	let sec = seconds % 60;
	return "" + h + ":" + min + ":" + sec;
}


/********
 sorting
********/
// <0  -  a < b, a comes first
//  0  -  a = b
// >0  -  a > b, b comes first
export function SortSongsByTrackNumber_Asc(first, second)
{
	if(first.disc_number != second.disc_number)
		return first.disc_number - second.disc_number;

	let a = first.track_number || 0;
	let b = second.track_number || 0;
	return a - b;
}
export function SortSongsByTrackNumber_Desc(first, second)
{
	if(first.disc_number != second.disc_number)
		return second.disc_number - first.disc_number;

	let a = first.track_number || 0;
	let b = second.track_number || 0;
	return b - a;
}
export function SortSongsByAlbumName_Asc(first, second)
{
	if(first.album_id)
	{
		if(second.album_id)
			return first.album.localeCompare(second.album);
		else
			return -1;
	}
	else
	{
		if(second.album_id)
			return 1;
		else
			return 0;
	}
}
export function SortSongsByImportDate_Desc(first, second)
{
	return DateSort(first.import_date, second.import_date, "desc");
}
export function SortAlbumsByName_Asc(first, second)
{
	return StringSort(first.name, second.name);
	//return first.name.localeCompare(second.name);
}
export function SortDates_Desc(first, second)
{
	return DateSort(first, second, "desc");
}
function DateSort(first, second, mode = "asc")
{
	let a = first, b = second;
	if(mode == "desc")
	{
		let t = a;
		a = b;
		b = t;
	}

	let y1 = Number(a.substring(0,4));
	let y2 = Number(b.substring(0,4));
	if(y1 != y2)
		return y1 - y2;
	if(y1.length <= 4 || y2.length <= 4)
		return 0;
	let m1 = Number(a.substring(5,7));
	let m2 = Number(b.substring(5,7));
	if(m1 != m2)
		return m1 - m2;
	if(y1.length <= 7 || y2.length <= 7)
		return 0;
	let d1 = Number(a.substring(8));
	let d2 = Number(b.substring(8));
	return d1-d2;
}
function StringSort(first, second, mode = "asc")
{
	if(mode == "asc")
		return first.localeCompare(second);
	else
		return second.localeCompare(first);
}
/*********
 grouping
*********/
export function GroupSongsByDate(songs)
{
	let container = {};
	for(let item of songs)
	{
		if(!container[item.import_date])
			container[item.import_date] = [];
		container[item.import_date].push(item);
	}
	return container;
}
export function GroupSongsByMonth(songs)
{
	let container = {};
	let key;
	for(let item of songs)
	{
		key = item.import_date.substring(0, 7);
		if(!container[key])
			container[key] = [];
		container[key].push(item);
	}
	return container;
}
export function GroupSongsByYear(songs)
{
	let container = {};
	let year;
	for(let item of songs)
	{
		year = item.import_date.substring(0, 4);
		if(!container[year])
			container[year] = [];
		container[year].push(item);
	}
	return container;
}




export function IsSpecialChar(c)
{
	return c.match(new RegExp('^[`~!@#\\$%\\^&\\*\\(\\)\\./,\\<\\>\\?\\[\\]\\"\\\';\\:\\-_=+\\]\\\\|\\{\\}]'));
}
export function IsPunct(c)
{
	return c.match(/^[:punct:]/);
}
export function EscHtml(str)
{
	let ret = "";
	for(var c of str)
	{
		switch(c)
		{
			case '<':
				ret += "&lt;";
			break;
			case '>':
				ret += "&gt;";
			break;
			case '\'':
				ret += "&#039;";
			break;
			case '"':
				ret += "&quot;";
			break;
			/*case '`':
				ret += "&;";
			break;*/
			case '&':
				ret += "&amp;";
			break;
			default:
				ret += c;
			break;
		}
	}
	return ret;
}
