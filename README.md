# Twitter Archive CSV Export

Converts your Twitter archive into a CSV file


## Usage Instructions

Request your Twitter archive from twitter.com

Move the Twitter archive folder to here and call it `twitter-archive`

Run the convert script:
* `php convert.php twitter-archive`

You'll end up with a file `tweets.csv` with an export of all your tweets. This will include columns for:

* Date
* Time
* Tweet text with all URLs expanded
* The first URL mentioned in a tweet
* Tweet permalink
* The URL of the first photo in the tweet
* The Twitter app used

Every bit.ly/buff.ly/ed.gr/etc URL will be un-shortened so that you can see the real URL that was linked to. These are cached locally so they only have to be fetched one time.
