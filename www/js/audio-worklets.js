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
		this.maxSamples = 4410;
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

		if(this.buf1[0].length >= this.maxSamples)
		{
			if(inputs.length == 1)
				this.WriteAudio_OneInput(outputs);
			else
				this.WriteAudio_TwoInputs(inputs, outputs);
		}

		//Add new audio to buffers, only use first two inputs
		for(let i = 0; i < inputs.length; ++i)
		{
			let input = inputs[i];
			let buffer = this["buf"+(i+1)];
			//only 2 channels per input
			for(let ch = 0; ch < 2; ++ch)
			{
				buffer[ch].push(...(input[ch]));
			}
		}

		return true;
	}

	WriteAudio_OneInput(outputs)
	{
		let sampleCount = outputs[0][0].length;

		//If flag is set, discard first buffer and use second
		if(this.switchFlag)
		{
			//move buf2 to buf1
			this.buf1[0] = this.buf2[0];
			this.buf1[1] = this.buf2[1];
			this.buf2 = [[], []];
			this.switchFlag = false;
		}

		for(let out of outputs)
			this.CopyToOutput(this.buf1, out);

		this.buf1[0].splice(0, sampleCount);
		this.buf1[1].splice(0, sampleCount);
	}

	WriteAudio_TwoInputs(inputs, outputs)
	{
		let sampleCount = outputs[0][0].length;

		//Until second buffer is full just keep going with input 1
		if(this.buf2[0].length < this.maxSamples)
		{
			for(let out of outputs)
				this.CopyToOutput(this.buf1, out);
			this.buf1[0].splice(0, sampleCount);
			this.buf1[1].splice(0, sampleCount);
		}
		//If flag is set, ignore first buffer
		else if(this.switchFlag)
		{
			for(let out of outputs)
				this.CopyToOutput(this.buf2, o);
			this.buf2[0].splice(0, sampleCount);
			this.buf2[1].splice(0, sampleCount);
		}
		else
		{
			//TODO - Find the point to switch at
			//set up buf2
			//set switchFlag so buf2 gets used
			/*for(let input of inputs)
			{
				
			}*/
			this.switchFlag = true;
			console.log("Switching to buffer 2");
		}
	}

	CopyToOutput(buffer, output)
	{
		for(let i = 0; i < buffer.length; ++i)
		{
			let in_ch = buffer[i];
			let out_ch = output[i];
			for(let sample = 0; sample < in_ch.length; ++sample)
			{
				out_ch[sample] = in_ch[sample];
			}
		}
	}
}
registerProcessor("buffered-swap", BufferedSwapNode);
