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
		this.targetBuffer = this.buf1;
		this.maxSamples = 4410;
		this.minBufferFill = 1;
		//When true, use buffer 2
		this.switchFlag = 0;
		this.port.onmessage = this.onMessage.bind(this);

		//this.counter = 0;
	}

	process (inputs, outputs, params)
	{
		/*if(++this.counter > 1725)
		{
			console.group("5s tick");
			console.log("Inputs: " + inputs.length);
			console.log("Samples: " + inputs[0][0].length);
			console.groupEnd();
			this.counter = 0;
		}*/

		let sampleCount = inputs[0][0].length;

		//Add new input to buffers
		for(let i = 0; i < 2; ++i)
		{
			let input = inputs[i];
			let buffer = this["buf"+(i+1)];
			//only 2 channels per input
			for(let ch = 0; ch < input.length; ++ch)
			{
				let channel = input[ch];
				buffer[ch].push(...channel);
			}
		}

		//minBufferFill is 1.0 on init and 0.5 after it starts writing audio
		if(this.targetBuffer[0].length >= this.maxSamples * this.minBufferFill)
			this.WriteAudio(inputs, outputs);

		return true;
	}

	WriteAudio(inputs, outputs)
	{
		let sampleCount = outputs[0][0].length;

		//Before switching, check if buffer 2 is full and attempt to switch
		//Once the node has switched this will always eval to false
		if(this.buf2[0].length >= this.maxSamples * (1 + this.switchFlag))
		{

			//TODO? - I was originally planning to analyze the samples to find
			//where the two buffers can be seamlessly stitched together, but they
			//don't seem to be getting desynched in my testing
			//if switching
				//set up buf2
				this.targetBuffer = this.buf2;
				this.switchFlag = 1;
				this.minBufferFill = 0.5;
				this.port.postMessage("switched");
		}
		
		//Write samples
		for(let out of outputs)
			this.CopyToOutput(this.targetBuffer, out);
		this.targetBuffer[0].splice(0, sampleCount);
		this.targetBuffer[1].splice(0, sampleCount);
	}

	CopyToOutput(buffer, output)
	{
		for(let ch = 0; ch < output.length; ++ch)
		{
			let in_ch = buffer[ch];
			let out_ch = output[ch];
			for(let sample = 0; sample < out_ch.length; ++sample)
			{
				out_ch[sample] = in_ch[sample];
			}
		}
	}

	SetInput(input)
	{
		switch(input)
		{
			case 1:
				this.targetBuffer = this.buf1;
				this.switchFlag = 0;
				this.minBufferFill = 1;
			break;
			case 2:
				this.targetBuffer = this.buf2;
				this.switchFlag = 1;
				this.minBufferFill = 0.5;
			break;
		}
	}

	Reset()
	{
		console.log("[BufferedSwapNode] Reseting");
		//FIXME - According to https://hacks.mozilla.org/2020/05/high-performance-web-audio-with-audioworklet-in-firefox/
		//the old arrays will be garbage collected and I probably don't have to sweat the performance impact
		//on the worker thread
		//That being said, it's still probably best to build the processor in WASM
		this.buf1 = [[], []];
		this.buf2 = [[], []];
		this.SetInput(1);
	}

	onMessage(e)
	{
		switch(e.data)
		{
			case "stop":
				this.Reset();
			break;
		}
	}
}
registerProcessor("buffered-swap", BufferedSwapNode);
