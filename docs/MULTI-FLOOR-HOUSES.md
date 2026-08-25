# Multi-floor houses

An owner rents a house one of two ways, and BR-013 keeps them exclusive:

- **Independent unit** - one rent, one tenant, one application queue. This is what the
  system did before, and it is still the default for every listing.
- **Floor by floor** - the ground floor goes to one tenant, the first floor to another.
  Each floor is listed, priced, published, applied for, and rented on its own.

The two are exclusive on purpose: a house and one of its own floors can never be let to two
different people at the same time.

## The model

A floor is a `properties` row of its own, hanging off its house through `parent_id`. That is
the whole trick - tenancies, applications, monthly records, maintenance requests, favourites,
audit logs, and reporting already point at `properties.id`, so every one of them works on a
floor with no change at all.

| Column | Meaning |
|--------|---------|
| `parent_id` | the house a floor belongs to; `null` on a top-level property |
| `rental_mode` | `whole` (rentable) or `by_floor` (a container for its floors) |
| `unit_label` | the floor's name, e.g. "Ground Floor" |
| `floor_number` | ordering; `0` is the ground floor, a basement is negative |

`rental_mode = by_floor` is only ever set on a top-level house, and a floor is always
`whole`, so `Property::rentable()` is the single scope that separates "something a tenant can
rent" from "the building around it".

### What the house owns, and what the floor owns

A floor sits inside its house, so `type`, `area`, `city`, `address`, and the contact details
are copied down on create and re-propagated by `UpdateProperty` whenever the house changes
them. The floor form never even accepts those fields. The floor owns its `rent_amount`,
gallery, description, status, and publication.

The floor's stored `title` is composed as `"<house> - <floor>"`, so every screen that already
prints `$property->title` - applications, tenancies, monthly records, reports, notifications -
reads "Gulberg Villa - Ground Floor" without a single change.

A house in `by_floor` mode keeps `rent_amount` as the **sum of its active floors**
(`SyncHouseRent`), so reporting and the owner list still show what the building brings in
without the owner maintaining a second number by hand.

## The rules

| Rule | Where it lives |
|------|----------------|
| A house let floor by floor never reaches the applicant feed | `Property::scopeRentable`, used by `publiclyVisible()` |
| ...and cannot be published | `PublishProperty`, `PropertyPolicy::publish` |
| ...and cannot take a tenancy | `PropertyPolicy::assignRenter`, the tenancy dropdowns |
| ...and has no occupancy status of its own | `ChangePropertyStatus` |
| Splitting a house needs its tenancy ended first | `ChangeRentalMode` |
| Going back to one unit needs its floors archived first | `ChangeRentalMode` |
| Floors are never nested inside a floor | `CreateFloor`, `PropertyPolicy::addFloor` |
| Archiving a house archives its floors, and restoring brings them back | `ArchiveProperty`, `RestoreProperty` |
| A house cannot be archived while a floor is rented | `ArchiveProperty` |

## Screens

- **Owner list** - top-level properties only, with each house's floors as indented rows
  underneath. A split house shows a combined rent and no status of its own.
- **Property page** - a house gains a *Floors* table and an *Add floor* button; a floor links
  back up to its house and loses the publish controls it does not own.
- **Property form** - picking "rented floor by floor" hides the rent and visibility fields.
  This is progressive enhancement only: without JavaScript the fields stay visible, the rules
  make the rent optional, and the actions ignore the rest.
- **Public listing** - a floor shows its name beside the type and links to the other floors of
  the same house, which are the closest alternatives an applicant has.

## API (SRS §9.3)

`PropertyResource` gained `rental_mode`, `is_rentable`, `house_id`, `unit_label`,
`floor_number`, and nested `floors`. `GET /api/v1/properties` returns top-level properties
with their floors inside, rather than a flat list that would count a building twice.

`rental_mode` is **optional** on write: an omitted value leaves an existing listing as it is
and makes a new one an independent unit, so payloads written before floors existed keep
working unchanged.

## Migration

`2026_08_21_000100_add_floors_to_properties_table` is purely additive - every existing row
becomes an independent unit through the `whole` column default, so nothing has to be
backfilled.
