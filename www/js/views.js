/**
 * Views are any objects with these properties:
 *     *initialized
 *         Boolean flag. Value determines if Init() or Draw() is used to
 *         instantiate view
 *     *Init()
 *         Handles initialization, only called once. Some views will have
 *         unchanging properties like the letter buckets for artists and albums,
 *         this is where the data for those is established
 *     *Draw()
 *         Instantiates the view's template. Some will replace the current view
 *         by clearing the #instance element, others will bring up modals.
 *     *Out()
 *         Called before initializing another view if the current one needs to do cleanup
 *
 * There's also some conventions
 *     *Apply()
 *         Not called from external code, but a convention used for views that
 *         depend on data from an endpoint. This is used to redraw elements
 *         related to that data without redrawing the entire view.
 *     *_underscore prefix
 *         Functions starting with an underscore are event listeners that don't
 *         use bind() to change their "this" reference. All other functions
 *         have "this" referencing their view object
 */

import * as Util from './util.js';
import * as Config from './config.js';

let Instance = document.getElementById("instance");
let Views = [];
let CurrentView,
CurrentSection,
ChangingViews = false;

export function AddView(obj, label)
{
	Views[label] = obj;
}

export function Get(name)
{
	if(Views[name])
		return Views[name];
	else
		Util.DisplayError("No view with label \"" + name + "\"");
}

export function LoadTemplate(template, section = "default")
{
	let template_obj = $(template);
	if(template_obj)
	{
		console.log(`Loading template ${template} | section: ${section}`);

		while(Instance.firstChild)
			Instance.removeChild(Instance.firstChild);
		
		for(let ele of template_obj.content.children)
		{
			Instance.appendChild(ele.cloneNode(true));
		}
	}
	else
	{
		Util.DisplayError("No template found for selector: " + template);
	}
}

window.SetView = function(view, section = "default")
{
	if(!view || ChangingViews)
		return;

	ChangingViews = true;
	let viewObj = Get(CurrentView || Config.Get("default_view"));
	if(viewObj.initialized && viewObj.Out)
		viewObj.Out();
	viewObj = Get(view);
	if(!viewObj.initialized)
		viewObj.Init(section);
	else
		viewObj.Draw();

	CurrentView = view;
	localStorage.setItem("last_view", view);
	RefreshViewIndicator();
	let instClasses = document.getElementById("instance").classList;
	while(instClasses.length > 0)
		instClasses.remove(instClasses[0]);
	instClasses.add(view);
	
	ChangingViews = false;
}

function RefreshViewIndicator()
{
	//Deactivate buttons
	for(let button of $$(".active[data-view]"))
	{
		button.classList.remove("active");
		button.addEventListener("click", _setview);
	}
	//Activate category button for current view
	if(CurrentView == Config.Get("default_view"))
		return;
	for(let button of $$("#categories > *[data-view=" + CurrentView + "]"))
	{
		button.classList.add("active");
	}
}

function _setview(e)
{
	//TODO - get previous section for this view
	if(CurrentView == this.dataset.view)
		return;
	SetView(this.dataset.view);
}

for(let button of document.querySelectorAll("*[data-view]"))
{
	button.addEventListener("click", _setview);
}
