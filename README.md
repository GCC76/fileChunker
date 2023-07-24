# fileChunker
Send and receive file splitted into multiple chunks

# Purpose of the project
There are situations, when transmitting data between servers, where it is necessary to send large files. When it is not possible to use the FTP protocol, because for example we are working with web API, the file is usually uploaded to the server, encoded in base 64 and sent to an endpoint of a second server.
This procedure involves multiple steps where something may go wrong (client connection, server resources, etc.). In these cases the upload procedure is interrupted and the file must be uploaded again.

A valid alternative is to divide the file, directly from the user's device, into many small fragments as, for example, occurs with the Torrent protocol. The fragments, much lighter, can therefore be directly sent almost simultaneously to the final server.
The final server will be responsible for monitoring, receiving and reconstructing the original file.
In case of problems during the operation it would thus be possible to request only the necessary fragments and not the entire file.

fileChunker is just an example, with lots of controls and feautures that can be added.
