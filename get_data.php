<?php
//*******************************************
//An example of how to handle the multiple chunks stream of files sent from the front-end
//Since there could be multiple chunks, coming in the same time, we need a DB (MongoDb in this case) to check te flux
//We also need to define what running code/ working session is the last one so it can complete the process,
//otherwise there could be more joining process working together
//******************************************


header("Access-Control-Allow-Origin: *");
require_once __DIR__ . '/vendor/autoload.php';


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
		
	function __construct(){
		
		$this->client 			= new MongoDB\Client("mongodb://localhost:27017");
		$this->database 		= $this->client->chunker;
		$this->collection 		= $this->database->files;
		$this->customerID 		= (int)$_POST['customerID'];
		$this->serviceID 		= (int)$_POST['serviceID'];
		$this->fileName 		= $_POST['fileName'];
		$this->fileSize 		= (int)$_POST['fileSize'];
		$this->fileExtension 	= $_POST['fileExtension'];
		$this->chuncksNumber 	= (int)$_POST['chuncksNumber'];
		$this->currentChunk		= (int)$_POST['currentChunk'];
		$this->chunkSize 		= (int)$_POST['chunkSize'];
		
		//Random string
		$this->thread 			= bin2hex(random_bytes(16));
	
	}
	
	//Check and create relative folder
	public function createFolder(){
		
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

		$this->file 	= $this->folder.$this->fileName.'.'.$this->fileExtension;
		$this->tmpName 	= $this->file.'_'.$this->currentChunk.'.tmp';
		$this->partial	= $this->file."_".$this->thread.".partial";
		
		return $this->tmpName;
		
	}
	
	//Move the chunk to the relative folder
	public function moveFile($file,$tmpName){
		return move_uploaded_file($file,$tmpName) ? true : false;
	}
	
	
	//Check incoming chunk and save it to db
	public function setFiles(){
		
		$document = $this->collection->findOne(
			["customerID" => $this->customerID, "serviceID" => $this->serviceID, "name" => $this->fileName, "chunkNumber" => $this->currentChunk]
		);
		
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
			]);

		} else {
			
			$updateResult = $this->collection->updateOne(
				["customerID" => $this->customerID, "serviceID" => $this->serviceID, "name" => $this->fileName, "chunkNumber" => $this->currentChunk],
				['$set' => ["chunnksize" => (int)$this->chunkSize, "folder" => $this->tmpName]]
			);
			
		}
	}
	
	//Verify the saved chunks
	public function checkFile(){
		
		$document = $this->collection->find(["customerID" => $this->customerID, "serviceID" => $this->serviceID, "name" => $this->fileName]);

		$saved = count($document->toArray());
		return ($saved == $this->chuncksNumber) ? true: false;
	}
	
	
	//Join the chunks together in a new file
	public function fileJoin(){
		
		$size = 0;
		
		//Update all chunks with the string generated in this working session
		$updateResult = $this->collection->updateMany(
			["customerID" => $this->customerID, "serviceID" => $this->serviceID, "name" => $this->fileName],
			['$set' => ["runnigThread" => $this->thread]]
		);

		
		//Get all saved chunks
		$document = $this->collection->find(["customerID" => $this->customerID, "serviceID" => $this->serviceID, "name" => $this->fileName], ["sort" => ["chunkNumber" => 1]]);
		foreach($document as $row ){
			
			$chunk = $row['folder'];
			$size += $row['chunnksize'];
			
			//Check the saved chunk
			//Since there could be multiple sessions running at the same time i need only the last one to start the joining process
			if(file_exists($chunk) and $row['runnigThread'] == $this->thread){
				$content = file_get_contents($chunk);
				file_put_contents($this->partial,$content,FILE_APPEND);
			};
		}
		
		//If the partial file is already been created
		//One working session could have skipped the process before
		if(file_exists($this->partial)){
			
			$tmp_size = filesize($this->partial);

			if($tmp_size == $this->fileSize){
				rename($this->partial,$this->file);
				array_map('unlink', glob($this->folder."*.tmp"));
				$document = $this->collection->deleteMany(["customerID" => $this->customerID, "serviceID" => $this->serviceID, "name" => $this->fileName]);
				return true;
			}
		}
	}
}



$chunker 	= new fileChunker();
$tmpName 	= $chunker->createFolder();
$moveFile 	= $chunker->moveFile($_FILES['chunk']['tmp_name'],$tmpName);
$fileSave	= $chunker->setFiles();
$checkFile	= $chunker->checkFile();
if(!$checkFile){
	exit;
};

$fileJoin	= $chunker->fileJoin();

?>