# 安装

在composer.json中添加
~~~
"thefunpower/imap_inbox": "dev-main" 
~~~

或

~~~
composer require thefunpower/imap_inbox --ignore-platform-reqs
~~~

# 配置
 
~~~
$config['imap_server']   = 'imap.qq.com';
$config['imap_address']  = 'demo@qq.com';
$config['imap_password'] = 'password'; 
~~~

# 使用

~~~
$imap = new imap_inbox(PATH,'/uploads/mail_inbox'); 
//默认取30天
$imap->days = 30; 
$imap->only_ext = ['pdf'];
$list = $imap->get($name = 'inbox');  
print_r($list);
~~~

- `days`属性，下载最近多少天内的邮件

~~~
$imap->days = 2; 
~~~

- `only_ext` 属性，只保留有附件且后缀在以下范围内的邮件

~~~
$imap->only_ext = ['pdf'];
~~~

- `allow_ext` 属性，附件支持的后缀

默认值
~~~
$imap->allow_ext = ['zip','ofd','xml','md','txt','pdf','jpg','jpeg','png','webp','webm','mp3','mp4','gz','7z','doc','docx','xlsx','xlsx','ppt','pptx',
'dpg','csv',];
~~~


`$name`支持 
~~~ 
['inbox','sent_messages','drafts','deleted_messages']
~~~

`$base_path` 项目WEB可以访问到的根目录

`$save_url` 是要保存到的相对路径 

# 依赖 

- 打开扩展 `imap`

- 删除禁用函数 `imap_open`

# 返回数据

~~~
[
    [
        [id]=>'消息唯一值',
        [subject] => TEST
        [from] => kungfutime@foxmail.com
        [to] => Array
            (
                [0] => kungfutime@foxmail.com
            )

        [date] => 2024-03-28 11:58:32
        [time_zone] => +08:00 
        [body] =>
        [file] => 
        [files] =>
    ]
]
~~~

# action


~~~
add_action("imap_inbox", function(&$item){
    if($item['subject'] == 'test'){
        $item['countinue'] = true;
    } 
}); 
~~~
 


### 开源协议 

[Apache License 2.0](LICENSE)