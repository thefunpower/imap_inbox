<?php
/**
 * Get Mail Inbox List
 * @author ken <yiiphp@foxmail.com>
 * @license Apache-2.0
 */
use Ddeboer\Imap\Server;
use helper_v3\Image;

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
    public static $data;
    public static $key;
    public $extract_url;
    /**
     * 附件支持的后缀
     */
    public $allow_ext = [
        'zip','ofd','xml','md','txt','pdf','jpg','jpeg','png','webp','webm','mp3','mp4','gz','7z','doc','docx','xlsx','xlsx','ppt','pptx',
        'dpg','csv',
    ];
    /**
     * 默认取30天数据
     */
    public $days = 30;
    public function __construct($base_path = '', $save_url = '', $extract_url = '')
    {
        $imap_server = get_config('imap_server');
        $imap_address = get_config('imap_address');
        $imap_password = get_config('imap_password');
        if(!$imap_server || !$imap_address || !$imap_password) {
            throw new \Exception("请配置 imap_server 、imap_address、 imap_password参数");
        }
        $server = new Server($imap_server);
        $connection = $server->authenticate($imap_address, $imap_password);
        $this->mailboxes = $connection->getMailboxes();
        $this->base_path = $base_path ?: PATH;
        $this->save_url = $save_url ?: '/data/mail_inbox/';
        $this->extract_url = $this->base_path.($extract_url ?: '/data/mail_inbox_tmp_extract/');
        $this->save_path = $this->base_path.$this->save_url;
        self::$key = "mail_inbox".$imap_server.$imap_address;
        return $this;
    }
    /**
     * 获取收件箱
     * @param $allow_box ['inbox','sent_messages','drafts','deleted_messages']
     */
    public function get($allow_box = 'inbox', $days = '')
    {
        if(is_numeric($allow_box)) {
            $this->days = $allow_box;
            $allow_box = 'inbox';
        }
        if($days > 0) {
            $this->days = $days;
        }
        if(is_string($allow_box)) {
            $array_in[] = $allow_box;
        } else {
            $array_in = $allow_box;
        }
        $cache_key = self::$key.md5(json_encode($array_in));
        $full = [];
        if(!self::$data[$cache_key]) {
            foreach ($this->mailboxes as $mailbox) {
                // Skip container-only mailboxes
                // @see https://www.php.net/manual/en/function.imap-getmailboxes.php
                if ($mailbox->getAttributes() & \LATT_NOSELECT) {
                    continue;
                }
                //INBOX
                $name = $mailbox->getName();
                $name = str_replace(" ", "_", $name);
                $name = trim(strtolower($name));
                if(!in_array($name, $array_in)) {
                    continue;
                }
                $top =  ['name' => $name,'count' => $mailbox->count()];
                if($this->load_all_date) {
                    $messages = $mailbox->getMessages();
                } else {
                    $today = new DateTimeImmutable();
                    $thirtyDaysAgo = $today->sub(new DateInterval('P'.$this->days.'D'));
                    $messages = $mailbox->getMessages(
                        new Ddeboer\Imap\Search\Date\Since($thirtyDaysAgo),
                        \SORTDATE,
                        true
                    );
                }
                $items = [];
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
                    $item['number'] = $message->getNumber();
                    $id = $message->getId();
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
                    if(!$id) {
                        $id = "m".$item['date'].$item['subject'].$item['from'].json_encode($item['to']);
                    }
                    $id = str_replace("<", "[", $id);
                    $id = str_replace(">", "]", $id);
                    if(substr($id, 0, 1) == '[') {
                        $id = substr($id, 1);
                    }
                    if(substr($id, -1) == ']') {
                        $id = substr($id, 0, -1);
                    }
                    $item['id'] = $id;
                    $attachments = $message->getAttachments();
                    $file = [];
                    foreach ($attachments as $attachment) {
                        $name = $attachment->getFilename();
                        $content = $attachment->getDecodedContent();
                        $ext = get_ext($name) ?? get_mime_content($content, true);
                        $new_name = md5($content).".".$ext;
                        if(!in_array($ext, $this->allow_ext)) {
                            continue;
                        }
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
                            if(strpos($v, '://') !== false) {
                                $content = file_get_contents($v);
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
                                $body = str_replace($v, $this->save_url.$new_name, $body);
                            } elseif($file[$i]['url']) {
                                $body = str_replace($v, $file[$i]['url'], $body);
                                unset($file[$i]);
                            }
                        }
                    }
                    $item['body'] = $body;
                    if($file) {
                        $file = array_values($file);
                    }
                    $item['file'] = $file;
                    $items[] = $item;
                }
                $full[$top['name']] =  $items;
            }
        } else {
            $full = self::$data[$cache_key];
        }
        $new_list = [];
        foreach($full as $k => $v) {
            if(in_array($k, $array_in)) {
                $new_list[$k] = $v;
            }
        }
        if(count($array_in) == 1) {

            return $new_list[$array_in[0]];
        }
        return $new_list;
    }
}
