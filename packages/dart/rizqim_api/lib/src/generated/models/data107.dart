// coverage:ignore-file
// GENERATED CODE - DO NOT MODIFY BY HAND
// ignore_for_file: type=lint, unused_import, invalid_annotation_target, unnecessary_import

import 'package:json_annotation/json_annotation.dart';

import 'payment_settings.dart';
import 'payment_account_type.dart';
import 'store_status.dart';
import 'tax_regime.dart';
import 'fiscal_settings4.dart';

part 'data107.g.dart';

@JsonSerializable()
class Data107 {
  const Data107({
    required this.id,
    required this.name,
    required this.timezone,
    required this.logo,
    required this.logoUrl,
    required this.banner,
    required this.bannerUrl,
    required this.description,
    required this.phone,
    required this.address,
    required this.locationUrl,
    required this.isOpen,
    required this.minOrderAmount,
    required this.paymentSettings,
    required this.paymentAccountType,
    required this.status,
    required this.isOrderLocked,
    required this.allowedMethods,
    required this.tin,
    required this.taxRegime,
    required this.fiscalSettings,
  });

  factory Data107.fromJson(Map<String, Object?> json) =>
      _$Data107FromJson(json);

  final int id;
  final String name;
  final String timezone;
  final String? logo;
  @JsonKey(name: 'logo_url')
  final String? logoUrl;
  final String? banner;
  @JsonKey(name: 'banner_url')
  final String? bannerUrl;
  final String? description;
  final String? phone;
  final String? address;
  @JsonKey(name: 'location_url')
  final String? locationUrl;
  @JsonKey(name: 'is_open')
  final bool isOpen;
  @JsonKey(name: 'min_order_amount')
  final num minOrderAmount;
  @JsonKey(name: 'payment_settings')
  final PaymentSettings paymentSettings;
  @JsonKey(name: 'payment_account_type')
  final PaymentAccountType paymentAccountType;
  final StoreStatus status;
  @JsonKey(name: 'is_order_locked')
  final bool isOrderLocked;
  @JsonKey(name: 'allowed_methods')
  final List<String> allowedMethods;
  final String? tin;
  @JsonKey(name: 'tax_regime')
  final TaxRegime? taxRegime;
  @JsonKey(name: 'fiscal_settings')
  final FiscalSettings4 fiscalSettings;

  Map<String, Object?> toJson() => _$Data107ToJson(this);
}
