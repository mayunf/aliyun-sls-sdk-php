<?php
// Please include the below file before sls_logs.proto.php
//require('protocolbuffers.inc.php');

namespace Aliyun\SLS\Proto {

    use Exception;

/**
 * Class to aid in the parsing and creating of Protocol Buffer Messages
 * This class should be included by the developer before they use a
 * generated protobuf class.
 *
 * @author Andrew Brampton
 *
 */
    class Protobuf {

        const TYPE_DOUBLE   = 1;   // double, exactly eight bytes on the wire.
        const TYPE_FLOAT    = 2;   // float, exactly four bytes on the wire.
        const TYPE_INT64    = 3;   // int64, varint on the wire.  Negative numbers
        // take 10 bytes.  Use TYPE_SINT64 if negative
        // values are likely.
        const TYPE_UINT64   = 4;   // uint64, varint on the wire.
        const TYPE_INT32    = 5;   // int32, varint on the wire.  Negative numbers
        // take 10 bytes.  Use TYPE_SINT32 if negative
        // values are likely.
        const TYPE_FIXED64  = 6;   // uint64, exactly eight bytes on the wire.
        const TYPE_FIXED32  = 7;   // uint32, exactly four bytes on the wire.
        const TYPE_BOOL     = 8;   // bool, varint on the wire.
        const TYPE_STRING   = 9;   // UTF-8 text.
        const TYPE_GROUP    = 10;  // Tag-delimited message.  Deprecated.
        const TYPE_MESSAGE  = 11;  // Length-delimited message.

        const TYPE_BYTES    = 12;  // Arbitrary byte array.
        const TYPE_UINT32   = 13;  // uint32, varint on the wire
        const TYPE_ENUM     = 14;  // Enum, varint on the wire
        const TYPE_SFIXED32 = 15;  // int32, exactly four bytes on the wire
        const TYPE_SFIXED64 = 16;  // int64, exactly eight bytes on the wire
        const TYPE_SINT32   = 17;  // int32, ZigZag-encoded varint on the wire
        const TYPE_SINT64   = 18;  // int64, ZigZag-encoded varint on the wire

        /**
         * Returns a string representing this wiretype
         */
        public static function get_wiretype($wire_type) {
            switch ($wire_type) {
                case 0: return 'varint';
                case 1: return '64-bit';
                case 2: return 'length-delimited';
                case 3: return 'group start';
                case 4: return 'group end';
                case 5: return '32-bit';
                default: return 'unknown';
            }
        }

        /**
         * Returns how big (in bytes) this number would be as a varint
         */
        public static function size_varint($i) {
            /*		$len = 0;
                    do {
                        $i = $i >> 7;
                        $len++;
                    } while ($i != 0);
                    return $len;
            */
            // TODO Change to a binary search
            if ($i < 0x80)
                return 1;
            if ($i < 0x4000)
                return 2;
            if ($i < 0x200000)
                return 3;
            if ($i < 0x10000000)
                return 4;
            if ($i < 0x800000000)
                return 5;
            if ($i < 0x40000000000)
                return 6;
            if ($i < 0x2000000000000)
                return 7;
            if ($i < 0x100000000000000)
                return 8;
            if ($i < 0x8000000000000000)
                return 9;
        }

        /**
         * Tries to read a varint from $fp.
         * @returns the Varint from the stream, or false if the stream has reached eof.
         */
        public static function read_varint($fp, &$limit = null) {
            $value = '';
            $len = 0;
            do { // Keep reading until we find the last byte
                $b = fread($fp, 1);
                if ($b === false)
                    throw new Exception("read_varint(): Error reading byte");
                if (strlen($b) < 1)
                    break;

                $value .= $b;
                $len++;
            } while ($b >= "\x80");

            if ($len == 0) {
                if (feof($fp))
                    return false;
                throw new Exception("read_varint(): Error reading byte");
            }

            if ($limit !== null)
                $limit -= $len;

            $i = 0;
            $shift = 0;
            for ($j = 0; $j < $len; $j++) {
                $i |= ((ord($value[$j]) & 0x7F) << $shift);
                $shift += 7;
            }

            return $i;
        }

        public static function read_double($fp){throw "I've not coded it yet Exception";}
        public static function read_float ($fp){throw "I've not coded it yet Exception";}
        public static function read_uint64($fp){throw "I've not coded it yet Exception";}
        public static function read_int64 ($fp){throw "I've not coded it yet Exception";}
        public static function read_uint32($fp){throw "I've not coded it yet Exception";}
        public static function read_int32 ($fp){throw "I've not coded it yet Exception";}
        public static function read_zint32($fp){throw "I've not coded it yet Exception";}
        public static function read_zint64($fp){throw "I've not coded it yet Exception";}

        /**
         * Writes a varint to $fp
         * returns the number of bytes written
         * @param $fp
         * @param $i The int to encode
         * @return The number of bytes written
         */
        public static function write_varint($fp, $i) {
            $len = 0;
            do {
                $v = $i & 0x7F;
                $i = $i >> 7;

                if ($i != 0)
                    $v |= 0x80;

                if (fwrite($fp, chr($v)) !== 1)
                    throw new Exception("write_varint(): Error writing byte");

                $len++;
            } while ($i != 0);

            return $len;
        }

        public static function write_double($fp, $d){throw "I've not coded it yet Exception";}
        public static function write_float ($fp, $f){throw "I've not coded it yet Exception";}
        public static function write_uint64($fp, $i){throw "I've not coded it yet Exception";}
        public static function write_int64 ($fp, $i){throw "I've not coded it yet Exception";}
        public static function write_uint32($fp, $i){throw "I've not coded it yet Exception";}
        public static function write_int32 ($fp, $i){throw "I've not coded it yet Exception";}
        public static function write_zint32($fp, $i){throw "I've not coded it yet Exception";}
        public static function write_zint64($fp, $i){throw "I've not coded it yet Exception";}

        /**
         * Seek past a varint
         */
        public static function skip_varint($fp) {
            $len = 0;
            do { // Keep reading until we find the last byte
                $b = fread($fp, 1);
                if ($b === false)
                    throw new Exception("skip(varint): Error reading byte");
                $len++;
            } while ($b >= "\x80");
            return $len;
        }

        /**
         * Seek past the current field
         */
        public static function skip_field($fp, $wire_type) {
            switch ($wire_type) {
                case 0: // varint
                    return Protobuf::skip_varint($fp);

                case 1: // 64bit
                    if (fseek($fp, 8, SEEK_CUR) === -1)
                        throw new Exception('skip(' . ProtoBuf::get_wiretype(1) . '): Error seeking');
                    return 8;

                case 2: // length delimited
                    $varlen = 0;
                    $len = Protobuf::read_varint($fp, $varlen);
                    if (fseek($fp, $len, SEEK_CUR) === -1)
                        throw new Exception('skip(' . ProtoBuf::get_wiretype(2) . '): Error seeking');
                    return $len - $varlen;

                //case 3: // Start group TODO we must keep looping until we find the closing end grou

                //case 4: // End group - We should never skip a end group!
                //	return 0; // Do nothing

                case 5: // 32bit
                    if (fseek($fp, 4, SEEK_CUR) === -1)
                        throw new Exception('skip('. ProtoBuf::get_wiretype(5) . '): Error seeking');
                    return 4;

                default:
                    throw new Exception('skip('. ProtoBuf::get_wiretype($wire_type) . '): Unsupported wire_type');
            }
        }

        /**
         * Read a unknown field from the stream and return its raw bytes
         */
        public static function read_field($fp, $wire_type, &$limit = null) {
            switch ($wire_type) {
                case 0: // varint
                    return Protobuf::read_varint($fp, $limit);

                case 1: // 64bit
                    $limit -= 8;
                    return fread($fp, 8);

                case 2: // length delimited
                    $len = Protobuf::read_varint($fp, $limit);
                    $limit -= $len;
                    return fread($fp, $len);

                //case 3: // Start group TODO we must keep looping until we find the closing end grou

                //case 4: // End group - We should never skip a end group!
                //	return 0; // Do nothing

                case 5: // 32bit
                    $limit -= 4;
                    return fread($fp, 4);

                default:
                    throw new Exception('read_unknown('. ProtoBuf::get_wiretype($wire_type) . '): Unsupported wire_type');
            }
        }

        /**
         * Used to aid in pretty printing of Protobuf objects
         */
        private static $print_depth = 0;
        private static $indent_char = "\t";
        private static $print_limit = 50;

        public static function toString($key, $value) {
            if (is_null($value))
                return;
            $ret = str_repeat(self::$indent_char, self::$print_depth) . "$key=>";
            if (is_array($value)) {
                $ret .= "array(\n";
                self::$print_depth++;
                foreach($value as $i => $v)
                    $ret .= self::toString("[$i]", $v);
                self::$print_depth--;
                $ret .= str_repeat(self::$indent_char, self::$print_depth) . ")\n";
            } else {
                if (is_object($value)) {
                    self::$print_depth++;
                    $ret .= get_class($value) . "(\n";
                    $ret .= $value->__toString() . "\n";
                    self::$print_depth--;
                    $ret .= str_repeat(self::$indent_char, self::$print_depth) . ")\n";
                } elseif (is_string($value)) {
                    $safevalue = addcslashes($value, "\0..\37\177..\377");
                    if (strlen($safevalue) > self::$print_limit) {
                        $safevalue = substr($safevalue, 0, self::$print_limit) . '...';
                    }

                    $ret .= '"' . $safevalue . '" (' . strlen($value) . " bytes)\n";

                } elseif (is_bool($value)) {
                    $ret .= ($value ? 'true' : 'false') . "\n";
                } else {
                    $ret .= (string)$value . "\n";
                }
            }
            return $ret;
        }
    }

// message Log.Content
    class Log_Content
    {
        private $_unknown;

        function __construct($in = NULL, &$limit = PHP_INT_MAX)
        {
            if ($in !== NULL) {
                if (is_string($in)) {
                    $fp = fopen('php://memory', 'r+b');
                    fwrite($fp, $in);
                    rewind($fp);
                } else if (is_resource($in)) {
                    $fp = $in;
                } else {
                    throw new Exception('Invalid in parameter');
                }
                $this->read($fp, $limit);
            }
        }

        function read($fp, &$limit = PHP_INT_MAX)
        {
            while (!feof($fp) && $limit > 0) {
                $tag = Protobuf::read_varint($fp, $limit);
                if ($tag === false) break;
                $wire = $tag & 0x07;
                $field = $tag >> 3;
                //var_dump("Log_Content: Found $field type " . Protobuf::get_wiretype($wire) . " $limit bytes left");
                switch ($field) {
                    case 1:
                        ASSERT('$wire == 2');
                        $len = Protobuf::read_varint($fp, $limit);
                        if ($len === false)
                            throw new Exception('Protobuf::read_varint returned false');
                        if ($len > 0)
                            $tmp = fread($fp, $len);
                        else
                            $tmp = '';
                        if ($tmp === false)
                            throw new Exception("fread($len) returned false");
                        $this->key_ = $tmp;
                        $limit -= $len;
                        break;
                    case 2:
                        ASSERT('$wire == 2');
                        $len = Protobuf::read_varint($fp, $limit);
                        if ($len === false)
                            throw new Exception('Protobuf::read_varint returned false');
                        if ($len > 0)
                            $tmp = fread($fp, $len);
                        else
                            $tmp = '';
                        if ($tmp === false)
                            throw new Exception("fread($len) returned false");
                        $this->value_ = $tmp;
                        $limit -= $len;
                        break;
                    default:
                        $this->_unknown[$field . '-' . Protobuf::get_wiretype($wire)][] = Protobuf::read_field($fp, $wire, $limit);
                }
            }
            if (!$this->validateRequired())
                throw new Exception('Required fields are missing');
        }

        function write($fp)
        {
            if (!$this->validateRequired())
                throw new Exception('Required fields are missing');
            if (!is_null($this->key_)) {
                fwrite($fp, "\x0a");
                Protobuf::write_varint($fp, strlen($this->key_));
                fwrite($fp, $this->key_);
            }
            if (!is_null($this->value_)) {
                fwrite($fp, "\x12");
                Protobuf::write_varint($fp, strlen($this->value_));
                fwrite($fp, $this->value_);
            }
        }

        public function size()
        {
            $size = 0;
            if (!is_null($this->key_)) {
                $l = strlen($this->key_);
                $size += 1 + Protobuf::size_varint($l) + $l;
            }
            if (!is_null($this->value_)) {
                $l = strlen($this->value_);
                $size += 1 + Protobuf::size_varint($l) + $l;
            }
            return $size;
        }

        public function validateRequired()
        {
            if ($this->key_ === null) return false;
            if ($this->value_ === null) return false;
            return true;
        }

        public function __toString()
        {
            return ''
                . Protobuf::toString('unknown', $this->_unknown)
                . Protobuf::toString('key_', $this->key_)
                . Protobuf::toString('value_', $this->value_);
        }

        // required string Key = 1;

        private $key_ = null;

        public function clearKey()
        {
            $this->key_ = null;
        }

        public function hasKey()
        {
            return $this->key_ !== null;
        }

        public function getKey()
        {
            if ($this->key_ === null) return ""; else return $this->key_;
        }

        public function setKey($value)
        {
            $this->key_ = $value;
        }

        // required string Value = 2;

        private $value_ = null;

        public function clearValue()
        {
            $this->value_ = null;
        }

        public function hasValue()
        {
            return $this->value_ !== null;
        }

        public function getValue()
        {
            if ($this->value_ === null) return ""; else return $this->value_;
        }

        public function setValue($value)
        {
            $this->value_ = $value;
        }

        // @@protoc_insertion_point(class_scope:Log.Content)
    }

// message Log
    class Log
    {
        private $_unknown;

        function __construct($in = NULL, &$limit = PHP_INT_MAX)
        {
            if ($in !== NULL) {
                if (is_string($in)) {
                    $fp = fopen('php://memory', 'r+b');
                    fwrite($fp, $in);
                    rewind($fp);
                } else if (is_resource($in)) {
                    $fp = $in;
                } else {
                    throw new Exception('Invalid in parameter');
                }
                $this->read($fp, $limit);
            }
        }

        function read($fp, &$limit = PHP_INT_MAX)
        {
            while (!feof($fp) && $limit > 0) {
                $tag = Protobuf::read_varint($fp, $limit);
                if ($tag === false) break;
                $wire = $tag & 0x07;
                $field = $tag >> 3;
                //var_dump("Log: Found $field type " . Protobuf::get_wiretype($wire) . " $limit bytes left");
                switch ($field) {
                    case 1:
                        ASSERT('$wire == 0');
                        $tmp = Protobuf::read_varint($fp, $limit);
                        if ($tmp === false)
                            throw new Exception('Protobuf::read_varint returned false');
                        $this->time_ = $tmp;

                        break;
                    case 2:
                        ASSERT('$wire == 2');
                        $len = Protobuf::read_varint($fp, $limit);
                        if ($len === false)
                            throw new Exception('Protobuf::read_varint returned false');
                        $limit -= $len;
                        $this->contents_[] = new Log_Content($fp, $len);
                        ASSERT('$len == 0');
                        break;
                    default:
                        $this->_unknown[$field . '-' . Protobuf::get_wiretype($wire)][] = Protobuf::read_field($fp, $wire, $limit);
                }
            }
            if (!$this->validateRequired())
                throw new Exception('Required fields are missing');
        }

        function write($fp)
        {
            if (!$this->validateRequired())
                throw new Exception('Required fields are missing');
            if (!is_null($this->time_)) {
                fwrite($fp, "\x08");
                Protobuf::write_varint($fp, $this->time_);
            }
            if (!is_null($this->contents_))
                foreach ($this->contents_ as $v) {
                    fwrite($fp, "\x12");
                    Protobuf::write_varint($fp, $v->size()); // message
                    $v->write($fp);
                }
        }

        public function size()
        {
            $size = 0;
            if (!is_null($this->time_)) {
                $size += 1 + Protobuf::size_varint($this->time_);
            }
            if (!is_null($this->contents_))
                foreach ($this->contents_ as $v) {
                    $l = $v->size();
                    $size += 1 + Protobuf::size_varint($l) + $l;
                }
            return $size;
        }

        public function validateRequired()
        {
            if ($this->time_ === null) return false;
            return true;
        }

        public function __toString()
        {
            return ''
                . Protobuf::toString('unknown', $this->_unknown)
                . Protobuf::toString('time_', $this->time_)
                . Protobuf::toString('contents_', $this->contents_);
        }

        // required uint32 Time = 1;

        private $time_ = null;

        public function clearTime()
        {
            $this->time_ = null;
        }

        public function hasTime()
        {
            return $this->time_ !== null;
        }

        public function getTime()
        {
            if ($this->time_ === null) return 0; else return $this->time_;
        }

        public function setTime($value)
        {
            $this->time_ = $value;
        }

        // repeated .Log.Content Contents = 2;

        private $contents_ = null;

        public function clearContents()
        {
            $this->contents_ = null;
        }

        public function getContentsCount()
        {
            if ($this->contents_ === null) return 0; else return count($this->contents_);
        }

        public function getContents($index)
        {
            return $this->contents_[$index];
        }

        public function getContentsArray()
        {
            if ($this->contents_ === null) return array(); else return $this->contents_;
        }

        public function setContents($index, $value)
        {
            $this->contents_[$index] = $value;
        }

        public function addContents($value)
        {
            $this->contents_[] = $value;
        }

        public function addAllContents(array $values)
        {
            foreach ($values as $value) {
                $this->contents_[] = $value;
            }
        }

        // @@protoc_insertion_point(class_scope:Log)
    }

// message LogGroup
    class LogGroup
    {
        private $_unknown;

        function __construct($in = NULL, &$limit = PHP_INT_MAX)
        {
            if ($in !== NULL) {
                if (is_string($in)) {
                    $fp = fopen('php://memory', 'r+b');
                    fwrite($fp, $in);
                    rewind($fp);
                } else if (is_resource($in)) {
                    $fp = $in;
                } else {
                    throw new Exception('Invalid in parameter');
                }
                $this->read($fp, $limit);
            }
        }

        function read($fp, &$limit = PHP_INT_MAX)
        {
            while (!feof($fp) && $limit > 0) {
                $tag = Protobuf::read_varint($fp, $limit);
                if ($tag === false) break;
                $wire = $tag & 0x07;
                $field = $tag >> 3;
                //var_dump("LogGroup: Found $field type " . Protobuf::get_wiretype($wire) . " $limit bytes left");
                switch ($field) {
                    case 1:
                        ASSERT('$wire == 2');
                        $len = Protobuf::read_varint($fp, $limit);
                        if ($len === false)
                            throw new Exception('Protobuf::read_varint returned false');
                        $limit -= $len;
                        $this->logs_[] = new Log($fp, $len);
                        ASSERT('$len == 0');
                        break;
                    case 2:
                        ASSERT('$wire == 2');
                        $len = Protobuf::read_varint($fp, $limit);
                        if ($len === false)
                            throw new Exception('Protobuf::read_varint returned false');
                        if ($len > 0)
                            $tmp = fread($fp, $len);
                        else
                            $tmp = '';
                        if ($tmp === false)
                            throw new Exception("fread($len) returned false");
                        $this->category_ = $tmp;
                        $limit -= $len;
                        break;
                    case 3:
                        ASSERT('$wire == 2');
                        $len = Protobuf::read_varint($fp, $limit);
                        if ($len === false)
                            throw new Exception('Protobuf::read_varint returned false');
                        if ($len > 0)
                            $tmp = fread($fp, $len);
                        else
                            $tmp = '';
                        if ($tmp === false)
                            throw new Exception("fread($len) returned false");
                        $this->topic_ = $tmp;
                        $limit -= $len;
                        break;
                    case 4:
                        ASSERT('$wire == 2');
                        $len = Protobuf::read_varint($fp, $limit);
                        if ($len === false)
                            throw new Exception('Protobuf::read_varint returned false');
                        if ($len > 0)
                            $tmp = fread($fp, $len);
                        else
                            $tmp = '';
                        if ($tmp === false)
                            throw new Exception("fread($len) returned false");
                        $this->source_ = $tmp;
                        $limit -= $len;
                        break;
                    default:
                        $this->_unknown[$field . '-' . Protobuf::get_wiretype($wire)][] = Protobuf::read_field($fp, $wire, $limit);
                }
            }
            if (!$this->validateRequired())
                throw new Exception('Required fields are missing');
        }

        function write($fp)
        {
            if (!$this->validateRequired())
                throw new Exception('Required fields are missing');
            if (!is_null($this->logs_))
                foreach ($this->logs_ as $v) {
                    fwrite($fp, "\x0a");
                    Protobuf::write_varint($fp, $v->size()); // message
                    $v->write($fp);
                }
            if (!is_null($this->category_)) {
                fwrite($fp, "\x12");
                Protobuf::write_varint($fp, strlen($this->category_));
                fwrite($fp, $this->category_);
            }
            if (!is_null($this->topic_)) {
                fwrite($fp, "\x1a");
                Protobuf::write_varint($fp, strlen($this->topic_));
                fwrite($fp, $this->topic_);
            }
            if (!is_null($this->source_)) {
                fwrite($fp, "\"");
                Protobuf::write_varint($fp, strlen($this->source_));
                fwrite($fp, $this->source_);
            }
        }

        public function size()
        {
            $size = 0;
            if (!is_null($this->logs_))
                foreach ($this->logs_ as $v) {
                    $l = $v->size();
                    $size += 1 + Protobuf::size_varint($l) + $l;
                }
            if (!is_null($this->category_)) {
                $l = strlen($this->category_);
                $size += 1 + Protobuf::size_varint($l) + $l;
            }
            if (!is_null($this->topic_)) {
                $l = strlen($this->topic_);
                $size += 1 + Protobuf::size_varint($l) + $l;
            }
            if (!is_null($this->source_)) {
                $l = strlen($this->source_);
                $size += 1 + Protobuf::size_varint($l) + $l;
            }
            return $size;
        }

        public function validateRequired()
        {
            return true;
        }

        public function __toString()
        {
            return ''
                . Protobuf::toString('unknown', $this->_unknown)
                . Protobuf::toString('logs_', $this->logs_)
                . Protobuf::toString('category_', $this->category_)
                . Protobuf::toString('topic_', $this->topic_)
                . Protobuf::toString('source_', $this->source_);
        }

        // repeated .Log Logs = 1;

        private $logs_ = null;

        public function clearLogs()
        {
            $this->logs_ = null;
        }

        public function getLogsCount()
        {
            if ($this->logs_ === null) return 0; else return count($this->logs_);
        }

        public function getLogs($index)
        {
            return $this->logs_[$index];
        }

        public function getLogsArray()
        {
            if ($this->logs_ === null) return array(); else return $this->logs_;
        }

        public function setLogs($index, $value)
        {
            $this->logs_[$index] = $value;
        }

        public function addLogs($value)
        {
            $this->logs_[] = $value;
        }

        public function addAllLogs(array $values)
        {
            foreach ($values as $value) {
                $this->logs_[] = $value;
            }
        }

        // optional string Category = 2;

        private $category_ = null;

        public function clearCategory()
        {
            $this->category_ = null;
        }

        public function hasCategory()
        {
            return $this->category_ !== null;
        }

        public function getCategory()
        {
            if ($this->category_ === null) return ""; else return $this->category_;
        }

        public function setCategory($value)
        {
            $this->category_ = $value;
        }

        // optional string Topic = 3;

        private $topic_ = null;

        public function clearTopic()
        {
            $this->topic_ = null;
        }

        public function hasTopic()
        {
            return $this->topic_ !== null;
        }

        public function getTopic()
        {
            if ($this->topic_ === null) return ""; else return $this->topic_;
        }

        public function setTopic($value)
        {
            $this->topic_ = $value;
        }

        // optional string Source = 4;

        private $source_ = null;

        public function clearSource()
        {
            $this->source_ = null;
        }

        public function hasSource()
        {
            return $this->source_ !== null;
        }

        public function getSource()
        {
            if ($this->source_ === null) return ""; else return $this->source_;
        }

        public function setSource($value)
        {
            $this->source_ = $value;
        }

        // @@protoc_insertion_point(class_scope:LogGroup)
    }

// message LogGroupList
    class LogGroupList
    {
        private $_unknown;

        function __construct($in = NULL, &$limit = PHP_INT_MAX)
        {
            if ($in !== NULL) {
                if (is_string($in)) {
                    $fp = fopen('php://memory', 'r+b');
                    fwrite($fp, $in);
                    rewind($fp);
                } else if (is_resource($in)) {
                    $fp = $in;
                } else {
                    throw new Exception('Invalid in parameter');
                }
                $this->read($fp, $limit);
            }
        }

        function read($fp, &$limit = PHP_INT_MAX)
        {
            while (!feof($fp) && $limit > 0) {
                $tag = Protobuf::read_varint($fp, $limit);
                if ($tag === false) break;
                $wire = $tag & 0x07;
                $field = $tag >> 3;
                //var_dump("LogGroupList: Found $field type " . Protobuf::get_wiretype($wire) . " $limit bytes left");
                switch ($field) {
                    case 1:
                        ASSERT('$wire == 2');
                        $len = Protobuf::read_varint($fp, $limit);
                        if ($len === false)
                            throw new Exception('Protobuf::read_varint returned false');
                        $limit -= $len;
                        $this->logGroupList_[] = new LogGroup($fp, $len);
                        ASSERT('$len == 0');
                        break;
                    default:
                        $this->_unknown[$field . '-' . Protobuf::get_wiretype($wire)][] = Protobuf::read_field($fp, $wire, $limit);
                }
            }
            if (!$this->validateRequired())
                throw new Exception('Required fields are missing');
        }

        function write($fp)
        {
            if (!$this->validateRequired())
                throw new Exception('Required fields are missing');
            if (!is_null($this->logGroupList_))
                foreach ($this->logGroupList_ as $v) {
                    fwrite($fp, "\x0a");
                    Protobuf::write_varint($fp, $v->size()); // message
                    $v->write($fp);
                }
        }

        public function size()
        {
            $size = 0;
            if (!is_null($this->logGroupList_))
                foreach ($this->logGroupList_ as $v) {
                    $l = $v->size();
                    $size += 1 + Protobuf::size_varint($l) + $l;
                }
            return $size;
        }

        public function validateRequired()
        {
            return true;
        }

        public function __toString()
        {
            return ''
                . Protobuf::toString('unknown', $this->_unknown)
                . Protobuf::toString('logGroupList_', $this->logGroupList_);
        }

        // repeated .LogGroup logGroupList = 1;

        private $logGroupList_ = null;

        public function clearLogGroupList()
        {
            $this->logGroupList_ = null;
        }

        public function getLogGroupListCount()
        {
            if ($this->logGroupList_ === null) return 0; else return count($this->logGroupList_);
        }

        public function getLogGroupList($index)
        {
            return $this->logGroupList_[$index];
        }

        public function getLogGroupListArray()
        {
            if ($this->logGroupList_ === null) return array(); else return $this->logGroupList_;
        }

        public function setLogGroupList($index, $value)
        {
            $this->logGroupList_[$index] = $value;
        }

        public function addLogGroupList($value)
        {
            $this->logGroupList_[] = $value;
        }

        public function addAllLogGroupList(array $values)
        {
            foreach ($values as $value) {
                $this->logGroupList_[] = $value;
            }
        }

        // @@protoc_insertion_point(class_scope:LogGroupList)
    }

}