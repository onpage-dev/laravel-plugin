<?php
namespace OnPage;

use function OnPage\Models\op_url;

class File {
    public $name;
    public $token;

    function __construct(array $file)
    {
        $this->name = $file['name'];
        $this->token = $file['token'];
    }

    function link() : string {
        return op_url($this->token, $this->name);
    }

    function isImage() : bool {
        $ext = pathinfo($this->name, PATHINFO_EXTENSION);
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
    }

    function thumb(array $opts = []) :string {
        $suffix = '';
        if ($this->isImage() && (isset($opts['x']) || isset($opts['y']))) {
            $suffix.= ".{$opts['x']}x{$opts['y']}";

            if (isset($opts['contain'])) {
                $suffix.= '-contain';
            }
        }

        if ($suffix || isset($opts['ext'])) {
            if (!isset($opts['ext'])) {
                $opts['ext'] = 'jpg';
            }
            $suffix.= ".{$opts['ext']}";
        }
        return op_url("{$this->token}{$suffix}", $this->name);
    }
}