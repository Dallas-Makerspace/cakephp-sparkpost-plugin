<?php

/*
 * Copyright (c) 2015 Syntax Era Development Studio
 *
 * Licensed under the MIT License (the "License"); you may not use this
 * file except in compliance with the License. You may obtain a copy of
 * the License at
 *
 *      https://opensource.org/licenses/MIT
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */
namespace SparkPost\Mailer\Transport;

use Cake\Core\Configure;
use Cake\Mailer\AbstractTransport;
use Cake\Mailer\Email;
use Cake\Network\Exception\BadRequestException;
use GuzzleHttp\Client;
use Http\Adapter\Guzzle6\Client as GuzzleAdapter;
use SparkPost\SparkPostException;
use SparkPost\SparkPost;

/**
 * Spark Post Transport Class
 *
 * Provides an interface between the CakePHP Email functionality and the SparkPost API.
 * 
 * Modified 12/17/2018 to use GuzzleHttp for Adapter - Mike Cole <MikeColeGuru@gmail.com> 
 *
 * @package SparkPost\Mailer\Transport
 */
class SparkPostTransport extends AbstractTransport
{
    /**
     * Send mail via SparkPost REST API
     *
     * @param \Cake\Mailer\Email $email Email message
     * @return array
     */
    public function send(Email $email)
    {
        // Load SparkPost configuration settings
        $apiKey = $this->config('apiKey');

        // Set up HTTP request adapter
        $adapter = new GuzzleAdapter(new Client());

        // Create SparkPost API accessor
        $sparkpost = new SparkPost($adapter, [ 'key' => $apiKey ]);

        // Pre-process CakePHP email object fields
        $from = (array) $email->from();
        $to = (array) $email->to();
        $replyTo = $email->getReplyTo() ? array_values($email->getReplyTo())[0] : null;
        
        foreach ($to as $toEmail => $toName) {
            $recipients[] = ['address' => [ 'name' => mb_encode_mimeheader($toName), 'email' => $toEmail]];
        }

        // Build message to send
        $message = [
            'content' => [
                'from' => [
                    'name' => mb_encode_mimeheader(array_values($from)[0]),
                    'email' => array_keys($from)[0],
                ],
                'subject' => mb_decode_mimeheader($email->subject()),
                'html' => empty($email->message('html')) ? $email->message('text') : $email->message('html'),
                'text' => $email->message('text'),
            ],
            'recipients' => $recipients,
        ];

        if ($replyTo) {
            $message['replyTo'] = $replyTo;
        }

        // Send message
        try {
            $promise = $sparkpost->transmissions->post($message);
            $promise->wait();
        } catch(SparkPostException $e) {
            // TODO: Determine if BRE is the best exception type
            throw new BadRequestException(sprintf('SparkPost error (%d): %s',
                $e->getCode(), ucfirst($e->getMessage())
            ));
        }
    }
}
