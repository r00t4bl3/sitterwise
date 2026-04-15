# Bubble URL Scraping Implementation

## ✅ Status: Working

The Bubble scraping implementation successfully uses browser automation with Symfony Panther and XHR response interception to extract caregiver data from Bubble.io.

## How It Works

### The Scraping Process

1. **Browser Launch**: Opens Chrome (visible or headless) with ChromeDriver
2. **Navigation**: Loads the Bubble page and waits for it to be ready
3. **Popup Handling**: Dismisses any "Upgrade Bubble Version" popup
4. **Navigation Sequence**:
   - Clicks tab caption (App Data)
   - Sets up **XHR response interceptor** (captures all Elasticsearch API calls)
   - Clicks "Caregivers" nested view
   - Clicks "Switch to live database"
   - Finds and clicks "XXX additional fields" button (dynamic count)
   - Clicks "(select all)" in modal
   - Clicks "SAVE"
5. **Data Interception**: The XHR interceptor captures the `https://bubble.io/elasticsearch/search` API response automatically
6. **Pagination**: Clicks "Load 50 more items..." button and waits for intercepted response on each page

### XHR Response Interceptor

The interceptor wraps `XMLHttpRequest` and `fetch` API to capture any requests to `elasticsearch/search`:

```javascript
// Intercepts both XHR and fetch
// Stores response in window._capturedResponse
// Logs: "Intercepted Elasticsearch response with X hits"
```

## Usage

### Basic Usage (Default Bubble URL)

```bash
php artisan caregivers:import --bubble
```

### Custom Bubble URL

```bash
php artisan caregivers:import --bubble --bubble-url="https://bubble.io/page?id=your-url"
```

### With Record Limit

```bash
php artisan caregivers:import --bubble --limit=100
```

### Dry Run (Preview Only)

```bash
php artisan caregivers:import --bubble --dry-run
```

### Force Overwrite Existing Records

```bash
php artisan caregivers:import --bubble --force
```

### Combine Options

```bash
php artisan caregivers:import --bubble --limit=50 --dry-run
php artisan caregivers:import --bubble --force --no-transaction
php artisan caregivers:import --bubble --bubble-url="URL" --limit=100 --force
```

## Browser Mode

### Headless (Default)
Chrome runs in the background without visible window.

### Visible Mode (For Debugging)
Remove `--headless=new` from Chrome arguments in `ImportCaregivers.php` to see what the browser is doing.

## Key Features

- **XHR Interception**: Captures actual in-browser API responses instead of making separate HTTP calls
- **Dynamic Button Detection**: Finds "XXX additional fields" button regardless of count
- **Smart Pagination**: Automatically clicks "Load 50 more items" until no more data
- **Progress Tracking**: Shows real-time record count in console
- **Error Handling**: Gracefully handles missing buttons or failed interceptions
- **Rate Limiting**: Built-in delays between actions to avoid overwhelming the API

## Prerequisites

### Chrome/Chromium
```bash
# Install Google Chrome
wget -q -O - https://dl.google.com/linux/linux_signing_key.pub | sudo apt-key add -
sudo sh -c 'echo "deb [arch=amd64] http://dl.google.com/linux/chrome/deb/ stable main" >> /etc/apt/sources.list.d/google-chrome.list'
sudo apt-get update
sudo apt-get install -y google-chrome-stable
```

### ChromeDriver
```bash
./vendor/bin/bdi detect drivers --os=linux
```

### Dependencies
```bash
composer require --dev symfony/panther dbrekelmans/bdi
```

## Implementation Details

### Key Methods

| Method | Purpose |
|--------|---------|
| `scrapeBubbleData()` | Main orchestrator, handles pagination |
| `configureBubbleFieldsAndGetCookies()` | Navigates page, clicks buttons, returns intercepted data |
| `setupResponseInterceptor()` | Injects XHR/fetch interceptor |
| `waitForInterceptedResponse()` | Waits for `window._capturedResponse` to be set |
| `waitForConsoleLog()` | Waits for specific console.log message |

### Constants

- **`DEFAULT_BUBBLE_URL`**: Default Bubble URL to scrape
- **`BUBBLE_API_URL`**: `https://bubble.io/elasticsearch/search`

### Interceptor Flow

```
1. setupResponseInterceptor()
   ↓
2. Click SAVE button
   ↓
3. Browser makes XHR/fetch to elasticsearch/search
   ↓
4. Interceptor captures response → window._capturedResponse
   ↓
5. waitForInterceptedResponse() retrieves it
   ↓
6. Return hits array for import
```

## Debugging

### Enable Debug Logging
Pass `true` as 4th parameter to `waitForConsoleLog()`:
```php
$this->waitForConsoleLog($client, 'message', 60, true); // Shows all browser logs
```

### Make Browser Visible
Comment out headless flag to watch what's happening:
```php
// '--headless=new', // Comment this out
```

### Check Interceptor
Look for console output:
```
Intercepted Elasticsearch response with 50 hits
```

## Notes

- Each page fetches 50 records (Bubble's standard batch size)
- 0.5-5 second delays between actions for stability
- All existing import logic (validation, user creation, etc.) works identically
- File-based import still works: `php artisan caregivers:import path/to/file.json`
- Must use either file path OR `--bubble` flag (not neither)
