//8,820 samples would a 1/5th of a second for 44.1khz
//4,410 = 1/10th of a second = 100ms
//TODO - Audio worklets require a secure context to use (meaning SSL)
//Chrome has chrome://flags/#unsafely-treat-insecure-origin-as-secure
//to bypass it but firefox doesn't.
//There's a bug report though https://bugzilla.mozilla.org/show_bug.cgi?id=1410365
class BufferedSwapNode extends AudioWorkletProcessor
{
	constructor (options)
	{
		super();
		this.buf1 = [[], []];
		this.buf2 = [[], []];
		this.outBuf = [[], []];
		this.maxSamples = 8820;
		//When true, tells process() to just ignore input 1
		this.switchFlag = false;
		this.port.onmessage = function(event) {
			//FIXME - According to https://hacks.mozilla.org/2020/05/high-performance-web-audio-with-audioworklet-in-firefox/
			//the old arrays will be garbage collected and I probably don't have to sweat the performance impact
			//on the worker thread
			//That being said, it's still probably best to build the processor in WASM
			this.buf1 = [[], []];
			this.buf2 = [[], []];
			this.switchFlag = false;
		};
	}
	process (inputs, outputs, params)
	{
		let sampleCount = inputs[0][0].length;

		//Start by writing from the output buffer
		if(outBuf[0].length >= this.maxSamples)
		{
			let out = [ this.outBuf[0].splice(0, sampleCount), this.outBuf[1].splice(0, sampleCount) ];
			for(let output of outputs)
			{
				for(let i = 0; i < sampleCount; ++i)
				{
					output[0][i] = out[0][i];
					output[1][i] = out[1][i];
				}
			}
		}

		if(inputs.length == 1)
		{
			this.switchFlag = false;
			for(let i = 0; i < o.length; ++i)
			{
				this.buf1[i].push_back();
			}
		}
		else if(inputs.length == 2)
		{
			if(this.switchFlag)
			{
			}
			else
			{
				for(let input of inputs)
				{
					
				}
			}
		}
		return true;
	}
}
registerProcessor("buffered-swap", BufferedSwapNode);
