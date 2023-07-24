
//Split file in chunks and send them to backend

class fileChunker{
	
	
	constructor(htmlInput=null,customerID=null,serviceID=null){
		
		this.postURL = "get_data.php"
		this.chuckMinSize = 512000;
		this.input = document.querySelector(htmlInput);
		this.customerID = customerID;
		this.serviceID = serviceID;
		this.fileSplit();
		
	}
	
	fileSplit(){
		
		this.input.addEventListener('change', () => {
		
			const file = this.input.files[0];
			const fileSize = file.size;
			const fileNameSplit = file.name.split('.')
			const fileExtension = fileNameSplit.pop();
			const fileName =  fileNameSplit[0];

			const multiplier = Math.round( (fileSize / this.chuckMinSize) - 1);
			const divider = (multiplier > 0) ? multiplier : 1;
			const blobSize = fileSize / divider;  
			const chunks = [];
			
			let startPointer = 0;
			
			while (startPointer < fileSize) {
				let newStartPointer = startPointer + blobSize;
				chunks.push(file.slice(startPointer,newStartPointer));
				startPointer = newStartPointer;
			}
			
			const chuncksNumber = chunks.length;
			
			let currentChunk = 1;
			
			chunks.forEach(chunk => {
				
				//console.log(chuncksNumber, currentChunk);
				
				let formData = new FormData();
				let chunkSize = chunk.size
				
				formData.append("customerID",this.customerID);
				formData.append("serviceID",this.serviceID);
				formData.append("fileName",fileName);
				formData.append("fileSize",fileSize);
				formData.append("fileExtension",fileExtension);
				formData.append("chuncksNumber",chuncksNumber);
				formData.append("currentChunk",currentChunk);
				formData.append("chunkSize",chunkSize);
				formData.append("chunk",chunk);
				this.dataSend(formData);
				
				currentChunk ++;
			})
		})
	}
	
	dataSend(formData){
		
		fetch(this.postURL, {
			method: 'post',
			body: formData
		})
		.then(res => {
			return res.text()
		})
		.then(data => {
			console.log(data)
		});
		
	}
	
	
}