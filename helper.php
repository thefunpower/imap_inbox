<?php
/**
 * Get Mail Inbox List
 * @author ken <yiiphp@foxmail.com>
 * @license Apache-2.0
 */
use Ddeboer\Imap\Server;
use helper_v3\Image;

function imap_inbox($base_path = '', $save_url = '')
{
    $imap = new imap_inbox($base_path);
    return $imap->get();
}

class imap_inbox
{
    protected $mailboxes;
    /**
     * 绝对路径，根目录
     */
    public $base_path;
    protected $save_path;
    /**
     * 相对路径
     */
    public $save_url;
    /**
     * 是否加载所有邮件
     * 默认加载最近30天的
     */
    public $load_all_date = false;

    public function __construct($base_path = '', $save_url = '')
    {
        $imap_server = get_config('imap_server');
        $imap_address = get_config('imap_address');
        $imap_password = get_config('imap_password');
        $server = new Server($imap_server);
        $connection = $server->authenticate($imap_address, $imap_password);
        $this->mailboxes = $connection->getMailboxes();
        $this->base_path = $base_path ?: PATH;
        $this->save_url = $save_url ?: '/data/mail_inbox/';
        $this->save_path = $this->base_path.$this->save_url;

    }

    public function get()
    {
        foreach ($this->mailboxes as $mailbox) {
            // Skip container-only mailboxes
            // @see https://www.php.net/manual/en/function.imap-getmailboxes.php
            if ($mailbox->getAttributes() & \LATT_NOSELECT) {
                continue;
            }
            //INBOX
            $list[$mailbox->getName()] =  $mailbox->count();
            if($this->load_all_date) {
                $messages = $mailbox->getMessages();
            } else {
                $today = new DateTimeImmutable();
                $thirtyDaysAgo = $today->sub(new DateInterval('P30D'));
                $messages = $mailbox->getMessages(
                    new Ddeboer\Imap\Search\Date\Since($thirtyDaysAgo),
                    \SORTDATE,
                    true
                );
            }
            foreach ($messages as $message) {
                $item['subject'] = $message->getSubject();
                $item['from'] = $message->getFrom()->getAddress();    // Message\EmailAddress
                $to = $message->getTo();
                $to_list = [];
                foreach($to as $_to) {
                    $to_list[] = $_to->getAddress();
                }
                $item['to'] = $to_list;
                $date = $message->getDate();
                $item['date'] = $date->format('Y-m-d H:i:s');
                $item['time_zone'] = $date->getTimezone()->getName();
                $item['is_answered'] = $message->isAnswered();
                $item['is_deleted'] = $message->isDeleted();
                $item['is_draft'] = $message->isDraft();
                $item['is_seen'] = $message->isSeen();
                $body = $message->getBodyHtml();
                if ($body === null) {
                    $body = $message->getBodyText();
                }
                $attachments = $message->getAttachments();
                $file = [];
                foreach ($attachments as $attachment) {
                    $name = $attachment->getFilename();
                    $content = $attachment->getDecodedContent();
                    $ext = get_mime_content($content, true);
                    $new_name = md5($content).".".$ext;
                    $save_file =  $this->save_path.$new_name;
                    if(!file_exists($save_file)) {
                        $dir = get_dir($save_file);
                        create_dir_if_not_exists([$dir]);
                        file_put_contents(
                            $save_file,
                            $content
                        );
                    }
                    $file[] = [
                        'name' => $name,
                        'url' => $this->save_url.$new_name
                    ];
                }
                $arr = Image::get_img_tag($body);
                $files = $file;
                if($arr) {
                    foreach($arr as $i => $v) {
                        $body = str_replace($v, $file[$i]['url'], $body);
                        unset($file[$i]);
                    }
                }
                $item['body'] = $body;
                $item['file'] = $file;
                $item['files'] = $files;
                $items[] = $item;
            }
            return $items;
        }
    }
}