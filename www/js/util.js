import * as Config from './config.js';
import * as Cache from './cache.js';
import * as Spotlight from './spotlight.js';
import * as Queue from './queue.js';

export let Months = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

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

export function XhrErrorCheck(response, errMsg1 = "", errMsg2 = "")
{
	if(response.status != 200)
	{
		if(errMsg1)
			Util.DisplayError(errMsg1);
		return null;
	}
	
	let obj = JSON.parse(response.responseText);
	if(obj.error_message)
	{
		if(errMsg2)
			Util.DisplayError(errMsg2 + obj.error_message);
		return null;
	}

	return obj;
}

export function RandomInt(max)
{
	max = Math.floor(max);
	return Math.floor(Math.random() * max);
}

export function Clamp(num, min, max)
{
	return Math.min(Math.max(min, num), max);
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
export function SortSongsByImportDate_Asc(first, second)
{
	return DateSort(first.import_date, second.import_date);
}
export function SortSongsByImportDate_Desc(first, second)
{
	return DateSort(first.import_date, second.import_date, "desc");
}
export function SortAlbumsByTitle_Asc(first, second)
{
	return StringSort(first.title, second.title);
}
export function SortAlbumsByTitle_Desc(first, second)
{
	return StringSort(first.title, second.title, "desc");
}
export function SortArtistsByName_Asc(first, second)
{
	return StringSort(first.name, second.name);
}
export function SortArtistsByName_Desc(first, second)
{
	return StringSort(first.name, second.name, "desc");
}
export function SortDates_Asc(first, second)
{
	return DateSort(first, second);
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
export function RenderMarkdown(string, container)
{
	let ele = make("p", "");
	for(let line of string.split('\n'))
	{
		if(line.length == 0)
		{
			ele.innerHTML += "<br/>";
		}
		else if(line.indexOf('[') == -1)
		{
			ele.innerHTML += EscHtml(line);
		}
		else
		{
			let start = 0;
			let i = line.indexOf('[');
			do
			{
				let ii = line.indexOf(']', i+1);
				let paren1 = line.indexOf('(', ii);
				let paren2 = line.indexOf(')', ii);
				let a = make("a");
				if(i-start > 0)
					ele.innerHTML += EscHtml(line.substring(start,i));
				a.innerHTML = EscHtml(line.substring(i+1, ii));
				a.href = line.substring(paren1+1, paren2);
				ele.appendChild(a);

				start = paren2+1;
				i = line.indexOf('[', start);
			} while(i != -1);
			if(start <= line.length)
				ele.innerHTML += line.substring(start);
		}
	}
	container.appendChild(ele);
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
function _albumArtError(error)
{
	let albumUrl = location.href + "img/album.png";
	if(this.src != albumUrl)
		this.src = albumUrl;
}
export function MakeAlbumTile(album)
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
	i.addEventListener("click", Spotlight._collectionClick);
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
export function _appendCollection(e)
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
export function _addSong()
{
	if(Config.Get("queue.always_append") === true || Queue.IndexOf(this.dataset.songid) == -1)
		Queue.AddSong(this.dataset.songid);
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

export function GetCollectionRoot(ele)
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

export function _downloadCollection(e)
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
