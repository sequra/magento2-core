<?php

namespace Sequra\Core\Services\BusinessLogic;

use InvalidArgumentException;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use SeQura\Core\BusinessLogic\Domain\Integration\Banner\BannerServiceInterface;
use SeQura\Core\BusinessLogic\Domain\Multistore\StoreContext;
use SeQura\Core\Infrastructure\Logger\Logger;

class BannerService implements BannerServiceInterface
{
    public const DISPLAY_ON_HOME_PAGE = 'displayOnHomePage';
    public const DISPLAY_ON_PRODUCT_PAGE = 'displayOnProductPage';
    public const DISPLAY_ON_CART_PAGE = 'displayOnCartPage';
    public const DISPLAY_ON_PRODUCT_LISTING_PAGE = 'displayOnProductListingPage';
    public const BANNER_MEDIA_DIR = 'sequra/banners';
    private const MAX_IMAGE_BYTES = 2 * 1024 * 1024;
    private const DATA_URI_MARKER = 'base64,';
    private const ERROR_TOO_LARGE = 'Banner image exceeds the 2 MB size limit.';

    /**
     * Allowed MIME type → file extension.
     */
    private const ALLOWED_MIME_EXTENSIONS = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    /**
     * @var Filesystem
     */
    private Filesystem $filesystem;

    /**
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * @param Filesystem $filesystem
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(Filesystem $filesystem, StoreManagerInterface $storeManager)
    {
        $this->filesystem = $filesystem;
        $this->storeManager = $storeManager;
    }

    /**
     * @inheritDoc
     */
    public function getBannerDisplayLocations(): array
    {
        return [
            self::DISPLAY_ON_HOME_PAGE,
            self::DISPLAY_ON_PRODUCT_PAGE,
            self::DISPLAY_ON_CART_PAGE,
            self::DISPLAY_ON_PRODUCT_LISTING_PAGE,
        ];
    }

    /**
     * @inheritDoc
     *
     * @throws FileSystemException|NoSuchEntityException|InvalidArgumentException
     */
    public function saveBannerImage(string $country, string $displayLocation, string $imageBase64): string
    {
        $this->assertCountry($country);
        $this->assertDisplayLocation($displayLocation);

        $bytes = $this->decodeBase64($imageBase64);
        $this->assertIsValidImage($bytes);
        $extension = $this->resolveExtension($bytes);

        $relativePath = $this->relativePathFor($country, $displayLocation, $extension);

        $mediaDir = $this->getMediaWrite();
        $this->removeOtherVariants($mediaDir, $country, $displayLocation, $extension);
        $mediaDir->writeFile($relativePath, $bytes);

        return $this->getMediaBaseUrl() . $relativePath;
    }

    /**
     * @inheritDoc
     *
     * @throws FileSystemException|InvalidArgumentException
     */
    public function deleteBannerImage(string $country, string $displayLocation): void
    {
        $this->assertCountry($country);
        $this->assertDisplayLocation($displayLocation);

        $this->removeAllVariants($this->getMediaWrite(), $country, $displayLocation);
    }

    /**
     * @inheritDoc
     *
     * @throws FileSystemException
     * @throws NoSuchEntityException
     * @throws InvalidArgumentException
     */
    public function changeBannerImageDisplayLocation(
        string $country,
        string $oldDisplayLocation,
        string $newDisplayLocation
    ): string {
        $this->assertCountry($country);
        $this->assertDisplayLocation($oldDisplayLocation);
        $this->assertDisplayLocation($newDisplayLocation);

        $mediaDir = $this->getMediaWrite();
        $baseUrl = $this->getMediaBaseUrl();

        $existingExtension = $this->findExistingExtension($mediaDir, $country, $oldDisplayLocation);

        if ($oldDisplayLocation === $newDisplayLocation) {
            $extension = $existingExtension ?? self::ALLOWED_MIME_EXTENSIONS['image/png'];

            return $baseUrl . $this->relativePathFor($country, $newDisplayLocation, $extension);
        }

        if ($existingExtension === null) {
            Logger::logWarning(
                sprintf(
                    'Banner image not found while relocating country=%s from %s to %s; '
                    . 'returning the would-be URL so the admin can re-upload.',
                    strtoupper($country),
                    $oldDisplayLocation,
                    $newDisplayLocation
                ),
                'Integration'
            );

            return $baseUrl
                . $this->relativePathFor($country, $newDisplayLocation, self::ALLOWED_MIME_EXTENSIONS['image/png']);
        }

        $source = $this->relativePathFor($country, $oldDisplayLocation, $existingExtension);
        $destination = $this->relativePathFor($country, $newDisplayLocation, $existingExtension);

        $this->removeAllVariants($mediaDir, $country, $newDisplayLocation);

        try {
            $mediaDir->renameFile($source, $destination);
        } catch (FileSystemException $e) {
            throw new InvalidArgumentException(
                'Failed to relocate banner image: ' . $e->getMessage(),
                0,
                $e
            );
        }

        return $baseUrl . $destination;
    }

    /**
     * Returns the extension of the currently stored banner variant, or null when none exist.
     *
     * @param WriteInterface $mediaDir
     * @param string $country
     * @param string $displayLocation
     *
     * @return string|null
     */
    private function findExistingExtension(
        WriteInterface $mediaDir,
        string $country,
        string $displayLocation
    ): ?string {
        foreach (self::ALLOWED_MIME_EXTENSIONS as $ext) {
            if ($mediaDir->isExist($this->relativePathFor($country, $displayLocation, $ext))) {
                return $ext;
            }
        }

        return null;
    }

    /**
     * Decodes a Base64 image payload, guarding both encoded and decoded sizes.
     *
     * @param string $imageBase64
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    private function decodeBase64(string $imageBase64): string
    {
        $payload = $this->extractBase64Payload($imageBase64);
        $this->assertEncodedSizeWithinLimit($payload);

        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged -- needed to decode incoming banner payload
        $decoded = base64_decode($payload, true);
        if ($decoded === false || $decoded === '') {
            throw new InvalidArgumentException('Banner image payload is not valid Base64.');
        }

        $this->assertDecodedSizeWithinLimit($decoded);

        return $decoded;
    }

    /**
     * Returns just the Base64 part, without any data URI prefix or whitespace.
     *
     * @param string $imageBase64
     *
     * @return string
     */
    private function extractBase64Payload(string $imageBase64): string
    {
        $pos = strpos($imageBase64, self::DATA_URI_MARKER);
        $payload = $pos !== false
            ? substr($imageBase64, $pos + strlen(self::DATA_URI_MARKER))
            : $imageBase64;

        return preg_replace('/\s+/', '', $payload);
    }

    /**
     * Rejects oversized payloads before decoding to keep memory bounded.
     *
     * Base64 is ~4/3 of the raw size; the +64 covers padding.
     *
     * @param string $payload
     *
     * @throws InvalidArgumentException
     */
    private function assertEncodedSizeWithinLimit(string $payload): void
    {
        $maxEncodedLength = (int)ceil(self::MAX_IMAGE_BYTES * 4 / 3) + 64;
        if (strlen($payload) > $maxEncodedLength) {
            throw new InvalidArgumentException(self::ERROR_TOO_LARGE);
        }
    }

    /**
     * Checks size on the decoded bytes.
     *
     * @param string $decoded
     *
     * @throws InvalidArgumentException
     */
    private function assertDecodedSizeWithinLimit(string $decoded): void
    {
        if (strlen($decoded) > self::MAX_IMAGE_BYTES) {
            throw new InvalidArgumentException(self::ERROR_TOO_LARGE);
        }
    }

    /**
     * Confirms the decoded bytes are a structurally valid image.
     *
     * @param string $bytes
     *
     * @throws InvalidArgumentException
     */
    private function assertIsValidImage(string $bytes): void
    {
        // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged -- handled via false return below
        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            throw new InvalidArgumentException('Banner image is not a valid image file.');
        }
    }

    /**
     * Maps the bytes' detected MIME type to its file extension.
     *
     * @param string $bytes
     *
     * @return string
     *
     * @throws InvalidArgumentException
     */
    private function resolveExtension(string $bytes): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->buffer($bytes);

        if (!isset(self::ALLOWED_MIME_EXTENSIONS[$mime])) {
            throw new InvalidArgumentException(
                'Banner image has an unsupported MIME type. Allowed: '
                . implode(', ', array_keys(self::ALLOWED_MIME_EXTENSIONS))
            );
        }

        return self::ALLOWED_MIME_EXTENSIONS[$mime];
    }

    /**
     * Validates the country is a 2-letter ISO code.
     *
     * @param string $country
     *
     * @throws InvalidArgumentException
     */
    private function assertCountry(string $country): void
    {
        if (!preg_match('/^[A-Za-z]{2}$/', $country)) {
            throw new InvalidArgumentException('Country must be a 2-letter ISO code.');
        }
    }

    /**
     * Validates the display location against the supported list.
     *
     * @param string $displayLocation
     *
     * @throws InvalidArgumentException
     */
    private function assertDisplayLocation(string $displayLocation): void
    {
        if (!in_array($displayLocation, $this->getBannerDisplayLocations(), true)) {
            throw new InvalidArgumentException(
                'Banner display location is invalid. Allowed: '
                . implode(', ', $this->getBannerDisplayLocations())
            );
        }
    }

    /**
     * Builds the relative media path for a banner image per store id.
     *
     * @param string $country
     * @param string $displayLocation
     * @param string $extension
     *
     * @return string
     */
    private function relativePathFor(string $country, string $displayLocation, string $extension): string
    {
        return self::BANNER_MEDIA_DIR
            . '/' . $this->storeSegment()
            . '/' . strtoupper($country) . '_' . $displayLocation . '.' . $extension;
    }

    /**
     * Filesystem path segment for the active store.
     *
     * @return string
     */
    private function storeSegment(): string
    {
        $storeId = StoreContext::getInstance()->getStoreId();
        $sanitized = preg_replace('/[^A-Za-z0-9_-]/', '', $storeId) ?? '';

        return $sanitized !== '' ? $sanitized : 'default';
    }

    /**
     * Removes every stored variant for a (country, displayLocation) pair.
     *
     * @param WriteInterface $mediaDir
     * @param string $country
     * @param string $displayLocation
     *
     * @throws FileSystemException
     */
    private function removeAllVariants(
        WriteInterface $mediaDir,
        string $country,
        string $displayLocation
    ): void {
        foreach (self::ALLOWED_MIME_EXTENSIONS as $ext) {
            $this->deleteIfExists($mediaDir, $this->relativePathFor($country, $displayLocation, $ext));
        }
    }

    /**
     * Removes every stored variant except the given extension.
     *
     * Used before writing a new file so the format can change without leaving stale copies.
     *
     * @param WriteInterface $mediaDir
     * @param string $country
     * @param string $displayLocation
     * @param string $keepExtension
     *
     * @throws FileSystemException
     */
    private function removeOtherVariants(
        WriteInterface $mediaDir,
        string $country,
        string $displayLocation,
        string $keepExtension
    ): void {
        foreach (self::ALLOWED_MIME_EXTENSIONS as $ext) {
            if ($ext === $keepExtension) {
                continue;
            }

            $this->deleteIfExists($mediaDir, $this->relativePathFor($country, $displayLocation, $ext));
        }
    }

    /**
     * Deletes the given path when it exists.
     *
     * @param WriteInterface $mediaDir
     * @param string $path
     *
     * @throws FileSystemException
     */
    private function deleteIfExists(WriteInterface $mediaDir, string $path): void
    {
        if ($mediaDir->isExist($path)) {
            $mediaDir->delete($path);
        }
    }

    /**
     * Returns a writable handle to the Magento media directory.
     *
     * @return WriteInterface
     *
     * @throws FileSystemException
     */
    private function getMediaWrite(): WriteInterface
    {
        return $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
    }

    /**
     * Resolves the public media base URL for the current store context.
     *
     * Falls back to the default store if the StoreContext has no storeId set.
     *
     * @return string
     *
     * @throws NoSuchEntityException
     */
    private function getMediaBaseUrl(): string
    {
        $storeId = StoreContext::getInstance()->getStoreId();
        $store = $storeId !== ''
            ? $this->storeManager->getStore($storeId)
            : $this->storeManager->getStore();

        return $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);
    }
}
