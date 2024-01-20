# DCE to Mask migration

These two TYPO3 extensions help you to migrate from DCE to Mask. This was originally made for TYPO3 v12, but should also
work in TYPO3 v11 installations.

## Step 1

Install Mask and recreate all DCE elements in Mask.

## Step 2

Copy `dce_to_mask` into your local package repository and install it via `composer req hov/dce-to-mask:@dev`. If you
have a classic installation, you might need to add an ext_emconf.php file.
Now map the DCE identifiers and FlexForm field names to the new Mask type and column names in
`DceToMaskUpgradeWizard.php`. See the examples there on how to do that. It is also possible to migrate DCE sections to
Mask Inline fields.

## Step 3

Only if you have old DCE type `group` fields with `allowed` set to `sys_file`. This needs migration to FAL. Copy
`db_to_file` into your local package repository and install it via `composer req hov/db-to-file:@dev`. If you have a
classic installation, you might need to add an ext_emconf.php file. Have a look at `DbToFileUpgradeWizard.php` on how
to map the fields.

## Step 4

Make a backup of your DB in case you made an error in the configuration and need to run the migrations again.

## Step 5

Run the migration wizards. Either via the backend or the command line with `vendor/bin/typo3 upgrade:run dceToMask` and
then `vendor/bin/typo3 upgrade:run dbToFile`.


## Step 6

Migrate `sys_file_reference` records with manually written SQL migrations:

```
UPDATE sys_file_reference
    JOIN tt_content
ON sys_file_reference.uid_foreign = tt_content.uid
    SET sys_file_reference.fieldname = 'tx_mask_logos' -- the new Mask image field name
WHERE sys_file_reference.fieldname = 'logos' -- the old DCE FlexForm field identifier
  AND tt_content.CType = 'mask_logos'; -- the new Mask CType
```

## Step 7

Uninstall DCE and run Database Analyzer in order to remove obsolete tables.
