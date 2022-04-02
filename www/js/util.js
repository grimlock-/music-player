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


// -1  -  a < b, a comes first
//  0  -  a = b
//  1  -  a > b, b comes first
export function SongsByTrackNumber_Asc(first, second)
{
	if(first.disc_number > second.disc_number)
		return 1;
	if(second.disc_number > first.disc_number)
		return -1;

	if(first.track_number)
	{
		if(second.track_number)
			return first.track_number - second.track_number;
		else
			return 1;
	}
	else
	{
		if(second.track_number)
			return -1;
		else
			return 0;
	}
}

export function SongsByTrackNumber_Desc(first, second)
{
	if(first.disc_number > second.disc_number)
		return -1;
	if(second.disc_number > first.disc_number)
		return 1;

	if(first.track_number)
	{
		if(second.track_number)
			return second.track_number - first.track_number;
		else
			return -1;
	}
	else
	{
		if(second.track_number)
			return 1;
		else
			return 0;
	}
}

export function SongsByAlbumName_Asc(first, second)
{
	if(first.album_id)
	{
		if(!second.album_id)
			return 1;
		else
			return second.album.localeCompare(first.album);
	}
	else
	{
		if(!second.album_id)
			return second.album;
		else
			return -1;
	}
}

export function AlbumsByName_Asc(first, second)
{
	return first.name.localeCompare(second.name);
}

export function Dates_Desc(first, second)
{
	let y1 = Number(first.substring(0,4));
	let y2 = Number(second.substring(0,4));
	if(y1 != y2)
	{
		if(y1 > y2)
			return -1;
		else
			return 1;
	}
	if(y1.length > 4 || y2.length > 4)
		return 0;
	let m1 = Number(first.substring(5,7));
	let m2 = Number(second.substring(5,7));
	if(m1 != m2)
	{
		if(m1 > m2)
			return -1;
		else
			return 1;
	}
	if(y1.length > 7 || y2.length > 7)
		return 0;
	let d1 = Number(first.substring(8));
	let d2 = Number(second.substring(8));
	return d2-d1;
}

export function IsSpecialChar(c)
{
	return c.match(new RegExp('^[`~!@#\\$%\\^&\\*\\(\\)\\./,\\<\\>\\?\\[\\]\\"\\\';\\:\\-_=+\\]\\\\|\\{\\}]'));
}
export function IsPunct(c)
{
	return c.match(/^[:punct:]/);
}

/***********************************/
// https://stackoverflow.com/questions/7255719/downloading-binary-data-using-xmlhttprequest-without-overridemimetype#answer-60760997
export function hexdump(uint8array)
{
    let count = 0;
    let line = "";
    let lineCount = 0;
    let content = "";
    for(let i=0; i<uint8array.byteLength; i++) {
        let c = uint8array[i];
        let hex =  c.toString(16).padStart (2, "0");
        line += hex + " ";
        count++;
        if (count === 16) {
            let lineCountHex = (lineCount).toString (16).padStart (7, "0") + "0";
            content += lineCountHex + " " + line + "\n";
            line = "";
            count = 0;
            lineCount++;
        }
    }
    if(line) {
        let lineCountHex = (lineCount).toString (16).padStart (7, "0") + "0";
        content += lineCountHex + " " + line + "\n";
        line = "";
        //            count = 0;
        lineCount++;
    }
    content+= (lineCount).toString (16).padStart (7, "0") + count.toString(16) +"\n";
    return content;
}

export function textToFile(fname, text)
{
	let tag = document.createElement("a");
	tag.setAttribute("href", "data:text/plain;charset=utf-8," + encodeURIComponent(text));
	tag.setAttribute("download", fname);
	tag.style.display = "none";
	document.body.appendChild(tag);
	tag.click();
	document.body.removeChild(tag);
}
/*console.log("`: " + (IsSpecialChar("`") != null));
console.log("~: " + (IsSpecialChar("~") != null));
console.log("!: " + (IsSpecialChar("!") != null));
console.log("@: " + (IsSpecialChar("@") != null));
console.log("#: " + (IsSpecialChar("#") != null));
console.log("$: " + (IsSpecialChar("$") != null));
console.log("%: " + (IsSpecialChar("%") != null));
console.log("^: " + (IsSpecialChar("^") != null));
console.log("&: " + (IsSpecialChar("&") != null));
console.log("*: " + (IsSpecialChar("*") != null));
console.log("(: " + (IsSpecialChar("(") != null));
console.log("): " + (IsSpecialChar(")") != null));
console.log("_: " + (IsSpecialChar("_") != null));
console.log("+: " + (IsSpecialChar("+") != null));
console.log("-: " + (IsSpecialChar("-") != null));
console.log("=: " + (IsSpecialChar("=") != null));
console.log(",: " + (IsSpecialChar(",") != null));
console.log(".: " + (IsSpecialChar(".") != null));
console.log("/: " + (IsSpecialChar("/") != null));
console.log(";: " + (IsSpecialChar(";") != null));
console.log(":: " + (IsSpecialChar(":") != null));
console.log("\\: " + (IsSpecialChar("\\") != null));
console.log("|: " + (IsSpecialChar("|") != null));
console.log("<: " + (IsSpecialChar("<") != null));
console.log(">: " + (IsSpecialChar(">") != null));
console.log("?: " + (IsSpecialChar("?") != null));
console.log("[: " + (IsSpecialChar("[") != null));
console.log("]: " + (IsSpecialChar("]") != null));
console.log("{: " + (IsSpecialChar("{") != null));
console.log("}: " + (IsSpecialChar("}") != null));
console.log("a: " + (IsSpecialChar("a") != null));
console.log("q: " + (IsSpecialChar("q") != null));
console.log("3: " + (IsSpecialChar("3") != null));
console.log("ü: " + (IsSpecialChar("ü") != null));
console.log("letter S: " + (IsSpecialChar("S") != null));*/
/***********************************/

