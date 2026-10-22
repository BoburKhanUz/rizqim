// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'tax_regime.dart';
import 'fiscal_settings5.dart';
import 'payment_settings2.dart';

part 'v1_owner_settings_request_body.g.dart';

@JsonSerializable()
class V1OwnerSettingsRequestBody {
  const V1OwnerSettingsRequestBody({
    required this.isOpen,
    required this.paymentSettings,
    this.name,
    this.timezone,
    this.logo,
    this.banner,
    this.description,
    this.phone,
    this.address,
    this.locationUrl,
    this.minOrderAmount,
    this.tin,
    this.taxRegime,
    this.fiscalSettings,
  });

  factory V1OwnerSettingsRequestBody.fromJson(Map<String, Object?> json) =>
      _$V1OwnerSettingsRequestBodyFromJson(json);

  /// P2-12: the store's name and timezone (the panel's settings page edits them too); left out = kept
  final String? name;
  final String? timezone;
  final String? logo;
  final String? banner;
  final String? description;
  final String? phone;
  final String? address;
  @JsonKey(name: 'location_url')
  final String? locationUrl;
  @JsonKey(name: 'is_open')
  final bool isOpen;
  @JsonKey(name: 'min_order_amount')
  final num? minOrderAmount;

  /// P3-11: STIR, 9 ta raqam.
  final String? tin;

  /// P3-11: vat | vat_simplified | turnover.
  @JsonKey(name: 'tax_regime')
  final TaxRegime? taxRegime;

  /// P3-11: fiskal sozlamalar (PUT /owner/fiscal/settings kabi).
  @JsonKey(name: 'fiscal_settings')
  final FiscalSettings5? fiscalSettings;

  /// To'lov va yetkazish sozlamalari (obyekt).
  @JsonKey(name: 'payment_settings')
  final PaymentSettings2 paymentSettings;

  Map<String, Object?> toJson() => _$V1OwnerSettingsRequestBodyToJson(this);
}
