<?php

namespace app\model;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class email_queue_model extends model
{
    public $table_name = 'email_queue';
    
    public function send($id)
    {
        try {
            if ($queue = $this->find(array('id' => $id))) {
                $mailer = new PHPMailer();
                $mailer->isSMTP();
                $mailer->CharSet = 'UTF-8';
                $mailer->SMTPAuth = TRUE;
                $mailer->Host = $GLOBALS['cfg']['smtp_server'];
                $mailer->Port = $GLOBALS['cfg']['smtp_port'];
                $mailer->Username = $GLOBALS['cfg']['smtp_user'];
                $mailer->Password = $GLOBALS['cfg']['smtp_password'];
                $mailer->SMTPSecure = $GLOBALS['cfg']['smtp_secure'];
                $mailer->SetFrom($GLOBALS['cfg']['smtp_user'], $GLOBALS['cfg']['site_name']);

                $mailer->addAddress($queue['email']);
                $mailer->isHTML($queue['is_html'] == 1 ? TRUE : FALSE);
                $mailer->Subject = $queue['subject'];
                $mailer->Body = $queue['body'];

                if (!$mailer->send()) {
                    $data = array('last_err' => $mailer->ErrorInfo, 'err_count' => $queue['err_count'] + 1);
                    $this->update(array('id' => $id), $data);
                    return FALSE;
                }

                $this->delete(array('id' => $id));
                return true;
            }
        } catch (\Exception $e){

        }
        return false;
    }
}
