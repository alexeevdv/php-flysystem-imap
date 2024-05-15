<?php

namespace alexeevdv\Flysystem\Imap;

final class ImapConnection implements Connection
{
    private const CONTENT_SECTION = 1;

    /**
     * @var resource
     */
    private $imap;

    private string $mailbox;

    public function __construct(
        string $username,
        string $password,
        string $mailbox,
    ) {
        $this->imap = imap_open($mailbox, $username, $password);
        if ($this->imap === false) {
            throw new \RuntimeException('Cannot connect to IMAP: ' . imap_last_error());
        }
        $this->mailbox = $mailbox;
    }

    public function __destruct()
    {
        imap_close($this->imap, CL_EXPUNGE);
    }

    public function readResource(string $uid)
    {
        $tmpFile = tempnam(sys_get_temp_dir(), '');
        $result = imap_savebody($this->imap, $tmpFile, $uid, self::CONTENT_SECTION, FT_UID|FT_INTERNAL|FT_PEEK);
        if ($result === false) {
            throw new \RuntimeException('Could not save body to temporary file');
        }

        $fd = fopen($tmpFile, 'rb+');
        if ($fd === false) {
            throw new \RuntimeException('Cant open file');
        }

        stream_filter_append($fd, 'convert.base64-decode',STREAM_FILTER_READ);

        return $fd;
    }

    public function read(string $uid): string
    {
        $result = imap_fetchbody($this->imap, $uid, self::CONTENT_SECTION, FT_UID);
        if ($result === false) {
            throw new \RuntimeException('imap_fetchbody');
        }

        $result = imap_base64($result);
        if ($result === false) {
            throw new \RuntimeException('imap_base64');
        }

        return $result;
    }

    public function write(string $subject, string $contents): bool
    {
        $envelope = [
            'subject' => $subject,
        ];

        $bodies = [
            1 => [
                'type' => TYPEMULTIPART,
                'subtype' => 'mixed',
            ],
            2 => [
                'type' => TYPEMULTIPART,
                'encoding' => ENCBINARY,
                'subtype' => 'octet-stream',
                'contents.data' => $contents,
            ],
        ];

        $message = imap_mail_compose($envelope, $bodies);

        return imap_append($this->imap, $this->mailbox, $message);
    }

    public function getUid(string $subject): ?string
    {
        $uids = imap_search($this->imap, 'UNDELETED SUBJECT "' . $subject . '"', SE_UID);
        if ($uids === false) {
            return null;
        }

        if (count($uids) > 1) {
            throw new \RuntimeException('There is more than one message for given path');
        }

        return (string) reset($uids);
    }

    public function delete(string $uid): bool
    {
        return imap_delete($this->imap, $uid, FT_UID);
    }
}
