# wabr-php

*Web API Barcode Reader Server* &nbsp;(**[WABR](http://how-to.inliteresearch.com/web-api-barcode-reader/)**) offers a REST API for the Inlite Research [ClearImage Barcode Recognition](http://www.inliteresearch.com/barcode-recognition-sdk.php) technology.  
This `wabr-php` SDK  simplifies the WABR client-side PHP development.<br>  Use `wabr.php` from this repository in your project;
Use sample code from `test.php` and `sample_code.php`

If your PHP source files with  UTF-8 character the following should be set in `php.ini`
```
mbstring.internal_encoding = UTF-8
default_charset = "utf-8"
```

With IIS server source files with  UTF-8 character should have BOM

### Create a Reader
```php
include "wabr.php";

$reader = new WABarcodeReader($serverUrl, $authorization);
```
Where
+ `serverURL` is the WABR server URL 
	+ It is the URL of your hosted server
	+ To interact with the Inlite-hosted Test Server use the empty string to send HTTP Request to `wabr.inliteresearch.com`.
+ `authorization` is a value set in `Authorization` field of the HTTP Request header. If `authorization` is an empty string, then the value is set from the `WABR_AUTH` environmental variable (if present).
	+ For your hosted server: Use the empty string if no authentication is required, otherwise use the value expected by your IIS Authentication handler
	+ For the Inlite-hosted Test Server: Use the  *Authorization Code* supplied by Inlite.  Without a valid `authorization` value, the Test Server returns partial results, which could be sufficient for testing the operation of your client-side code.  To obtain an Authorization Code contact Inlite at [sales@inliteresearch.com](mailto:sales@inliteresearch.com">sales@inliteresearch.com</a></span>).

### Read barcodes
Use the `Read()` method to obtain barcode values as an Array of `WABarcode` objects
```php
try
  {
  $barcodes = $reader->Read($image, $types, $directions, $tbr_code);
 // Process barcode reading results
  }
catch (Exception $ex)
  {
    echo "EXCEPTION: " . $ex->getMessage();
  }
``` 

#### Parameters
+ `image_source` is a required parameter that points to the image(s) that the WABR server will read. The following formats are accepted.
	- *URL* of Internet-based file. The name should start with `http://` , `https://` or `file://`.   Examples: 
    ```
    https://wabr.inliteresearch.com/SampleImages/1d.pdf
    ttp://upload.wikimedia.org/wikipedia/commons/0/07/Better_Sample_PDF417.png
    ```
<!-- end the list -->
	- *Path* of a file located on or accessible from the client.&nbsp;&nbsp;   Examples: 
    ```
    c:/image_folder/some_image_file.tif
    \\COMPUTER_NAME\another_folder\another_file.pdf
    ```
<!-- end the list -->
	- *Base64-encoded* string representing content of an image file.&nbsp;&nbsp;  Format:
	```
	[ data:[<MIME-type>][;base64],]<content>[:::<filename>]
	```
	Example: 
<!-- end the list -->	
	```
	data:application/pdf;base64,WTTVKM3OWFKFMERCMT5... :::IMAGE_FILE.PDF
	```
<!-- end the list -->	
The values in  **[ ]** are optional. The values in **< >** are variables. NOTE:  Neither **[ ]** nor  **< >** should be included in `image_source` string.<br>
`content` is the only *required* value. It is an image file content encoded as base64.<br>
`filename` is a value assigned to `WABarcode.File` property of each barcode.  Default is an empty string.<br>
`MIME-type` identifies file format. e.g. `application/pdf` or `image/tiff`.  The value is only for compatibility with data URI scheme.    The Barcode reader will automatically identify file format based on content.
 </div>
 <BR>Most popular image formats are acceptable as image_source, including *PDF*, *TIF*, *JPEG* etc.  Multi-page *PDF* and *TIF* files are supported<br>
To specify several image sources in a single request separate them with the ` | ` character. 
+ `types`  is an optional string parameter (*not case-sensitive*) that contains *comma-separated* barcode types to read.  An empty string is the same as `1d,2d`.  The list of valid type is available in the `WABarcodeReader.validtypes` variable.  Barcodes in this list are:

	`1d` - same as `Code39, Code128, Code93, Ucc128, Codabar, Interleaved2of5, Upca, Upce, Ean8, Ean13`<br>
	`Code39` - Code 39 Full ASCII<br>
	`Code128` - Code 128<br>
	`Code93` - Code 93<br>
	`Codabar` - Codabar<br>
	`Ucc128` - GS1-128<br>
	`Interleaved2of5` - Inteleaved 2 of 5<br>
	`Ean13` - EAN-13<br>
	`Ean8` - EAN-8<br>
	`Upca` - UPC-A<br>
	`Upce` - UPC-E<br>
	`2d` - same as `Pdf417, DatMatrix, QR`<br>
	`Pdf417` - PDF417 code<br>
	`DataMatrix` - DataMatrix code<br>
	`QR` - QR code<br>
	`DrvLic` - Driver License, ID Cards and military CAC ID<br>
	`postal` - same as `imb, bpo, aust, sing, postnet`<br>
	`imb` - US Post Intelligent Mail Barcode<br>
	`bpo` - UK Royal Mail barcode (RM4SCC)<br>
	`aust` - Australia Post barcode<br>
	`sing` - Singapore Post barcode <br>
	`postnet` - Postnet, Planet<br>
	`Code39basic` - Code 93 Basic<br>
	`Patch` - Patch code<br>

+ `directions`  is an optional string parameter, which limits the barcode recognition only to barcodes with the specified orientation. This limitation can reduce recognition time. Valid values are:

	*an empty string* (default) - read barcode in any direction and skew angle.<br>
	`horz` - read only horizontal barcodes.<br>
	`vert` - read only vertical barcodes.<br>
	`horz, vert` - read only horizontal and vertical barcodes.<br>


+ `tbr_code` is an optional integer parameter supplied by Inlite to addresses *customer-specific requirements* for barcode recognition.  Read more about [*Targeted Barcode Reader (TBR)*](http://how-to.inliteresearch.com/barcode-reading-howto/tbr/).  The default value is 0, meaning *TBR* is disabled.

### Use barcode reading results

The following examples demonstrate processing of an Array of `WABarcode` objects returned by `Read()` method.  
Examples use `WAUtils.printUTF8` to correctly represent UTF8 character on `stdout`.

Print each barcode text value, type and file name: 
```php
foreach ($barcodes as $barcode)
{
    echo "Barcode Type:" . $barcode->Type . "  File:" . $barcode->File . "<br>"; 
    echo $barcode->Text . "<br>";
}
``` 

Decode `Values` of a *Driver License*, *ID Card* or *Military CAC Card*. To obtain these values include `DrvLic` in the `types` parameter of the `Read()` call.  
```php
foreach ($barcode->Values as $key => $value) 
    echo $key  . " : " . $value . "<br>";
``` 

#### `WABarcode` properties

`Text` - barcode text value (ASCII or UTF-8 if enabled) [string]<br>
`Data` - *binary data* encoded in `Pdf417`, `DataMatrix` or `QR` barcodes [array of integers]<br>
`Type` - barcode type [string]<br>
`Page` - page in multi-page *PDF* and *TIF* file [integer]<br>
`Top` - top coordinate in pixels [integer]<br>
`Left` - left coordinate in pixels [integer]<br>
`Right` - right coordinate in pixels [integer]<br>
`Bottom` - bottom coordinate in pixels [integer]<br>
`File` - file name of image file.  Depending on `image_source` type it is <br>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;a *URL* value, base-name of *Path*, `filename` of *Base64-encoded* string (if present) [string]<br>
`Rotation` - rotation of barcode on a page. Values: `none`, `upsideDown`, `left`, `right` or `diagonal` [string]<br>
`Meta` - XML formatted string representing barcode meta-data [string]<br>
`Values` - Driver License fields, such as last name, date of birth etc. [dictionary: key-field name string as key, value - field value string]<br>

