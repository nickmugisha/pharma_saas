# Demo form reference

The seeder enters these values automatically. This document is included so the same records can be reviewed or recreated manually through the UI.

## Super Admin: central catalogue

Location:

```text
Super Admin → Medicine Catalogue → Medicines
```

Every demo medicine receives:

- brand and generic names;
- category;
- dosage form;
- manufacturer;
- strength and package size;
- fictional barcode and regulatory code;
- online access rule;
- marketplace summary;
- clinical information;
- active and approved status;
- a primary fictional package image.

The central catalogue contains 16 products:

1. ParaCare 500 mg — OTC.
2. CetiRelief 10 mg — OTC.
3. ORS Balance — OTC.
4. QuiniSafe 300 mg — prescription required.
5. FeverEase Junior 120 mg/5 mL — OTC.
6. AllerClear Kids 5 mg/5 mL — OTC.
7. ZincPlus 20 mg — OTC.
8. VitaC Boost 500 mg — OTC.
9. IbuEase 400 mg — pharmacist review.
10. GastroCalm 10 mg — pharmacist review.
11. CoughEase DM 15 mg/5 mL — pharmacist review.
12. IronCare Plus — pharmacist review.
13. AmoxiGuard 500 mg — prescription required.
14. MetroSafe 400 mg — prescription required.
15. ArteLum 20/120 mg — prescription required.
16. InsuCare R 100 IU/mL — in-store only.

## Pharmacy panel: My Medicines

Location:

```text
Pharmacy panel → Medicine Management → My Medicines
```

For each pharmacy and medicine, the seeder creates:

- pharmacy-specific SKU;
- selling price;
- online price;
- active availability;
- public marketplace visibility;
- stock-alert thresholds;
- pharmacy-specific description.

The prices differ by pharmacy:

- Umoja Care generally has the lowest online prices.
- Horizon Santé uses the central reference prices.
- Tanganyika Plus uses premium prices.

## Inventory batches

Location:

```text
Pharmacy panel → Inventory → Medicine Batches
```

Each listing receives two active batches:

- Batch A expires earlier, approximately ten months from seeding.
- Batch B expires later, approximately twenty-two months from seeding.

This gives the FEFO engine two valid batches to choose from for every medicine.

## Marketplace Offers

Location:

```text
Pharmacy panel → Sales → Marketplace Offers
```

Each listing receives an active branch offer with:

- online price;
- pickup availability;
- delivery availability;
- delivery fee;
- maximum order quantity;
- preparation time;
- access-control description.

Prescription-required and in-store-only products are pickup-only in this demo dataset. The central medicine rule still controls whether checkout requires a prescription or blocks online ordering.

## Pharmacy branches

The package expects or creates these main branches:

```text
Horizon Santé — Rohero        HZN-ROHERO
Umoja Care — Kamenge          UMO-KAMENGE
Tanganyika Plus — Kinindo     TGP-KININDO
```

## Images

Image records use:

```text
Disk: public
Path: demo-medicines/<product>.png
Primary: Yes
```

Every image includes the text `FICTIONAL DEMO PRODUCT` to avoid presenting it as a real pharmaceutical package.
