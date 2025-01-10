<?php

namespace moon\reademail;

use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Google\Client;
use Google_Service_Gmail;
use Google\Service\Gmail;
use Google\Service\Gmail\ModifyMessageRequest;

/**
 * Class MoonEmailReader
 * Handles reading emails from Gmail and processing them.
 */
class MoonEmailReader
{
    private $from;
    private $body;
    private $headers;
    private $filePath;
    private $request;

    /**
     * Constructor to initialize request and file path.
     *
     * @param array $request Request data.
     */
    public function __construct($request)
    {
        $this->request = $request;
        $this->filePath = __DIR__ . '/gmail_history.json';
    }

    /**
     * Retrieves and processes emails from moonemailreader.
     *
     * @return array Response data.
     */
    public function getEmail()
    {
        try {
            $request = $this->request;
            if (!empty($request['message']['data'])) {
                $notificationDataJson = base64_decode($request['message']['data']);
                if (empty($notificationDataJson)) {
                    return $this->fail([], 'Invalid request');
                }

                $notificationData = json_decode($notificationDataJson, true);
                if (empty($notificationData)) {
                    return $this->fail([], 'Invalid JSON');
                }

                if ($request['subscription'] !== config('moonemailread.EMAIL_READ_SUBSCRIPTION_NAME')) {
                    return $this->fail([], 'Not subscribed to defined subscription');
                }

                $newHistoryId = $notificationData['historyId'];
                $oldHistoryId = $this->readHistoryId();
                $this->writeHistoryId($newHistoryId);

                $message = null;
                if ($oldHistoryId) {
                    $refreshToken = config('moonemailreader.REFRESH_TOKEN');
                    $client = $this->getClient($refreshToken);
                    $service = new Google_Service_Gmail($client);
                    $response = $service->users_history->listUsersHistory('me', ['startHistoryId' => $oldHistoryId]);
                    $historyList = $response->getHistory();
                    $gmailService = new Gmail($client);
                    foreach ($historyList as $history) {
                        foreach ($history->messagesAdded as $message) {
                            $message = $service->users_messages->get('me', $message->getMessage()->id);
                            if (config('moonemailreader.email_mark_as_read')) {
                                $gmailService->users_messages->modify('me', $message->getId(), new ModifyMessageRequest([
                                    'removeLabelIds' => ['UNREAD'],
                                ]));
                            }
                            if (!empty($message)) {
                                $this->processEmail($message);
                            }
                        }
                    }
                }

                if (!empty($message)) {
                    return $this->success([
                        'body' => $this->getBody(),
                        'from_email' => $this->getFromEmail(),
                        'subject' => $this->getSubject()
                    ]);
                } else {
                    return $this->success([]);
                }
            }

            return $this->fail([], 'Invalid request');
        } catch (Exception $e) {
            return $this->fail([], $e->getMessage());
        }
    }

    /**
     * Processes the email message to extract headers and body.
     *
     * @param \Google\Service\Gmail\Message $message Gmail message object.
     */
    private function processEmail($message)
    {
        $headers = $message->getPayload()->getHeaders();
        $this->headers = $headers;
        $from = '';
        foreach ($headers as $header) {
            if ($header->getName() === 'From') {
                $from = $header->getValue();
                break;
            }
        }
        $this->from = $from;

        $body = '';
        $parts = $message->getPayload()->getParts();
        foreach ($parts as $part) {
            if (in_array($part->getMimeType(), ['text/plain', 'text/html'])) {
                $body = base64_decode(str_replace(['-', '_'], ['+', '/'], $part->getBody()->getData()));
            }
        }
        $this->body = $body;
    }

    /**
     * Creates and returns a Google Client with a refreshed access token.
     *
     * @param string $refreshToken Refresh token for Google API.
     * @return \Google\Client Configured Google client.
     */
    private function getClient($refreshToken)
    {
        $client = new Client();
        $client->setClientId(config('moonemailreader.GOOGLE_CLIENT_ID'));
        $client->setClientSecret(config('moonemailreader.GOOGLE_CLIENT_SECRET'));
        $accessTokenObj = $client->fetchAccessTokenWithRefreshToken($refreshToken);
        $accessToken = $accessTokenObj['access_token'];
        $this->accessToken = $accessToken;
        $client->setAccessToken($accessToken);
        return $client;
    }

    /**
     * Builds and returns a standardized response.
     *
     * @param array $data Response data.
     * @param string $msg Response message.
     * @param int $code HTTP status code.
     * @return array Standardized response array.
     */
    private function res($data, $msg, $code)
    {
        return [
            'status' => $code === 200,
            'code' => $code,
            'msg' => $msg,
            'data' => $data
        ];
    }

    /**
     * Returns a successful response.
     *
     * @param array $data Response data.
     * @param string $msg Response message.
     * @param int $code HTTP status code.
     * @return array Successful response array.
     */
    private function success($data = [], $msg = 'Success', $code = 200)
    {
        return $this->res($data, $msg, $code);
    }

    /**
     * Returns a failure response.
     *
     * @param array $data Response data.
     * @param string $msg Error message.
     * @param int $code HTTP status code.
     * @return array Failure response array.
     */
    private function fail($data = [], $msg = "Something went wrong!", $code = 400)
    {
        return $this->res($data, $msg, $code);
    }

    /**
     * Reads the history ID from the JSON file.
     *
     * @return string|null History ID or null if not found.
     */
    protected function readHistoryId()
    {
        if (file_exists($this->filePath)) {
            $data = json_decode(file_get_contents($this->filePath), true);
            return $data['historyId'] ?? null;
        }
        return null;
    }

    /**
     * Writes or updates the history ID in the JSON file.
     *
     * @param string $newHistoryId New history ID to be written.
     */
    protected function writeHistoryId($newHistoryId)
    {
        $data = ['historyId' => $newHistoryId];
        file_put_contents($this->filePath, json_encode($data, JSON_PRETTY_PRINT));
    }

    // Getters for extracted email details

    public function getBody()
    {
        return $this->body;
    }

    public function getFrom()
    {
        return $this->from;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function getHeader($headerName, $regex = null)
    {
        $value = null;
        foreach ($this->headers as $header) {
            if ($header->name === $headerName) {
                $value = $header->value;
                if ($regex) {
                    preg_match_all($regex, $header->value, $value);
                }
                break;
            }
        }
        return is_array($value) ? $value[1] ?? null : $value;
    }

    public function getSubject()
    {
        return $this->getHeader('Subject');
    }

    public function getFromName()
    {
        return preg_replace('/ <(.*)>/', '', $this->getHeader('From'));
    }

    public function getFromEmail()
    {
        $from = $this->getHeader('From');
        if (filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return $from;
        }
        preg_match('/<(.*)>/', $from, $matches);
        return $matches[1] ?? null;
    }

    /**
     * Sets up a watch request for Gmail notifications.
     *
     * @param string $topic Google Pub/Sub topic name.
     * @return array|null Response or failure message.
     */
    public function setWatch($topic)
    {
        try {
            $refreshToken = config('moonemailreader.REFRESH_TOKEN');
            $client = $this->getClient($refreshToken);
            $gmailService = new Gmail($client);

            $watchRequest = new Gmail\WatchRequest();
            $watchRequest->setTopicName($topic);
            $watchRequest->setLabelIds(['INBOX']);
            $watchRequest->setLabelFilterAction('include');
            $response = $gmailService->users->watch('me', $watchRequest);
            $this->writeHistoryId($response->historyId);
        } catch (Exception $e) {
            return $this->fail([], $e->getMessage());
        }
    }
}
