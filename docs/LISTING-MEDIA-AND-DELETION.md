# Listing media and deletion

Covers the gallery rules, walkthrough videos, and what Delete actually does. All
three apply identically to a house and to each of its floors, because a floor is
a property row of its own — see [MULTI-FLOOR-HOUSES.md](MULTI-FLOOR-HOUSES.md).

## Photos: 3 minimum, 12 maximum

| Where | Rule |
| --- | --- |
| Creating a house or floor | at least 3 photos must be uploaded, at most 12 |
| Editing one | the total after the edit must stay within 3–12, so a listing that already has 3+ needs no new upload |
| Removing a photo | refused when it would leave the listing below 3 — upload the replacement first |

The minimum is enforced in three places so it cannot be walked around: the form
request (`PropertyRequest`), the standalone upload request
(`StorePropertyImagesRequest`), and `PropertyImageService::delete()`. The API
answers to the same rules — it shares the form request.

Both bounds are configurable:

```dotenv
RENTAL_IMAGES_MIN_PER_PROPERTY=3
RENTAL_IMAGES_PER_PROPERTY=12
```

Listings created before this rule keep working; the edit form asks only for the
photos still missing, and counts what the listing already has.

## Videos: 200 MB each, own table

Videos are optional and live in **`property_videos`**, not in a column on
`properties` — the same normalisation the gallery already uses:

| Column | |
| --- | --- |
| `property_id` | FK, cascade on delete |
| `disk`, `file_path` | where the file lives |
| `original_name`, `mime_type`, `size_bytes` | file metadata |
| `sort_order` | display order |

`MP4`, `MOV` and `WEBM` are accepted, up to **200 MB per file** and 3 files per
listing by default:

```dotenv
RENTAL_VIDEO_MAX_KB=204800
RENTAL_VIDEOS_PER_PROPERTY=3
```

Extension, MIME type, size and per-listing count are all checked server side
(NFR-SEC-005). `PropertyVideoService` mirrors `PropertyImageService` minus the
primary-file invariant, and deletes the file from disk when a row goes — a
failed write cleans up after itself rather than leaving a 200 MB orphan.

> **PHP limits.** A 200 MB upload only works if `upload_max_filesize` and
> `post_max_size` are at least that large, and `post_max_size` has to cover the
> whole form — several videos plus the photos in one submit. Laravel cannot
> raise these; PHP discards the request body before the framework runs.

Owners manage videos from the property page; applicants see them on the public
listing, right under the gallery. The API exposes them as `videos` on both
`PropertyResource` and `ListingResource`.

## Delete

**Delete unpublishes and hides — it never destroys the record.** The listing
stops being visible to applicants, keeps appearing for its owner under
**Deleted**, and can be restored and published again at any time. Underneath it
is a soft delete (`deleted_at`), so tenancy, payment and audit history survive
intact (BR-009).

| Action | Result |
| --- | --- |
| Delete a floor | that floor only; its house and the other floors carry on, and the house's combined rent drops by its share |
| Delete a house | the house **and all of its floors** go together |
| Delete a listing with an active tenancy | refused — end the tenancy first |
| Delete a house whose floor is rented | refused, naming the floor |
| Restore | comes back **Available but unpublished**, so one click publishes it again |

A deleted floor stays reachable from its house page under *Deleted floors* with
its own Restore button, so it never becomes an orphan the owner cannot find.
Deleted top-level properties live under the **Deleted** tab of the property list.

Delete is offered on the property list rows (house and floor) and on each
property page, and asks for confirmation first.
