<?php

namespace VodHost\Storage;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
use Aws\Common\Exception\MultipartUploadException;
use Aws\S3\Model\MultipartUpload\UploadBuilder;

class S3StorageEngine extends StorageEngine
{
    private $s3;
    private $s3_bucket;

    /**
     * Initializes the S3 connection instance
     */
    public function __construct(array $setup, $log)
    {
        parent::__construct($setup, $log);

        $this->s3_bucket = $setup['s3_bucket'];

        $this->s3 = new S3Client([
            'version'     => 'latest',
            'region'      => $setup['s3_region'],
            'credentials' => [
                'key'    => $setup['s3_key'],
                'secret' => $setup['s3_secret'],
            ],
        ]);
    }

    /**
     * Uploads files via the AWS S3 API. If the file is less than 5MB
     * it is uploaded in a single chunk. Files larger than 5MB are uploaded
     * using the MultipartUpload interface.
     */
    public function put($local_path, $remote_path)
    {
        if (!file_exists($local_path)) {
            throw new \Exception("File not found");
        }

        $filesize = filesize($local_path);

        if ($filesize < 5 * 1024 * 1024) { // Upload as single object
            try {
                $result = $this->s3->putObject(array(
                    'Bucket' => $this->s3_bucket,
                    'Key'    => $remote_path,
                    'SourceFile'   => $local_path,
                    'ACL'    => 'public-read'
                ));

                // Print the URL to the object.
                $this->log->info($result['ObjectURL'] . PHP_EOL);
            } catch (S3Exception $e) {
                $this->log->error($e->getMessage() . PHP_EOL);
            }
        } else { // MultiPart Upload

            $result = $this->s3->createMultipartUpload(array(
                'Bucket'       => $this->s3_bucket,
                'Key'          => $remote_path,
                'StorageClass' => 'REDUCED_REDUNDANCY',
                'ACL'          => 'public-read',
            ));
            $uploadId = $result['UploadId'];

            // Upload the file in parts.
            try {
                $file = fopen($local_path, 'r');
                $parts = array();
                $partNumber = 1;
                while (!feof($file)) {
                    $result = $this->s3->uploadPart(array(
                        'Bucket'     => $this->s3_bucket,
                        'Key'        => $remote_path,
                        'UploadId'   => $uploadId,
                        'PartNumber' => $partNumber,
                        'Body'       => fread($file, 16 * 1024 * 1024),
                    ));
                    $parts[] = array(
                        'PartNumber' => $partNumber++,
                        'ETag'       => $result['ETag'],
                    );

                    echo "Uploading part {$partNumber} of {$local_path}.\n";
                }

                fclose($file);
            } catch (S3Exception $e) {
                $result = $this->s3->abortMultipartUpload(array(
                    'Bucket'   => $this->s3_bucket,
                    'Key'      => $remote_path,
                    'UploadId' => $uploadId
                ));

                $this->log->error("Upload of {$local_path} failed" . PHP_EOL);
            }

            // Complete multipart upload.
            $result = $this->s3->completeMultipartUpload(array(
                'Bucket'   => $this->s3_bucket,
                'Key'      => $remote_path,
                'UploadId' => $uploadId,
                'MultipartUpload' => array(
                    'Parts' => $parts,
                ),
            ));

            $url = $result['Location'];

            $this->log->info("Uploaded {$local_path} to {$url}." . PHP_EOL);
        }
    }

    public function get($remote_path, $local_path)
    {
        $object_url = $this->s3->getObjectUrl($this->s3_bucket, $remote_path);
        if($object_url) {
            $fp = fopen($local_path, 'w+');

            $curl_handle = curl_init(str_replace(" ", "%20", $object_url));
            curl_setopt($curl_handle, CURLOPT_FILE, $fp);
            curl_setopt($curl_handle, CURLOPT_FOLLOWLOCATION, true);
            $ret = curl_exec($curl_handle);
            curl_close($curl_handle);
            fclose($fp);

            if($ret) {
                return true;
            }
        }

        return false;
    }

    public function listDirectory($remote_path)
    {
        $result = $this->s3->listObjectsV2([
            'Bucket' => 'vodhost',
            'Prefix' => $remote_path,
        ]);

        $keys = array();

        if($result['Contents']) {
            foreach ($result['Contents'] as $object) {
                $keys[] = $object['Key'];
            }
        }

        return $keys;
    }

    public function delete($remote_path)
    {
        $result = $this->s3->deleteObject([
            'Bucket' => 'vodhost',
            'Key' => $remote_path,
        ]);
    }

    public function exists($remote_path)
    {
        throw new \Exception("Not Implemented");
    }
}
