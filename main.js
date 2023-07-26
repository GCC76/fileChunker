//	*********************************************
//	fileChunker BY Gian Carlo Ciaccolini
//	https://github.com/GCC76
//	Not for commercial use
// 	********************************************

class fileChunker{
	
	
	constructor(htmlInput=null,customerID=null,serviceID=null){
		
		this.postURL = "get_data.php"
		this.chuckMinSize = 256000;
		this.input = document.querySelector(htmlInput);
		this.customerID = customerID;
		this.serviceID = serviceID;
		this.fileSplit();
		
	}
	
	fileSplit(){
		
		this.input.addEventListener('change', () => {
			
			document.querySelector("#chunks_container").innerHTML = '';
			
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
			
			for(let c=1; c <= chuncksNumber; c++){
				let div = document.createElement("div");
				div.setAttribute("class","chunk");
				div.setAttribute("id","chunk_"+c);
				document.querySelector("#chunks_container").appendChild(div);
			}
			
			let currentChunk = 1;
			
			chunks.forEach(chunk => {
				
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
				this.dataSend(formData,currentChunk);
				
				currentChunk ++;
			})
		})
	}
	
	dataSend(formData,currentChunk){
		
		let chunk = document.querySelector("#chunk_"+currentChunk);
		
		fetch(this.postURL, {
			method: 'post',
			body: formData
		})
		.then(res => {
			if(res.status == 200){
				chunk.classList.add("saved");
			} else {
				chunk.classList.add("unsaved");
			}
			return res.json()
		})
		.then(data => {
			
			let response = (data && data['description']) ? data['description'] : null;
			
			if(response){
				if(response == "done"){
					alert("Operation completed");
				} else{
					console.log(response)
				}
			}
		});
	}
}