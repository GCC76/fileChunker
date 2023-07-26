<?php
//*******************************************
//	fileChunker BY Gian Carlo Ciaccolini
//	https://github.com/GCC76
//
//	An example of how to handle the multiple chunks stream of files sent from the front-end
//	Since there could be multiple chunks, coming in the same time, we need a DB (MongoDb in this case) to check the flux
//	We also need to define what running code/ working session is the last one so it can complete the process,
//	otherwise there could be more joining process working together
//	******************************************

header("HTTP/1.1 200 OK");


class fileChunker{
	
	public $customerID  	= null;
	public $serviceID 		= null;
	public $fileName 		= null;
	public $fileSize 		= null;
	public $fileExtension 	= null;
	public $chuncksNumber 	= null;
	public $currentChunk	= null;
	public $chunkSize 		= null;
	public $thread			= null;
	public $file			= null;
	public $chunk			= null;
	private $client			= null;
	private $folder			= null;
	private $database		= null;
	private $resp 			= array();
		
	function __construct(){
		
		$this->client 			= new MongoDB\Client("mongodb://localhost:27017");
		$this->database 		= $this->client->chunker;
		$this->collection 		= $this->database->files;
		
		
		$this->customerID 		= isset($_POST["customerID"]) 			? (int)$_POST["customerID"] 	: 0;
		$this->serviceID 		= isset($_POST["serviceID"]) 			? (int)$_POST["serviceID"] 		: 0;
		$this->fileName 		= isset($_POST["fileName"]) 			? $_POST["fileName"] 			: null;
		$this->fileSize 		= isset($_POST["fileSize"]) 			? (int)$_POST["fileSize"] 		: null;
		$this->fileExtension 	= isset($_POST["fileExtension"])		? $_POST["fileExtension"] 		: null;
		$this->chuncksNumber 	= isset($_POST["chuncksNumber"])		? (int)$_POST["chuncksNumber"] 	: null;
		$this->currentChunk		= isset($_POST["currentChunk"])			? (int)$_POST["currentChunk"] 	: null;
		$this->chunkSize 		= isset($_POST["chunkSize"]) 			? (int)$_POST["chunkSize"] 		: null;
		$this->chunk			= isset($_FILES["chunk"]["tmp_name"]) 	? $_FILES["chunk"]["tmp_name"] 	: null;
		
		//Random string
		$this->thread 			= bin2hex(random_bytes(16));
	
	}
	
	//Main function
	public function fileRecompile(){
		
		if(!$this->chuckCheck()){
			return $this->resp;
		};
		
		if(!$this->fileMove()){
			$this->resp["description"] = "Chunk ".$this->chuncksNumber." cannot be saved. Try again.";
			return $this->resp;
		};
		
		if(!$this->setFiles()){
			return $this->resp;
		};
		
		if(!$this->checkFiles()){
			return $this->resp;
		} 
		
		if(!$this->fileJoin()){
			return $this->resp;
		}

		return ($this->fileRename());

	}
	
	//Check fo incomig data
	private function chuckCheck(){
		
		try{
			
			if(!$this->fileName){
				$this->resp["description"] = "File name is required";
				throw new Exception("400");
			};
			if(!$this->fileSize){
				$this->resp["description"] = "Total file size is required";
				throw new Exception("400");
			};
			if(!$this->fileExtension){
				$this->resp["description"] = "File extension is required";
				throw new Exception("400");
			};
			if(!$this->chuncksNumber){
				$this->resp["description"] = "Total chunks number is required";
				throw new Exception("400");
			};
			if(!$this->currentChunk){
				$this->resp["description"] = "Receiving chunk number is required";
				throw new Exception("400");
			};
			if(!$this->chunkSize){
				$this->resp["description"] = "Receiving chunk size is required";
				throw new Exception("400");
			};
			if(!$this->chunk){
				$this->resp["description"] = "No file received";
				throw new Exception("400");
			};
			
			return true;
			
		}
		catch(Exception $e){
			
			header("HTTP/1.1 ".$e->getMessage());
			return false;
		}
	}
	
	//Create relative folder and save file
	private function fileMove(){
		
		try {
			
			$this->folder = getcwd()."\\upload\\";
			if(!file_exists($this->folder )){
				$oldmask = umask(0);
				Mkdir($this->folder ,0777);
				umask($oldmask);
			};

			$this->folder = getcwd()."\\upload\\".$this->customerID."\\";
			if(!file_exists($this->folder )){
				$oldmask = umask(0);
				Mkdir($this->folder ,0777);
				umask($oldmask);
			};

			$this->folder = getcwd()."\\upload\\".$this->customerID."\\".$this->serviceID."\\";
			if(!file_exists($this->folder )){
				$oldmask = umask(0);
				Mkdir($this->folder ,0777);
				umask($oldmask);
			};

			$this->folder = getcwd()."\\upload\\".$this->customerID."\\".$this->serviceID."\\".$this->fileName."\\";
			if(!file_exists($this->folder )){
				$oldmask = umask(0);
				Mkdir($this->folder ,0777);
				umask($oldmask);
			};

			$this->file 	= $this->folder.$this->fileName.".".$this->fileExtension;
			$this->tmpName 	= $this->file."_".$this->currentChunk.".tmp";
			$this->partial	= $this->file.$this->thread.".partial";
			
			if(!move_uploaded_file($this->chunk, $this->tmpName)){
				throw new Exception("449");
			};
			
			//Delete any previous file
			/*
			if(file_exists($this->partial)){
				array_map("unlink", glob($this->folder."*.partial"));
			}*/
			
			return true;
			
		}
		catch(Exception $e){
			
			header("HTTP/1.1 ".$e->getMessage());
			return false;
		}
	}
	
	
	//Check incoming chunk and save it to db
	private function setFiles(){
		
		try {
			
			$document = $this->collection->findOne([
				"customerID" => $this->customerID, 
				"serviceID" => $this->serviceID, 
				"name" => $this->fileName, 
				"chunkNumber" => $this->currentChunk
			]);
			
			if(!$document){
				
				$insertOneResult = $this->collection->insertOne([
					"customerID" => $this->customerID,
					"serviceID" => $this->serviceID,
					"name" => $this->fileName,
					"size" => $this->fileSize,
					"extension" => $this->fileExtension,
					"totalChunks" => $this->chuncksNumber,
					"chunkNumber" => $this->currentChunk,
					"chunnksize" => $this->chunkSize,
					"folder" => $this->tmpName,
					"runnigThread" => "",
					"active" => 1,
				]);
		
			} else {
				
				$updateResult = $this->collection->updateOne(
					[
					"customerID" => $this->customerID, 
					"serviceID" => $this->serviceID, 
					"name" => $this->fileName, 
					"chunkNumber" => $this->currentChunk
					],
					['$set' => ["chunnksize" => (int)$this->chunkSize, "folder" => $this->tmpName, "active" => 1]]
				);
			}
			
			return true;
		}
		catch(Exception $e){
			
			header("HTTP/1.1 500");
			$this->resp["description"] = $e->getMessage();
			return false;
		}
		
	}
	
	//Verify if all chunked are been saved
	private function checkFiles(){
		$document = $this->collection->find([
			"customerID" => $this->customerID,
			"serviceID" => $this->serviceID,
			"name" => $this->fileName,
			"active" => 1
		]);
		$saved = count($document->toArray());
		return ($saved == $this->chuncksNumber) ? true: false;
	}
	
	
	//Join the chunks together in a new file
	private function fileJoin(){
		
		try {
			
			$size = 0;
			
			//Update all chunks with the string generated in this working session
			$updateResult = $this->collection->updateMany(
				["customerID" => $this->customerID, "serviceID" => $this->serviceID, "name" => $this->fileName],
				['$set' => ["runnigThread" => $this->thread]]
			);
		
			//Wait for others working session to update finishing
			usleep(500000);

			//Get all saved chunks with the working session
			$document = $this->collection->find(
				[
				"customerID" => $this->customerID, 
				"serviceID" => $this->serviceID, 
				"name" => $this->fileName,
				"active" => 1,
				],
				["sort" => ["chunkNumber" => 1]]
			);
			foreach($document as $row ){
				
				$chunk = $row["folder"];
				$size += $row["chunnksize"];
				$chunkNumber = $row["chunkNumber"];
				
				//Check the saved chunk
				//Since there could be multiple sessions running in parallel time i need to take only chunks from this section
				if(file_exists($chunk) and $row["runnigThread"] == $this->thread){
					
					$content = file_get_contents($chunk);
					if(!file_put_contents($this->partial,$content,FILE_APPEND)){
						$this->resp['description'] = "Error during merging process. Try again";
						throw new Exception("449");
					}
					$updateResult = $this->collection->updateOne(
						[
						"customerID" => $this->customerID, 
						"serviceID" => $this->serviceID, 
						"name" => $this->fileName, 
						"chunkNumber" => $chunkNumber
						],
						['$set' => ["active" => 0]]
					);
				}
			}
			//If this working session has skipped the merging process (it's not the last one) the partial file should not been already created
			return (file_exists($this->partial)) ? true : false;
		}
		catch(Exception $e){
			header("HTTP/1.1 ".$e->getMessage());
			return false;
		}
	}
	
	//Rename partial to original file extension
	private function fileRename(){
		
		try {
			
			$tmp_size = filesize($this->partial);
			if($tmp_size == $this->fileSize){
				
				if(!rename($this->partial,$this->file)){
					$this->resp['description'] = "Error while renaming file. Try again.";
					throw new Exception("449");
				}
				
				if(!array_map("unlink", glob($this->folder."*.tmp"))){
					$this->resp['description'] = "Error cleaning folder. Try again.";
					throw new Exception("449");
				}
				
				array_map("unlink", glob($this->folder."*.partial"));
				$this->resp["description"] = "done";
				return $this->resp;
				
			};
			
		}
		catch(Exception $e){
			header("HTTP/1.1 ".$e->getMessage());
			return $this->resp;
		}
		
	}
}
?>