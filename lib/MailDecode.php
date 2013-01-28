<?php
/**
 * The MailDecode class is used to decode mail/mime messages
 *
 * This class will parse a raw mime email and return the structure.
 *
 * @link       http://pear.php.net/package/Mail_mime
 */
class MailDecode
{
    // The raw email to decode
    public $input;

    // The header part of the input
    public $header;

    // The body part of the input
    public $body;

    // If an error occurs, this is used to store the message
    public $error;

    // Flag to determine whether to include bodies in the returned object.
    public $include_bodies;

    // Flag to determine whether to decode bodies
    public $decode_bodies;

    // Flag to determine whether to decode headers
    public $decode_headers;

    /**
     * Flag to determine whether to include attached messages
     * as body in the returned object. Depends on $_include_bodies
     */
    public $rfc822_bodies;

    /**
     * Constructor.
     *
     * Sets up the object, initialise the variables, and splits and
     * stores the header and body of the input.
     *
     * @param string $input The input to decode
     */
    public function __construct($input)
    {
        list($header, $body)   = $this->splitBodyHeader($input);

        $this->input          = $input;
        $this->header         = $header;
        $this->body           = $body;
        $this->decode_bodies  = false;
        $this->include_bodies = true;
        $this->rfc822_bodies  = false;
    }

    /**
     *  静态调用解析邮件
     *
     * @param string $input
     * @param array An array of various parameters that determine
     *              various things:
     *              include_bodies - Whether to include the body in the returned
     *                               object.
     *              decode_bodies  - Whether to decode the bodies
     *                               of the parts. (Transfer encoding)
     *              decode_headers - Whether to decode headers
     *              input          - If called statically, this will be treated
     *                               as the input
     * @return object Decoded results|UPF_Exception
     */
    static function decode($input, $params = null)
    {

        $obj = new MailDecode($input);

        $obj->include_bodies = isset($params['include_bodies']) ? $params['include_bodies'] : false;
        $obj->decode_bodies = isset($params['decode_bodies']) ? $params['decode_bodies'] : false;
        $obj->decode_headers = isset($params['decode_headers']) ? $params['decode_headers'] : false;
        $obj->rfc822_bodies = isset($params['rfc_822bodies']) ? $params['rfc_822bodies'] : false;

        $structure = $obj->mail_decode($obj->header, $obj->body);
        if ($structure === false) {
            $structure = new UPF_Exception($obj->error);
        }

        return $structure;
    }

    /**
     * Performs the decoding. Decodes the body string passed to it
     * If it finds certain content-types it will call itself in a
     * recursive fashion
     *
     * @param string $headers Header section
     * @param string $body Body section
     * @param string $default_ctype
     * @return object Results of decoding process
     */
    private function mail_decode($headers, $body, $default_ctype = 'text/plain')
    {
        $return = new stdClass;
        $return->headers = array();
        $headers = $this->parseHeaders($headers);

        foreach ($headers as $value) {
            $value['value'] = $this->decode_headers ? $this->decodeHeader($value['value']) : $value['value'];
            if (isset($return->headers[strtolower($value['name'])]) AND !is_array($return->headers[strtolower($value['name'])])) {
                $return->headers[strtolower($value['name'])]   = array($return->headers[strtolower($value['name'])]);
                $return->headers[strtolower($value['name'])][] = $value['value'];

            } elseif (isset($return->headers[strtolower($value['name'])])) {
                $return->headers[strtolower($value['name'])][] = $value['value'];

            } else {
                $return->headers[strtolower($value['name'])] = $value['value'];
            }
        }


        foreach ($headers as $key => $value) {
            $headers[$key]['name'] = strtolower($headers[$key]['name']);
            switch ($headers[$key]['name']) {

                case 'content-type':
                    $content_type = $this->parseHeaderValue($headers[$key]['value']);

                    if (preg_match('/([0-9a-z+.-]+)\/([0-9a-z+.-]+)/i', $content_type['value'], $regs)) {
                        $return->ctype_primary   = $regs[1];
                        $return->ctype_secondary = $regs[2];
                    }

                    if (isset($content_type['other'])) {
                        foreach($content_type['other'] as $p_name => $p_value) {
                            $return->ctype_parameters[$p_name] = $p_value;
                        }
                    }
                    break;

                case 'content-disposition':
                    $content_disposition = $this->parseHeaderValue($headers[$key]['value']);
                    $return->disposition   = $content_disposition['value'];
                    if (isset($content_disposition['other'])) {
                        foreach($content_disposition['other'] as $p_name => $p_value) {
                            $return->d_parameters[$p_name] = $p_value;
                        }
                    }
                    break;

                case 'content-transfer-encoding':
                    $content_transfer_encoding = $this->parseHeaderValue($headers[$key]['value']);
                    break;
            }
        }

        if (isset($content_type)) {
            switch (strtolower($content_type['value'])) {
                case 'text/plain':
                    $encoding = isset($content_transfer_encoding) ? $content_transfer_encoding['value'] : '7bit';
                    $this->include_bodies ? $return->body = ($this->decode_bodies ? $this->decodeBody($body, $encoding) : $body) : null;
                    break;

                case 'text/html':
                    $encoding = isset($content_transfer_encoding) ? $content_transfer_encoding['value'] : '7bit';
                    $this->include_bodies ? $return->body = ($this->decode_bodies ? $this->decodeBody($body, $encoding) : $body) : null;
                    break;

                case 'multipart/parallel':
                case 'multipart/appledouble': // Appledouble mail
                case 'multipart/report': // RFC1892
                case 'multipart/signed': // PGP
                case 'multipart/digest':
                case 'multipart/alternative':
                case 'multipart/related':
                case 'multipart/mixed':
                case 'application/vnd.wap.multipart.related':
                    if(!isset($content_type['other']['boundary'])){
                        $this->error = 'No boundary found for ' . $content_type['value'] . ' part';
                        return false;
                    }

                    $default_ctype = (strtolower($content_type['value']) === 'multipart/digest') ? 'message/rfc822' : 'text/plain';

                    $parts = $this->boundarySplit($body, $content_type['other']['boundary']);
                    for ($i = 0; $i < count($parts); $i++) {
                        list($part_header, $part_body) = $this->splitBodyHeader($parts[$i]);
                        $part = $this->mail_decode($part_header, $part_body, $default_ctype);
                        if($part === false)
                            $part = new UPF_Exception($this->error);
                        $return->parts[] = $part;
                    }
                    break;

                case 'message/rfc822':
					if ($this->rfc822_bodies) {
						$encoding = isset($content_transfer_encoding) ? $content_transfer_encoding['value'] : '7bit';
						$return->body = ($this->decode_bodies ? $this->decodeBody($body, $encoding) : $body);
					}
                    $obj = new MailDecode($body);
                    $return->parts[] = $obj->decode(array('include_bodies' => $this->include_bodies,
					                                      'decode_bodies'  => $this->decode_bodies,
														  'decode_headers' => $this->decode_headers));
                    unset($obj);
                    break;

                default:
                    if(!isset($content_transfer_encoding['value']))
                        $content_transfer_encoding['value'] = '7bit';
                    $this->include_bodies ? $return->body = ($this->decode_bodies ? $this->decodeBody($body, $content_transfer_encoding['value']) : $body) : null;
                    break;
            }

        } else {
            $ctype = explode('/', $default_ctype);
            $return->ctype_primary   = $ctype[0];
            $return->ctype_secondary = $ctype[1];
            $this->include_bodies ? $return->body = ($this->decode_bodies ? $this->decodeBody($body) : $body) : null;
        }

        return $return;
    }

    /**
     * Given a string containing a header and body
     * section, this function will split them (at the first
     * blank line) and return them.
     *
     * @param string $input Input to split apart
     * @return array Contains header and body section
     * @access private
     */
    private function splitBodyHeader($input)
    {
        if (preg_match("/^(.*?)\r?\n\r?\n(.*)/s", $input, $match)) {
            return array($match[1], $match[2]);
        }
        // bug #17325 - empty bodies are allowed. - we just check that at least one line 
        // of headers exist..
        if (count(explode("\n",$input))) {
            return array($input, '');
        }
        $this->error = 'Could not split header and body';
        return false;
    }

    /**
     * Parse headers given in $input and return
     * as assoc array.
     *
     * @param string $input Headers to parse
     * @return array Contains parsed headers
     * @access private
     */
    private function parseHeaders($input)
    {

        if ($input !== '') {
            // Unfold the input
            $input   = preg_replace("/\r?\n/", "\r\n", $input);
            //#7065 - wrapping.. with encoded stuff.. - probably not needed,
            // wrapping space should only get removed if the trailing item on previous line is a 
            // encoded character
            $input   = preg_replace("/=\r\n(\t| )+/", '=', $input);
            $input   = preg_replace("/\r\n(\t| )+/", ' ', $input);
            
            $headers = explode("\r\n", trim($input));

            foreach ($headers as $value) {
                $hdr_name = substr($value, 0, $pos = strpos($value, ':'));
                $hdr_value = substr($value, $pos+1);
                if($hdr_value[0] == ' ')
                    $hdr_value = substr($hdr_value, 1);

                $return[] = array(
                                  'name'  => $hdr_name,
                                  'value' =>  $hdr_value
                                 );
            }
        } else {
            $return = array();
        }

        return $return;
    }

    /**
     * Function to parse a header value,
     * extract first part, and any secondary
     * parts (after ;) This function is not as
     * robust as it could be. Eg. header comments
     * in the wrong place will probably break it.
     *
     * @param string $input Header value to parse
     * @return array Contains parsed result
     * @access private
     */
    private function parseHeaderValue($input)
    {

        if (($pos = strpos($input, ';')) === false) {
            $input = $this->decode_headers ? $this->decodeHeader($input) : $input;
            $return['value'] = trim($input);
            return $return;
        }



        $value = substr($input, 0, $pos);
        $value = $this->decode_headers ? $this->decodeHeader($value) : $value;
        $return['value'] = trim($value);
        $input = trim(substr($input, $pos+1));

        if (!strlen($input) > 0) {
            return $return;
        }
        // at this point input contains xxxx=".....";zzzz="...."
        // since we are dealing with quoted strings, we need to handle this properly..
        $i = 0;
        $l = strlen($input);
        $key = '';
        $val = false; // our string - including quotes..
        $q = false; // in quote..
        $lq = ''; // last quote..

        while ($i < $l) {
            
            $c = $input[$i];
            //var_dump(array('i'=>$i,'c'=>$c,'q'=>$q, 'lq'=>$lq, 'key'=>$key, 'val' =>$val));

            $escaped = false;
            if ($c == '\\') {
                $i++;
                if ($i == $l-1) { // end of string.
                    break;
                }
                $escaped = true;
                $c = $input[$i];
            }            


            // state - in key..
            if ($val === false) {
                if (!$escaped && $c == '=') {
                    $val = '';
                    $key = trim($key);
                    $i++;
                    continue;
                }
                if (!$escaped && $c == ';') {
                    if ($key) { // a key without a value..
                        $key= trim($key);
                        $return['other'][$key] = '';
                        $return['other'][strtolower($key)] = '';
                    }
                    $key = '';
                }
                $key .= $c;
                $i++;
                continue;
            }
                     
            // state - in value.. (as $val is set..)

            if ($q === false) {
                // not in quote yet.
                if ((!strlen($val) || $lq !== false) && $c == ' ' ||  $c == "\t") {
                    $i++;
                    continue; // skip leading spaces after '=' or after '"'
                }
                if (!$escaped && ($c == '"' || $c == "'")) {
                    // start quoted area..
                    $q = $c;
                    // in theory should not happen raw text in value part..
                    // but we will handle it as a merged part of the string..
                    $val = !strlen(trim($val)) ? '' : trim($val);
                    $i++;
                    continue;
                }
                // got end....
                if (!$escaped && $c == ';') {

                    $val = trim($val);
                    $added = false;
                    if (preg_match('/\*[0-9]+$/', $key)) {
                        // this is the extended aaa*0=...;aaa*1=.... code
                        // it assumes the pieces arrive in order, and are valid...
                        $key = preg_replace('/\*[0-9]+$/', '', $key);
                        if (isset($return['other'][$key])) {
                            $return['other'][$key] .= $val;
                            if (strtolower($key) != $key) {
                                $return['other'][strtolower($key)] .= $val;
                            }
                            $added = true;
                        }
                        // continue and use standard setters..
                    }
                    if (!$added) {
                        $return['other'][$key] = $val;
                        $return['other'][strtolower($key)] = $val;
                    }
                    $val = false;
                    $key = '';
                    $lq = false;
                    $i++;
                    continue;
                }

                $val .= $c;
                $i++;
                continue;
            }
            
            // state - in quote..
            if (!$escaped && $c == $q) {  // potential exit state..

                // end of quoted string..
                $lq = $q;
                $q = false;
                $i++;
                continue;
            }
                
            // normal char inside of quoted string..
            $val.= $c;
            $i++;
        }
        
        // do we have anything left..
        if (strlen(trim($key)) || $val !== false) {
           
            $val = trim($val);
            $added = false;
            if ($val !== false && preg_match('/\*[0-9]+$/', $key)) {
                // no dupes due to our crazy regexp.
                $key = preg_replace('/\*[0-9]+$/', '', $key);
                if (isset($return['other'][$key])) {
                    $return['other'][$key] .= $val;
                    if (strtolower($key) != $key) {
                        $return['other'][strtolower($key)] .= $val;
                    }
                    $added = true;
                }
                // continue and use standard setters..
            }
            if (!$added) {
                $return['other'][$key] = $val;
                $return['other'][strtolower($key)] = $val;
            }
        }
        // decode values.
        foreach($return['other'] as $key =>$val) {
            $return['other'][$key] = $this->decode_headers ? $this->decodeHeader($val) : $val;
        }
       //print_r($return);
        return $return;
    }

    /**
     * This function splits the input based
     * on the given boundary
     *
     * @param string $input Input to parse
     * @return array Contains array of resulting mime parts
     * @access private
     */
    private function boundarySplit($input, $boundary)
    {
        $parts = array();

        $bs_possible = substr($boundary, 2, -2);
        $bs_check = '\"' . $bs_possible . '\"';

        if ($boundary == $bs_check) {
            $boundary = $bs_possible;
        }
        $tmp = preg_split("/--".preg_quote($boundary, '/')."((?=\s)|--)/", $input);

        $len = count($tmp) -1;
        for ($i = 1; $i < $len; $i++) {
            if (strlen(trim($tmp[$i]))) {
                $parts[] = $tmp[$i];
            }
        }
        
        // add the last part on if it does not end with the 'closing indicator'
        if (!empty($tmp[$len]) && strlen(trim($tmp[$len])) && $tmp[$len][0] != '-') {
            $parts[] = $tmp[$len];
        }
        return $parts;
    }

    /**
     * Given a header, this function will decode it
     * according to RFC2047. Probably not *exactly*
     * conformant, but it does pass all the given
     * examples (in RFC2047).
     *
     * @param string $input Input header value to decode
     * @param string $toEncoding
     * @return string Decoded header value
     */
    private function decodeHeader($input, $toEncoding = 'utf-8')
    {
        // Remove white space between encoded-words
        $input = preg_replace('/(=\?[^?]+\?(q|b)\?[^?]*\?=)(\s)+=\?/i', '\1=?', $input);

        // For each encoded-word...
        while (preg_match('/(=\?([^?]+)\?(q|b)\?([^?]*)\?=)/i', $input, $matches)) {

            $encoded  = $matches[1];
            $charset  = $matches[2];
            $encoding = $matches[3];
            $text     = $matches[4];

            switch (strtolower($encoding)) {
                case 'b':
                    $text = base64_decode($text);
                    if (function_exists('mb_convert_encoding'))
                        $text = mb_convert_encoding($text, $toEncoding, $charset);
                    break;

                case 'q':
                    $text = str_replace('_', ' ', $text);
                    preg_match_all('/=([a-f0-9]{2})/i', $text, $matches);
                    foreach($matches[1] as $value)
                        $text = str_replace('='.$value, chr(hexdec($value)), $text);
                    break;
            }

            $input = str_replace($encoded, $text, $input);
        }

        return $input;
    }

    /**
     * Given a body string and an encoding type,
     * this function will decode and return it.
     *
     * @param  string $input Input body to decode
     * @param  string $encoding Encoding type to use.
     * @return string Decoded body
     */
    private function decodeBody($input, $encoding = '7bit')
    {
        switch (strtolower($encoding)) {
            case '7bit':
                return $input;
                break;

            case 'quoted-printable':
                return $this->quotedPrintableDecode($input);
                break;

            case 'base64':
                return base64_decode($input);
                break;

            default:
                return $input;
        }
    }

    /**
     * Given a quoted-printable string, this
     * function will decode and return it.
     *
     * @param  string $input Input body to decode
     * @return string Decoded body
     */
    private function quotedPrintableDecode($input)
    {
        // Remove soft line breaks
        $input = preg_replace("/=\r?\n/", '', $input);

        // Replace encoded characters
		$input = preg_replace('/=([a-f0-9]{2})/ie', "chr(hexdec('\\1'))", $input);

        return $input;
    }

    /**
     * Checks the input for uuencoded files and returns
     * an array of them. Can be called statically, eg:
     *
     * $files =& MailDecode::uudecode($some_text);
     *
     * It will check for the begin 666 ... end syntax
     * however and won't just blindly decode whatever you
     * pass it.
     *
     * @param  string $input Input body to look for attahcments in
     * @return array  Decoded bodies, filenames and permissions
     */
    public static function &uudecode($input)
    {
        // Find all uuencoded sections
        preg_match_all("/begin ([0-7]{3}) (.+)\r?\n(.+)\r?\nend/Us", $input, $matches);

        for ($j = 0; $j < count($matches[3]); $j++) {

            $str      = $matches[3][$j];
            $filename = $matches[2][$j];
            $fileperm = $matches[1][$j];

            $file = '';
            $str = preg_split("/\r?\n/", trim($str));
            $strlen = count($str);

            for ($i = 0; $i < $strlen; $i++) {
                $pos = 1;
                $d = 0;
                $len=(int)(((ord(substr($str[$i],0,1)) -32) - ' ') & 077);

                while (($d + 3 <= $len) AND ($pos + 4 <= strlen($str[$i]))) {
                    $c0 = (ord(substr($str[$i],$pos,1)) ^ 0x20);
                    $c1 = (ord(substr($str[$i],$pos+1,1)) ^ 0x20);
                    $c2 = (ord(substr($str[$i],$pos+2,1)) ^ 0x20);
                    $c3 = (ord(substr($str[$i],$pos+3,1)) ^ 0x20);
                    $file .= chr(((($c0 - ' ') & 077) << 2) | ((($c1 - ' ') & 077) >> 4));

                    $file .= chr(((($c1 - ' ') & 077) << 4) | ((($c2 - ' ') & 077) >> 2));

                    $file .= chr(((($c2 - ' ') & 077) << 6) |  (($c3 - ' ') & 077));

                    $pos += 4;
                    $d += 3;
                }

                if (($d + 2 <= $len) && ($pos + 3 <= strlen($str[$i]))) {
                    $c0 = (ord(substr($str[$i],$pos,1)) ^ 0x20);
                    $c1 = (ord(substr($str[$i],$pos+1,1)) ^ 0x20);
                    $c2 = (ord(substr($str[$i],$pos+2,1)) ^ 0x20);
                    $file .= chr(((($c0 - ' ') & 077) << 2) | ((($c1 - ' ') & 077) >> 4));

                    $file .= chr(((($c1 - ' ') & 077) << 4) | ((($c2 - ' ') & 077) >> 2));

                    $pos += 3;
                    $d += 2;
                }

                if (($d + 1 <= $len) && ($pos + 2 <= strlen($str[$i]))) {
                    $c0 = (ord(substr($str[$i],$pos,1)) ^ 0x20);
                    $c1 = (ord(substr($str[$i],$pos+1,1)) ^ 0x20);
                    $file .= chr(((($c0 - ' ') & 077) << 2) | ((($c1 - ' ') & 077) >> 4));

                }
            }
            $files[] = array('filename' => $filename, 'fileperm' => $fileperm, 'filedata' => $file);
        }

        return $files;
    }
} // End of class
