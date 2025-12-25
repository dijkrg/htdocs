<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../includes/init.php';

// mail/mail_template.php
function renderMailTemplate($title, $content) {
    return "
    <!DOCTYPE html>
    <html lang='nl'>
    <head>
        <meta charset='UTF-8'>
        <style>
            body {
                font-family: Arial, sans-serif;
                background-color: #f6f6f6;
                margin: 0;
                padding: 20px;
            }
            .mail-container {
                max-width: 600px;
                margin: 0 auto;
                background: #ffffff;
                border-radius: 8px;
                overflow: hidden;
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
            .mail-header {
                background: #2954cc;
                color: #fff;
                padding: 15px;
                font-size: 20px;
                text-align: center;
            }
            .mail-body {
                padding: 20px;
                color: #333;
                line-height: 1.5;
            }
            .mail-footer {
                font-size: 12px;
                color: #777;
                text-align: center;
                padding: 15px;
                background: #f3f4f6;
            }
        </style>
    </head>
    <body>
        <div class='mail-container'>
            <div class='mail-header'>
                ABC Brandbeveiliging
            </div>
            <div class='mail-body'>
                <h2>{$title}</h2>
                {$content}
            </div>
            <div class='mail-footer'>
                Dit is een automatische e-mail vanuit het ABC Brandbeveiliging systeem.<br>
                Reageren op dit bericht is niet mogelijk.
            </div>
        </div>
    </body>
    </html>
    ";
}
